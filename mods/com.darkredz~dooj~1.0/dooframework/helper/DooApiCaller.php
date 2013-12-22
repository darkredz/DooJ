<?php
/**
 * DooApiCaller trait file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */


/**
 * DooApiCaller provides methods for calling API to an eventbus address
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @package doo.controller
 * @since 2.0
 */
trait DooApiCaller {
    protected $_apiAddress = 'myapp.api';
    protected $_apiKey = '';
    protected $_apiUrlRoot = 'http://localhost/';
    protected $_apiContentType = 'application/x-www-form-urlencoded';
    protected $_apiTimeout = 30000;

    protected function setApiAddress($v){
        $this->_apiAddress = $v;
    }

    protected function setApiKey($v){
        $this->_apiKey = $v;
    }

    protected function setApiUrlRoot($v){
        $this->_apiUrlRoot = $v;
    }

    protected function setApiContentType($v){
        $this->_apiContentType = $v;
    }

    protected function setTimeout($v){
        $this->_apiTimeout = $v;
    }

    protected function apiGet($uri, callable $callback) {
        $this->apiCall($uri, 'GET', null, $callback);
    }

    protected function apiPost($uri, $body, callable $callback) {
        $this->apiCall($uri, 'POST', $body, $callback);
    }

    protected function apiCall($uri, $method, $body, callable $callback){
        if($this->_apiContentType == 'application/x-www-form-urlencoded' && is_array($body)){
            $body = \http_build_query($body);
        }
        else if($this->_apiContentType == 'application/json' && is_array($body)){
            $body = \json_encode($body);
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

        \Vertx::eventBus()->sendWithTimeout($this->_apiAddress, $msg, $this->_apiTimeout, function($reply, $error) use ($callback){
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
                $callback(['statusCode' => 503, 'error' => 'Internal Server Error']);
            }
        });
    }
} 