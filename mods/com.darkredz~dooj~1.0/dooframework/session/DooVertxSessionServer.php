<?php
/**
 * DooVertxSessionServer class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */


/**
 * DooVertxSessionServer acts as a session server that stores, retrieve and delete session data. Use @see DooVertxSessionServerVerticle to deploy the session server.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @package doo.session
 * @since 2.0
 */
class DooVertxSessionServer {

    const GET        = 1;
    const SAVE       = 2;
    const DESTROY    = 3;
    const DESTROY_ALL= 4;
    const GET_FAILOVER = 5;
    const SAVE_FAILOVER = 6;
    const DESTROY_FAILOVER = 7;

    public $serverId;
    public $appNamespaceId;
    public $timeout;

    public $namespace;
    public $address;
    public $redis;
    public $logger;

    public $eventBusTimeout = 3000;

    public function log($msg){
        if($this->logger){
            $this->logger->info($msg);
        }
    }

    public function getAddress(){
        if($this->address){
            return $this->address;
        }
        return $this->getNamespace() . '.server' . $this->serverId;
    }

    public function getNamespace(){
        if($this->namespace){
            return $this->namespace;
        }
        return str_replace('\\','.',$this->appNamespaceId) . '.session';
    }

    public function getData($sid, callable $callback){
        //read from local shared data map if not using session cluster mode
        $gcmap = Vertx::sharedData()->getMap( $this->getNamespace() . '.gc' )->map;
        $map = Vertx::sharedData()->getMap( $this->getNamespace() )->map;

        if($map==null || $gcmap==null || $sid==null){
            $callback(null);
            return;
        }

        $serializeObj = $map->get($sid);

        if($serializeObj==null){
            //if it's not on local server, try get from redis
            if(isset($this->redis) && isset($this->redis['address'])) {
                Vertx::eventBus()->sendWithTimeout(
                    $this->redis['address'],
                    ["command" => "get", "args" => [$sid]],
                    $this->eventBusTimeout,

                    function($msg, $error) use ($sid, $gcmap, $map, $callback) {
                        $rs = $msg->body();

                        if($error || $rs['status']!='ok'){
                            $callback(null);
                        }else{
                            $serializeObj = $rs['value'];
                            if($serializeObj==null){
                                $callback(null);
                                return;
                            }

                            //update local server copy
                            $map->put($sid, $serializeObj);
                            $this->resetTimer($sid, $gcmap, $map);
                            $this->updateRedisTtl($sid);
                            $callback($serializeObj);
                        }
                    }
                );
            }
            else{
                $callback(null);
            }
        }
        else{
            //update timestamp when it's being accessed
            $this->resetTimer($sid, $gcmap, $map);

            //have to update TTL on Redis
            if(isset($this->redis) && isset($this->redis['address'])) {
                $this->updateRedisTtl($sid);
            }

            $callback($serializeObj);
        }
    }

    public function getDataFailOver($sid, $newSid, callable $callback){
        $gcmap = Vertx::sharedData()->getMap( $this->getNamespace() . '.gc' )->map;
        $map = Vertx::sharedData()->getMap( $this->getNamespace() )->map;

        if($gcmap->containsKey($sid)){
            Vertx::cancelTimer($gcmap->get($sid));
            $gcmap->remove($sid);
        }

        //save to local server after read from redis
        if(isset($this->redis) && isset($this->redis['address'])) {

            Vertx::eventBus()->sendWithTimeout(
                                    $this->redis['address'],
                                    ["command" => "get", "args" => [$sid]],
                                    $this->eventBusTimeout,

            function($msg, $error) use ($sid, $newSid, $gcmap, $map, $callback) {
                $rs = $msg->body();

                if($error || $rs['status']!='ok'){
                    $callback(null);
                }
                else{
                    $serializeObj = $rs['value'];

                    if($serializeObj==null){
                        $callback(null);
                        return;
                    }

                    //replace with new ID
                    $serializeObj = str_replace($sid, $newSid, $serializeObj);
                    $map->put($newSid, $serializeObj);
                    $map->remove($sid);
                    $this->resetTimer($newSid, $gcmap, $map);

                    //Update redis with new SID and remove the old one
                    Vertx::eventBus()->sendWithTimeout($this->redis['address'], [ "command" => "setex", "args" => [$newSid, $this->timeout/1000, $serializeObj]], $this->eventBusTimeout, function($msg, $error) use ($sid){
                        if($error)
                            $this->log('Redis: Failed to insert new SID ' .$sid);
                    });
                    Vertx::eventBus()->sendWithTimeout($this->redis['address'], [ "command" => "del", "args" => [$sid]], $this->eventBusTimeout, function($msg, $error) use ($sid){
                        if($error)
                            $this->log('Redis: Failed to remove old SID ' .$sid);
                    });

                    $callback($serializeObj);
                }
            });
        }
        else{
            $callback(null);
        }
    }

