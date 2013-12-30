<?php
/**
 * DooVertxSessionManager class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */


/**
 * DooVertxSessionManager manages session in Vertx shared memory. Sessions can be clusters throughout nodes connected. Failover is available if Redis is enabled
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @package doo.session
 * @since 2.0
 */
class DooVertxSessionManager {

    /**
     * @var DooWebApp
     */
    public $app;
    public $timeout;
    public $domain;
    public $path = '/';

    public $namespace;
    public $address;

    public $eventBusTimeout = 2500;

    public function getAddress($sid=null){
        if($this->address){
            return $this->address;
        }
        if($sid==null)
            return $this->getNamespace() . '.server' . $this->app->conf->SERVER_ID;

        $obj = $this->getSessionDetail($sid);
        return $this->getNamespace() . '.server' . $obj['serverID'];
    }

    public function getNamespace(){
        if($this->namespace){
            return $this->namespace;
        }
        return str_replace('\\','.',$this->app->conf->APP_NAMESPACE_ID) . '.session';
    }

    public function hasSessionCookie(){
        $cookie = $this->app->getCookie();
        return (!empty($cookie) && !empty($cookie['DVSESSID']));
    }

    public function encryptId($sid, $ts, $key=null){
        if($key==null){
            $key = $this->app->conf->SESSION_SECRET;
        }
        $enc = mcrypt_encrypt("rijndael-128", $key, $sid .'*'. $ts,'ECB');
        $enc = bin2hex($enc);
        return $enc;
    }

    public function decryptId($sid, $expireDur=30, $key=null){
        if($key==null){
            $key = $this->app->conf->SESSION_SECRET;
        }

        $retval = mcrypt_decrypt("rijndael-128",
            $key,
            hex2bin($sid) ,
            "ECB");
        if($retval == null || strpos($retval,'*') === false){
            return null;
        }
        $retval = explode('*', $retval);
        if(time() - intval($retval[1]) > $expireDur){
            return;
        }

        return $retval[0];
    }

    public function setSessionCookieWithId($sid){
        $timeout = null;
        if($this->timeout > 0){
            $timeout = time() + $this->timeout;
        }
        $this->app->setCookie(['DVSESSID' => $sid], $timeout, $this->path, $this->domain );
    }

    public function startSessionWithId($sid){
        $timeout = null;
        if($this->timeout > 0){
            $timeout = time() + $this->timeout;
        }
        $this->app->setCookie(['DVSESSID' => $sid], $timeout, $this->path, $this->domain );
        $session = new DooVertxSession();
        $session->id = $sid;
        $session->lastAccess = time();
        return $session;
    }

    public function startSession(){
        $sessionIdGen = new DooVertxSessionId();
        $sessionIdGen->app = &$this->app;
        $sid = $sessionIdGen->generateId();
        $timeout = null;
        if($this->timeout > 0){
            $timeout = time() + $this->timeout;
        }
        $this->app->setCookie(['DVSESSID' => $sid], $timeout, $this->path, $this->domain );
        $session = new DooVertxSession();
        $session->id = $sid;
        $session->lastAccess = time();
        return $session;
    }

    /**
     * Get details on the session
     * @param DooVertxSession|string $session Session object or session ID
     * @return array
     */
    public function getSessionDetail($session){
        $sessionIdGen = new DooVertxSessionId();
        $sessionIdGen->app = &$this->app;
        if(is_string($session)){
            $details = $sessionIdGen->decId($session);
        }else{
            $details = $sessionIdGen->decId($session->id);
        }

        if($details!=null){
            $details = explode('~', $details);
            $host = $details[0];
            $info = explode('-', $details[1]);
            $info = $sessionIdGen->uuidDecode($details[1]);
            $info['host'] = $host;
            return $info;
        }
    }

