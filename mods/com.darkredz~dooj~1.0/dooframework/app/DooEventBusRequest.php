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
class DooEventBusRequest {

    public $absoluteUri;
    public $uri;
    public $method;
    public $headers;
    public $body;
    public $remoteAddress;

    /**
     * @var DooEventBusResponse
     */
    public $response;

    public function params(){
        $q = explode('?', $this->uri, 2);
        if(sizeof($q) < 2) return [];
        $q = $q[1];
        parse_str($q, $queryVars);
//        Vertx::logger()->info( var_export($queryVars, true) );
        return $queryVars;
    }
}