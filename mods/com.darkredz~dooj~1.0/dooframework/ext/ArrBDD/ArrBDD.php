<?php
/**
 * ArrBDD class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/arr-bdd
 * @copyright Copyright &copy; 2011 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 * @since 0.1
 */

/**
 * ArrBDD - a simple BDD library utilizing PHP associative arrays and closures
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @since 0.1
 */
class ArrBDD
{

    protected $assertErrorKey;
    protected $assertError;
    protected $assertFailMsg = [];

    const SUBJECT_VAR_DUMP = 'var_dump';
    const SUBJECT_PRINT_R = 'print_r';
    const SUBJECT_VAR_EXPORT = 'var_export';

    public $subjectView;

    public function __construct($subjectView = ArrBDD::SUBJECT_VAR_DUMP)
    {
        assert_options(ASSERT_CALLBACK, [&$this, 'onAssertFail']);
        assert_options(ASSERT_WARNING, 0);
        $this->subjectView = $subjectView;
    }

    protected function exportSubject($subject)
    {
        switch ($this->subjectView) {
            case ArrBDD::SUBJECT_VAR_DUMP:
                var_dump($subject);
                break;
            case ArrBDD::SUBJECT_PRINT_R:
                print_r($subject);
                break;
            case ArrBDD::SUBJECT_VAR_EXPORT:
                var_export($subject, true);
                break;
        }
    }

    public function onAssertFail($file, $line, $expr)
    {
        if (empty($expr)) {
            $this->assertError = "Assertion failed in $file on line $line";
        } else {
            $this->assertError = "Assertion failed in $file on line $line: $expr";
        }
        //print "<br/>[$this->assertErrorKey] \n\t$this->assertError\n<br/>";
        //print "<br/>$this->assertError<br/>";

        if (is_array($this->assertErrorKey)) {
            if (empty($this->assertFailMsg[$this->assertErrorKey[0]][$this->assertErrorKey[1]])) {
                $this->assertFailMsg[$this->assertErrorKey[0]][$this->assertErrorKey[1]] = [];
            }
            $this->assertFailMsg[$this->assertErrorKey[0]][$this->assertErrorKey[1]][] = $this->assertError;
        } else {
            if (empty($this->assertFailMsg[$this->assertErrorKey])) {
                $this->assertFailMsg[$this->assertErrorKey] = [];
            }
            $this->assertFailMsg[$this->assertErrorKey][] = $this->assertError;
        }
        $this->assertError = [$this->assertError];
    }

    public function run($section, $specs, $includeSubject = false, $eachTestDoneCallback = null)
    {
        if ($eachTestDoneCallback == null) {
            return $this->runSync($specs, $includeSubject);
        } else {
            $this->runAsync($section, $specs, $includeSubject, $eachTestDoneCallback);
        }
    }

    public function runSync($specs, $includeSubject)
    {
        $testResults = [];

        foreach ($specs as $specName => $spec) {
            $results = [];
            $subject = null;

            // Subject can be a closure or the actual value
            if (isset($spec['subject'])) {
                $sbj = $spec['subject'];

                if (is_callable($sbj)) {
                    $subject = $sbj();
                } else {
                    $subject = $sbj;
                }
            }

            // include subject to the result
            if ($includeSubject) {
                ob_start();
                $this->exportSubject($subject);
                $content = ob_get_contents();
                ob_end_clean();
                $results['subject'] = $content;
            }

            foreach ($spec as $stepName => $step) {
                if ($stepName == 'subject') {
                    continue;
                }

                $this->assertErrorKey = $stepName;

                // not a WHEN
                if (is_callable($step)) {
                    $rs = $step($subject);
                    // $results[$stepName] = ($rs) ? $rs : $this->assertError;
                    $this->evalAsserts($results, $rs, $stepName);
                } // a WHEN
                else {
                    if (is_array($step)) {
                        $_subject = null;

                        // get inner step's subject, pass main step subject and inner subject to THEN closure
                        if (isset($step['subject'])) {
                            $sbj = $step['subject'];

                            // Need passing back the main subject to the inner step subject closure
                            if (is_callable($sbj)) {
                                $_subject = $sbj($subject);
                            } else {
                                $_subject = $sbj;
                            }
                        }

                        // include subject to the result
                        if ($includeSubject) {
                            ob_start();
                            $this->exportSubject($_subject);
                            $_content = ob_get_contents();
                            ob_end_clean();
                            $results[$stepName]['subject'] = $_content;
                        }

                        foreach ($step as $_stepName => $_step) {
                            if ($_stepName == 'subject') {
                                continue;
                            }

                            $this->assertErrorKey = [$stepName, $_stepName];
                            $rs = $_step($_subject, $subject);

                            $this->evalAsserts($results, $rs, $stepName, $_stepName);
                        }
                    }
                }
            }

            $testResults[$specName] = $results;
        }

        return $testResults;
    }

