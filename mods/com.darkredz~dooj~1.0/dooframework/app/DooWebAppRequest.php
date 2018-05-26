<?php
/**
 * DooEventBusRequest class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */


/**
 * DooEventBusRequest mimics HTTP requst API to allow easy conversion from a HTTP based app to eventbus.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @package doo.app
 * @since 2.0
 */
class DooWebAppRequest
{

    public $absoluteUri;
    public $uri;
    public $method;
    /**
     * @var DooEventRequestHeader
     */
    public $headerObj;
    public $body;
    public $remoteAddress;

    /**
     * @var DooEventBusResponse
     */
    public $response;

    function __construct()
    {
        $this->remoteAddress = $_SERVER['REMOTE_ADDR'];
        $this->headerObj = new DooEventRequestHeader($_SERVER);
        $this->uri = $_SERVER['REQUEST_URI'];
        $this->response = new DooWebAppResponse();
    }

    public function response()
    {
        return $this->response;
    }

    public function params()
    {
        $q = explode('?', $this->uri, 2);
        if (sizeof($q) < 2) {
            return [];
        }
        $q = $q[1];
        parse_str($q, $queryVars);
//        Vertx::logger()->info( var_export($queryVars, true) );
        return $queryVars;
    }

    public function createHeader($arr)
    {
        $this->headerObj = new DooEventRequestHeader($arr);
    }

    public function headers()
    {
        return $this->headerObj;
    }

    public function absoluteURI()
    {
        return (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
    }


    public function host()
    {
        return $_SERVER['HTTP_HOST'];
    }

    public function path()
    {
        return $_SERVER['REQUEST_URI'];
    }

    public function uri()
    {
        return $_SERVER['REQUEST_URI'];
    }

    public function method()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function remoteAddress()
    {
        return new class {
            public function host() {
                return $_SERVER['REMOTE_ADDR'];
            }
        };
    }

    public function version()
    {
        return $_SERVER['HTTP_CONNECTION'];
    }

    public function setExpectMultipart($expect)
    {
    }

    public function uploadHandler($callback)
    {
        $callback();
    }

    public function handler($callback)
    {
        $callback();
    }

    public function endHandler($callback)
    {
        $callback();
    }

}