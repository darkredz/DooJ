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

Doo::loadCore('helper/DooFile');
Doo::loadCore('controller/DooController');
Doo::loadCore('ext/ArrBDD/ArrBDD');
Doo::loadCore('ext/ArrBDD/ArrBDDSpec');

class DooBDDController extends DooController{
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
    
    public function __construct() {
        $this->bdd = new ArrBDD;
    }
    
    public function beforeRun($resource, $action) {
        $subject = $this->getKeyParam('subject');
        $this->includeSubject = (bool)$subject;
        $this->bdd->subjectView = ($subject!=='true' && $subject!=='false')? $subject: ArrBDD::SUBJECT_VAR_DUMP;        
    }

    /**
     * Enabled via AUTOROUTE. 
     */
    public function run(){
        $this->executeTest();
        $this->showResult();
    }
        
    /**
     * Returns the path where all the scenarios and specs are stored. By default, loads from bdd_scenario folder.
     * @return string
     */
    protected function getScenarioPath(){
        return Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . 'bdd_scenario';
    }

    /**
     * Execute test
     * @param string $section Default is null. Class name will be used as section name if not found.
     * @return array result 
     */
    protected function executeTest($section=null){
        if($section===null){
            $chosenSection = urldecode($this->getKeyParam('section'));            
        }else{
            $chosenSection = $section;
        }
//        echo 'Running test suite for all section';
        $list = DooFile::getFilePathIndexList( $this->getScenarioPath() );

        $this->result = array();

        foreach($list as $path){
            $pathinfo = pathinfo($path);
            if($pathinfo['extension']!=='php') continue;
            
            require_once $path;
            $cls = explode('.', $pathinfo['filename']);
            $cls = $cls[0];

            $obj = new $cls;
            $section = $obj->getSectionName();
            
            if(empty($section)){
                $section = $cls;
            }

            //if section is specified, test only that section
            if(!empty($chosenSection) && $chosenSection!==$section)
                continue;

            if(empty($section))
                $section = $cls;

            //prepare the specs
            $obj->prepare();

            $this->result[$section] = $this->bdd->run( $obj->specs, $this->includeSubject );
            
        }
        return $this->result;
    }  
    
    /**
     * Flatten the BDD result into a array with all the scenarios as keys.
     * @return array
     */
    protected function flattenResult(){
        $rs = array();
        foreach($this->result as $r){
           $rs = array_merge($rs, $r);
        }
        return $rs;
    }
    
    /**
     * Output the result in JSON format
     * @param bool $flatten To flatten the result.
     */
    protected function showResult($flatten=true){
        if($flatten)
            $this->toJSON($this->flattenResult(), true);
        else
            $this->toJSON($this->result, true);
    }
    
    /**
     * Save the result into file system in JSON format. By default, it creates and stores in a folder named 'bdd_result'
     * @param string $path Path to store the results.
     * @param bool $flatten To flatten the result. If true, everything will be saved in a single file. Else, it will be stored seperately with its section name.
     */
    protected function saveResult($path=null, $flatten=true){
        if($path===null){
            $path = Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . 'bdd_result/';
        }
        
        if($flatten){
            $f = new DooFile;
            $f->create($path.'/all_results.json', $this->toJSON($this->flattenResult()));              
        }
        else{
            foreach($this->result as $section => $rs){            
                $f = new DooFile;
                $f->create($path.'/'.$section.'.json', $this->toJSON($rs)); 
            }
        }
    }
}
