<?php
/**
 * Created by IntelliJ IDEA.
 * User: leng
 * Date: 08/06/2018
 * Time: 9:35 PM
 */

trait DooDbLogTrait
{
    /**
     * Create error handler for DB transactional calls to automatically rollback, use after calling beginTransaction(), eg. for asyncQuery and asyncExec
     * @param $method
     * @param \DooSmartModel $repo
     * @param $errorCallback
     * @return \Closure
     */
    protected function createDbTxErrorHandler($method, $repo, $msg, $errorCallback)
    {
        return function ($errCode, $error, $exception) use ($method, $repo, $msg, $errorCallback) {
            $repo->rollBack();
            $this->app->logException($method, $msg, $exception, $errCode);
            $errorCallback($errCode, $error, $exception);
        };
    }

    protected function createLogErrorHandler($method, $msg, $errorCallback)
    {
        return function ($errCode, $error, $exception = null) use ($method, $msg, $errorCallback) {
            $this->app->logException($method, $msg, $exception, $errCode);
            $errorCallback(500, $error, $exception);
        };
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
}