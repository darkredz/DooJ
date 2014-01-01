<?php
/**
 * DooEventBusApp class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */


/**
 * DooEventBusApp mimics API in @see DooWebApp where HTTP requests are processed. With the same API, requests can be send to a event bus centered app without modifying application code.
 * The same MVC structure can be reuse provided async mode is used and request's response are send/ended with replyReq() and endReq() in controller. This allows web based app to be converted into a eventbus based app(or vice versa) with minimal effort.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @package doo.app
 * @since 2.0
 */
class DooEventBusApp {
    /**
     * @var DooConfig
     */
    public $conf;

    public $_SERVER;
    public $_GET;
    public $_POST;
    /**
     * @var DooEventBusRequest
     */
    public $request;
    public $logger;
    public $db;
    public $endCallback;
    public $endCallbackData;
    public $async = false;
    public $ended = false;
    public $httpClients;

    /**
     * @var DooVertxSessionManager
     */
    public $sessionManager;

    /**
     * @var DooVertxSession
     */
    public $session;

    public $httpCodes = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Checkpoint',
        122 => 'Request-URI too long',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        308 => 'Resume Incomplete',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        444 => 'No Response',
        449 => 'Retry With',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        509 => 'Bandwidth Limit Exceeded'
    );

    /**
     * @var array Headers to be sent to client
     */
    public $headers = [];

    public $statusCode = 200;

    /**
     * @var array routes defined in <i>routes.conf.php</i>
     */
    public $route;


    public function init($message, $config){
        //msg format
        /*
            {
                "headers": {
                    "Content-Type": "application/json",
                    "Accept-Language": "en-US"
                },
                "absoluteUri": "http://xxxxx" or "ws://asdasd"
                "uri": "/dev/test-query-async",
                "method": "GET",
                "body": <Post content>
            }

        */
        $json = $message->body();

        if(empty($json)){
            $message->reply(['status' => 'error', 'value'=>'empty message body']);
            return false;
        }

        if(is_string($json)){
            $json = json_decode($json, true);
        }

        if(is_array($json) == false){
            $message->reply(['status' => 'error', 'value'=>'invalid message body']);
            return false;
        }

        if(empty($json['method'])){
            $message->reply(['status' => 'error', 'value'=>'Missing method']);
            return false;
        }

        if(empty($json['uri'])){
            $message->reply(['status' => 'error', 'value'=>'Missing uri']);
            return false;
        }

//    Vertx::logger()->info( var_export($json, true) );

        $request = new DooEventBusRequest();
        $request->absoluteUri    = $json['absoluteUri'];
        $request->uri            = $json['uri'];
        $request->method         = $json['method'];
        $request->body           = $json['body'];
        $request->remoteAddress  = $json['remoteAddress'];

        if(!empty($json['headers'])){
            $request->headers = json_decode($json['headers'],true);
        }

        $response = new DooEventBusResponse();
        $response->statusCode = 200;
        $response->statusMessage = 'OK';
        $response->replyHeaders = [];
        $response->replyOutput = '';
        $response->ebMessage = $message;

        $request->response = $response;

        $conf = new DooConfig();
        $conf->set($config);
        $response->debug = $conf->DEBUG_ENABLED;

        $this->request = $request;
        $this->conf = $conf;

        if($conf->DEBUG_ENABLED){
            Vertx::logger()->info( 'Request: ' . var_export($json, true) );
        }
        return true;
    }

    public function trace($obj){
        if($this->conf->DEBUG_ENABLED){
            $this->logger->debug( var_export($obj, true) );
        }
    }

    public function logInfo($msg){
        $this->logger->info( $msg );
    }

    public function logError($msg){
        $this->logger->error( $msg );
    }

    public function logDebug($msg){
        if($this->conf->DEBUG_ENABLED){
            $this->logger->debug( $msg );
        }
    }

    public function parseQueryString( $str ){
        $params = [];

        //ensure that the dots are not converted to underscores
        parse_str( $str, $params );

        if(strpos($str, '.') === false){
            return $params;
        }

        $separator = '&';

        // go through $params and ensure that the dots are not converted to underscores
        $args = explode( $separator, $str );
        foreach ( $args as $arg ) {
            $parts = explode( '=', $arg, 2 );
            if ( !isset( $parts[1] ) ) {
                $parts[1] = null;
            }

            if ( substr_count( $parts[0], '[' ) === 0 ) {
                $key = $parts[0];
            }
            else {
                $key = substr( $parts[0], 0, strpos( $parts[0], '[' ) );
            }

            $paramKey = str_replace( '.', '_', $key );
            if ( isset( $params[$paramKey] ) && strpos( $paramKey, '_' ) !== false ){
                $newKey = '';
                for ( $i = 0; $i < strlen( $paramKey ); $i++ ){
                    $newKey .= ( $paramKey{$i} === '_' && $key{$i} === '.' ) ? '.' : $paramKey{$i};
                }

                $keys = array_keys( $params );
                if ( ( $pos = array_search( $paramKey, $keys ) ) !== false ){
                    $keys[$pos] = $newKey;
                }
                $values = array_values( $params );
                $params = array_combine( $keys, $values );
            }
        }

        return $params;
    }

    /**
     * @param DooConfig $conf
     * @param array $route
     * @param $request
     */
    public function exec($conf, $route, $message, $autoInit = true){

        if($autoInit){
            if( $this->init($message, $conf) == false ){
                return false;
            }
        }

        $this->route = $route;

        if(!$this->logger){
            $this->logger = Vertx::logger();
        }

//        $fullpath = explode('/', $this->request->absoluteUri);

        $this->_GET = $this->request->params();
        $headers = $this->request->headers;

        $contentType = $headers["Content-Type"];
        $this->_SERVER['CONTENT_TYPE'] = $contentType;
        $this->_SERVER['CONTENT_LENGTH'] = $headers["Content-Length"];

        $fullpath = explode('/', $this->request->absoluteUri);

//        $this->_GET = $this->request->params();
        $this->_GET = [];
        if(strpos($this->request->uri,'?')!==false){
            $this->_GET = $this->parseQueryString( explode('?',$this->request->uri,2)[1] );  //$this->request->params()->map;
        }

        $headers = $this->request->headers;

        $contentType = $headers["Content-Type"];
        $this->_SERVER['CONTENT_TYPE'] = $contentType;
        $this->_SERVER['CONTENT_LENGTH'] = $headers["Content-Length"];

        $this->_SERVER['DOCUMENT_ROOT']	 = getcwd() + '/';
        $this->_SERVER['REQUEST_METHOD'] = $method = strtoupper($this->request->method);
        $this->_SERVER['REQUEST_URI']	   = $this->request->uri;
        $this->_SERVER['HTTP_HOST']		   = $fullpath[2];
        $this->_SERVER['REMOTE_ADDR'] 	 = $this->request->remoteAddress;
//        $this->_SERVER['SERVER_PROTOCOL'] = $this->request->version();

        $this->_SERVER['HTTP_ACCEPT'] = $headers['Accept'];
        $this->_SERVER['HTTP_ACCEPT_LANGUAGE'] = $headers['Accept-Language'];
        $this->_SERVER['HTTP_ACCEPT_ENCODING'] = $headers['Accept-Encoding'];
        $this->_SERVER['HTTP_CACHE_CONTROL'] = $headers['Cache-Control'];
        $this->_SERVER['HTTP_USER_AGENT'] = $headers['User-Agent'];
        $this->_SERVER['HTTP_X_REQUESTED_WITH'] = $headers['X-Requested-With'];

        if($method == 'GET' || $method == 'OPTIONS' || $method == 'HEAD'){
            $this->processRequest();
        }
        else{
            if(empty($contentType) || stripos($contentType, 'application/x-www-form-urlencoded') !== false){
                $this->_POST = $this->parseQueryString($this->request->body);
            }
            else if(stripos($contentType, 'multipart/form-data') !== false ){
                //parse multipart body to get parameters
                preg_match_all('/([\-]{3,}[a-zA-Z0-9]+\s?\n)Content\-Disposition\: form\-data\; name\=\"([a-zA-Z0-9\-\_]+)\"\s?\n([^\-]+)/', $this->request->body, $matches);
                $psize = sizeof($matches);


                if($psize == 4){
                    for($i=0; $i < sizeof($matches[0]); $i++){
                        $this->_POST[$matches[2][$i]] = trim($matches[3][$i]);
                    }
                }
            }
            else{
                $this->_POST = $this->request->body;
            }
            $this->processRequest();
        }
    }

    protected function processRequest(){
        //if async mode, do not end http request response in this method. end it manually in controller methods
        if($this->conf->SESSION_ENABLE == true){
            $this->sessionManager = new DooVertxSessionManager();
            $this->sessionManager->app = &$this;

            //get session data and set it to app before running controller
            $this->getSession(function($session){
                if(!empty($session)){
                    $session->resetModified();
                    $this->session = $session;
                }
                $this->run();
            });
        }
        else{
            $this->run();
        }
    }


    /**
     * Start new session. Generates new session ID and new session instance. Do not write any response to client before starting a new session.
     */
    public function startNewSession(){
        $this->session = $this->sessionManager->startSession();
        $this->session->resetModified(true);
    }

    /**
     * Start new session. Do not write any response to client before starting a new session.
     */
    public function destroyCurrentSession(){
        if($this->session)
            $this->sessionManager->destroySession($this->session);
    }

    /**
     * Get session for this request if available
     * @param callable $callback Callback function once session is retrieve from session cluster
     */
    public function getSession(callable $callback){
        $this->sessionManager->getSession($callback);
    }

    /**
     * Save session data to session cluster
     * @param DooVertxSession $obj
     * @param callable $callback Callback function once session data is saved
     */
    public function saveSessionData(DooVertxSession $obj, callable $callback=null){
        if($this->sessionManager==null) return;
        $obj->resetModified();
        $this->sessionManager->saveSessionData($obj, $callback);
    }

    public function endBlock($result){
        //blocking mode
        $this->end($result);
    }

    public function eventBus(){
        return Vertx::eventBus();
    }

    /**
     * End the app process for current request
     * @param string $out Additional output to end with request
     */
    public function end($output=null){
        if($this->ended) return;
        $appHeaders = $this->headers;
        $statusCode = $this->statusCode;
        $this->request->response->statusCode = $statusCode;
        $this->request->response->statusMessage = $this->httpCodes[$statusCode];

        if(sizeof($appHeaders) === 0){
            $this->request->response->putHeader('Content-Type', 'text/html');
        }
        else{
            foreach ($appHeaders as $key => $value) {
                $this->request->response->putHeader($key, $value);
            }
            if(!isset($appHeaders['Content-Type'])){
                $this->request->response->putHeader('Content-Type', 'text/html');
            }
        }

        //auto save session
        if($this->conf->SESSION_ENABLE == true && $this->session!=null && $this->session->isModified()){
            $this->saveSessionData($this->session);
        }

        //if status code is in error range, plus no output, try to check if ERROR_CODE_PAGES is defined.
        //if is defined as a php file, error_503.php include and render the file.
        //if is a route /error/code/503, reroute and render the final output
        if($output===null && isset($this->conf->ERROR_CODE_PAGES) && $this->conf->ERROR_CODE_PAGES[$statusCode]){
            $errPage = $this->conf->ERROR_CODE_PAGES[$statusCode];

            if($errPage{0}=='/'){
                $this->reroute($errPage, true);

                if($this->async == false){
                    $result = ob_get_clean();
                    $this->endBlock($result);
                }
                return;
            }
            else{
                ob_start();
                include $this->conf->SITE_PATH . $errPage;
                $output = ob_get_contents();
                ob_end_clean();
            }
        }

        //end response for async mode since end() method is explicitly called from controller once process if done and not needed.
        if($this->async==true){
            if($output==null){
                $this->request->response->end();
            }
            else{
                $this->request->response->end($output);
            }
        }
        else{
            if($output==null){
                $this->request->response->end();
            }
            else{
                $this->request->response->end($output);
            }
        }

        $this->ended = true;

        if(!empty($this->endCallback)){
            call_user_func_array($this->endCallback, [$this]);
        }
    }


    /**
     * Set cookies data
     * @param array $cookieArr Associative array with field as key and data as value
     * @param int $expires The time the cookie expires. This is a Unix timestamp so is in number of seconds since the epoch. In other words, you'll most likely set this with the time() function plus the number of seconds before you want it to expire. Or you might use mktime(). time()+60*60*24*30 will set the cookie to expire in 30 days. If set to 0, or omitted, the cookie will expire at the end of the session (when the browser closes).
     * @param string $path The path on the server in which the cookie will be available on. If set to '/', the cookie will be available within the entire domain. If set to '/foo/', the cookie will only be available within the /foo/ directory and all sub-directories such as /foo/bar/ of domain. The default value is the current directory that the cookie is being set in.
     * @param string $domain The domain that the cookie is available to. Setting the domain to 'www.example.com' will make the cookie available in the www subdomain and higher subdomains. Cookies available to a lower domain, such as 'example.com' will be available to higher subdomains, such as 'www.example.com'. Older browsers still implementing the deprecated » RFC 2109 may require a leading . to match all subdomains.
     */
    public function setCookie($cookieArr, $expires=null, $path='/', $domain=null){
        $cookies = [];

        if($expires!=null){
            $expires = ' expires=' . date('D, d-M-Y H:i:s', $expires) .' GMT;';
        }
        if($domain!=null){
            $domain = ' domain=' . $domain .';';
        }
        else if($this->conf->COOKIE_DOMAIN!=null){
            $domain = ' domain=' . $this->conf->COOKIE_DOMAIN .';';
        }

        foreach($cookieArr as $k=>$v){
            $cookies[] = "$k=$v; path=$path;". $expires . $domain;
        }

        $this->request->response->putHeader('Set-Cookie', $cookies);
    }

    /**
     * Get cookies data sent from browser
     * @return array
     */
    public function getCookie(){
        if(!isset( $this->request->headers['Cookie'])) return;

        $headers = $this->request->headers['Cookie'];

        $cookie = [];
        $parts = explode(";", $headers);
        foreach($parts as $p){
            $dt = explode("=", $p);
            $cookie[trim($dt[0])] = $dt[1];
        }

        return $cookie;
    }

    /**
     * Main function to run the web application
     */
    public function run(){
        $this->throwHeader( $this->routeTo() );
        if($this->async == false){
            $result = ob_get_clean();
            $this->endBlock($result);
        }
    }

    /**
     * Run the web application from a http request or a CLI execution.
     */
    public function autorun(){
        $opt = getopt('u:');
        if(isset($opt['u'])===true){
            $this->runFromCli();
        }else{
            $this->run();
        }
    }

    /**
     * Run the web application from a CLI execution. Execution through this method will set $this->conf->FROM_CLI to true.
     * Options required in CLI:
     * <code>
     *   // -u (required) URI: any route you have in your application
     *   -u="/any/uri/route/"
     *
     *   // -m (optional) Request method: post, put, get, delete. Default is get.
     *   -m="get"
     * </code>
     */
    public function runFromCli(){
        $opt = getopt('u:m::');
        if(isset($opt['u'])===true){
            $uri = $opt['u'];

            if($uri[0]!='/')
                $uri = '/' . $uri;

            $this->conf->SUBFOLDER = '/';
            $this->_SERVER['REQUEST_URI'] = $uri;
            $this->_SERVER['REQUEST_METHOD'] = (isset($opt['m'])) ? $opt['m'] : 'GET';
            $this->conf->FROM_CLI = true;
            $this->run();
        }
    }

    /**
     * Handles the routing process.
     * Auto routing, sub folder, subdomain, sub folder on subdomain are supported.
     * It can be used with or without the <i>index.php</i> in the URI
     * @return mixed HTTP status code such as 404 or URL for redirection
     */
    public function routeTo(){
        $router = new DooUriRouter;
        $router->app = $this;
        $router->conf = $this->conf;
        $routeRs = $router->execute($this->route,$this->conf->SUBFOLDER);

        if(isset($routeRs['redirect'])===true){
            list($redirUrl, $redirCode) = $routeRs['redirect'];
//            DooUriRouter::redirect($redirUrl, true, $redirCode);
            $this->statusCode = $redirCode;
            $this->setHeader('Location', $redirUrl);
            return;
        }

        if($routeRs[0]!==null && $routeRs[1]!==null){
            //dispatch, call Controller class

            if($routeRs[0][0]!=='['){
                if(strpos($routeRs[0], '\\')!==false){
                    $nsClassFile = str_replace('\\','/',$routeRs[0]);
                    $nsClassFile = explode($this->conf->APP_NAMESPACE_ID.'/', $nsClassFile, 2);
                    $nsClassFile = $nsClassFile[1];
                    require_once $this->conf->SITE_PATH . $this->conf->PROTECTED_FOLDER . $nsClassFile .'.php';
                }else{
                    require_once $this->conf->SITE_PATH . $this->conf->PROTECTED_FOLDER . "controller/{$routeRs[0]}.php";
                }
            }else{
                $moduleParts = explode(']', $routeRs[0]);
                $moduleName = substr($moduleParts[0],1);

                if(isset($this->conf->PROTECTED_FOLDER_ORI)===true){
                    require_once $this->conf->SITE_PATH . $this->conf->PROTECTED_FOLDER_ORI . 'module/'. $moduleName .'/controller/'.$moduleParts[1].'.php';
                }else{
                    require_once $this->conf->SITE_PATH . $this->conf->PROTECTED_FOLDER . 'module/'. $moduleName .'/controller/'.$moduleParts[1].'.php';
                    $this->conf->PROTECTED_FOLDER_ORI = $this->conf->PROTECTED_FOLDER;
                }

                //set class name
                $routeRs[0] = $moduleParts[1];
                $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI . 'module/'.$moduleName.'/';
            }

            if(strpos($routeRs[0], '/')!==false){
                $clsname = explode('/', $routeRs[0]);
                $routeRs[0] = $clsname[ sizeof($clsname)-1 ];
            }

            //if defined class name, use the class name to create the Controller object
            $clsnameDefined = (sizeof($routeRs)===4);
            if($clsnameDefined)
                $controller = new $routeRs[3];
            else
                $controller = new $routeRs[0];

            $controller->app = &$this;
            $controller->_GET = &$this->_GET;
            $controller->_POST = &$this->_POST;
            $controller->params = $routeRs[2];

            if($this->conf->SESSION_ENABLE == true){
                $controller->session = &$this->session;
            }

            if(isset($controller->params['__extension'])===true){
                $controller->extension = $controller->params['__extension'];
                unset($controller->params['__extension']);
            }
            if(isset($controller->params['__routematch'])===true){
                $controller->routematch = $controller->params['__routematch'];
                unset($controller->params['__routematch']);
            }

            if($this->_SERVER['REQUEST_METHOD']==='PUT')
                $controller->init_put_vars();

            if($controller->async == false){
                $this->async = false;
                ob_start();
            }
            else{
                $this->async = true;
            }

            //before run, normally used for ACL auth
            if($clsnameDefined){
                if($rs = $controller->beforeRun($routeRs[3], $routeRs[1])){
                    return $rs;
                }
            }else{
                if($rs = $controller->beforeRun($routeRs[0], $routeRs[1])){
                    return $rs;
                }
            }

            $routeRs = $controller->$routeRs[1]();
            $controller->afterRun($routeRs);
            return $routeRs;
        }
        //if auto route is on, then auto search Controller->method if route not defined by user
        else if($this->conf->AUTOROUTE){

            list($controller_name, $method_name, $method_name_ori, $params, $moduleName )= $router->auto_connect($this->conf->SUBFOLDER, (isset($this->route['autoroute_alias'])===true)?$this->route['autoroute_alias']:null );

            if(empty($this->route['autoroute_force_dash'])===false){
                if($method_name!=='index' && $method_name===$method_name_ori && $method_name_ori[0]!=='_' && ctype_lower($method_name_ori)===false){
                    $this->throwHeader(404);
                    return;
                }
            }

            if(in_array($method_name, array('destroyCurrentSession','startNewSession','setCookie','getCookie','endReq','replyReq','getInput','setHeader','setRawHeader','initPutVars','load','db','acl','beforeRun','cache','saveRendered','saveRenderedC','view','render','renderc','language','acceptType','setContentType','clientIP','afterRun','getKeyParam','getKeyParams','viewRenderAutomation','isAjax','isSSL','toXML','toJSON'))){
                $this->throwHeader(404);
                return;
            }

            if(empty($this->route['autoroute_force_dash'])===false && strpos($moduleName, '-')!==false){
                $moduleName = str_replace('-', '_', $moduleName);
            }

            if(isset($moduleName)===true){
                $this->conf->PROTECTED_FOLDER_ORI = $this->conf->PROTECTED_FOLDER;
                $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI . 'module/'.$moduleName.'/';
            }

            $controller_file = $this->conf->SITE_PATH . $this->conf->PROTECTED_FOLDER . "controller/{$controller_name}.php";

            if(file_exists($controller_file)){
                require_once $controller_file;

                $methodsArray = get_class_methods($controller_name);

                //if controller name matches 2 classes with the same name, namespace and W/O namespace
                if($methodsArray!==null){
                    $unfoundInMethods = (in_array($method_name, $methodsArray)===false &&
                        in_array($method_name .'_'. strtolower($this->_SERVER['REQUEST_METHOD']), $methodsArray)===false );
                    if($unfoundInMethods){
                        $methodsArray = null;
                    }
                }

                //if the method not in controller class, check for a namespaced class with the same file name.
                if($methodsArray===null && isset($this->conf->APP_NAMESPACE_ID)===true){
                    if(isset($moduleName)===true){
                        $controller_name = $this->conf->APP_NAMESPACE_ID . '\\module\\'. $moduleName .'\\controller\\' . $controller_name;
                    }else{
                        $controller_name = $this->conf->APP_NAMESPACE_ID . '\\controller\\' . $controller_name;
                    }
                    $methodsArray = get_class_methods($controller_name);
                }

                //if method not found in both both controller and namespaced controller, 404 error
                if($methodsArray===null){
                    if(isset($this->conf->PROTECTED_FOLDER_ORI)===true)
                        $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI;
                    $this->throwHeader(404);
                    return;
                }
            }
            else if(isset($moduleName)===true && isset($this->conf->APP_NAMESPACE_ID)===true){
                if(isset($this->conf->PROTECTED_FOLDER_ORI)===true)
                    $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI;

                $controller_file = $this->conf->SITE_PATH . $this->conf->PROTECTED_FOLDER . '/controller/'.$moduleName.'/'.$controller_name .'.php';

                if(file_exists($controller_file)===false){
                    $this->throwHeader(404);
                    return;
                }
                $controller_name = $this->conf->APP_NAMESPACE_ID .'\\controller\\'.$moduleName.'\\'.$controller_name;
                #echo 'module = '.$moduleName.'<br>';
                #echo $controller_file.'<br>';                
                #echo $controller_name.'<br>';                   
                $methodsArray = get_class_methods($controller_name);
            }
            else{
                if(isset($this->conf->PROTECTED_FOLDER_ORI)===true)
                    $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI;
                $this->throwHeader(404);
                return;
            }

            //check for REST request as well, utilized method_GET(), method_PUT(), method_POST, method_DELETE()
            $restMethod = $method_name .'_'. strtolower($this->_SERVER['REQUEST_METHOD']);
            $inRestMethod = in_array($restMethod, $methodsArray);

            //check if method() and method_GET() etc. doesn't exist in the controller, 404 error
            if( in_array($method_name, $methodsArray)===false && $inRestMethod===false ){
                if(isset($this->conf->PROTECTED_FOLDER_ORI)===true)
                    $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI;
                $this->throwHeader(404);
                return;
            }

            //use method_GET() etc. if available
            if( $inRestMethod===true ){
                $method_name = $restMethod;
            }

            $controller = new $controller_name;
            $controller->app = &$this;
            $controller->_GET = &$this->_GET;
            $controller->_POST = &$this->_POST;

            if($this->conf->SESSION_ENABLE == true){
                $controller->session = &$this->session;
            }

            //if autoroute in this controller is disabled, 404 error
            if($controller->autoroute===false){
                if(isset($this->conf->PROTECTED_FOLDER_ORI)===true)
                    $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI;
                $this->throwHeader(404);
                return;
            }

            if($params!=null)
                $controller->params = $params;

            if($this->_SERVER['REQUEST_METHOD']==='PUT')
                $controller->initPutVars();

            if($controller->async == false){
                $this->async = false;
                ob_start();
            }
            else{
                $this->async = true;
            }

            //before run, normally used for ACL auth
            if($rs = $controller->beforeRun($controller_name, $method_name)){
                return $rs;
            }

            $routeRs = $controller->$method_name();
            $controller->afterRun($routeRs);

            return $routeRs;
        }
        else{
            $this->throwHeader(404);
            return;
        }
    }

    /**
     * Reroute the URI to an internal route
     * @param string $routeuri route uri to redirect to
     * @param bool $is404 send a 404 status in header
     */
    public function reroute($routeuri, $is404=false){

        if($this->conf->SUBFOLDER!='/')
            $this->_SERVER['REQUEST_URI'] = substr($this->conf->SUBFOLDER, 0, strlen($this->conf->SUBFOLDER)-1) . $routeuri;
        else
            $this->_SERVER['REQUEST_URI'] = $routeuri;

        if(isset($this->conf->PROTECTED_FOLDER_ORI)===true){
            $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI;
            unset( $this->conf->PROTECTED_FOLDER_ORI );
        }

        if($is404===true)
            $this->setHeader('HTTP/1.1', '404 Not Found');
        //$this->routeTo();
        $this->throwHeader( $this->routeTo() );
    }

    /**
     * Process a module from the main application.
     *
     * <p>This is similar to rerouting to a Controller. The framework offer 3 ways to process and render a module.</p>
     *
     * <p>Based on a predefined route:</p>
     * <code>
     * # The route is predefined in routes.conf.php
     * # $route['*']['/top/:nav'] = array('MyController', 'renderTop');
     * $data['top'] = Doo::app()->module('/top/banner');
     * </code>
     *
     * <p>Based on Controller name and Action method:</p>
     * <code>
     * Doo::app()->module('MyController', 'renderTop');
     *
     * # If controller is in sub folder
     * Doo::app()->module('folder/MyController', 'renderTop');
     *
     * # Passed in parameter if controller is using $this->param['var']
     * Doo::app()->module('MyController', 'renderTop', array('nav'=>'banner'));
     * </code>
     *
     * <p>If class name is different from controller filename:</p>
     * <code>
     * # filename is index.php, class name is Admin
     * Doo::app()->module(array('index', 'Admin'), 'renderTop');
     *
     * # in a sub folder
     * Doo::app()->module(array('admin/index', 'Admin'), 'renderTop');
     *
     * # with parameters
     * Doo::app()->module(array('admin/index', 'Admin'), 'renderTop', array('nav'=>'banner'));
     * </code>
     *
     * @param string|array $moduleUri URI or Controller name of the module
     * @param string $action Action to be called
     * @param array $params Parameters to be passed in to the Module
     * @return string Output of the module
     */
    public function module($moduleUri, $action=null, $params=null){
        if($moduleUri[0]=='/'){
            if($this->conf->SUBFOLDER!='/')
                $this->_SERVER['REQUEST_URI'] = substr($this->conf->SUBFOLDER, 0, strlen($this->conf->SUBFOLDER)-1) . $moduleUri;
            else
                $this->_SERVER['REQUEST_URI'] = $moduleUri;

            $tmp = $this->conf->PROTECTED_FOLDER;
            if(isset($this->conf->PROTECTED_FOLDER_ORI)===true){
                $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI;
                $tmpOri = $this->conf->PROTECTED_FOLDER_ORI;
            }

            ob_start();
            $this->routeTo();
            $data = ob_get_contents();
            ob_end_clean();

            $this->conf->PROTECTED_FOLDER = $tmp;

            if(isset($tmpOri)===true)
                $this->conf->PROTECTED_FOLDER_ORI = $tmpOri;

            return $data;
        }
        //if Controller name passed in:  Doo::app()->module('admin/SomeController', 'login',  array('nav'=>'home'));
        else if(is_string($moduleUri)){
            $controller_name = $moduleUri;
            if(strpos($moduleUri, '/')!==false){
                $arr = explode('/', $moduleUri);
                $controller_name = $arr[sizeof($arr)-1];
            }
            require_once $this->conf->SITE_PATH . $this->conf->PROTECTED_FOLDER . "controller/$moduleUri.php";

            $controller = new $controller_name;
            $controller->app = &$this;
            $controller->_GET = &$this->_GET;
            $controller->_POST = &$this->_POST;
            $controller->params = $params;

            if($this->conf->SESSION_ENABLE == true){
                $controller->session = &$this->session;
            }

            if($rs = $controller->beforeRun($controller_name, $action)){
                $this->throwHeader( $rs );
                return;
            }

            ob_start();
            $rs = $controller->{$action}();

            if($controller->autorender===true){
                $this->conf->AUTO_VIEW_RENDER_PATH = array(strtolower(substr($controller_name, 0, -10)), strtolower(preg_replace('/(?<=\\w)(?=[A-Z])/','-$1', $action)));
            }
            $controller->afterRun($rs);

            $this->throwHeader( $rs );

            $data = ob_get_contents();
            ob_end_clean();
            return $data;
        }
        //if array passed in. For controller file name != controller class name.
        //eg. Doo::app()->module(array('admin/Admin', 'AdminController'), 'login',  array('nav'=>'home'));
        else{
            require_once $this->conf->SITE_PATH . $this->conf->PROTECTED_FOLDER . "controller/{$moduleUri[0]}.php";

            $controller = new $moduleUri[1];
            $controller->app = &$this;
            $controller->_GET = &$this->_GET;
            $controller->_POST = &$this->_POST;
            $controller->params = $params;

            if($this->conf->SESSION_ENABLE == true){
                $controller->session = &$this->session;
            }

            if($rs = $controller->beforeRun($moduleUri[1], $action)){
                $this->throwHeader( $rs );
                return;
            }

            ob_start();
            $rs = $controller->{$action}();

            if($controller->autorender===true){
                $this->conf->AUTO_VIEW_RENDER_PATH = array(strtolower(substr($controller_name, 0, -10)), strtolower(preg_replace('/(?<=\\w)(?=[A-Z])/','-$1', $action)));
            }
            $controller->afterRun($rs);

            $this->throwHeader( $rs );

            $data = ob_get_contents();
            ob_end_clean();
            return $data;
        }
    }

    /**
     * Advanced version of DooWebApp::module(). Process a module from the main application or other modules.
     *
     * Module rendered using this method is located in SITE_PATH/PROTECTED_FOLDER/module
     *
     * @param string $moduleName Name of the module folder. To execute Controller/method in the main application, pass a null or empty string value for $moduleName.
     * @param string|array $moduleUri URI or Controller name of the module
     * @param string $action Action to be called
     * @param array $params Parameters to be passed in to the Module
     * @return string Output of the module
     */
    public function getModule($moduleName, $moduleUri, $action=null, $params=null){
        if(empty($moduleName)===false){
            if(isset($this->conf->PROTECTED_FOLDER_ORI)===false){
                $this->conf->PROTECTED_FOLDER_ORI = $tmp = $this->conf->PROTECTED_FOLDER;
                $this->conf->PROTECTED_FOLDER = $tmp . 'module/'.$moduleName.'/';
                $result = $this->module($moduleUri, $action, $params);
                $this->conf->PROTECTED_FOLDER = $tmp;
            }else{
                $tmp = $this->conf->PROTECTED_FOLDER;
                $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI . 'module/'.$moduleName.'/';
                $result = $this->module($moduleUri, $action, $params);
                $this->conf->PROTECTED_FOLDER = $tmp;
            }
        }
        else{
            $tmp = $this->conf->PROTECTED_FOLDER;
            $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI;
            $result = $this->module($moduleUri, $action, $params);
            $this->conf->PROTECTED_FOLDER = $tmp;
        }
        return $result;
    }


    public function redirect($url){
        $this->throwHeader($url);
        $this->end();
    }

    /**
     * Analyze controller return value and send appropriate headers such as 404, 302, 301, redirect to internal routes.
     *
     * <p>It is very SEO friendly but you would need to know the basics of HTTP status code.</p>
     * <p>Automatically handles 404, include error document or redirect to inner route
     * to handle the error based on config <b>ERROR_404_DOCUMENT</b> and <b>ERROR_404_ROUTE</b></p>
     * <p>Controller return value examples:</p>
     * <code>
     * 404                                  #send 404 header
     * array('/internal/route', 404)        #send 404 header & redirect to an internal route
     * 'http://www.google.com'              #redirect to URL. default 302 Found sent
     * array('http://www.google.com',301)   #redirect to URL. forced 301 Moved Permenantly sent
     * array('/hello/sayhi', 'internal')    #redirect internally, 200 OK
     * </code>
     * @param mixed $code
     */
    public function throwHeader($code){
        if($code!=null){
            if(is_int($code)){
                if($code===404){
                    //Controller return 404, send 404 header, include file if ERROR_404_DOCUMENT is set by user
                    // $this->setHeader('HTTP/1.1', '404 Not Found');
                    $this->statusCode = 404;

                    if(!empty($this->conf->ERROR_404_DOCUMENT)){
                        ob_start();
                        include $this->conf->SITE_PATH . $this->conf->ERROR_404_DOCUMENT;
                        $data = ob_get_contents();
                        ob_end_clean();
                        $this->end($data);
                        return 404;
                    }
                    //execute route to handler 404 display if ERROR_404_ROUTE is defined, the route handler shouldn't send any headers or return 404
                    else if(!empty($this->conf->ERROR_404_ROUTE)){
                        $this->reroute($this->conf->ERROR_404_ROUTE, true);
                        return 404;
                    }
                }
                //if not 404, just send the header code
                else{
                    $this->statusCode = $code;

                    //if status code is in error range, plus no output, try to check if ERROR_CODE_PAGES is defined.
                    //if is defined as a php file, error_503.php include and render the file.
                    //if is a route /error/code/503, reroute and render the final output
                    if(isset($this->conf->ERROR_CODE_PAGES) && $this->conf->ERROR_CODE_PAGES[$code]){
                        $errPage = $this->conf->ERROR_CODE_PAGES[$code];
                        $output = null;

                        if($errPage{0}=='/'){
                            $this->reroute($errPage, true);

                            if($this->async == false){
                                $result = ob_get_clean();
                                $this->endBlock($result);
                            }
                            return;
                        }
                        else{
                            ob_start();
                            include $this->conf->SITE_PATH . $errPage;
                            $output = ob_get_contents();
                            ob_end_clean();
                        }

                        $this->end($output);
                    }
                }
            }
            elseif(is_string($code)){
                //Controller return the redirect location, it sends 302 Found
                // DooUriRouter::redirect($code, false);
                $this->statusCode = 302;
                $this->setHeader('Location', $code);
            }
            elseif(is_array($code)){
                //Controller return array('/some/routes/here', 'internal')
                if($code[1]=='internal'){
                    return $this->reroute($code[0]);
                }
                //Controller return array('http://location.to.redirect', 301)
                elseif($code[1]===404){
                    return $this->reroute($code[0], true);
                }
                // if array('http://location.to.redirect', 302), 302 Found is sent before Location:
                elseif($code[1]===302){
                    $this->statusCode = 302;
                    $this->setHeader('Location', $code[0]);
//                    DooUriRouter::redirect($code[0],false, $code[1], array("HTTP/1.1 302 Found"));
                }
                //else redirect with the http status defined,eg. 307 Moved Temporarily
                else if($code[1] > 299 && $code[1] < 400){
                    $this->statusCode = $code[1];
                    $this->setHeader('Location', $code[0]);
//                    DooUriRouter::redirect($code[0],false, $code[1]);
                }
                else{
                    if(!empty($code[1])){
                        $this->statusCode = $code[1];
                    }
                    // $this->setHeader(null, true, $code[1]);
                    return $this->reroute($code[0]);
                }
            }
        }
    }


    /**
     * Set header. eg. setHeader('Content-Type', 'application/json')
     * @param string $name Header name
     * @param string $content Header content
     */
    public function setHeader($name, $content){
        $this->headers[$name] = $content;
    }

    /**
     * Set raw header. eg. 'HTTP/1.1 200 OK'
     * @param string $rawHeader Header content
     * @param bool $replace Whether to replace the same header that is previously set
     * @param int $code HTTP status code
     */
    public function setRawHeader($rawHeader, $replace=true, $code=null){
        Vertx::logger()->info("Don't use setRawHeader for vertx");
        var_dump("Don't use setRawHeader for vertx");
    }

    /**
     * To debug variables with DooPHP's diagnostic view
     * @param mixed $var The variable to view in diagnostics.
     */
    public function debug($var){
        throw new DooDebugException($var);
    }

}