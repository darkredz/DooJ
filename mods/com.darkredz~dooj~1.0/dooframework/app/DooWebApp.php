<?php
/**
 * DooWebApp class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */


/**
 * DooWebApp is the global context that processed client requests. Request can be proxied to connected nodes in the cluster through event bus @see DooEventBusApp.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @package doo.app
 * @since 2.0
 */
class DooWebApp implements DooAppInterface
{
    /**
     * @var DooConfig
     */
    public $conf;

    public $vertx;

    /**
     * @var DooContainer
     */
    public $container;

    public $logPrefixDebug = '[DEBUG]: ';
    public $logPrefixInfo = '[INFO]: ';
    public $logPrefixError = '[ERROR]: ';

    public $_SERVER;
    public $_GET;
    public $_POST;
    public $_FILES = [];
    protected $filesUploadFound = 0;
    protected $filesUploadProcessed = 0;
    public $bodySizeExceeded = false;
    public $cookiesToSet = [];
    public $request;
    public $logger;
    public $db;
    public $endCallback;
    public $endCallbackData;
    public $async = false;
    public $proxy;
    public $proxyTimeout = 29000;
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

    public $httpCodes = [
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
        509 => 'Bandwidth Limit Exceeded',
    ];

    /**
     * @var array Headers to be sent to client
     */
    public $headers = [];

    public $statusCode = 200;

    /**
     * @var array routes defined in <i>routes.conf.php</i>
     */
    public $route;


    public function getServerErrorHandler(
        $msg = '{"error":"Service Unavailable"}',
        $contentType = 'application/json',
        $status = 503
    )
    {
        return function () use ($msg, $contentType, $status) {
            if (!$this->ended && !$this->request->response()->ended()) {
                $this->setHeader('Content-Type', $contentType);
                $this->statusCode = $status;

                if ($contentType == 'application/json' && is_array($msg)) {
                    $msg = \JSON::encode($msg);
                }

                $this->end($msg);
            }
        };
    }

    public function execServerErrorHandler(
        $msg = '{"error":"Service Unavailable"}',
        $contentType = 'application/json',
        $status = 503
    )
    {
        $callable = $this->getServerErrorHandler($msg, $contentType, $status);
        $callable();
    }

    public function trace($obj)
    {
        if ($this->conf->DEBUG_ENABLED) {
            $this->logger->debug(print_r($obj, true));
        }
    }

    public function logInfo($msg)
    {
        $this->logger->info($this->logPrefixInfo . $msg);
    }

    public function logError($msg)
    {
        $this->logError($this->logPrefixError . $msg);
    }

    public function logDebug($msg)
    {
        if ($this->conf->DEBUG_ENABLED) {
            $this->logger->debug($this->logPrefixDebug . $msg);
        }
    }

    public function parseQueryString($str)
    {
        $params = [];

        //ensure that the dots are not converted to underscores
        parse_str($str, $params);

        if (strpos($str, '.') === false) {
            return $params;
        }

        $separator = '&';

        // go through $params and ensure that the dots are not converted to underscores
        $args = explode($separator, $str);
        foreach ($args as $arg) {
            $parts = explode('=', $arg, 2);
            if (!isset($parts[1])) {
                $parts[1] = null;
            }

            if (substr_count($parts[0], '[') === 0) {
                $key = $parts[0];
            } else {
                $key = substr($parts[0], 0, strpos($parts[0], '['));
            }

            $paramKey = str_replace('.', '_', $key);
            if (isset($params[$paramKey]) && strpos($paramKey, '_') !== false) {
                $newKey = '';
                for ($i = 0; $i < strlen($paramKey); $i++) {
                    $newKey .= ($paramKey{$i} === '_' && $key{$i} === '.') ? '.' : $paramKey{$i};
                }

                $keys = array_keys($params);
                if (($pos = array_search($paramKey, $keys)) !== false) {
                    $keys[$pos] = $newKey;
                }
                $values = array_values($params);
                $params = array_combine($keys, $values);
            }
        }

        return $params;
    }

