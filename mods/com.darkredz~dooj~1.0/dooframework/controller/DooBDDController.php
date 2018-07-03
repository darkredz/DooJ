<?php
/**
 * DooBDDController class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2011 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */

/**
 * Provides functionalities serving as a test suite for testing BDD stories utilizing ArrBDD library.
 * Extend this class and override DooBDDController::run() with your own requirements, eg. saving results.
 * Override DooBDDController::getScenarioPath() to set where you write your BDD stories.
 *
 * By default, it loads scenarios and specs from bdd_scenario folder and stores result(if enabled) in bdd_result folder.
 *
 * Example: You can access the BDD results from http://yourappdomain/bdd/run
 * To test a certain section(using auto route): http://yourappdomain/bdd/run/section/Section Name
 * To include subject in BDD result, use subject/true: http://yourappdomain/bdd/run/subject/true
 * To change subject view to use print_r() instead of the default var_dump(): http://yourappdomain/bdd/run/subject/print_r
 * <code>
 * class BDDController extends DooBDDController{
 *
 *     public function run(){
 *         $this->executeTest();
 *         $this->showResult();
 *         $this->saveResult();
 *     }
 * }
 * </code>
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @package doo.controller
 * @since 1.5
 */
//
//Doo::loadCore('helper/DooFile');
//Doo::loadCore('controller/DooController');
//Doo::loadCore('ext/ArrBDD/ArrBDD');
//Doo::loadCore('ext/ArrBDD/ArrBDDSpec');

class DooBDDController extends DooController
{
    /**
     * Result array
     * @var array
     */
    protected $result;

    /**
     * BDD instance
     * @var ArrBDD
     */
    protected $bdd;

    /**
     * To include subject in result, set to True.
     * @var bool
     */
    protected $includeSubject = false;


    protected $passes = 0;
    protected $fails = 0;
    protected $startTime = 0;
    protected $totalTime = 0;

    public function __construct()
    {
        $this->bdd = new ArrBDD;
    }

    public function beforeRun($resource, $action, $beforeRunHandler = NULL)
    {
        $subject = $this->getKeyParam('subject');
        $this->includeSubject = (bool)$subject;
        $this->bdd->subjectView = ($subject !== 'true' && $subject !== 'false') ? $subject : ArrBDD::SUBJECT_VAR_DUMP;
    }

    /**
     * Enabled via AUTOROUTE.
     */
    public function run()
    {
        $this->executeTest();
        $this->showResult();
    }

    /**
     * Returns the path where all the scenarios and specs are stored. By default, loads from bdd_scenario folder.
     * @return string
     */
    protected function getScenarioPath()
    {
        return $this->app->conf->SITE_PATH . $this->app->conf->PROTECTED_FOLDER . 'bdd_scenario';
    }

    /**
     * Execute test
     * @param string $section Default is null. Class name will be used as section name if not found.
     * @return array result
     */
    protected function executeTest($doneCallback, $section = null)
    {
        $this->prepareTestList($section);

        $this->logInfo("[BDD] Running $this->totalTest tests");

        $this->eachDoneCallback = function ($section, $testResult) use (&$doneCallback) {
            $this->logInfo("[BDD] Finish testing specs for section $section");
            $this->result[$section] = $testResult;

            $this->trace($testResult);

            if (sizeof($this->result) === $this->totalTest) {
                $this->totalTime = \time() - $this->startTime;
                $doneCallback($this->totalTime);
            } else {
                $this->runSpecTest();
            }
        };

        $this->runSpecTest();
    }

    protected function executeTestInCLI($doneCallback, $section = null)
    {
        $this->prepareTestList($section);
        $this->logInfo("[BDD] Running $this->totalTest tests");

        $result = [];

        foreach ($this->testSections as $item) {
            $sect = $item['section'];
            $this->logInfo("[BDD] Testing section $sect...");
            $text = exec("php index.php 'REQUEST_URI=/test/bdd/run/section/$sect?silent=true'");
            $rs = \JSON::decode($text, true);
            $key = array_keys($rs)[0];
            $result[$key] = $rs[$key];
            $passes = $rs['BDD Passes'];
            $failed = $rs['BDD Fails'];
            $total = $passes + $failed;

            $this->logInfo("[BDD] Section $sect test result = $passes/$total");
        }

        $this->result = $result;
        $this->totalTime = \time() - $this->startTime;

        $doneCallback($this->totalTime);
    }

