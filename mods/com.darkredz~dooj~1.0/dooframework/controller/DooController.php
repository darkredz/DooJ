<?php
/**
 * DooController class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */

/**
 * Base class of all controller
 *
 * <p>Provides a few shorthand methods to access commonly used component during development. e.g. DooLoader, DooLog, DooSqlMagic.</p>
 *
 * <p>Parameter lists and extension type defined in routes configuration can be accessed through <b>$this->params</b> and <b>$this->extension</b></p>
 *
 * <p>If a client sends PUT request to your controller, you can retrieve the values sent through <b>$this->puts</b></p>
 *
 * <p>GET and POST variables can still be accessed via php $_GET and $_POST. They are not handled/process by Doo framework.</p>
 *
 * <p>Auto routing can be denied from a Controller by setting <b>$autoroute = false</b></p>
 *
 * Therefore, the following class properties & methods is reserved and should not be used in your Controller class.
 * <code>
 * $params
 * $puts
 * $extension
 * $autoroute
 * $vdata
 * $renderMethod
 * initPutVars()
 * load()
 * db()
 * acl()
 * beforeRun()
 * cache()
 * saveRendered()
 * saveRenderedC()
 * view()
 * render()
 * renderc()
 * language()
 * acceptType()
 * setContentType()
 * clientIP()
 * afterRun()
 * getKeyParam()
 * getKeyParams()
 * viewRenderAutomation()
 * isAjax()
 * isSSL()
 * toXML()
 * toJSON()
 * setHeader()
 * setRawHeader()
 * getInput()
 * replyReq()
 * endReq()
 * setCookie()
 * getCookie()
 * startNewSession(()
 * destroyCurrentSession()
 * </code>
 *
 * You still have a lot of freedom to name your methods and properties other than names mentioned.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooController.php 1000 2009-07-7 18:27:22
 * @package doo.controller
 * @since 1.0
 */
class DooController
{

    /**
     * @var DooWebApp
     */
    public $app;

    /**
     * @var DooContainer
     */
    public $container;

    /**
     * To enable async mode where output are send through explicit calls of $this->replyReq() and the request has to be ended manually with  $this->endReq() once done processed
     * PHP output functions echo, print_r, var_dump or include php template, cannot be used with async mode. Manually call ob_start() to get PHP output if needed.
     * @var bool
     */
    public $async = false;

    public $asyncBeforeRun = false;
    public $asyncAfterRun = false;


    /**
     * Associative array of the parameter list found matched in a URI route.
     * @var array
     */
    public $params;

    /**
     * Associative array of the PUT values sent by client.
     * @var array
     */
    public $puts;

    /**
     * Extension name (.html, .json, .xml ,...) found in the URI. Routes can be specified with a string or an array as matching extensions
     * @var string
     */
    public $extension;

    /**
     * Deny or allow auto routing access to a Controller. By default auto routes are allowed in a controller.
     * @var bool
     */
    public $autoroute = true;

    /**
     * Data to be pass from controller to view to be rendered
     * @var mixed
     */
    public $vdata;

    /**
     * Enable auto render of view at the end of a controller -> method request
     * @var bool
     */
    public $autorender = false;

    /**
     * Render method for auto render. You can use 'renderc' & 'render' or your own method in the controller.
     * @var string Default is renderc
     */
    public $renderMethod = 'renderc';

    /**
     * Session object for the current request. Only available when SESSION_ENABLE is true.
     * Session is automatically save when request is ended.
     * @var DooVertxSession
     */
    public $session;

    public $_GET;
    public $_POST;
    public $_FILES;

    protected $_load;
    protected $_view;

    /**
     * @param DooAppInterface $app
     * @param DooContainer $container
     */
    function __construct($app = null, $container = null)
    {
        $this->app = $app;
        $this->container = $container;
    }

    /**
     * Set PUT request variables in a controller. This method is to be used by the main web app class.
     */
    public function initPutVars()
    {
        parse_str(file_get_contents('php://input'), $this->puts);
    }

    /**
     * Returns the RBAC instance from containers
     * @return DooRbac
     */
    public function rbac()
    {
        if ($this->container->getShared('rbac')) {
            return $this->container->getShared('rbac');
        }
        return null;
    }

    /**
     * This will be called before the actual action is executed
     */
    public function beforeRun($resource, $action, $beforeRunHandler = null)
    {
    }