    public function runAsync($section, $specs, $includeSubject, $eachTestDoneCallback)
    {
        $this->section = $section;
        $this->testResults = [];
        $this->specsTotal = sizeof($specs);
        $this->specsTestIndex = 0;
        $this->testSpec($specs, $includeSubject, $eachTestDoneCallback);
    }

    protected function testSpec($specs, $includeSubject, $eachTestDoneCallback)
    {
        $specName = array_keys($specs)[$this->specsTestIndex++];
        $spec = $specs[$specName];

        $results = [];
        $subject = null;

        // Subject can be a closure or the actual value
        if (isset($spec['subject'])) {
            $sbj = $spec['subject'];

            if (is_callable($sbj)) {
                $subject = $sbj();
            } else {
                $subject = $sbj;
            }
        }

        // include subject to the result
        if ($includeSubject) {
            ob_start();
            $this->exportSubject($subject);
            $content = ob_get_contents();
            ob_end_clean();
            $results['subject'] = $content;
        }

        $this->stepTotal = sizeof($spec);
        $this->stepIndex = 0;
        $this->testStep($spec, $subject, $specName, $specs, $includeSubject, $eachTestDoneCallback);
    }

    protected function testStep($steps, $subject, $specName, $specs, $includeSubject, $eachTestDoneCallback)
    {
        $stepName = array_keys($steps)[$this->stepIndex++];
        $step = $steps[$stepName];

        if ($stepName == 'subject') {
            $this->testStep($steps, $subject, $specName, $specs, $includeSubject, $eachTestDoneCallback);
        } else {
            $this->assertErrorKey = $stepName;

            // not a WHEN
            if (is_callable($step)) {
                $step($subject, function ($rs) use (
                    $eachTestDoneCallback,
                    $specName,
                    $stepName,
                    $steps,
                    $subject,
                    $specs,
                    $includeSubject
                ) {
                    $this->evalAsserts($results, $rs, $stepName, null, $includeSubject);
                    if (empty($this->testResults[$specName])) {
                        $this->testResults[$specName] = [];
                    }
                    $this->testResults[$specName][$stepName] = $results[$stepName];

                    if ($this->stepIndex < $this->stepTotal) {
                        $this->testStep($steps, $subject, $specName, $specs, $includeSubject, $eachTestDoneCallback);
                    } else {
                        if ($this->specsTestIndex < $this->specsTotal) {
                            $this->testSpec($specs, $includeSubject, $eachTestDoneCallback);
                        } else {
                            $eachTestDoneCallback($this->section, $this->testResults);
                        }
                    }
                });
            } // a WHEN
            else {
                if (is_array($step)) {
                    $_subject = null;

                    // get inner step's subject, pass main step subject and inner subject to THEN closure
                    if (isset($step['subject'])) {
                        $sbj = $step['subject'];

                        // Need passing back the main subject to the inner step subject closure
                        if (is_callable($sbj)) {
                            $_subject = $sbj($subject);
                        } else {
                            $_subject = $sbj;
                        }
                    }

                    // include subject to the result
                    if ($includeSubject) {
                        ob_start();
                        $this->exportSubject($_subject);
                        $_content = ob_get_contents();
                        ob_end_clean();
                        $results[$stepName]['subject'] = $_content;
                    }

                    $this->stepInnerTotal = sizeof($step);
                    $this->stepInnerIndex = 0;

                    $this->testStepInner($stepName, $subject, $step, $_subject, $specName, $specs, $includeSubject,
                        $eachTestDoneCallback);
                }
            }
        }
    }


    protected function testStepInner(
        $stepName,
        $subject,
        $steps,
        $_subject,
        $specName,
        $specs,
        $includeSubject,
        $eachTestDoneCallback
    )
    {
        $_stepName = array_keys($steps)[$this->stepInnerIndex++];
        $step = $steps[$_stepName];

        if ($_stepName == 'subject') {
            $this->testStepInner($stepName, $subject, $steps, $_subject, $specName, $specs, $includeSubject,
                $eachTestDoneCallback);
        } else {
            $this->assertErrorKey = [$stepName, $_stepName];

            $step($_subject, $subject, function ($rs) use (
                $eachTestDoneCallback,
                $specName,
                $_stepName,
                $stepName,
                $steps,
                $_subject,
                $subject,
                $specs,
                $includeSubject
            ) {
                $this->evalAsserts($results, $rs, $stepName, $_stepName, $includeSubject);

                if (empty($this->testResults[$specName])) {
                    $this->testResults[$specName] = [];
                }
                $this->testResults[$specName][$stepName] = $results[$stepName];

                if ($this->stepInnerIndex < $this->stepInnerTotal) {
                    $this->testStepInner($stepName, $subject, $steps, $_subject, $specName, $specs, $includeSubject,
                        $eachTestDoneCallback);
                } else {
                    if ($this->specsTestIndex < $this->specsTotal) {
                        $this->testSpec($specs, $includeSubject, $eachTestDoneCallback);
                    } else {
                        $eachTestDoneCallback($this->section, $this->testResults);
                    }
                }
            });
        }
    }