    /**
     * @param DooConfig $conf
     * @param array $route
     * @param $request
     */
    public function exec($conf, $route, $request)
    {

        $this->conf = $conf;
        $this->route = $route;
        $this->request = $request;

        if (!$this->logger) {
            $this->logger = \LoggerFactory::getLogger(__CLASS__);
        }

        $fullpath = explode('/', $this->request->absoluteURI());
        $lastpart = $fullpath[sizeof($fullpath) - 1];

        $headers = [];

        $headersJav = $this->request->headers();
        foreach ($headersJav as $f => $h) {
            $headers[$f] = $h;
        }

        //serve static files
        if (isset($conf->WEB_STATIC_PATH)) {
            if (strpos($lastpart, '.') !== false) {
                $file = $this->request->path();
                $file = $conf->WEB_STATIC_PATH . $file;

                if (file_exists($file)) {
                    if ($conf->WEB_STATIC_INCLUDE_LAST_MODIFIED) {
                        $lastModified = filemtime($file);
                        $lastModified = gmdate("D, d M Y H:i:s", $lastModified) . " GMT";
                        $this->request->response()->putHeader('Last-Modified', $lastModified);
                    }

                    if ($conf->WEB_STATIC_ETAG || $this->request->uri() == '/favicon.ico') {
                        $etag = md5_file($file);

                        if (trim($headers['If-None-Match']) == '"' . $etag . '"') {
                            $this->request->response()->statusCode = 304;
                            $this->request->response()->statusMessage = $this->httpCodes[304];
                            $this->request->response()->end();

                            if (!empty($this->endCallback)) {
                                call_user_func_array($this->endCallback, [$this, true]);
                            }
                            return;
                        }

                        $this->request->response()->putHeader('Etag', '"' . $etag . '"');
                    }

                    if (isset($conf->WEB_STATIC_CACHE_CONTROL_EXPIRY)) {
                        $this->request->response()->putHeader('Cache-Control',
                            "public, max-age=" . $conf->WEB_STATIC_CACHE_CONTROL_EXPIRY);
                        $this->request->response()->putHeader('Expires',
                            gmdate("D, d M Y H:i:s", time() + $conf->WEB_STATIC_CACHE_CONTROL_EXPIRY) . " GMT");
                    } else {
                        if ($this->request->uri() == '/favicon.ico') {
                            $this->request->response()->putHeader('Cache-Control', "public, max-age=86400");
                            $this->request->response()->putHeader('Expires',
                                gmdate("D, d M Y H:i:s", time() + 86400) . " GMT");
                        }
                    }

                    $this->ended = true;
                    $this->request->response()->sendFile($file);

                    if (!empty($this->endCallback)) {
                        call_user_func_array($this->endCallback, [$this, true]);
                    }
                    return;
                }
            }
        }

        $this->_GET = [];
        if (strpos($this->request->uri(), '?') !== false) {
            $this->_GET = $this->parseQueryString(explode('?', $this->request->uri(),
                2)[1]);  //$this->request->params()->map;
        }

        $contentType = $headers["Content-Type"];
        $this->_SERVER['CONTENT_TYPE'] = $contentType;
        $this->_SERVER['CONTENT_LENGTH'] = $headers["Content-Length"];

        $this->_SERVER['DOCUMENT_ROOT'] = getcwd() . '/';
        $this->_SERVER['REQUEST_METHOD'] = $method = strtoupper($this->request->method());
        $this->_SERVER['REQUEST_URI'] = $this->request->uri();
        $this->_SERVER['HTTP_HOST'] = $fullpath[2];
        $this->_SERVER['REMOTE_ADDR'] = $this->request->remoteAddress()->host();
        $this->_SERVER['SERVER_PROTOCOL'] = $this->request->version();
        $this->_SERVER['HTTP_ACCEPT'] = $headers['Accept'];
        $this->_SERVER['HTTP_ACCEPT_LANGUAGE'] = $headers['Accept-Language'];
        $this->_SERVER['HTTP_ACCEPT_ENCODING'] = $headers['Accept-Encoding'];
        $this->_SERVER['HTTP_CACHE_CONTROL'] = $headers['Cache-Control'];
        $this->_SERVER['HTTP_USER_AGENT'] = $headers['User-Agent'];
        $this->_SERVER['HTTP_X_REQUESTED_WITH'] = $headers['X-Requested-With'];
        $this->_SERVER['HTTPS'] = strpos($this->request->absoluteURI(), 'https://') === 0;

        if ($method == 'GET' || $method == 'OPTIONS' || $method == 'HEAD') {
            $this->processRequest();
        } else {
            //try processing input for POST, PUT, DELETE and etc HTTP methods
//             $logger->info('transfer-encoding = ' . $headers['Transfer-Encoding']);
//             $logger->info('content-length = ' . $headers['Content-Length']);

            if (!isset($headers['Transfer-Encoding']) && !isset($headers['Content-Length'])) {
                $this->processRequest();
                return;
            }

//             $logger->info('$contentType = ' . $contentType);

            $isJSON = $contentType != null && strpos($contentType, "application/json") !== false;
            $isMultipart = $contentType != null && strpos($contentType, "multipart/form-data") !== false;
            $isUrlencoded = $contentType != null && strpos($contentType, "application/x-www-form-urlencoded") !== false;
            $buffer = (!$isMultipart && !$isUrlencoded) ? \Buffer::buffer() : null;

            $this->request->setExpectMultipart(true);

            $body = \Buffer::buffer();

//             $logger->info('$isMultipart = ' . $isMultipart);


            $this->_POST = [];
            $app = &$this;


            $boundary = $app->request->headers()->get('Content-Type');

            if (!empty($boundary)) {
                $boundary = explode('boundary=', $boundary);
                if (sizeof($boundary) == 2) {
                    $boundary = $boundary[sizeof($boundary) - 1];
                }
            }

            $endHandler = function () use ($body, $app, $contentType, $isMultipart, $boundary) {
//                $app->logDebug('filesUploadFound ' . $app->filesUploadFound . ' processed '. $app->filesUploadProcessed);
                if ($app->filesUploadFound > 0 && $app->filesUploadFound != $app->filesUploadProcessed) {
//                    $app->logDebug('still have files to upload ' . ($app->filesUploadFound - $app->filesUploadProcessed));
                    return;
                }

                if ($body->length() > 0) {
                    if ($isMultipart) {

                        //parse multipart body to get parameters
                        if (!empty($boundary)) {
                            $formData = explode($boundary, $body->toString());

                            foreach ($formData as $dt) {
                                preg_match('/Content\-Disposition\: form\-data\; name\=\"([a-zA-Z0-9\-\_\[\]]+)\"\s?\n([.\S\s]+)/',
                                    $dt, $matches);
                                if (!empty($matches)) {
                                    $val = trim($matches[2]);
                                    if (substr($val, -4) == "\r\n--") {
                                        $val = substr($val, 0, strlen($val) - 4);
                                    } else {
                                        if (substr($val, -3) == "\n--") {
                                            $val = substr($val, 0, strlen($val) - 3);
                                        }
                                    }
                                    $app->_POST[$matches[1]] = $val;
                                }
                            }
                        }

//                        $app->logDebug($body->toString());
                    } else {
                        if (empty($contentType) || stripos($contentType,
                                'application/x-www-form-urlencoded') !== false) {
                            $app->_POST = $this->parseQueryString($body->toString());

                        } else {
                            $app->_POST = $body->toString();
                        }
                    }

                    if (!empty($app->_FILES)) {
                        foreach ($app->_FILES as $k => $f) {
                            reset($f);
                            list($key, $value) = each($f);

                            //make file field uploadField[] into indexed array
                            if ($key == $value['tmp_name']) {
                                $app->_FILES[$k] = array_values($f);
                            } else {
                                $parts = explode('[', $k);
                                if (sizeof($parts) == 2 && substr($parts[1], -1) == ']') {
                                    $main = $parts[0];
                                    $field = substr($parts[1], 0, -1);
                                    if (!isset($app->_FILES[$main])) {
                                        $app->_FILES[$main] = [];
                                    }
                                    $app->_FILES[$main][$field] = $f;
                                    unset($app->_FILES[$k]);
                                }
                            }
                        }
                    }
                }

                $app->processRequest();
            };

            $this->request->uploadHandler(g(function ($upload) use ($endHandler, $app) {
//                $this->logDebug('------------------ uploadHandler ');
                $filename = $filenameOri = $upload->filename();

//                $this->logDebug('file ' . $filename);

                if ($app->bodySizeExceeded || empty($filename) || $filename == '') {
                    return;
                }

                $keyName = $upload->name();
                $ext = explode('.', $filename);
                $ext = $ext[sizeof($ext) - 1];
                $filename = \UUID::randomUUID() . '.' . $ext;
                $tmpName = $app->conf->UPLOAD_FOLDER . $filename; // .'___'. $filename;

                if (strpos($keyName, '[]') !== false) {
                    $keyName = str_replace('[]', '', $keyName);
                    if (!isset($app->_FILES[$keyName])) {
                        $app->_FILES[$keyName] = [];
                    }
                    $app->_FILES[$keyName][$tmpName] = [
                        'filename' => $filename,
                        'original_name' => $filenameOri,
                        'tmp_name' => $tmpName,
                        'type' => $upload->contentType(),
                    ];
                } else {
                    $app->_FILES[$keyName] = [
                        'filename' => $filename,
                        'original_name' => $filenameOri,
                        'tmp_name' => $tmpName,
                        'type' => $upload->contentType(),
                    ];
                }

                $upload->exceptionHandler(g(function ($cause) use ($upload, $app, $endHandler, $tmpName) {
//                    $this->logDebug('------------------ exceptionHandler ' . $cause);
                    $keyName = $upload->name();
                    if (strpos($keyName, '[]') !== false) {
                        $keyName = str_replace('[]', '', $keyName);
                        $app->_FILES[$keyName][$tmpName]['error'] = $cause->getMessage();
                    } else {
                        $app->_FILES[$keyName]['error'] = $cause->getMessage();
                    }
                    $app->filesUploadFound = $app->filesUploadProcessed = 0;
                    $endHandler();
                }));

                $upload->endHandler(v(function () use ($upload, $app, $endHandler, $tmpName) {
//                    $this->logDebug('------------------ upload endHandler ' . $upload->name() . ' '. $upload->filename() . ' ' . $upload->size());
                    $keyName = $upload->name();

                    if (strpos($keyName, '[]') !== false) {
                        $keyName = str_replace('[]', '', $keyName);
                        $item = &$app->_FILES[$keyName][$tmpName];
                    } else {
                        $item = &$app->_FILES[$keyName];
                    }

                    $fileSize = $upload->size();
                    $item['size'] = $fileSize;
                    $item['isSizeAvailable'] = $upload->isSizeAvailable();

                    if ($app->conf->UPLOAD_MAX_SIZE > -1 && $fileSize > $app->conf->UPLOAD_MAX_SIZE) {
                        $item['error'] = UPLOAD_ERR_INI_SIZE;

                        $app->vertx->setTimer(50, g(function ($tid) use ($app, $tmpName) {
                            $app->vertx->fileSystem()->exists($tmpName,
                                a(function ($result, $error) use ($app, $tmpName) {
                                    if ($result) {
                                        $app->vertx->fileSystem()->delete($tmpName, null);
                                    }
                                }));
                        }));
                    }

                    $app->filesUploadProcessed++;
                    $endHandler();
                }));

                $upload->streamToFileSystem($tmpName);
            }));

            // enable the parsing at Vert.x level
            $this->request->handler(g(function ($buffer) use ($app, $body, $boundary) {
//                 $app->logger->info('$buffer = ' . $buffer->toString());
                if ($this->conf->BODY_MAX_SIZE > -1 && $body->length() > $this->conf->BODY_MAX_SIZE) {
                    $app->logError('Body size exceed limit ' . $this->conf->BODY_MAX_SIZE);
                    $app->bodySizeExceeded = true;
                    return;
                }
                $body->appendBuffer($buffer);

                if (!empty($boundary)) {
                    preg_match_all('/[\-]{2,}[' . preg_quote($boundary) . ']+\s?\nContent\-Disposition\: form\-data\; name\=\"[a-zA-Z0-9\-\_\[\]]+\"; filename=\"(.+)\"\s?\n[^\-]+/',
                        $body->toString(), $mathces);
                    if ($mathces && isset($mathces[0])) {
                        $this->filesUploadFound = sizeof($mathces[0]);
                    }
                }
            }));

            $this->request->endHandler(v($endHandler));
        }
    }