    /**
     * Returns the cache singleton, shorthand to Doo::cache()
     * @return DooFileCache|DooFrontCache|DooApcCache|DooMemCache|DooXCache|DooEAcceleratorCache
     */
    public function cache($cacheType = 'file')
    {
        return Doo::cache($cacheType);
    }

    /**
     * Set header. eg. setHeader('Content-Type', 'application/json')
     * @param string $name Header name
     * @param string $content Header content
     */
    public function setHeader($name, $content)
    {
        if ($this->async) {
            $this->app->setHeader($name, $content);
            $this->app->request->response()->putHeader($name, $content);
        } else {
            $this->app->setHeader($name, $content);
        }
    }

    /**
     * Set raw header. eg. 'HTTP/1.1 200 OK'
     * @param string $rawHeader Header content
     * @param bool $replace Whether to replace the same header that is previously set
     * @param int $code HTTP status code
     */
    public function setRawHeader($rawHeader, $replace = true, $code = null)
    {
        $this->app->setRawHeader($rawHeader, $replace, $code);
    }

    /**
     * Writes the generated output produced by render() to file.
     * @param string $path Path to save the generated output.
     * @param string $templatefile Template file name (without extension name)
     * @param array $data Associative array of the data to be used in the Template file. eg. <b>$data['username']</b>, you should use <b>{{username}}</b> in the template.
     * @return string|false The file name of the rendered output saved (html).
     */
    public function saveRendered($path, $templatefile, $data = null)
    {
        return $this->view()->saveRendered($path, $templatefile, $data);
    }

    /**
     * Writes the generated output produced by renderc() to file.
     * @param string $path Path to save the generated output.
     * @param string $templatefile Template file name (without extension name)
     * @param array $data Associative array of the data to be used in the Template file. eg. <b>$data['username']</b>, you should use <b>{{username}}</b> in the template.
     * @param bool $enableControllerAccess Enable the view scripts to access the controller property and methods.
     * @param bool $includeTagClass If true, DooView will determine which Template tag class to include. Else, no files will be loaded
     * @return string|false The file name of the rendered output saved (html).
     */
    public function saveRenderedC(
        $path,
        $templatefile,
        $data = null,
        $enableControllerAccess = false,
        $includeTagClass = true
    )
    {
        if ($enableControllerAccess === true) {
            return $this->view()->saveRenderedC($file, $data, $this, $includeTagClass);
        } else {
            return $this->view()->saveRenderedC($file, $data, null, $includeTagClass);
        }
    }

    /**
     * The view singleton, auto create if the singleton has not been created yet.
     * @return DooView|DooViewBasic
     */
    public function view()
    {
        if ($this->_view == null) {
            $engine = $this->app->conf->TEMPLATE_ENGINE;
//            Doo::loadCore('view/' . $engine, &$this->app->conf);
            $this->_view = new $engine;
            $this->_view->conf = &$this->app->conf;
        }

        return $this->_view;
    }

    /**
     * Short hand for $this->view()->render() Renders the view file.
     *
     * @param string $file Template file name (without extension name)
     * @param array $data Associative array of the data to be used in the Template file. eg. <b>$data['username']</b>, you should use <b>{{username}}</b> in the template.
     * @param bool $process If TRUE, checks the template's last modified time against the compiled version. Regenerates if template is newer.
     * @param bool $forceCompile Ignores last modified time checking and force compile the template everytime it is visited.
     */
    public function render($file, $data = null, $process = null, $forceCompile = false)
    {
        if ($this->async) {
            ob_start();
        }
        $this->view()->render($file, $data, $process, $forceCompile);
        if ($this->async) {
            $data = ob_get_contents();
            ob_end_clean();
            $this->endReq($data);
        }
    }

    /**
     * Short hand for $this->view()->renderc() Renders the view file(php) located in viewc.
     *
     * @param string $file Template file name (without extension name)
     * @param array $data Associative array of the data to be used in the php template.
     * @param bool $enableControllerAccess Enable the view scripts to access the controller property and methods.
     * @param bool $includeTagClass If true, DooView will determine which Template tag class to include. Else, no files will be loaded
     */
    public function renderc($file, $data = null, $enableControllerAccess = false, $includeTagClass = true)
    {
        if ($this->async) {
            ob_start();
        }

        if ($enableControllerAccess === true) {
            $this->view()->renderc($file, $data, $this, $includeTagClass);
        } else {
            $this->view()->renderc($file, $data, null, $includeTagClass);
        }

        if ($this->async) {
            $output = utf8_decode(ob_get_clean());
            $this->endReq($output);
        }
    }

