<?php
/**
 * Created by IntelliJ IDEA.
 * User: leng
 * Date: 10/22/16
 * Time: 2:01 AM
 */

class DooServiceModel
{
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

    protected function defaultErrorHandlerWithPromise($errorHandler, $callback, $promise = null)
    {
        $errorNewHandler = function ($error) use ($promise, $errorHandler, $callback) {
            if ($promise != null) {
                $promise->reject($error);
            }
            $func = $this->defaultErrorHandler($errorHandler, $callback);
            $func($error);
        };
        return $errorNewHandler;
    }

    protected function defaultErrorHandler(
        $errHandler,
        $callback,
        $uniqueField = null,
        $duplicateErrorMsg = null,
        $errorMsgFieldReplace = null
    )
    {
        $defaultErr = function ($error) use (
            $errHandler,
            $callback,
            $uniqueField,
            $duplicateErrorMsg,
            $errorMsgFieldReplace
        ) {
            $duplicateEntryValue = $this->isDuplicateError($error);

            if ($duplicateEntryValue && $uniqueField && (\is_array($uniqueField) || $duplicateErrorMsg)) {

                if (\is_array($duplicateEntryValue)) {
                    $duplicateField = $duplicateEntryValue['field'];
                    $duplicateEntryValue = $duplicateEntryValue['value'];
                } else {
                    if (\is_string($uniqueField)) {
                        $duplicateField = $uniqueField;
                    }
                }

                if (\is_string($uniqueField)) {
                    //replace error message String with the entry value that is duplicated
                    if (!empty($errorMsgFieldReplace)) {
                        $duplicateErrorMsg = \str_replace($errorMsgFieldReplace, $duplicateEntryValue,
                            $duplicateErrorMsg);

                        $error = [
                            'statusCode' => 409,
                            'error' => $duplicateErrorMsg,
                            'errorList' => [
                                $duplicateField => ['unique' => $duplicateErrorMsg],
                            ],
                        ];
                    }
                } else {
                    if (\is_array($uniqueField)) {
                        $duplicateErrorMsg = $uniqueField[$duplicateField];

                        $duplicateErrorMsg = \str_replace(":$duplicateField", $duplicateEntryValue, $duplicateErrorMsg);

                        $error = [
                            'statusCode' => 409,
                            'error' => $duplicateErrorMsg,
                            'errorList' => [
                                $duplicateField => ['unique' => $duplicateErrorMsg],
                            ],
                        ];
                    }
                }

                $this->app->trace($error);

                if ($errHandler) {
                    $errHandler($error);
                } else {
                    $callback($error);
                }
                return;
            }

            if (is_object($error) && stripos(get_class($error), 'exception') !== false) {
                $errMsg = [
                    'statusCode' => 500,
                    'error' => 'Oops! Some unexpected errors happened on our servers. Please try again later',
                ];

                if ($this->app->conf->DEBUG_ENABLED) {
                    $errMsg['errorList'] = ['exception' => $error->getMessage()];
                }

                if ($errHandler) {
                    $errHandler($errMsg);
                } else {
                    $callback($errMsg);
                }
                return;
            }

            if ($errHandler) {
                $errHandler($error);
            } else {
                $callback($error);
            }
        };
        return $defaultErr;
    }

    protected function isDuplicateError($err)
    {
        if (is_object($err) && stripos(get_class($err), 'exception') !== false) {
            $msg = $err->getMessage();
            //MySQL
            $matched = preg_match('/23000 \- Duplicate entry \\\'(.+)[\n\r\\\'] for key/', $msg, $matches);
            if ($matched) {
                if (!empty($matches)) {
                    $duplicateEntryValue = $matches[1];
                    return $duplicateEntryValue;
                }
                return true;
            }

            //Postgresql
            if (!$matched) {
                $matched = preg_match('/Key \((.+)\)\=\((.+)\)\ already exists\.\,/', $msg, $matches);
            }
            if ($matched) {
                if (!empty($matches)) {
                    $duplicateEntryField = $matches[1];
                    $duplicateEntryValue = $matches[2];
                    return ['field' => $duplicateEntryField, 'value' => $duplicateEntryValue];
                }
                return true;
            }
        }
        return false;
    }

    protected function repeat($params, $callbacksArr, $startValue = true)
    {
        return \DooPromise::repeat($params, $callbacksArr, $startValue);
    }

    protected function now($timezone = 'UTC')
    {
        $dt = new \DateTime();
        $dt->setTimeZone(new \DateTimeZone($timezone));
        $dt->setTimestamp(time());
        return $dt->format('Y-m-d\TH:i:s.\0\0\0');
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