    protected function processRequest()
    {
        //if proxy enable, send request to other servers
        if (isset($this->proxy)) {
            //forward all if there's only one address (proxy is a string)
            if (is_string($this->proxy)) {
                $this->sendProxyRequest($this->proxy);
                return;
            } else {
                if (is_array($this->proxy)) {
                    $uri = $this->request->uri();
                    if (strpos($uri, '?') !== false) {
                        $uri = explode('?', $uri, 2)[0];
                    }

                    foreach ($this->proxy as $regex => $address) {
                        if ($regex == '_others') {
                            continue;
                        }

                        //if regex contain domain and port (Virtual Host), try matching it against full URL (exclude query string)
                        if (strpos($regex, '^http:') === 0 || strpos($regex, '^http\:') === 0
                            || strpos($regex, '^https:') === 0 || strpos($regex, '^https\:') === 0) {
                            $host = $this->request->headers()->get('Host');
                            $uriParts = parse_url($this->request->absoluteURI());

                            if ($uriParts['port'] == 80) {
                                $fullUrl = $uriParts['scheme'] . '://' . $host . $uriParts['path'];
                            } else {
                                $fullUrl = $uriParts['scheme'] . '://' . $host . ':' . $uriParts['port'] . $uriParts['path'];
                            }

                            $match = preg_match('/' . $regex . '/', $fullUrl);
                        } else {
                            $match = preg_match('/' . $regex . '/', $uri);
                        }

                        if ($match) {
                            if ($this->conf->DEBUG_ENABLED) {
                                $this->logInfo("Proxy $regex to $address");
                            }

                            $this->sendProxyRequest($address);
                            return;
                        }
                    }

                    if ($this->proxy['_others']) {
                        $this->sendProxyRequest($this->proxy['_others']);
                        return;
                    }
                }
            }
        }

        //if async mode, do not end http request response in this method. end it manually in controller methods
        if ($this->conf->SESSION_ENABLE == true) {
            if ($this->conf->SESSION_MANAGER_CLASS) {
                $this->sessionManager = new $this->conf->SESSION_MANAGER_CLASS();
            } else {
                $this->sessionManager = new DooVertxSessionManager();
            }
            $this->sessionManager->app = &$this;

            //get session data and set it to app before running controller
            $this->getSession(function ($session) {
//                $this->logDebug('[APP]:getting session ' . $dmp);
                if (!empty($session)) {
                    $session->resetModified();
                    $this->session = $session;
                }
                $this->run();
            });
        } else {
            $this->run();
        }
    }