    public function getSession(callable $callback){
        $cookie = $this->app->getCookie();
        if(empty($cookie) && $cookie['DVSESSID']==null){
            if(is_callable($callback)) {
                $callback(null);
            }
            return;
        }

        $sid = $cookie['DVSESSID'];
        $msg = ['id' => $sid, 'act' => DooVertxSessionServer::GET];

        //read from local shared data map if not using session cluster mode
        $address = $this->getAddress($sid);

        if($address == $this->getAddress()){
            Vertx::eventBus()->send($address, $msg, function($reply) use ($callback) {
                $serial = $reply->body;

                if(is_null($serial)){
                    $callback(null);
                }
                else{
                    $obj = unserialize($serial);
                    $callback($obj);
                }
            });
        }
        else{
            //session is on another node, get from that node
            //event bus to get session from the node by SERVER_ID
            Vertx::eventBus()->sendWithTimeout($address, $msg, $this->eventBusTimeout, function($reply, $error) use ($callback, $msg) {
                if (!$error) {
                    $serial = $reply->body();
                    $obj = unserialize($serial);
                    $callback($obj);
                }
                //Timeout, FAILED get from node. Get from redis to this server and update local shared map to complete the fail over
                else if(!empty($this->app->conf->SESSION_REDIS['address'])){
                    $msg['act'] = DooVertxSessionServer::GET_FAILOVER;

                    $newSession = $this->startSession();
                    $msg['newId'] = $newSession->id;

                    Vertx::eventBus()->send($this->getAddress(), $msg, function($reply) use ($callback) {
                        //once got from redis, swap ID to this server
                        $serial = $reply->body();
                        $obj = unserialize($serial);
                        $callback($obj);
                    });
                }
                else{
                    $callback(null);
                }
            });
        }
    }

    public function saveSessionData(DooVertxSession $session, callable $callback=null){
        if(!$session || empty($session->id)) return;

        $session->lastAccess = time();
        $ser = serialize($session);
        $msg = ['data' => $ser, 'id' => $session->id, 'act' => DooVertxSessionServer::SAVE];

        $address = $this->getAddress($session->id);

        if($address == $this->getAddress()){
            Vertx::eventBus()->send($address, $msg, function($reply) use ($callback) {
                if(is_callable($callback))
                    $callback($reply->body());
            });
        }
        else{
            //session is on another node, save to it
            Vertx::eventBus()->sendWithTimeout($address, $msg, $this->eventBusTimeout, function($reply, $error) use ($callback, $msg) {
                if (!$error) {
                    if(is_callable($callback))
                        $callback($reply->body());
                }
                //Timeout, FAILED saving to node. Save to redis through this server and update to shared map to complete the fail over
                else if(!empty($this->app->conf->SESSION_REDIS['address'])){
                    $msg['act'] = DooVertxSessionServer::SAVE_FAILOVER;

                    Vertx::eventBus()->send($this->getAddress(), $msg, function($reply) use ($callback) {
                        if(is_callable($callback))
                            $callback($reply->body());
                    });
                }
                else{
                    if(is_callable($callback))
                        $callback(false);
                }
            });
        }
    }


    public function destroySession(DooVertxSession $session){
        if(empty($session) || empty($session->id)) return;

        //purge the session cookie
        $this->app->setCookie(['DVSESSID' => '-1'], -3600, $this->path, $this->domain );

        $msg = ['id' => $session->id, 'act' => DooVertxSessionServer::DESTROY];
        $address = $this->getAddress($session->id);

        if($address == $this->getAddress()){
            Vertx::eventBus()->send($address, $msg);
        }
        else{
            //session is on another node, destroy that session on the node
            Vertx::eventBus()->sendWithTimeout($address, $msg, $this->eventBusTimeout, function($reply, $error) use ($msg, $address) {
                //failed to destroy the session on that node, fail over to current node to destroy the data on redis.
                if($error){
                    $msg['act'] = DooVertxSessionServer::DESTROY_FAILOVER;
                    Vertx::eventBus()->send($this->getAddress(), $msg);
                }
            });
        }
    }


}