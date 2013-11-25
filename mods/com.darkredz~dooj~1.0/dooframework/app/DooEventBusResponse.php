<?php
/**
 * DooEventBusResponse class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */


/**
 * DooEventBusResponse mimics HTTP response API to allow easy conversion from a HTTP based app to eventbus.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @package doo.app
 * @since 2.0
 */
class DooEventBusResponse {

    public $statusCode = 200;
    public $statusMessage = 'OK';
    public $replyHeaders = [];
    public $replyOutput = '';
    public $ebMessage;
    public $sendOnlyBody = false;
    public $debug = false;

    public function putHeader($key, $value) {
        $this->replyHeaders[$key] = $value;
    }

    public function write($output) {
        $this->replyOutput .= $output;
    }

    public function end($output='') {
//        Vertx::logger()->info('ending request');

        $this->replyOutput .= $output;

        if($this->sendOnlyBody){
            $this->ebMessage->reply($this->replyOutput);
        }
        else{
            $msg = [
                'headers'        => $this->replyHeaders,
                'statusCode'     => $this->statusCode,
                'statusMessage'  => $this->statusMessage,
                'body'           => $this->replyOutput
            ];

            if($this->debug){
                Vertx::logger()->info('Send: ' . var_export($msg, true));
            }
            $this->ebMessage->reply($msg);
        }
    }
}