    protected function evalAsserts(&$results, &$rs, $stepName, $_stepName = null, $includeSubject = false)
    {

        // if an array is returned, check if the first value(assertion value) pass the test
        if (is_array($rs)) {
            //====== single assertion =====
            if ($rs[0] === true) {
                $rs = $rs[0];
            } // if fail, then use the assertion failed msg provided, if available
            else {
                if (!empty($rs[1]) && is_string($rs[1])) {
                    $err = $rs[1];
                    $this->assertError = [$err];
                    $rs = false;        // failed
                } // Single assertion, if fail, and no debug msg specify, use default assertion error msg
                else {
                    if (empty($rs[1]) && empty($rs[0][1])) {
                        $rs = false;
                    } //====== multiple assertion =====
                    else {
                        if (is_array($rs[0])) {
                            $asserts = $rs;

                            // multiple assertion, get the error debug msg if available, eg. array( expr, "msg on the test" )
                            // $assert[0] = expr,  $assert[1] = msg
                            $rs = [];
                            foreach ($asserts as $assert) {
                                # var_dump($assert);
                                if ($assert[0] !== true) {
                                    if (!empty($assert[1])) {
                                        $rs[] = $assert[1];
                                    } else {
                                        $rs[] = false;//$this->assertError[0];
                                    }
                                } else {
                                    $rs[] = true;
                                }
                            }
                        }
                    }
                }
            }
        } else {
            if (is_object($rs) && is_a($rs, 'ArrAssertStatement')) {
                $asserts = $rs->getStatements();

                if ($includeSubject) {
                    $subject = $rs->getSubject();
                    $rs = [];

                    if (!is_null($subject)) {
                        $rs[] = ['subject' => $subject];
                    }
                } else {
                    $rs = [];
                }

                foreach ($asserts as $msg => $assert) {
                    if (is_a($assert, 'ArrAssert')) {
                        $finalRes = $assert->result();
                    } else {
                        if (is_bool($assert)) {
                            $finalRes = $assert;
                        } else {
                            $finalRes = boolval($assert);
                        }
                    }
                    $rs[] = [$msg => $finalRes];
                }
            }
        }

        $afMsg = null;

        // assign result/fail msg for the steps (inner step, if inner is set)
        // if test passed, use boolean true, else if failed use the assertion error message
        if (isset($_stepName)) {
            $rst = &$results[$stepName][$_stepName];
            if (!empty($this->assertFailMsg[$stepName][$_stepName])) {
                $afMsg = $this->assertFailMsg[$stepName][$_stepName];
            }
        } else {
            $rst = &$results[$stepName];
            if (!empty($this->assertFailMsg[$stepName])) {
                $afMsg = $this->assertFailMsg[$stepName];
            }
        }

        if (empty($afMsg)) {
            if ($this->assertError === null) {
                $this->assertError = false;
            }
            $rst = ($rs) ? $rs : $this->assertError;
        } else {
            if (!empty($rs)) {
                $this->assertError = $afMsg;
                $i = 0;
                foreach ($rs as &$rsvalue) {
                    if ($rsvalue === true) {
                        continue;
                    }
                    if ($rsvalue === false) {
                        $rsvalue = $this->assertError[$i];
                    } else {
                        $rsvalue = $this->assertError[$i] . ': ' . $rsvalue;
                    }
                    $i++;
                }
                $rst = $rs;
            } else {
                if (is_array($this->assertError) && sizeof($this->assertError) === 1) {
                    $rst = $this->assertError[0];
                    if (isset($afMsg[0]) && strpos($rst, 'Assertion failed in ') !== 0) {
                        $rst = $afMsg[0] . ': ' . $rst;
                    }
                } else {
                    $rst = $this->assertError;
                }
            }
        }

        //errors will always be an array.
        if ($rst !== true && !is_array($rst)) {
            $rst = [$rst];
        }

        $this->assertError = null;
    }
}