<?php
/**
 * DooApiController class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */


/**
 * DooApiController is the root class of all classes to provide an API interface through REST or eventbus
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @package doo.controller
 * @since 2.0
 */
class DooApiController extends DooController
{

    public $lang = 'en_US';
    public $async = true;
    public $action;
    public $actionField;
    public $apiKey = 'AZ00B701J2pI90P84e7yrGhM401Z801';
    public $authHeaderTokenField = 'token';
    public $authHeaderApiKeyField = 'apikey';

    public $authority;
    public $enabledTraceInput = true;
    public $allowCORS = false;
    public $allowCORSCredentials = false;
    public $allowCORSMethods = "GET,HEAD,OPTIONS,POST,PUT,DELETE";
    public $allowCORSHeaders = "Origin,X-Requested-With,Content-Type,Accept,Authorization";

    public $input;

    /**
     * Get RFC2617 Authorization formatted data. format is credentials = auth-scheme
     * eg. Authorization: FIRE-TOKEN apikey=0PN5J17HBGZHT7JJ3X82, hash=frJIUN8DYpKDtOLCwo//yllqDzg
     * @return array|null
     */
    protected function getAuthHeaderData()
    {
        $auth = $this->app->request->headers()->get('Authorization');
        if (empty($auth)) {
            return null;
        }
        $methodDataParts = \explode(' ', $auth, 2);

        if (sizeof($methodDataParts) < 2) {
            return null;
        }

        $authParts = \explode(',', $methodDataParts[1]);
        $authData = [];

        foreach ($authParts as $val) {
            $keyAndVal = \explode('=', $val);
            if (sizeof($keyAndVal) < 2) {
                continue;
            }
            $authData[trim($keyAndVal[0])] = trim($keyAndVal[1]);
        }

        if (empty($authData)) {
            $authData = $methodDataParts[1];
        }

        return [
            'method' => $methodDataParts[0],
            'data' => $authData,
        ];
    }

    protected function getBearer()
    {
        $authData = $this->getAuthHeaderData();
        if ($authData['method'] != 'Bearer') {
            return null;
        }
        if (is_string($authData['data'])) {
            return $authData['data'];
        }
        return $authData['data'];
    }

    protected function getBearerApiKey()
    {
        $authData = $this->getAuthHeaderData();
        if ($authData['method'] != 'Bearer') {
            return null;
        }
        if (is_string($authData['data'])) {
            return $authData['data'];
        }
        return $authData['data'][$this->authHeaderApiKeyField];
    }

    protected function getBearerToken()
    {
        $authData = $this->getAuthHeaderData();
        if ($authData['method'] != 'Bearer') {
            return null;
        }
        if (is_string($authData['data'])) {
            return $authData['data'];
        }
        return $authData['data'][$this->authHeaderTokenField];
    }

    public function beforeRun($resource, $action, $beforeRunHandler = null)
    {
        if (isset($this->app->conf->API_TRACE_ENABLED)) {
            $this->enabledTraceInput = $this->app->conf->API_TRACE_ENABLED;
        }
        $bearerData = $this->getBearer();
        $authOk = false;

        if (is_string($bearerData) && $bearerData == $this->apiKey) {
            $authOk = true;
        } else {
            if (is_array($bearerData) && $bearerData[$this->authHeaderApiKeyField] == $this->apiKey) {
                $authOk = true;
            }
        }

        $needApiKeyCheck = true;
        //Resource => action No need to check API Key if inside exclude list, thus making it public
        if (!empty($this->excludeApiKey) && \in_array($resource, \array_keys($this->excludeApiKey))) {
            if (!empty($this->excludeApiKey[$resource])) {
                $needApiKeyCheck = !\in_array($action, $this->excludeApiKey[$resource]);
            } else {
                $needApiKeyCheck = false;
                $this->app->logDebug('NO NEED API KEY');
            }
        }

        if ($this->app->_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            if ($this->allowCORS) {
                $this->setHeader("Access-Control-Allow-Origin", ($this->allowCORS === true) ? '*' : $this->allowCORS);
                $this->setHeader("Access-Control-Allow-Credentials", ($this->allowCORSCredentials) ? 'true' : 'false');
                $this->setHeader("Access-Control-Allow-Methods", $this->allowCORSMethods);
                $this->setHeader("Access-Control-Allow-Headers", $this->allowCORSHeaders);
                $this->app->logDebug('CORS OPTIONS Pre flight');
                $this->setContentType('json');
                $this->app->statusCode = 200;
                $this->endReq('{"response":"allow"}');
                return 200;
            } else {
                $this->endReq('');
            }
        }

        if ($needApiKeyCheck && !$authOk) {
            $this->setContentType('json');
            $this->app->statusCode = 401;
            $this->endReq('{"error":"Invalid API key"}');
            return 401;
        }

        $this->initReqAction($action);

        if ($this->isMethodAllow() == false) {
            $this->setContentType("json");
            $this->app->statusCode = 404;
            $this->endReq('{"error":"Method Not Found"}');
            return;
        }
    }

