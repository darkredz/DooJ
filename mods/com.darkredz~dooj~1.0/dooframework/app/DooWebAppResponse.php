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
class DooWebAppResponse
{

    public $statusCode = 200;
    public $statusMessage = 'OK';
    public $replyHeaders = [];
    public $replyOutput = '';
    public $debug = false;


    /*
     * setStatusCode
     * setStatusMessage
     * setChunked
     * isChunked() true/false
     * write()
     * getStatusCode()
     * putHeader()
     */

    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;
    }

    public function setStatusMessage($msg)
    {
        $this->statusMessage = $msg;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function isChunked()
    {
        return false;
    }

    public function setChunked($set)
    {
    }

    public function putHeader($key, $value)
    {
        $this->replyHeaders[$key] = $value;
    }

    public function write($output)
    {
        $this->replyOutput .= $output;
    }


    public function ended() {
        return false;
    }

    public function end($output = '')
    {
        $this->replyOutput .= $output;

        http_response_code($this->statusCode);

        foreach ($this->replyHeaders as $header => $value) {
            header("$header: $value");
        }

        echo $this->replyOutput;

//        if ($this->sendOnlyBody) {
//            $this->ebMessage->reply($this->replyOutput);
//        } else {
//            $msg = [
//                'headers' => $this->replyHeaders,
//                'statusCode' => $this->statusCode,
//                'statusMessage' => $this->statusMessage,
//                'body' => $this->replyOutput,
//            ];
//
//            if ($this->debug) {
//                $logger = \LoggerFactory::getLogger(__CLASS__);
//                $logger->info('Send: ' . var_export($msg, true));
//            }
//            $this->ebMessage->reply(\jto($msg));
//        }
    }
}