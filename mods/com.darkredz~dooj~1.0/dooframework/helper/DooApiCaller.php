<?php
/**
 * DooApiCaller class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */


/**
 * DooApiCaller provides methods for calling API through an eventbus address
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @package doo.controller
 * @since 2.0
 */
class DooApiCaller
{
    protected $apiAddress = 'myapp.api';
    protected $apiKey = '';
    protected $apiUrlRoot = 'http://localhost/';
    protected $apiContentType = 'application/x-www-form-urlencoded';
    protected $apiTimeout = 30000;
    protected $proxy;
    protected $debug = null;

    /**
     * @var DooWebApp|DooOrientDbModel
     */
    public $app;

    public function setApiProxy($v)
    {
        $this->proxy = $v;
    }

    public function setDebug($debug)
    {
        $this->debug = $debug;
    }

    public function isDebugEnabled()
    {
        if ($this->debug === null) {
            return $this->app->conf->DEBUG_ENABLED;
        } else {
            return $this->debug;
        }
    }

    /**
     * Set Event bus address for the API call. An array can be passed in to proxy the request
     * @param string|array $proxy A string value for the api address or an array for proxy request, same as proxy array used in DooWebApp.
     * Example:
     * <code>
     * $this->setApiAddress([
     *      '^/api/user' => 'myapp.api.user',
     *      '^/api/contact' => 'myapp.api.contact',
     *      '_others' => 'myapp.api',
     * ]);
     * </code>
     */
    public function address($proxy)
    {
        $this->apiAddress = $proxy;
        return $this;
    }

    public function apiKey($v)
    {
        $this->apiKey = $v;
        return $this;
    }

    public function urlRoot($v)
    {
        $this->apiUrlRoot = $v;
        return $this;
    }

    public function contentType($v)
    {
        $this->apiContentType = $v;
        return $this;
    }

    public function timeout($v)
    {
        $this->apiTimeout = $v;
        return $this;
    }

    public function getAddress()
    {
        return $this->apiAddress;
    }

    public function getApiKey()
    {
        return $this->apiKey;
    }

    public function getUrlRoot()
    {
        return $this->apiUrlRoot;
    }

    public function getContentType()
    {
        return $this->apiContentType;
    }

    public function getTimeout()
    {
        return $this->apiTimeout;
    }

    public function get($uri, callable $callback)
    {
        $this->call($uri, 'GET', null, $callback);
    }

    public function post($uri, $body, callable $callback)
    {
        $this->call($uri, 'POST', $body, $callback);
    }

    public function put($uri, $body, callable $callback)
    {
        $this->call($uri, 'PUT', $body, $callback);
    }

    public function delete($uri, $body, callable $callback)
    {
        $this->call($uri, 'DELETE', $body, $callback);
    }

    public function call($uri, $method, $body, callable $callback)
    {
        if ($this->apiContentType == 'application/x-www-form-urlencoded' && is_array($body)) {
            $body = \http_build_query($body);
        } else {
            if ($this->apiContentType == 'application/json' && is_array($body)) {
                $body = \JSON::encode($body);
            }
        }

        //proxy api address based on uri
        $ebAddress = null;

        if (isset($this->proxy)) {
            if (is_string($this->proxy)) {
                $ebAddress = $this->proxy;
            } else {
                if (is_array($this->proxy)) {
                    if (strpos($uri, '?') !== false) {
                        $uri = explode('?', $uri, 2)[0];
                    }

                    foreach ($this->proxy as $regex => $address) {
                        if ($regex == '_others') {
                            continue;
                        }

                        if (preg_match('/' . $regex . '/', $uri)) {
                            if ($this->isDebugEnabled()) {
                                $this->logInfo("Proxy $regex to $address");
                            }

                            $ebAddress = $address;
                            break;
                        }
                    }

                    if ($ebAddress == null && $this->proxy['_others']) {
                        $ebAddress = $this->proxy['_others'];
                    }
                }
            }
        } else {
            $ebAddress = $this->apiAddress;
        }

        $headers = ['Authority' => $this->apiKey, "Content-Type" => $this->apiContentType];
        $headers = \JSON::encode($headers);

        $msg = [
            'headers' => $headers,
            'absoluteUri' => $this->apiUrlRoot . $uri,
            'uri' => $uri,
            'method' => $method,
            'remoteAddress' => $this->app->conf->SERVER_ID,
        ];

        if ($body != null) {
            $msg['body'] = $body;
        }

//        $this->app->trace($uri);

//        $this->app->eventBus()->sendWithTimeout($ebAddress, $msg, $this->_apiTimeout, function($reply, $error) use ($callback, $ebAddress){
        $delivery = new \DeliveryOptions();
        $delivery->setSendTimeout($this->apiTimeout);

        $this->app->eventBus()->send($ebAddress, \jto($msg), $delivery,
            g(function ($reply) use ($callback, $ebAddress) {
                if ($reply->succeeded()) {
                    $res = $reply->result()->body();
//                $this->app->logInfo($res);
                    $res = \jfrom($res);

                    if ($this->isDebugEnabled()) {
                        $this->app->logDebug('API result received:');
                        $this->app->trace($res);
                    }

                    if ($res['statusCode'] > 299) {
                        $err = \JSON::decode($res['body'], true);
                        $callback(['statusCode' => $res['statusCode'], 'error' => $err]);
                    } else {
                        $body = \JSON::decode($res['body'], true);
                        $callback($body);
                    }
                } else {
                    $this->app->logDebug('API server error');
                    $this->app->trace(\JSON::encode($reply->cause()->getMessage()));
                    $callback(['statusCode' => 503, 'error' => 'Service Unavailable']);
                }
            }));
    }

    public static function decUtf8(&$rs)
    {
        foreach ($rs as &$r1) {
            if (is_string($r1)) {
                $r1 = utf8_decode($r1);
            } else {
                if (is_array($r1)) {
                    self::decUtf8($r1);
                }
            }
        }
    }

    public static function encUtf8(&$rs)
    {
        foreach ($rs as &$r1) {
            if (is_string($r1)) {
                $r1 = utf8_encode($r1);
            } else {
                if (is_array($r1)) {
                    self::encUtf8($r1);
                }
            }
        }
    }
} 