    public function sendProxyRequest($addr)
    {
        $headers = [];

        $headersJav = $this->request->headers();
        foreach ($headersJav as $f => $h) {
            $headers[$f] = $h;
        }

        $headers['HTTP_X_FORWARDED_FOR'] = $this->_SERVER['REMOTE_ADDR'];
        $headers = \JSON::encode($headers);
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
        $msg = [
            'headers' => $headers,
            'absoluteUri' => $this->request->absoluteURI(),
            'uri' => $this->request->uri(),
            'method' => $this->request->method(),
            'remoteAddress' => $this->request->remoteAddress()->host(),
        ];

        $msg['body'] = $this->_POST;

        // Vertx::eventBus()->sendWithTimeout($addr, $msg, $this->proxyTimeout, function($reply, $error) {
        $delivery = new \DeliveryOptions();
        $delivery->setSendTimeout($this->proxyTimeout);

        $this->eventBus()->send($addr, $msg, $delivery, g(function ($reply, $error) {
            if (!$error) {
                $res = $reply->body();

                //reply has full body content, headers, statuscode
                if (isset($res['body'])) {
                    $this->headers = $res['headers'];
                    $this->statusCode = $res['statusCode'];
                    $this->endBlock($res['body']);
                } else {
                    $this->endBlock($res['body']);
                }
            } else {
                $this->statusCode = 503;
                $this->end();
                $this->logInfo('Error proxy timeout');
            }
        }));
    }


    /**
     * Start new session. Generates new session ID and new session instance. Do not write any response to client before starting a new session.
     */
    public function startNewSession()
    {
//        $this->logDebug('[APP]:start new session');
        $this->session = $this->sessionManager->startSession();
        $this->session->resetModified(true);
    }

    /**
     * Start new session. Do not write any response to client before starting a new session.
     */
    public function destroyCurrentSession()
    {
        if ($this->session) {
            $this->logDebug('[APP]:Destroy current session');
            $this->sessionManager->destroySession($this->session);
        }
    }

    /**
     * Get session for this request if available
     * @param callable $callback Callback function once session is retrieve from session cluster
     */
    public function getSession(callable $callback)
    {
        $this->sessionManager->getSession($callback);
    }

    /**
     * Save session data to session cluster
     * @param DooVertxSession $obj
     * @param callable $callback Callback function once session data is saved
     */
    public function saveSessionData(DooVertxSession $obj, callable $callback = null)
    {
        if ($this->sessionManager == null) {
            return;
        }
//        $this->logDebug('[APP]:save session data');
        $obj->resetModified();
        $this->sessionManager->saveSessionData($obj, $callback);
    }

    public function endBlock($result)
    {
        //blocking mode
        $this->end($result);
    }

    public function eventBus()
    {
        return $this->vertx->eventBus();
    }

    /**
     * End the app process for current request
     * @param string $out Additional output to end with request
     */
    public function end($output = null)
    {
        if ($this->ended || $this->request->response()->ended()) {
            return;
        }
        $this->setCookiesInHeader();
        $appHeaders = $this->headers;
        $statusCode = $this->statusCode;
        $this->request->response()->setStatusCode($statusCode);
        $this->request->response()->setStatusMessage($this->httpCodes[$statusCode]);

        if (sizeof($appHeaders) === 0) {
            $this->request->response()->putHeader('Content-Type', 'text/html');
        } else {
            foreach ($appHeaders as $key => $value) {
                $this->request->response()->putHeader($key, $value);
            }
            if (!isset($appHeaders['Content-Type'])) {
                $this->request->response()->putHeader('Content-Type', 'text/html');
            }
        }

        //auto save session
        if ($this->conf->SESSION_ENABLE == true && $this->session != null && $this->session->isModified()) {
            $this->saveSessionData($this->session);
        }

        //if status code is in error range, plus no output, try to check if ERROR_CODE_PAGES is defined.
        //if is defined as a php file, error_503.php include and render the file.
        //if is a route /error/code/503, reroute and render the final output
        if ($output === null && isset($this->conf->ERROR_CODE_PAGES) && $this->conf->ERROR_CODE_PAGES[$statusCode]) {
            $errPage = $this->conf->ERROR_CODE_PAGES[$statusCode];

            if ($errPage{0} == '/') {
                $this->reroute($errPage, true);

                if ($this->async == false) {
                    $result = utf8_decode(ob_get_clean());
                    $this->endBlock($result);
                }
                return;
            } else {
                ob_start();
                include $this->conf->SITE_PATH . $errPage;
                $output = utf8_decode(ob_get_contents());
                ob_end_clean();
            }
        }

        //end response for async mode since end() method is explicitly called from controller once process if done and not needed.
        if ($this->async == true) {
            if ($output == null) {
                $this->request->response()->end();
            } else {
                $this->request->response()->end($output);
            }
        } else {
            if ($output == null) {
                $this->request->response()->end();
            } else {
                $this->request->response()->end($output);
            }
        }

        $this->ended = true;

        if (!empty($this->endCallback)) {
            call_user_func_array($this->endCallback, [$this, false]);
        }
    }

    /**
     * Set a list or a single raw cookie string
     * @param $cookies Array|String
     */
    public function setRawCookie($cookies)
    {
        if (!empty($cookies)) {
            if (is_array($cookies) && sizeof($cookies) > 1) {
                $arr = new \Java('java.util.HashSet');
                foreach ($cookies as $cookie) {
                    $arr->add($cookie);
                }
//                $this->request->response()->putHeader('Set-Cookie', $arr);
                \ResponseHeader::put($this->request->response(), 'Set-Cookie', $arr);
            } else {
                if (is_array($cookies)) {
                    $this->request->response()->putHeader('Set-Cookie', $cookies[0]);
                } else {
                    $this->request->response()->putHeader('Set-Cookie', $cookies);
                }
            }
        }
    }

