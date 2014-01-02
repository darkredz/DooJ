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
class DooApiCaller {
    protected $_apiAddress = 'myapp.api';
    protected $_apiKey = '';
    protected $_apiUrlRoot = 'http://localhost/';
    protected $_apiContentType = 'application/x-www-form-urlencoded';
    protected $_apiTimeout = 30000;
    protected $_proxy;
    public $app;

    public function setApiProxy($v){
        $this->_proxy = $v;
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
    public function address($proxy){
        $this->_apiAddress = $proxy;
        return $this;
    }

    public function apiKey($v){
        $this->_apiKey = $v;
        return $this;
    }

    public function urlRoot($v){
        $this->_apiUrlRoot = $v;
        return $this;
    }

    public function contentType($v){
        $this->_apiContentType = $v;
        return $this;
    }

    public function timeout($v){
        $this->_apiTimeout = $v;
        return $this;
    }

    public function getAddress(){
        return $this->_apiAddress;
    }

    public function getApiKey(){
        return $this->_apiKey;
    }

    public function getUrlRoot(){
        return $this->_apiUrlRoot;
    }

    public function getContentType(){
        return $this->_apiContentType;
    }

    public function getTimeout(){
        return $this->_apiTimeout;
    }

    public function get($uri, callable $callback) {
        $this->call($uri, 'GET', null, $callback);
    }

    public function post($uri, $body, callable $callback) {
        $this->call($uri, 'POST', $body, $callback);
    }

    public function put($uri, $body, callable $callback) {
        $this->call($uri, 'PUT', $body, $callback);
    }

    public function delete($uri, $body, callable $callback) {
        $this->call($uri, 'DELETE', $body, $callback);
    }

    public function call($uri, $method, $body, callable $callback){
        if($this->_apiContentType == 'application/x-www-form-urlencoded' && is_array($body)){
            $body = \http_build_query($body);
        }
        else if($this->_apiContentType == 'application/json' && is_array($body)){
            $body = \json_encode($body);
        }

        //proxy api address based on uri
        $ebAddress = null;

        if(isset($this->_proxy)){
            if(is_string($this->_proxy)){
                $ebAddress = $this->_proxy;
            }
            else if(is_array($this->_proxy)){
                if(strpos($uri,'?')!==false){
                    $uri = explode('?', $uri,2)[0];
                }

                foreach($this->_proxy as $regex => $address){
                    if($regex=='_others') continue;

                    if(preg_match('/'. $regex .'/', $uri)){
                        if($this->app->conf->DEBUG_ENABLED){
                            $this->logInfo("Proxy $regex to $address");
                        }

                        $ebAddress = $address;
                        break;
                    }
                }

                if($ebAddress==null && $this->_proxy['_others']){
                    $ebAddress = $this->_proxy['_others'];
                }
            }
        }
        else{
            $ebAddress = $this->_apiAddress;
        }

        $headers = ['Authority' => $this->_apiKey, "Content-Type" => $this->_apiContentType];
        $headers = json_encode($headers);

        $msg = [
            'headers'        => $headers,
            'absoluteUri'    => $this->_apiUrlRoot . $uri,
            'uri'            => $uri,
            'method'         => $method,
            'remoteAddress'  => $this->app->conf->SERVER_ID,
        ];

        if($body!=null){
            $msg['body'] = $body;
        }

//        \Vertx::eventBus()->sendWithTimeout($ebAddress, $msg, $this->_apiTimeout, function($reply, $error) use ($callback){
        $this->app->eventBus()->sendWithTimeout($ebAddress, $msg, $this->_apiTimeout, function($reply, $error) use ($callback){
            if (!$error) {
                $res = $reply->body();

                if($this->app->conf->DEBUG_ENABLED){
                    $this->app->logDebug('API result received:');
                    $this->app->trace(json_encode($res));
                }

                if($res['statusCode'] > 299){
                    $callback(['statusCode' => $res['statusCode'], 'error'=> json_decode($res['body'],true)]);
                }
                else{
                    $callback(json_decode($res['body'], true));
                }
            }
            else{
                $this->app->logDebug('API server error');
                $callback(['statusCode' => 503, 'error' => 'Service Unavailable']);
            }
        });
    }
} 