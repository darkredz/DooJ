<?php
/**
 * DooVertxSession class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */


/**
 * DooVertxSessionId generates session ID that is useful for session clustering
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @package doo.session
 * @since 2.0
 */
class DooVertxSessionId {

    /**
     * @var DooWebApp
     */
    public $app;

    public function generateId(){
        $clientIp = $this->app->_SERVER['REMOTE_ADDR'];
        $serverID = $this->app->conf->SERVER_ID;
        $sid = php_uname('n') .'~'. $this->uuid($serverID, $clientIp);            //java.util.UUID.randomUUID().toString()
        $enc = mcrypt_encrypt("rijndael-128", $this->app->conf->SESSION_SECRET, $sid,'ECB');
        $enc = bin2hex($enc);
        return $enc;
    }

    public function decId($sid){
        $retval = mcrypt_decrypt("rijndael-128",
            $this->app->conf->SESSION_SECRET,
            hex2bin($sid) ,
            "ECB");
        return $retval;
    }

    public function uuid($serverID=1, $clientIp="")
    {
        $t=explode(" ",microtime());
        return sprintf( '%04x-%08s-%08s-%04s-%04x%04x',
            $serverID,
            $clientIp,
            substr("00000000" . dechex($t[1]),-8),   // get 8HEX of unixtime
            substr("0000" . dechex(round($t[0]*65536)),-4), // get 4HEX of microtime
            mt_rand(0,0xffff), mt_rand(0,0xffff));
    }

    public function uuidDecode($uuid) {
        $rez=Array();
        $u=explode("-",$uuid);
        if(is_array($u)&&count($u)==5) {
            $rez=Array(
                'serverID'  => hexdec($u[0]),
                'ip'        => $u[1],
                'unixtime'  => hexdec($u[2]),
                'micro'     => (hexdec($u[3])/65536)
            );
        }
        return $rez;
    }

}