    public function updateRedisTtl($sid) {
        Vertx::eventBus()->sendWithTimeout($this->redis['address'], ["command" => "expire", "args" => [$sid, $this->timeout/1000]], $this->eventBusTimeout, function($msg, $error){
            if($error)
                $this->log('Redis: Failed to update session expiry');
        });
    }

    public function saveData($sid, $serializeObj){
        if(!$serializeObj || empty($sid)) return false;

        //save to local shared data map if not using session cluster mode
        $gcmap = Vertx::sharedData()->getMap( $this->getNamespace() . '.gc' )->map;
        $map = Vertx::sharedData()->getMap( $this->getNamespace() )->map;
        $map->put($sid, $serializeObj);

        $this->resetTimer($sid, $gcmap, $map);

        //have to update TTL and serializeObj on Redis
        $this->saveDataFailOver($sid, $serializeObj);

        return true;
    }

    public function saveDataFailOver($sid, $serializeObj, callable $callback = null){
        if(!$serializeObj || empty($sid)) return false;

        if($callback!=null){
            //save to local server first
            $this->saveData($sid, $serializeObj);
        }

        if(isset($this->redis) && isset($this->redis['address'])) {
            Vertx::eventBus()->sendWithTimeout($this->redis['address'], [ "command" => "setex", "args" => [$sid, $this->timeout/1000, $serializeObj]], $this->eventBusTimeout, function($msg, $error) use ($callback, $sid){
                $rs = $msg->body();

                if($callback!=null){
                    if($error || $rs['status']!='ok'){
                        $callback(false);
                    }else{
                        $callback(true);
                    }
                }
            });
        }
    }

    public function resetTimer($sid, &$gcmap, &$map){
        if($gcmap->containsKey($sid)){
            Vertx::cancelTimer($gcmap->get($sid));
        }

        $timerID = Vertx::setTimer($this->timeout, function($tid) use ($sid, $gcmap, $map) {
            $map->remove($sid);
            $gcmap->remove($sid);
        });

        $gcmap->put($sid, $timerID);

        return $timerID;
    }

    public function destroyData($sid){
        if(empty($sid)) return false;
        //remove session from store
        $gcmap = Vertx::sharedData()->getMap( $this->getNamespace() . '.gc' )->map;
        $map = Vertx::sharedData()->getMap( $this->getNamespace() )->map;
        $map->remove($sid);

        if($gcmap->containsKey($sid)){
            Vertx::cancelTimer($gcmap->get($sid));
        }

        $gcmap->remove($sid);

        Vertx::eventBus()->sendWithTimeout($this->redis['address'], [ "command" => "del", "args" => [$sid]], $this->eventBusTimeout, function($msg, $error) use ($sid){
            if($error)
                $this->log('Redis: Failed to remove old SID ' .$sid);
        });
        return true;
    }

    public function destroyDataFailOver($sid){
        $this->destroyData($sid);
    }


    public function destroyAll(){
        //remove session from store
        $gcmap = Vertx::sharedData()->getMap( $this->getNamespace() . '.gc' )->map;
        $map = Vertx::sharedData()->getMap( $this->getNamespace() )->map;
        $map->clear();

        $size = $gcmap->size();
        if($size > 0){
            foreach($gcmap as $sid=>$timerId){
                Vertx::cancelTimer($timerId);
            }
        }

        $gcmap->clear();

        return $size;
    }
}