    protected function prepareTestList($section)
    {
        $this->startTime = \time();
        if ($section === null) {
            $chosenSection = urldecode($this->getKeyParam('section'));
        } else {
            $chosenSection = $section;
        }
//        echo 'Running test suite for all section';
        $list = DooFile::getFilePathIndexList($this->getScenarioPath());

        $this->result = [];
        $this->testSections = [];

        foreach ($list as $path) {
            $pathinfo = pathinfo($path);
            if ($pathinfo['extension'] !== 'php') {
                continue;
            }

            require_once $path;
            $cls = explode('.', $pathinfo['filename']);
            $cls = $cls[0];

            /**
             * @var ArrBDDSpec
             */
            $obj = new $cls;
            $obj->app = &$this->app;
            $section = $obj->getSectionName();

            if (empty($section)) {
                $section = $cls;
            }

            //if section is specified, test only that section
            if (!empty($chosenSection) && $chosenSection !== $section) {
                continue;
            }

            if (empty($section)) {
                $section = $cls;
            }

            $this->testSections[] = [
                'specObject' => $obj,
                'section' => $section,
            ];
        }

        $this->totalTest = sizeof($this->testSections);
    }

    protected function runSpecTest()
    {
        $i = sizeof($this->result);
        $obj = $this->testSections[$i]['specObject'];
        $section = $this->testSections[$i]['section'];

        $this->logInfo("[BDD] Testing against $section specs");

        //prepare the specs
        /** @var ArrBDDSpec $obj */
        $obj->prepare();

        $this->bdd->run($section, $obj->specs, $this->includeSubject, $this->eachDoneCallback);
    }

    /**
     * Flatten the BDD result into a array with all the scenarios as keys.
     * @return array
     */
    protected function flattenResult()
    {
        $rs = [];
        foreach ($this->result as $r) {
            $rs = array_merge($rs, $r);
        }
        return $rs;
    }

    protected function flattenArray(array $array)
    {
        $return = [];
        array_walk_recursive($array, function ($a) use (&$return) {
            $return[] = $a;
        });
        return $return;
    }

    /**
     * Output the result in JSON format
     * @param bool $flatten To flatten the result.
     */
    protected function showResult($flatten = true)
    {
        $testResults = $this->result;
        if ($flatten) {
            $testResults = $this->flattenResult();
        }

//        $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($testResults));
        $it = $this->flattenArray($testResults);
        foreach ($it as $res) {
            if ($res === true) {
                $this->passes++;
            } else {
                if ($res === false) {
                    $this->fails++;
                }
            }
        }

        $testResults['BDD Passes'] = $this->passes;
        $testResults['BDD Fails'] = $this->fails;

        $this->logInfo("[BDD] Passes = $this->passes");
        $this->logInfo("[BDD] Fails = $this->fails");
        $this->logInfo("Total time used = $this->totalTime");
        $this->toJSON($testResults, true);
    }

    protected function logInfo($msg)
    {
        if (empty($this->_GET['silent'])) {
            $this->app->logInfo($msg);
        }
    }

    protected function trace($obj)
    {
        if (empty($this->_GET['silent'])) {
            $this->app->trace($obj);
        }
    }

    /**
     * Save the result into file system in JSON format. By default, it creates and stores in a folder named 'bdd_result'
     * @param string $path Path to store the results.
     * @param bool $flatten To flatten the result. If true, everything will be saved in a single file. Else, it will be stored seperately with its section name.
     */
    protected function saveResult($path = null, $flatten = true)
    {
        if ($path === null) {
            $path = $this->app->conf->SITE_PATH . $this->app->conf->PROTECTED_FOLDER . 'bdd_result/';
        }

        if ($flatten) {
            $f = new DooFile;
            $f->create($path . '/all_results.json', $this->toJSON($this->flattenResult()));
        } else {
            foreach ($this->result as $section => $rs) {
                $f = new DooFile;
                $f->create($path . '/' . $section . '.json', $this->toJSON($rs));
            }
        }
    }
}