    /**
     * Set cookies data
     * @param array $cookieArr Associative array with field as key and data as value
     * @param int $expires The time the cookie expires. This is a Unix timestamp so is in number of seconds since the epoch. In other words, you'll most likely set this with the time() function plus the number of seconds before you want it to expire. Or you might use mktime(). time()+60*60*24*30 will set the cookie to expire in 30 days. If set to 0, or omitted, the cookie will expire at the end of the session (when the browser closes).
     * @param string $path The path on the server in which the cookie will be available on. If set to '/', the cookie will be available within the entire domain. If set to '/foo/', the cookie will only be available within the /foo/ directory and all sub-directories such as /foo/bar/ of domain. The default value is the current directory that the cookie is being set in.
     * @param string $domain The domain that the cookie is available to. Setting the domain to 'www.example.com' will make the cookie available in the www subdomain and higher subdomains. Cookies available to a lower domain, such as 'example.com' will be available to higher subdomains, such as 'www.example.com'. Older browsers still implementing the deprecated » RFC 2109 may require a leading . to match all subdomains.
     */
    public function setCookie($cookieArr, $expires = null, $path = '/', $domain = null)
    {
        if ($expires != null) {
            $expires = ' expires=' . date('D, d-M-Y H:i:s', $expires) . ' GMT;';
        }
        if ($domain != null) {
            $domain = ' domain=' . $domain . ';';
        } else {
            if ($this->conf->COOKIE_DOMAIN != null) {
                $domain = ' domain=' . $this->conf->COOKIE_DOMAIN . ';';
            }
        }

        $httpOnly = '';
        $secure = '';
        if ($this->conf->COOKIE_HTTP_ONLY) {
            $httpOnly = ' HttpOnly;';
        }
        if ($this->conf->COOKIE_SECURE) {
            $secure = ' Secure;';
        }

        foreach ($cookieArr as $k => $v) {
            $cookieStr = "$k=$v; path=$path;" . $expires . $domain . $httpOnly . $secure;
            $this->cookiesToSet[] = $cookieStr;
        }

        //doesn't work quercus sometimes convert it to String instead of java HashSet as defined in this scope.
//        $this->request->response()->putHeader('Set-Cookie', $cookies);
//        \ResponseHeader::put($this->request->response(), 'Set-Cookie', $cookies);
    }

    public function setCookiesInHeader()
    {
        $cookies = new \Java('java.util.HashSet');
        foreach ($this->cookiesToSet as $str) {
            $cookies->add($str);
        }
        \ResponseHeader::put($this->request->response(), 'Set-Cookie', $cookies);
    }

    /**
     * Get cookies data sent from browser
     * @return array
     */
    public function getCookie($cookieStr = null)
    {
        if ($cookieStr === null) {
            $cookieStr = $this->request->headers()->get('Cookie');
            if (!isset($cookieStr)) {
                return;
            }
        }

        $cookie = [];
        $parts = explode(";", $cookieStr);
        foreach ($parts as $p) {
            $dt = explode("=", $p);
            $cookie[trim($dt[0])] = $dt[1];
        }

        return $cookie;
    }

    /**
     * Main function to run the web application
     */
    public function run()
    {
        $this->throwHeader($this->routeTo());
        if ($this->async == false) {
            $result = utf8_decode(ob_get_clean());
            $this->endBlock($result);
        }
    }

