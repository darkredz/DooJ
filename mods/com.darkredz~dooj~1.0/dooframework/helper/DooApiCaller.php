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
    public $apiAddress = 'myapp.api';
    public $apiKey = '';
    public $apiUrlRoot = 'http://localhost/';
    public $apiContentType = 'application/x-www-form-urlencoded';
    public $apiTimeout = 30000;

    function apiGet($uri, callable $callback) {
        $this->apiCall($uri, 'GET', null, $callback);
    }

    function apiPost($uri, $body, callable $callback) {
        $this->apiCall($uri, 'POST', $body, $callback);
    }

    function apiCall($uri, $method, $body, callable $callback){

        if($this->apiContentType == 'application/x-www-form-urlencoded' && is_array($body)){
            $body = \http_build_query($body);
        }
        else if($this->apiContentType == 'application/json' && is_array($body)){
            $body = \json_encode($body);
        }

        $headers = ['Authority' => $this->apiKey, "Content-Type" => $this->apiContentType];
        $headers = json_encode($headers);

        $msg = [
            'headers'        => $headers,
            'absoluteUri'    => $this->apiUrlRoot . $uri,
            'uri'            => $uri,
            'method'         => $method,
            'remoteAddress'  => $this->app->conf->SERVER_ID,
        ];

        if($body!=null){
            $msg['body'] = $body;
        }

        \Vertx::eventBus()->sendWithTimeout($this->apiAddress, $msg, $this->apiTimeout, function($reply, $error) use ($callback){
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