    /**
     * Get the client accept language from the header
     *
     * @param bool $countryCode to return the language code along with country code
     * @return string The language code. eg. <b>en</b> or <b>en-US</b>
     */
    public function language($countryCode = false)
    {
        $langcode = (!empty($this->app->_SERVER['HTTP_ACCEPT_LANGUAGE'])) ? $this->app->_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
        $langcode = (!empty($langcode)) ? explode(';', $langcode) : $langcode;
        $langcode = (!empty($langcode[0])) ? explode(',', $langcode[0]) : $langcode;
        if (!$countryCode) {
            $langcode = (!empty($langcode[0])) ? explode('-', $langcode[0]) : $langcode;
        }
        return is_array($langcode) ? $langcode[0] : $langcode;
    }

    /**
     * Checks a content type against request's Accept header
     * @param $contentType Content type eg. application/json
     * @return bool
     */
    protected function checkReqAcceptType($contentType, $allowMultipleValues = false)
    {
        return $this->checkReqHeaderValue('Accept', $contentType, $allowMultipleValues);
    }

    /**
     * Checks a content type against request's Content-Type header
     * @param $contentType Content type eg. application/json
     * @return bool
     */
    protected function checkReqContentType($contentType, $allowMultipleValues = false)
    {
        return $this->checkReqHeaderValue('Content-Type', $contentType, $allowMultipleValues);
    }