    /**
     * Run the web application from a http request or a CLI execution.
     */
    public function autorun()
    {
        $opt = getopt('u:');
        if (isset($opt['u']) === true) {
            $this->runFromCli();
        } else {
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
    public function runFromCli()
    {
        $opt = getopt('u:m::');
        if (isset($opt['u']) === true) {
            $uri = $opt['u'];

            if ($uri[0] != '/') {
                $uri = '/' . $uri;
            }

            $this->conf->SUBFOLDER = '/';
            $this->_SERVER['REQUEST_URI'] = $uri;
            $this->_SERVER['REQUEST_METHOD'] = (isset($opt['m'])) ? $opt['m'] : 'GET';
            $this->conf->FROM_CLI = true;
            $this->run();
        }
    }

    public function resolveConstructor($controllerName)
    {
        return $this->container->resolveConstructor($controllerName, true);
    }

    public function getProtectedRootPath()
    {
        if (isset($this->conf->PROTECTED_FOLDER_ORI) === true) {
            return $this->conf->SITE_PATH . $this->conf->PROTECTED_FOLDER_ORI;
        }
        return $this->conf->SITE_PATH . $this->conf->PROTECTED_FOLDER;
    }

    /**
     * Handles the routing process.
     * Auto routing, sub folder, subdomain, sub folder on subdomain are supported.
     * It can be used with or without the <i>index.php</i> in the URI
     * @return mixed HTTP status code such as 404 or URL for redirection
     */
    public function routeTo()
    {
        $router = new DooUriRouter;
        $router->app = $this;
        $router->conf = $this->conf;
        $routeRs = $router->execute($this->route, $this->conf->SUBFOLDER);

        if (isset($routeRs['redirect']) === true) {
            list($redirUrl, $redirCode) = $routeRs['redirect'];
//            DooUriRouter::redirect($redirUrl, true, $redirCode);
            $this->statusCode = $redirCode;
            $this->setHeader('Location', $redirUrl);
            return;
        }

        if ($routeRs[0] !== null && $routeRs[1] !== null) {
            //dispatch, call Controller class

            if ($routeRs[0][0] !== '[') {
                if (strpos($routeRs[0], '\\') !== false) {
                    $nsClassFile = str_replace('\\', '/', $routeRs[0]);
                    $nsClassFile = explode($this->conf->APP_NAMESPACE_ID . '/', $nsClassFile, 2);
                    $nsClassFile = $nsClassFile[1];
                    require_once $this->conf->SITE_PATH . $this->conf->PROTECTED_FOLDER . $nsClassFile . '.php';
                } else {
                    require_once $this->conf->SITE_PATH . $this->conf->PROTECTED_FOLDER . "controller/{$routeRs[0]}.php";
                }
            } else {
                $moduleParts = explode(']', $routeRs[0]);
                $moduleName = substr($moduleParts[0], 1);

                if (isset($this->conf->PROTECTED_FOLDER_ORI) === true) {
                    require_once $this->conf->SITE_PATH . $this->conf->PROTECTED_FOLDER_ORI . 'module/' . $moduleName . '/controller/' . $moduleParts[1] . '.php';
                } else {
                    require_once $this->conf->SITE_PATH . $this->conf->PROTECTED_FOLDER . 'module/' . $moduleName . '/controller/' . $moduleParts[1] . '.php';
                    $this->conf->PROTECTED_FOLDER_ORI = $this->conf->PROTECTED_FOLDER;
                }

                //set class name
                $routeRs[0] = $moduleParts[1];
                $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI . 'module/' . $moduleName . '/';
            }

            if (strpos($routeRs[0], '/') !== false) {
                $clsname = explode('/', $routeRs[0]);
                $routeRs[0] = $clsname[sizeof($clsname) - 1];
            }

            //if defined class name, use the class name to create the Controller object
            $clsnameDefined = (sizeof($routeRs) === 4);
            if ($clsnameDefined) {
                $controllerName = $routeRs[3];
                $resolver = $this->resolveConstructor($controllerName);
                $controller = $resolver[0];
                $classRef = $resolver[1];
            } else {
                $controllerName = $routeRs[0];
                $resolver = $this->resolveConstructor($routeRs[0]);
                $controller = $resolver[0];
                $classRef = $resolver[1];
            }

            $controller->app = &$this;
            $controller->container = &$this->container;
            $controller->_GET = &$this->_GET;
            $controller->_POST = &$this->_POST;
            $controller->_FILES = &$this->_FILES;
            $controller->params = $routeRs[2];

            if ($this->conf->SESSION_ENABLE == true) {
                $controller->session = &$this->session;
            }

            if (isset($controller->params['__extension']) === true) {
                $controller->extension = $controller->params['__extension'];
                unset($controller->params['__extension']);
            }
            if (isset($controller->params['__routematch']) === true) {
                $controller->routematch = $controller->params['__routematch'];
                unset($controller->params['__routematch']);
            }

            if ($this->_SERVER['REQUEST_METHOD'] === 'PUT') {
                $controller->init_put_vars();
            }

            if ($controller->async == false) {
//                Vertx::logger()->info('Blocking mode start');
                $this->async = false;
                ob_start();
            } else {
                $this->async = true;
            }

            //before run, normally used for ACL auth
            if (!$controller->asyncBeforeRun) {
                if ($clsnameDefined) {
                    if ($rs = $controller->beforeRun($routeRs[3], $routeRs[1])) {
                        return $rs;
                    }
                } else {
                    if ($rs = $controller->beforeRun($routeRs[0], $routeRs[1])) {
                        return $rs;
                    }
                }

//                $routeRs = $controller->$routeRs[1]();
                $routeRs = $this->container->invokeMethod($controller, $controllerName, $classRef, $routeRs[1]);
                $controller->afterRun($routeRs);
                return $routeRs;
            } else {
                $func = function ($rs = null) use ($controller, $routeRs, $controllerName, $classRef) {
                    if (!empty($rs)) {
                        $this->throwHeader($rs);
                    } else {
//                        $routeRs = $controller->$routeRs[1]();
                        $routeRs = $this->container->invokeMethod($controller, $controllerName, $classRef, $routeRs[1]);
                        $controller->afterRun($routeRs);
                        $this->throwHeader($routeRs);
                    }
                };
                if ($clsnameDefined) {
                    $controller->beforeRun($routeRs[3], $routeRs[1], $func);
                } else {
                    $controller->beforeRun($routeRs[0], $routeRs[1], $func);
                }
            }
        } //if auto route is on, then auto search Controller->method if route not defined by user
        else {
            if ($this->conf->AUTOROUTE) {

                list($controllerName, $methodName, $methodNameOri, $params, $moduleName) = $router->autoConnect($this->conf->SUBFOLDER,
                    (isset($this->route['autoroute_alias']) === true) ? $this->route['autoroute_alias'] : null);

                if (empty($this->route['autoroute_force_dash']) === false) {
                    if ($methodName !== 'index' && $methodName === $methodNameOri && $methodNameOri[0] !== '_' && ctype_lower($methodNameOri) === false) {
                        $this->throwHeader(404);
                        return;
                    }
                }

                if (in_array($methodName, [
                    'destroyCurrentSession',
                    'startNewSession',
                    'setCookie',
                    'getCookie',
                    'endReq',
                    'replyReq',
                    'getInput',
                    'setHeader',
                    'setRawHeader',
                    'initPutVars',
                    'load',
                    'db',
                    'acl',
                    'beforeRun',
                    'cache',
                    'saveRendered',
                    'saveRenderedC',
                    'view',
                    'render',
                    'renderc',
                    'language',
                    'acceptType',
                    'setContentType',
                    'clientIP',
                    'afterRun',
                    'getKeyParam',
                    'getKeyParams',
                    'viewRenderAutomation',
                    'isAjax',
                    'isSSL',
                    'toXML',
                    'toJSON',
                ])) {
                    $this->throwHeader(404);
                    return;
                }

                if (empty($this->route['autoroute_force_dash']) === false && strpos($moduleName, '-') !== false) {
                    $moduleName = str_replace('-', '_', $moduleName);
                }

                if (isset($moduleName) === true) {
                    $this->conf->PROTECTED_FOLDER_ORI = $this->conf->PROTECTED_FOLDER;
                    $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI . 'module/' . $moduleName . '/';
                }

                $controller_file = $this->conf->SITE_PATH . $this->conf->PROTECTED_FOLDER . "controller/{$controllerName}.php";

                if (file_exists($controller_file)) {
                    require_once $controller_file;

                    $methodsArray = get_class_methods($controllerName);

                    //if controller name matches 2 classes with the same name, namespace and W/O namespace
                    if ($methodsArray !== null) {
                        $unfoundInMethods = (in_array($methodName, $methodsArray) === false &&
                            in_array($methodName . '_' . strtolower($this->_SERVER['REQUEST_METHOD']),
                                $methodsArray) === false);
                        if ($unfoundInMethods) {
                            $methodsArray = null;
                        }
                    }

                    //if the method not in controller class, check for a namespaced class with the same file name.
                    if ($methodsArray === null && isset($this->conf->APP_NAMESPACE_ID) === true) {
                        if (isset($moduleName) === true) {
                            $controllerName = $this->conf->APP_NAMESPACE_ID . '\\module\\' . $moduleName . '\\controller\\' . $controllerName;
                        } else {
                            $controllerName = $this->conf->APP_NAMESPACE_ID . '\\controller\\' . $controllerName;
                        }
                        $methodsArray = get_class_methods($controllerName);
                    }

                    //if method not found in both both controller and namespaced controller, 404 error
                    if ($methodsArray === null) {
                        if (isset($this->conf->PROTECTED_FOLDER_ORI) === true) {
                            $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI;
                        }
                        $this->throwHeader(404);
                        return;
                    }
                } else {
                    if (isset($moduleName) === true && isset($this->conf->APP_NAMESPACE_ID) === true) {
                        if (isset($this->conf->PROTECTED_FOLDER_ORI) === true) {
                            $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI;
                        }

                        $controller_file = $this->conf->SITE_PATH . $this->conf->PROTECTED_FOLDER . '/controller/' . $moduleName . '/' . $controllerName . '.php';

                        if (file_exists($controller_file) === false) {
                            $this->throwHeader(404);
                            return;
                        }
                        $controllerName = $this->conf->APP_NAMESPACE_ID . '\\controller\\' . $moduleName . '\\' . $controllerName;
                        #echo 'module = '.$moduleName.'<br>';
                        #echo $controller_file.'<br>';
                        #echo $controllerName.'<br>';
                        $methodsArray = get_class_methods($controllerName);
                    } else {
                        if (isset($this->conf->PROTECTED_FOLDER_ORI) === true) {
                            $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI;
                        }
                        $this->throwHeader(404);
                        return;
                    }
                }

                //check for REST request as well, utilized method_GET(), method_PUT(), method_POST, method_DELETE()
                $restMethod = $methodName . '_' . strtolower($this->_SERVER['REQUEST_METHOD']);
                $inRestMethod = in_array($restMethod, $methodsArray);

                //check if method() and method_GET() etc. doesn't exist in the controller, 404 error
                if (in_array($methodName, $methodsArray) === false && $inRestMethod === false) {
                    if (isset($this->conf->PROTECTED_FOLDER_ORI) === true) {
                        $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI;
                    }
                    $this->throwHeader(404);
                    return;
                }

                //use method_GET() etc. if available
                if ($inRestMethod === true) {
                    $methodName = $restMethod;
                }

                $resolver = $this->resolveConstructor($controllerName);
                $controller = $resolver[0];
                $classRef = $resolver[1];
                $controller->app = &$this;
                $controller->container = &$this->container;
                $controller->_GET = &$this->_GET;
                $controller->_POST = &$this->_POST;
                $controller->_FILES = &$this->_FILES;

                if ($this->conf->SESSION_ENABLE == true) {
                    $controller->session = &$this->session;
                }

                //if autoroute in this controller is disabled, 404 error
                if ($controller->autoroute === false) {
                    if (isset($this->conf->PROTECTED_FOLDER_ORI) === true) {
                        $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI;
                    }
                    $this->throwHeader(404);
                    return;
                }

                if ($params != null) {
                    $controller->params = $params;
                }

                if ($this->_SERVER['REQUEST_METHOD'] === 'PUT') {
                    $controller->initPutVars();
                }

                if ($controller->async == false) {
//                Vertx::logger()->info('Blocking mode start');
                    $this->async = false;
                    ob_start();
                } else {
                    $this->async = true;
                }

                //before run, normally used for ACL auth
                if (!$controller->asyncBeforeRun) {
                    if ($rs = $controller->beforeRun($controllerName, $methodName)) {
                        return $rs;
                    }
//                $routeRs = $controller->$methodName();
                    $routeRs = $this->container->invokeMethod($controller, $controllerName, $classRef, $methodName);
                    $controller->afterRun($routeRs);

                    return $routeRs;
                } else {
                    $func = function ($rs = null) use ($controller, $routeRs, $controllerName, $classRef, $methodName) {
                        if (!empty($rs)) {
                            $this->throwHeader($rs);
                        } else {
//                        $routeRs = $controller->$methodName();
                            $routeRs = $this->container->invokeMethod($controller, $controllerName, $classRef,
                                $methodName);
                            $controller->afterRun($routeRs);
                            $this->throwHeader($routeRs);
                        }
                    };
                    $controller->beforeRun($controllerName, $methodName, $func);
                }
            } else {
                $this->throwHeader(404);
                return;
            }
        }
    }

    /**
     * Reroute the URI to an internal route
     * @param string $routeuri route uri to redirect to
     * @param bool $is404 send a 404 status in header
     */
    public function reroute($routeuri, $is404 = false)
    {

        if ($this->conf->SUBFOLDER != '/') {
            $this->_SERVER['REQUEST_URI'] = substr($this->conf->SUBFOLDER, 0,
                    strlen($this->conf->SUBFOLDER) - 1) . $routeuri;
        } else {
            $this->_SERVER['REQUEST_URI'] = $routeuri;
        }

        if (isset($this->conf->PROTECTED_FOLDER_ORI) === true) {
            $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI;
            unset($this->conf->PROTECTED_FOLDER_ORI);
        }

        if ($is404 === true) {
            $this->setHeader('HTTP/1.1', '404 Not Found');
        }
        //$this->routeTo();
        $this->throwHeader($this->routeTo());
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
    public function module($moduleUri, $action = null, $params = null)
    {
        if ($moduleUri[0] == '/') {
            if ($this->conf->SUBFOLDER != '/') {
                $this->_SERVER['REQUEST_URI'] = substr($this->conf->SUBFOLDER, 0,
                        strlen($this->conf->SUBFOLDER) - 1) . $moduleUri;
            } else {
                $this->_SERVER['REQUEST_URI'] = $moduleUri;
            }

            $tmp = $this->conf->PROTECTED_FOLDER;
            if (isset($this->conf->PROTECTED_FOLDER_ORI) === true) {
                $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI;
                $tmpOri = $this->conf->PROTECTED_FOLDER_ORI;
            }

            ob_start();
            $this->routeTo();
            $data = utf8_decode(ob_get_contents());
            ob_end_clean();

            $this->conf->PROTECTED_FOLDER = $tmp;

            if (isset($tmpOri) === true) {
                $this->conf->PROTECTED_FOLDER_ORI = $tmpOri;
            }

            return $data;
        } //if Controller name passed in:  Doo::app()->module('admin/SomeController', 'login',  array('nav'=>'home'));
        else {
            if (is_string($moduleUri)) {
                $controllerName = $moduleUri;
                if (strpos($moduleUri, '/') !== false) {
                    $arr = explode('/', $moduleUri);
                    $controllerName = $arr[sizeof($arr) - 1];
                }
                require_once $this->conf->SITE_PATH . $this->conf->PROTECTED_FOLDER . "controller/$moduleUri.php";

                $resolver = $this->resolveConstructor($controllerName);
                $controller = $resolver[0];
                $controller->app = &$this;
                $controller->container = &$this->container;
                $controller->_GET = &$this->_GET;
                $controller->_POST = &$this->_POST;
                $controller->_FILES = &$this->_FILES;
                $controller->params = $params;

                if ($this->conf->SESSION_ENABLE == true) {
                    $controller->session = &$this->session;
                }

                if ($rs = $controller->beforeRun($controllerName, $action)) {
                    $this->throwHeader($rs);
                    return;
                }

                ob_start();
                $rs = $controller->{$action}();

                if ($controller->autorender === true) {
                    $this->conf->AUTO_VIEW_RENDER_PATH = [
                        strtolower(substr($controllerName, 0, -10)),
                        strtolower(preg_replace('/(?<=\\w)(?=[A-Z])/', '-$1', $action)),
                    ];
                }
                $controller->afterRun($rs);

                $this->throwHeader($rs);

                $data = utf8_decode(ob_get_contents());
                ob_end_clean();
                return $data;
            }
            //if array passed in. For controller file name != controller class name.
            //eg. Doo::app()->module(array('admin/Admin', 'AdminController'), 'login',  array('nav'=>'home'));
            else {
                require_once $this->conf->SITE_PATH . $this->conf->PROTECTED_FOLDER . "controller/{$moduleUri[0]}.php";

                $resolver = $this->resolveConstructor($moduleUri[1]);
                $controller = $resolver[0];
                $controller->app = &$this;
                $controller->container = &$this->container;
                $controller->_GET = &$this->_GET;
                $controller->_POST = &$this->_POST;
                $controller->_FILES = &$this->_FILES;
                $controller->params = $params;

                if ($this->conf->SESSION_ENABLE == true) {
                    $controller->session = &$this->session;
                }

                if ($rs = $controller->beforeRun($moduleUri[1], $action)) {
                    $this->throwHeader($rs);
                    return;
                }

                ob_start();
                $rs = $controller->{$action}();

                if ($controller->autorender === true) {
                    $this->conf->AUTO_VIEW_RENDER_PATH = [
                        strtolower(substr($controllerName, 0, -10)),
                        strtolower(preg_replace('/(?<=\\w)(?=[A-Z])/', '-$1', $action)),
                    ];
                }
                $controller->afterRun($rs);

                $this->throwHeader($rs);

                $data = utf8_decode(ob_get_contents());
                ob_end_clean();
                return $data;
            }
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
    public function getModule($moduleName, $moduleUri, $action = null, $params = null)
    {
        if (empty($moduleName) === false) {
            if (isset($this->conf->PROTECTED_FOLDER_ORI) === false) {
                $this->conf->PROTECTED_FOLDER_ORI = $tmp = $this->conf->PROTECTED_FOLDER;
                $this->conf->PROTECTED_FOLDER = $tmp . 'module/' . $moduleName . '/';
                $result = $this->module($moduleUri, $action, $params);
                $this->conf->PROTECTED_FOLDER = $tmp;
            } else {
                $tmp = $this->conf->PROTECTED_FOLDER;
                $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI . 'module/' . $moduleName . '/';
                $result = $this->module($moduleUri, $action, $params);
                $this->conf->PROTECTED_FOLDER = $tmp;
            }
        } else {
            $tmp = $this->conf->PROTECTED_FOLDER;
            $this->conf->PROTECTED_FOLDER = $this->conf->PROTECTED_FOLDER_ORI;
            $result = $this->module($moduleUri, $action, $params);
            $this->conf->PROTECTED_FOLDER = $tmp;
        }
        return $result;
    }

    public function redirect($url)
    {
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
    public function throwHeader($code)
    {
        if ($code != null) {
            if (is_int($code)) {
                if ($code === 404) {
                    //Controller return 404, send 404 header, include file if ERROR_404_DOCUMENT is set by user
                    // $this->setHeader('HTTP/1.1', '404 Not Found');
                    $this->statusCode = 404;

                    if (!empty($this->conf->ERROR_404_DOCUMENT)) {
                        ob_start();
                        include $this->conf->SITE_PATH . $this->conf->ERROR_404_DOCUMENT;
                        $data = utf8_decode(ob_get_contents());
                        ob_end_clean();
                        $this->end($data);
                        return 404;
                    } //execute route to handler 404 display if ERROR_404_ROUTE is defined, the route handler shouldn't send any headers or return 404
                    else {
                        if (!empty($this->conf->ERROR_404_ROUTE)) {
                            $this->reroute($this->conf->ERROR_404_ROUTE, true);
                            return 404;
                        }
                    }
                } //if not 404, just send the header code
                else {
                    $this->statusCode = $code;

                    //if status code is in error range, plus no output, try to check if ERROR_CODE_PAGES is defined.
                    //if is defined as a php file, error_503.php include and render the file.
                    //if is a route /error/code/503, reroute and render the final output
                    if (isset($this->conf->ERROR_CODE_PAGES) && $this->conf->ERROR_CODE_PAGES[$code]) {
                        $errPage = $this->conf->ERROR_CODE_PAGES[$code];
                        $output = null;

                        if ($errPage{0} == '/') {
                            $this->reroute($errPage, true);

                            if ($this->async == false) {
                                $result = utf8_decode(ob_get_clean());
                                $this->endBlock($result);
                            }
                            return;
                        } else {
                            ob_start();
                            include $this->conf->SITE_PATH . $errPage;
                            $output = utf8_decode(ob_get_contents());
                            ob_end_clean();
                        }

                        $this->end($output);
                    }
                }
            } elseif (is_string($code)) {
                //Controller return the redirect location, it sends 302 Found
                // DooUriRouter::redirect($code, false);
                $this->statusCode = 302;
                $this->setHeader('Location', $code);
            } elseif (is_array($code)) {
                //Controller return array('/some/routes/here', 'internal')
                if ($code[1] == 'internal') {
                    return $this->reroute($code[0]);
                } //Controller return array('http://location.to.redirect', 301)
                elseif ($code[1] === 404) {
                    return $this->reroute($code[0], true);
                } // if array('http://location.to.redirect', 302), 302 Found is sent before Location:
                elseif ($code[1] === 302) {
                    $this->statusCode = 302;
                    $this->setHeader('Location', $code[0]);
//                    DooUriRouter::redirect($code[0],false, $code[1], array("HTTP/1.1 302 Found"));
                } //else redirect with the http status defined,eg. 307 Moved Temporarily
                else {
                    if ($code[1] > 299 && $code[1] < 400) {
                        $this->statusCode = $code[1];
                        $this->setHeader('Location', $code[0]);
//                    DooUriRouter::redirect($code[0],false, $code[1]);
                    } else {
                        if (!empty($code[1])) {
                            $this->statusCode = $code[1];
                        }
                        // $this->setHeader(null, true, $code[1]);
                        return $this->reroute($code[0]);
                    }
                }
            }
        }
    }


    /**
     * Set header. eg. setHeader('Content-Type', 'application/json')
     * @param string $name Header name
     * @param string $content Header content
     */
    public function setHeader($name, $content)
    {
        $this->headers[$name] = $content;
    }

    /**
     * Set raw header. eg. 'HTTP/1.1 200 OK'
     * @param string $rawHeader Header content
     * @param bool $replace Whether to replace the same header that is previously set
     * @param int $code HTTP status code
     */
    public function setRawHeader($rawHeader, $replace = true, $code = null)
    {
        // Vertx::logger()->info("Don't use setRawHeader for vertx");
        var_dump("Don't use setRawHeader for vertx");
    }

    /**
     * To debug variables with DooPHP's diagnostic view
     * @param mixed $var The variable to view in diagnostics.
     */
    public function debug($var)
    {
        throw new DooDebugException($var);
    }

}