<?php
/**
 * Created by IntelliJ IDEA.
 * User: leng
 * Date: 10/22/16
 * Time: 2:01 AM
 */

class DooServiceModel
{
    use DooDbLogTrait;

    /**
     * @var DooWebApp
     */
    public $app;
    public $combiner;

    public function validateInput($input, $rules, $controllerCleanupCallback = null)
    {
        $inputValidator = new \DooInputValidator($rules);
        $validateRes = $inputValidator->validateInput($input);

        if (is_callable($controllerCleanupCallback)) {
            return $controllerCleanupCallback($validateRes);
        } else {
            if ($validateRes->resultType === \DooInputValidatorResult::VALID) {
                return $validateRes->inputValues;
            } else {
                if ($validateRes->resultType === \DooInputValidatorResult::VALID) {
                    return $validateRes->inputValues;
                } else {
                    if ($validateRes->resultType === \DooInputValidatorResult::INVALID_NO_INPUT) {
                        return false;
                    } else {
                        if ($validateRes->resultType === \DooInputValidatorResult::INVALID_NO_RULES) {
                            return false;
                        } else {
                            if ($validateRes->resultType === \DooInputValidatorResult::INVALID_RULE_ERRORS) {
                                return false;
                            }
                        }
                    }
                }
            }
        }
    }

    protected function repeat($params, $callbacksArr, $startValue = true)
    {
        return \DooPromise::repeat($params, $callbacksArr, $startValue);
    }

    protected function now($timezone = 'UTC', $format = 'Y-m-d\TH:i:s.\0\0\0')
    {
        $dt = new \DateTime();
        $dt->setTimeZone(new \DateTimeZone($timezone));
        $dt->setTimestamp(time());
        return $dt->format($format);
    }


    public static function isValidTimeZone($id)
    {
        $ids = \DateTimeZone::listIdentifiers();
        foreach ($ids as $tzId) {
            if ($tzId == $id) {
                return true;
            }
        }
        return false;
    }

    public function checkValidTimeZone($id)
    {
        return self::isValidTimeZone($id);
    }

    protected function currentMonth($timezone = 'UTC')
    {
        $dt = new \DateTime();
        $dt->setTimeZone(new \DateTimeZone($timezone));
        $dt->setTimestamp(time());
        return $dt->format('m');
    }

    protected function toDateTime($timestamp, $timezone = 'UTC')
    {
        $dt = new \DateTime();
        $dt->setTimeZone(new \DateTimeZone($timezone));
        $dt->setTimestamp($timestamp);
        return $dt->format('Y-m-d\TH:i:s.\0\0\0');
    }

    protected function fromDateTime($dateStr, $timezone = 'UTC')
    {
        $dt = new \DateTime($dateStr, new \DateTimeZone($timezone));
        return $dt->getTimestamp();
    }

    protected function mapData($tablesInfo, $result, $delimiter = '-', $nested = true, $objectListWithID = false)
    {
        $mapper = new \DooDataMapper();
        if (!is_array($tablesInfo)) {
            $tablesInfo = $tablesInfo->toArrayMap();
        }
        return $mapper->map($tablesInfo, $result, $delimiter, $nested, $objectListWithID);
    }

    function __call($name, array $arguments)
    {
        $finalCallback = null;

        if ($arguments) {
            $finalCallback = array_pop($arguments);
        }

        if (!\is_callable($finalCallback)) {
            $finalCallback = null;
        }

        if (strpos($name, '>') !== false) {
            $methods = explode('>', $name);
            $mid = 0;
            $msize = sizeof($methods);
            $rslist = [];

            $this->combiner = function ($res) use ($methods, &$rslist, &$mid, $msize, $finalCallback, &$arguments) {
                $mid++;
                $rslist[] = $res;
                $methodName = $methods[$mid];

                if ($mid < $msize) {
//                    $this->app->logInfo("Executing next $mid method {$methodName} ....");
                    call_user_func_array([$this, $methodName], $arguments);
                } else {
//                    $this->app->logInfo("FINAL CALLBACK RETURN ALL RESULT IN ARRAY");
                    $finalCallback($rslist);
                }
            };

            //call the first
            $arguments[] = $this->combiner;
//            $this->app->logInfo("Executing $mid method {$methods[$mid]} ....");
            call_user_func_array([$this, $methods[$mid]], $arguments);
        } else {
            if (strpos($name, '|') !== false) {
                $methods = explode('|', $name);
                $mid = 0;
                $msize = sizeof($methods);
                $rslist = [];
                $methodName = $methods[$mid];

                $this->combiner = function ($res) use ($methods, &$rslist, &$mid, $msize, $finalCallback, &$arguments) {
                    //save last func call results
                    $methodName = $methods[$mid];
                    $rslist[$methodName] = $res;

                    $mid++;
                    $methodName = $methods[$mid];
                    $argsForMethod = $arguments[0][$methodName];
                    $argsForMethod[] = $this->combiner;

                    if ($mid < $msize) {
//                    $this->app->logInfo("Executing next $mid method {$methodName} ....");
//                    $this->app->logInfo(print_r($arguments, true));
                        @call_user_func_array([$this, $methodName], $argsForMethod);
                    } else {
//                    $this->app->logInfo("FINAL CALLBACK RETURN ALL RESULT IN ARRAY");
//                    $this->app->logInfo(print_r($rslist, true));
                        $finalCallback($rslist);
                    }
                };

//            $this->app->trace($arguments);

                //call the first
                $argsForMethod = $arguments[0][$methodName];
                $argsForMethod[] = $this->combiner;
//            $this->app->logInfo("Executing $mid method {$methodName} ....");
                @call_user_func_array([$this, $methodName], $argsForMethod);
            } else {
                if (strpos($name, '+') !== false) {
                    $methods = explode('+', $name);
                    $mid = 0;
                    $msize = sizeof($methods);
                    $rslist = [];
                    $methodName = $methods[$mid];

                    $this->combiner = function ($res) use (
                        $methods,
                        &$rslist,
                        &$mid,
                        $msize,
                        $finalCallback,
                        &
                        $arguments
                    ) {
                        //save last func call results
                        $methodName = $methods[$mid];
                        $rslist[$methodName] = $res;

                        $mid++;
                        $methodName = $methods[$mid];
                        $argsForMethod = [$this->combiner];

                        if ($mid < $msize) {
//                    $this->app->logInfo("Executing next $mid method {$methodName} ....");
//                    $this->app->logInfo(print_r($arguments, true));
                            @call_user_func_array([$this, $methodName], $argsForMethod);
                        } else {
//                    $this->app->logInfo("FINAL CALLBACK RETURN ALL RESULT IN ARRAY");
//                    $this->app->logInfo(print_r($rslist, true));
                            $finalCallback($rslist);
                        }
                    };

//            $this->app->trace($arguments);

                    //call the first
                    $argsForMethod = [$this->combiner];
//            $this->app->logInfo("Executing $mid method {$methodName} ....");
                    @call_user_func_array([$this, $methodName], $argsForMethod);
                } else {
                    if ($finalCallback) {
                        $this->app->logDebug('ERR_METHOD_NOT_FOUND for ' . $name);
                        $finalCallback('ERR_METHOD_NOT_FOUND');
                    }
                }
            }
        }

    }
}