    /**
     * Checks a value againts request's header
     * @param $headerKey
     * @param $contentType
     * @param bool $allowMultipleValues
     * @return bool
     */
    protected function checkReqHeaderValue($headerKey, $contentType, $allowMultipleValues = false)
    {
        if ($allowMultipleValues == false) {
            return $this->app->request->headers()->get($headerKey) == $contentType;
        } else {
            $multiples = explode(',', $this->app->request->headers()->get($headerKey));
            foreach ($multiples as $type) {
                if (trim($type) == $contentType) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * Shorthand to get request's Content-Type header
     * @param $contentType
     * @return string
     */
    protected function requestContentType($contentType)
    {
        return $this->app->request->headers()->get('Content-Type');
    }

    /**
     * Get the client specified accept type from the header sent
     *
     * <p>Instead of appending a extension name like '.json' to a URL,
     * clients can use 'Accept: application/json' for RESTful APIs.</p>
     * @return string Client accept type
     */
    public function acceptType()
    {
        if (empty($this->app->_SERVER["HTTP_ACCEPT"])) {
            return null;
        }

        $type = [
            '*/*' => '*',
            'html' => 'text/html,application/xhtml+xml',
            'xml' => 'application/xml,text/xml,application/x-xml',
            'json' => 'application/json,text/x-json,application/jsonrequest,text/json',
            'js' => 'text/javascript,application/javascript,application/x-javascript',
            'css' => 'text/css',
            'rss' => 'application/rss+xml',
            'yaml' => 'application/x-yaml,text/yaml',
            'atom' => 'application/atom+xml',
            'pdf' => 'application/pdf',
            'text' => 'text/plain',
            'png' => 'image/png',
            'jpg' => 'image/jpg,image/jpeg,image/pjpeg',
            'gif' => 'image/gif',
            'form' => 'multipart/form-data',
            'url-form' => 'application/x-www-form-urlencoded',
            'csv' => 'text/csv',
            'tsv' => 'text/tsv',
        ];

        $matches = [];

        //search and match, add 1 priority to the key if found matched
        foreach ($type as $k => $v) {
            if (strpos($v, ',') !== false) {
                $tv = explode(',', $v);
                foreach ($tv as $k2 => $v2) {
                    if (stristr($this->app->_SERVER["HTTP_ACCEPT"], $v2)) {
                        if (isset($matches[$k])) {
                            $matches[$k] = $matches[$k] + 1;
                        } else {
                            $matches[$k] = 1;
                        }
                    }
                }
            } else {
                if (stristr($this->app->_SERVER["HTTP_ACCEPT"], $v)) {
                    if (isset($matches[$k])) {
                        $matches[$k] = $matches[$k] + 1;
                    } else {
                        $matches[$k] = 1;
                    }
                }
            }
        }

        if (sizeof($matches) < 1) {
            return null;
        }

        //sort by the highest priority, keep the key, return the highest
        arsort($matches);

        foreach ($matches as $k => $v) {
            return ($k === '*/*') ? 'html' : $k;
        }
    }

    /**
     * Sent a content type header
     *
     * <p>This can be used with your REST api if you allow clients to retrieve result format
     * by sending a <b>Accept type header</b> in their requests. Alternatively, extension names can be
     * used at the end of an URI such as <b>.json</b> and <b>.xml</b></p>
     *
     * <p>NOTE: This method should be used before echoing out your results.
     * Use accept_type() or $extension to determined the desirable format the client wanted to accept.</p>
     *
     * @param string $type Content type of the result. eg. text, xml, json, rss, atom
     * @param string $charset Charset of the result content. Default utf-8.
     */
    public function setContentType($type, $charset = 'utf-8')
    {
        $extensions = [
            'html' => 'text/html',
            'xml' => 'application/xml',
            'json' => 'application/json',
            'js' => 'application/javascript',
            'css' => 'text/css',
            'rss' => 'application/rss+xml',
            'yaml' => 'text/yaml',
            'atom' => 'application/atom+xml',
            'pdf' => 'application/pdf',
            'text' => 'text/plain',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'csv' => 'text/csv',
            'tsv' => 'text/tsv',
        ];
        if (isset($extensions[$type])) {
            $this->setHeader("Content-Type", "{$extensions[$type]}; charset=$charset");
        }
    }

    /**
     * Check token to prevent CSRF attack. Token had to be stored in session and verify against header/post field. Use com.doophp.util.UUID::randomUUID() to generate a unique CSRF token
     * @param $token Unique CSRF token stored
     * @param string $output Output if failed to pass CSRF token validation to the client
     * @param string $csrfParamName Field name/Header name for the CSRF token field. Default is 'x-csrf-token'
     * @return bool
     */
    public function checkCsrf($token, $output = null, $csrfParamName = 'x-csrf-token')
    {
        $method = $this->app->_SERVER['REQUEST_METHOD'];
        $failed = false;

        if (!($method == 'GET' || $method == 'OPTIONS' || $method == 'HEAD')) {
            if (!$this->app->request->headers()->get($csrfParamName) && !$this->_POST[$csrfParamName]) {
                $failed = true;
            } else {
                if (isset($this->app->request->headers()->get($csrfParamName)) && $this->app->request->headers()->get($csrfParamName) != $token) {
                    $failed = true;
                } else {
                    if (isset($this->_POST[$csrfParamName]) && $this->_POST[$csrfParamName] != $token) {
                        $failed = true;
                    }
                }
            }

            if ($failed) {
                $this->app->statusCode = 403;

                if ($this->async) {
                    $this->endReq($output);
                } else {
                    echo $output;
                }
            }
        }
        return !$failed;
    }

    /**
     * Get client's IP
     * @return string
     */
    public function clientIP()
    {

        if ($this->app->request->headers()->get('HTTP_X_FORWARDED_FOR')) {
            return $this->app->request->headers()->get('HTTP_X_FORWARDED_FOR');
        } else {
            if ($this->app->request->headers()->get('HTTP_CLIENT_IP')) {
                return $this->app->request->headers()->get('HTTP_CLIENT_IP');
            } else {
                if ($this->app->_SERVER['REMOTE_ADDR']) {
                    return $this->app->_SERVER['REMOTE_ADDR'];
                }
            }
        }
    }

    /**
     * This will be called if the action method returns null or success status(200 to 299 not including 204) after the actual action is executed
     * @param mixed $routeResult The result returned by an action
     */
    public function afterRun($routeResult)
    {
        if ($this->autorender === true && ($routeResult === null || ($routeResult >= 200 && $routeResult < 300 && $routeResult != 204))) {
            $this->viewRenderAutomation();
        }
    }

    /**
     * Retrieve value of a key from URI accessed from an auto route.
     * Example with a controller named UserController and a method named listAll():
     * <code>
     * //URI is http://localhost/user/list-all/id/11
     * $this->getKeyParam('id');   //returns 11
     * </code>
     *
     * @param string $key
     * @return mixed
     */
    public function getKeyParam($key, $urldecode = true)
    {
        if (!empty($this->params)) {
            $idx = array_search($key, $this->params);

            if ($idx !== false && $idx % 2 === 0) {
                if ($urldecode) {
                    $params = implode(' * ', $this->params);
                    $params = explode(' * ', urldecode($params));
                    $valueIndex = array_search($key, $params) + 1;
                    if ($valueIndex < sizeof($params)) {
                        return $params[$valueIndex];
                    }
                }

                $valueIndex = array_search($key, $this->params) + 1;
                if ($valueIndex < sizeof($this->params)) {
                    return $this->params[$valueIndex];
                }
            }
        }
    }

    /**
     * Retrieve an array of keys & values from URI accessed from an auto route.
     * Example with a controller named UserController and a method named listAll():
     * <code>
     * //URI is http://localhost/user/list-all/id/11/type/admin
     * $this->getKeyParams( array('id', 'type') );   //returns array('id'=>11, 'type'=>'admin')
     * </code>
     * @param type $keys
     * @return type
     */
    public function getKeyParams($keys, $excludeEmpty = false)
    {
        $params = [];
        foreach ($keys as $k) {
            $params[$k] = $this->getKeyParam($k);
            if ($excludeEmpty && empty($params[$k])) {
                unset($params[$k]);
            }
        }
        return $params;
    }


    public function getKeyParamsDashToUnderscore($keys, $excludeEmpty = false)
    {
        $params = [];
        foreach ($keys as $k) {
            $params[$k] = $this->getKeyParam($k);

            if (empty($params[$k]) && strpos($k, '_') !== false) {
                $params[$k] = $this->getKeyParam(str_replace('_', '-', $k));
            }
            if ($excludeEmpty && empty($params[$k])) {
                unset($params[$k]);
            }
        }
        return $params;
    }

    /**
     * Controls the automated view rendering process.
     */
    public function viewRenderAutomation()
    {
        if (is_string($this->app->conf->AUTO_VIEW_RENDER_PATH)) {
            $path = $this->app->conf->AUTO_VIEW_RENDER_PATH;
            $path = str_replace(':', '@', substr($path, 1));
            $this->{$this->renderMethod}($path, $this->vdata);
        } else {
            if (isset($this->app->conf->AUTO_VIEW_RENDER_PATH)) {
                $this->{$this->renderMethod}(strtolower($this->app->conf->AUTO_VIEW_RENDER_PATH[0]) . '/' . strtolower($this->app->conf->AUTO_VIEW_RENDER_PATH[1]),
                    $this->vdata);
            } else {
                $this->{$this->renderMethod}('index', $this->vdata);
            }
        }
    }

    /**
     * Check if the request is an AJAX request usually sent with JS library such as JQuery/YUI/MooTools
     * @return bool
     */
    public function isAjax()
    {
        return (isset($this->app->_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($this->app->_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    /**
     * Check if the connection is a SSL connection
     * @return bool determined if it is a SSL connection
     */
    public function isSSL()
    {
        if (!isset($this->app->_SERVER['HTTPS'])) {
            return false;
        }

        //Apache
        if ($this->app->_SERVER['HTTPS'] == 1) {
            return true;
        } //IIS
        elseif ($this->app->_SERVER['HTTPS'] === 'on') {
            return true;
        } //other servers
        elseif ($this->app->_SERVER['SERVER_PORT'] == 443) {
            return true;
        }
        return false;
    }

    /**
     * Convert DB result into XML string for RESTful api.
     * <code>
     * public function listUser(){
     *     $user = new User;
     *     $rs = $user->find();
     *     $this->toXML($rs, true);
     * }
     * </code>
     * @param mixed $result Result of a DB query. eg. $user->find();
     * @param bool $output Output the result automatically.
     * @param bool $setXMLContentType Set content type.
     * @param string $encoding Encoding of the result content. Default utf-8.
     * @return string XML string
     */
    public function toXML($result, $output = false, $setXMLContentType = false, $encoding = 'utf-8')
    {
        $str = '<?xml version="1.0" encoding="' . $encoding . '"?><result>';
        if (is_array($result)) {
            foreach ($result as $kk => $vv) {
                $cls = get_class($vv);
                $str .= (!empty($cls)) ? '<' . $cls . '>' : '<item>';
                foreach ($vv as $k => $v) {
                    if ($k != '_table' && $k != '_fields' && $k != '_primarykey') {
                        if (is_array($v)) {
                            //print_r($v);
                            //exit;
                            $str .= '<' . $k . '>';
                            foreach ($v as $v0) {
                                $str .= '<data>';
                                foreach ($v0 as $k1 => $v1) {
                                    if ($k1 != '_table' && $k1 != '_fields' && $k1 != '_primarykey') {
                                        if (is_array($v1)) {
                                            $str .= '<' . $k1 . '>';
                                            foreach ($v1 as $v2) {
                                                $str .= '<data>';
                                                foreach ($v2 as $k3 => $v3) {
                                                    if ($k3 != '_table' && $k3 != '_fields' && $k3 != '_primarykey') {
                                                        $str .= '<' . $k3 . '><![CDATA[' . $v3 . ']]></' . $k3 . '>';
                                                    }
                                                }
                                                $str .= '</data>';
                                            }
                                            $str .= '</' . $k1 . '>';
                                        } else {
                                            $str .= '<' . $k1 . '><![CDATA[' . $v1 . ']]></' . $k1 . '>';
                                        }
                                    }
                                }
                                $str .= '</data>';
                            }
                            $str .= '</' . $k . '>';

                        } else {
                            $str .= '<' . $k . '>' . $v . '</' . $k . '>';
                        }
                    }
                }
                $str .= (!empty($cls)) ? '</' . $cls . '>' : '</item>';
            }
        }
        $str .= '</result>';
        if ($setXMLContentType === true) {
            $this->setContentType('xml', $encoding);
        }
        if ($output === true) {
            echo $str;
        }
        return $str;
    }

    /**
     * Convert DB result into JSON string for RESTful api.
     * <code>
     * public function listUser(){
     *     $user = new User;
     *     $rs = $user->find();
     *     $this->toJSON($rs, true);
     * }
     * </code>
     * @param mixed $result Result of a DB query. eg. $user->find();
     * @param bool $output Output the result automatically.
     * @param bool $removeNullField Remove fields with null value from JSON string.
     * @param array $exceptField Remove fields that are null except the ones in this list.
     * @param array $mustRemoveFieldList Remove fields in this list.
     * @param bool $setJSONContentType Set content type.
     * @param string $encoding Encoding of the result content. Default utf-8.
     * @return string JSON string
     */
    public function toJSON(
        $result,
        $output = false,
        $removeNullField = false,
        $exceptField = null,
        $mustRemoveFieldList = null,
        $setJSONContentType = true,
        $encoding = 'utf-8'
    )
    {
        if (!is_string($result)) {
            $result = \JSON::encode($result);
        }
        $rs = preg_replace([
            '/\,\"\_table\"\:\".*\"/U',
            '/\,\"\_primarykey\"\:\".*\"/U',
            '/\,\"\_fields\"\:\[\".*\"\]/U',
        ], '', $result);
        if ($removeNullField) {
            if ($exceptField === null) {
                $rs = preg_replace(['/\,\"[^\"]+\"\:null/U', '/\{\"[^\"]+\"\:null\,/U'], ['', '{'], $rs);
            } else {
                $funca1 = create_function('$matches',
                    'if(in_array($matches[1], array(\'' . implode("','", $exceptField) . '\'))===false){
                                return "";
                            }
                            return $matches[0];');

                $funca2 = create_function('$matches',
                    'if(in_array($matches[1], array(\'' . implode("','", $exceptField) . '\'))===false){
                                return "{";
                            }
                            return $matches[0];');

                $rs = preg_replace_callback('/\,\"([^\"]+)\"\:null/U', $funca1, $rs);
                $rs = preg_replace_callback('/\{\"([^\"]+)\"\:null\,/U', $funca2, $rs);
            }
        }

        //remove fields in this array
        if ($mustRemoveFieldList !== null) {
            $funcb1 = create_function('$matches',
                'if(in_array($matches[1], array(\'' . implode("','", $mustRemoveFieldList) . '\'))){
                            return "";
                        }
                        return $matches[0];');

            $funcb2 = create_function('$matches',
                'if(in_array($matches[1], array(\'' . implode("','", $mustRemoveFieldList) . '\'))){
                            return "{";
                        }
                        return $matches[0];');

            $rs = preg_replace_callback([
                '/\,\"([^\"]+)\"\:\".*\"/U',
                '/\,\"([^\"]+)\"\:\{.*\}/U',
                '/\,\"([^\"]+)\"\:\[.*\]/U',
                '/\,\"([^\"]+)\"\:([false|true|0-9|\.\-|null]+)/',
            ], $funcb1, $rs);

            $rs = preg_replace_callback(['/\{\"([^\"]+)\"\:\".*\"\,/U', '/\{\"([^\"]+)\"\:\{.*\}\,/U'], $funcb2, $rs);

            preg_match('/(.*)(\[\{.*)\"(' . implode('|', $mustRemoveFieldList) . ')\"\:\[(.*)/', $rs, $m);

            if ($m) {
                if ($pos = strpos($m[4], '"}],"')) {
                    if ($pos2 = strpos($m[4], '"}]},{')) {
                        $d = substr($m[4], $pos2 + 5);
                        if (substr($m[2], -1) == ',') {
                            $m[2] = substr_replace($m[2], '},', -1);
                        }
                    } else {
                        if (strpos($m[4], ']},{') !== false) {
                            $d = substr($m[4], strpos($m[4], ']},{') + 3);
                            if (substr($m[2], -1) == ',') {
                                $m[2] = substr_replace($m[2], '},', -1);
                            }
                        } else {
                            if (strpos($m[4], '],"') === 0) {
                                $d = substr($m[4], strpos($m[4], '],"') + 2);
                            } else {
                                if (strpos($m[4], '}],"') !== false) {
                                    $d = substr($m[4], strpos($m[4], '],"') + 2);
                                } else {
                                    $d = substr($m[4], $pos + 4);
                                }
                            }
                        }
                    }
                } else {
                    $rs = preg_replace('/(\[\{.*)\"(' . implode('|', $mustRemoveFieldList) . ')\"\:\[.*\]\}(\,)?/U',
                        '$1}', $rs);
                    $rs = preg_replace('/(\".*\"\:\".*\")\,\}(\,)?/U', '$1}$2', $rs);
                }

                if (isset($d)) {
                    $rs = $m[1] . $m[2] . $d;
                }
            }
        }

        if ($output === true) {
            if ($setJSONContentType === true) {
                $this->setContentType('json', $encoding);
            }

            if (!$this->async) {
                echo $rs;
            } else {
                $this->endReq($rs);
            }
        }
        return $rs;
    }

    /**
     * Get input from array with a predefined set of fields
     * @param array $fields List of fields to extract from raw input array
     * @param array $rawInput Associative array which contain the data. fieldname => data
     * @return array Returns data that are only specified in $fields
     */
    public function getInput($fields, $rawInput)
    {
        $input = [];
        foreach ($fields as $k) {
            $input[$k] = $rawInput[$k];
        }
        return $input;
    }

    /**
     * Write response to client. HTTP chunk is enabled when this method is called. Output pass through reply always come before strings echo by PHP
     * @param string $msg Message to output
     * @param string $enc Encoding, default is UTF-8
     */
    public function replyReq($msg, $enc = 'UTF-8')
    {

        //auto set response status if not set
        if ($this->app->statusCode && !$this->app->request->response()->getStatusCode()) {
            $this->app->request->response()->setStatusCode($this->app->statusCode);
            $this->app->request->response()->setStatusMessage($this->app->httpCodes[$this->app->statusCode]);
        }

        if ($this->app->request->response()->isChunked() == false) {
            $this->app->request->response()->setChunked(true);
        }
        $this->app->request->response()->write($msg, $enc);
    }

    /**
     * Short hand for $this->app->end();
     * @param string $out Additional output to end with request
     */
    public function endReq($out = null)
    {
        if ($this->app->async && $this->app->request->response()->isChunked() == false) {
            $this->app->request->response()->setChunked(true);
        }
        $this->app->end($out);
    }

    /**
     * Set cookies data. Short hand to $this->app->setCookie()
     * @param array $cookieArr Associative array with field as key and data as value
     * @param int $expires The time the cookie expires. This is a Unix timestamp so is in number of seconds since the epoch. In other words, you'll most likely set this with the time() function plus the number of seconds before you want it to expire. Or you might use mktime(). time()+60*60*24*30 will set the cookie to expire in 30 days. If set to 0, or omitted, the cookie will expire at the end of the session (when the browser closes).
     * @param string $path The path on the server in which the cookie will be available on. If set to '/', the cookie will be available within the entire domain. If set to '/foo/', the cookie will only be available within the /foo/ directory and all sub-directories such as /foo/bar/ of domain. The default value is the current directory that the cookie is being set in.
     * @param string $domain The domain that the cookie is available to. Setting the domain to 'www.example.com' will make the cookie available in the www subdomain and higher subdomains. Cookies available to a lower domain, such as 'example.com' will be available to higher subdomains, such as 'www.example.com'. Older browsers still implementing the deprecated » RFC 2109 may require a leading . to match all subdomains.
     */
    public function setCookie($cookieArr, $expires = null, $path = '/', $domain = null)
    {
        $this->app->setCookie($cookieArr, $expires, $path, $domain);
    }

    /**
     * Get cookies data sent from browser. Short hand to $this->app->getCookie()
     * @return array
     */
    public function getCookie()
    {
        return $this->app->getCookie();
    }

    /**
     * Start new session. Generates new session ID and new session instance. Do not write any response to client before starting a new session.
     */
    public function startNewSession()
    {
        $this->app->startNewSession();
    }

    /**
     * Destroy client session and purge the session cookie
     */
    public function destroyCurrentSession()
    {
        $this->app->destroyCurrentSession();
    }

    public function authBasic(
        $authSchemeName,
        $authUserPass,
        $rejectMessage = 'Unauthorized',
        $authRejectPage = null,
        $authRejectPageData = null
    )
    {
        $authorization = $this->app->request->headers()->get("authorization");
        $userCd = null;
        $passCd = null;
        $scheme = null;

        if ($authorization == null) {
            $this->app->trace("401 auth reject null");
            $this->setHeader("WWW-Authenticate", "Basic realm=\"" . $authSchemeName . "\"");
            $this->app->statusCode = 401;
            if ($authRejectPage != null) {
                $this->renderc($authRejectPage, $authRejectPageData, true);
            } else {
                $this->endReq($rejectMessage);
            }
            return false;
        } else {
            try {
                $parts = explode(' ', $authorization);
                $scheme = $parts[0];
                $credentials = explode(':', \DatatypeConverter::parseBase64Binary($parts[1]));
//                $this->app->trace("Credentials " . $credentials);
                $userCd = $credentials[0];
                // when the header is: "user:"
                $passCd = sizeof($credentials) > 1 ? $credentials[1] : null;
            } catch (ArrayIndexOutOfBoundsException $e) {
//                $this->app->trace("401 auth reject invalid! IP " . $this->clientIP());
                $this->setHeader("WWW-Authenticate", "Basic realm=\"" . $authSchemeName . "\"");
                $this->app->statusCode = 401;
                if ($authRejectPage != null) {
                    $this->renderc($authRejectPage, $authRejectPageData, true);
                } else {
                    $this->endReq($rejectMessage);
                }
                return false;
            } catch (Exception $e) {
                // IllegalArgumentException includes PatternSyntaxException
//                $this->app->trace("401 auth reject invalid 2! IP " . $this->clientIP());
                $this->setHeader("WWW-Authenticate", "Basic realm=\"" . $authSchemeName . "\"");
                $this->app->statusCode = 401;
                if ($authRejectPage != null) {
                    $this->renderc($authRejectPage, $authRejectPageData, true);
                } else {
                    $this->endReq($rejectMessage);
                }
                return false;
            }

            if ($scheme != "Basic") {
//                $this->app->trace("401 auth reject invalid 3! IP " . $this->clientIP());
                $this->setHeader("WWW-Authenticate", "Basic realm=\"" . $authSchemeName . "\"");
                $this->app->statusCode = 401;
                if ($authRejectPage != null) {
                    $this->renderc($authRejectPage, $authRejectPageData, true);
                } else {
                    $this->endReq($rejectMessage);
                }
                return false;
            } else {
                $authOk = false;
                foreach ($authUserPass as $u => $p) {
                    if ($u == $userCd && $p == $passCd) {
                        $authOk = true;
                        break;
                    }
                }
                if ($authOk) {
//                    $this->app->trace("OK auth from web client! IP " . $this->clientIP());
                    return true;
                } else {
//                    $this->app->trace("401 auth wrong! IP " . $this->clientIP());
                    $this->setHeader("WWW-Authenticate", "Basic realm=\"" . $authSchemeName . "\"");
                    $this->app->statusCode = 401;
                    if ($authRejectPage != null) {
                        $this->renderc($authRejectPage, $authRejectPageData, true);
                    } else {
                        $this->endReq($rejectMessage);
                    }
                    return false;
                }
            }
        }
        return false;
    }


}