    protected function initReqAction($action)
    {
        $this->action = $action;
        $this->actionField = 'field' . ucfirst($this->action);
    }

    protected function isMethodAllow()
    {
        $allowMethod = $this->{$this->actionField}['_method'];

        if ($allowMethod && strtoupper($allowMethod) != $this->app->_SERVER['REQUEST_METHOD']) {
            return false;
        }
        return true;
    }

    public function getFieldSchema()
    {
        if ($this->actionField == null) {
            return;
        }
        return $this->{$this->actionField};
    }

    protected function prepApiInput()
    {
        $this->input = $this->getApiInput();
        return $this->input;
    }

    protected function getApiInput()
    {
        $field = array_keys($this->{$this->actionField});
        $method = $this->app->_SERVER['REQUEST_METHOD'];

        if ($method == 'GET') {
            $input = [];
            if (!empty($this->_GET)) {
                $input = $this->getInput($field, $this->_GET);
            }
            if (!empty($this->params)) {
                //convert to use field underscore _ if found key is using -
                $input1 = $this->getKeyParamsDashToUnderscore($field, true);
                $input = array_merge($input, $input1);
            }
        } else {
            if ($method == 'OPTIONS' || $method == 'HEAD') {
                $input = $this->getKeyParams($field, true);
            } else {
                $input = $this->getInput($field, $this->_POST);
                if (!empty($this->_FILES)) {
                    $allFields = $this->{$this->actionField};
                    $uploadFields = [];
                    foreach ($allFields as $key => $schema) {
                        if ($schema[0] == 'file') {
                            $uploadFields[] = $key;
                        }
                    }
                    $inputUpload = $this->getInput($uploadFields, $this->_FILES);
                    $input = array_merge($input, $inputUpload);
                }
            }
        }

        // if get is empty, set fields to null to avoid notice undefined index when used in controller,
        // reduce tedious checking work for controllers
        if (empty($input)) {
            foreach ($field as $f) {
                if ($f != '_method') {
                    $input[$f] = null;
                }
            }
        };

        unset($input['_method']);
        unset($input['_return_type']);

        if ($this->app->conf->DEBUG_ENABLED) {
            if ($this->enabledTraceInput) {
                $this->app->logDebug($method . ' Request input:');
                $this->app->trace($input);
            }
        }

        return $input;
    }

    protected function deleteUploadTmpFiles()
    {
        //if it's vertx but not php-fpm
        if ($this->app->isJVM) {
            //on error, if file has error remove it.
            foreach ($this->_FILES as $f) {
                $tmpName = $f['tmp_name'];
                $this->app->vertx->setTimer(100, g(function ($tid) use ($tmpName) {
                    $this->app->vertx->fileSystem()->exists($tmpName, a(function ($result, $error) use ($tmpName) {
                        if ($result) {
                            $this->app->vertx->fileSystem()->delete($tmpName, null);
                        }
                    }));
                }));
            }
        }
    }

    protected function getFieldRules($validationFields = null)
    {
        $rules = [];
        if (!empty($validationFields)) {
            if (is_array($validationFields)) {
                $fieldRules = $validationFields[$this->actionField];
            } else {
                if (is_object($validationFields)) {
                    $fieldRules = $validationFields->{$this->actionField};
                } else {
                    if (is_string($validationFields)) {
                        $validationFieldObj = \JSON::decode($validationFields);
                        $fieldRules = $validationFieldObj->{$this->actionField};
                    }
                }
            }
        } else {
            $fieldRules = $this->{$this->actionField};
        }

        foreach ($fieldRules as $fname => $p) {
            if ($fname == '_method') {
                continue;
            }
            if (isset($p[1])) {
                $rules[$fname] = $p[1];
            }
        }
        return $rules;
    }

    /**
     * @param DooInputValidator $validateRes
     * @return bool|array
     */
    protected function validationCleanup($validateRes)
    {
        if ($validateRes->resultType === \DooInputValidatorResult::VALID) {
            return $validateRes->inputValues;
        } else {
            if ($validateRes->resultType === \DooInputValidatorResult::INVALID_NO_INPUT) {
                $this->deleteUploadTmpFiles();
                $this->sendError('No data input');
                return false;
            } else {
                if ($validateRes->resultType === \DooInputValidatorResult::INVALID_NO_RULES) {
                    $this->deleteUploadTmpFiles();
                    $this->sendError('Please set the field rules for this API action');
                    return false;
                } else {
                    if ($validateRes->resultType === \DooInputValidatorResult::INVALID_RULE_ERRORS) {
                        $this->deleteUploadTmpFiles();
                        $this->sendError($validateRes->errors);
                        return false;
                    }
                }
            }
        }
    }

    protected function validateInput($input = null)
    {
        if (is_null($input)) {
            $input = $this->getApiInput();
        }
        $rules = $this->{$this->actionField};

        $inputValidator = new \DooInputValidator($rules);
        $validateRes = $inputValidator->validateInput($input);

        return $this->validationCleanup($validateRes);
    }

    protected function setupServiceValidationHook($service)
    {
        $controller = &$this;
        $validateHook = function ($serviceName, $serviceProvider, $params, $done) use ($controller) {
            $rules = $controller->{$controller->actionField};

            $controllerCleanupCallback = function ($validateRes) use ($controller, $done) {
                $resultCheck = $controller->validationCleanup($validateRes);
                $done($resultCheck);
            };

            $serviceProvider->validateInput($controller->input, $rules, $controllerCleanupCallback);
        };

        $service->setPreCallHook($validateHook);
        return $service;
    }

    protected function removeFields($opt, $removeTheseFields = null, $removeNull = true, $removeEmptyString = true)
    {
        //remove fields that are not needed for where query
        if ($removeTheseFields) {
            $optFields = array_keys($opt);

            foreach ($removeTheseFields as $field) {
                if (in_array($field, $optFields)) {
                    unset($opt[$field]);
                }
            }
        }

        //remove null fields
        if ($removeNull || $removeEmptyString) {
            foreach ($opt as $k => $v) {
                if ($removeNull && $v === null) {
                    unset($opt[$k]);
                    continue;
                }
                if ($removeEmptyString && $v === '') {
                    unset($opt[$k]);
                    continue;
                }
            }
        }
        return $opt;
    }

    public function endReq($out = null)
    {
        if ($this->allowCORS) {
            $this->setHeader("Access-Control-Allow-Origin", ($this->allowCORS === true) ? '*' : $this->allowCORS);
            $this->setHeader("Access-Control-Allow-Credentials", ($this->allowCORSCredentials) ? 'true' : 'false');
        }
        parent::endReq($out);
    }

    protected function sendError($err, $statusCode = 400)
    {
        $this->setContentType('json');
        $this->app->statusCode = $statusCode;
        if (is_string($err)) {
            $this->endReq($err);
        } else {
            $this->endReq(\JSON::encode($err));
        }
    }

    protected function sendResult($jsonStr, $statusCode = 200)
    {
        $this->setContentType('json');
        $this->app->statusCode = $statusCode;
        if (is_string($jsonStr)) {
            $this->endReq($jsonStr);
        } else {
            $this->endReq(\JSON::encode($jsonStr));
        }
    }


    protected function getEndpointSchema($endpointName)
    {
        $filepath = $this->app->conf->SITE_PATH . $this->app->conf->PROTECTED_FOLDER . 'endpoint/' . $endpointName . '.php';
        return include($filepath);
    }

    protected function prepareAllEndpointSchema()
    {
        $reflect = new \ReflectionClass($this);
        $clsName = \lcfirst(\substr($reflect->getShortName(), 0, -10));
        $props = $reflect->getProperties();

        foreach ($props as $prop) {
            $propName = $prop->getName();

            if (\is_null($this->{$propName}) && \strpos($propName, 'field') === 0) {
                $methodName = \lcfirst(\substr($propName, 5));
                $this->{$propName} = $this->getEndpointSchema($clsName . '/' . $methodName);
            }
        }
    }
}