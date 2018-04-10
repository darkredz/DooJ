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
class DooVertxSessionServer
{

    const GET = 1;
    const SAVE = 2;
    const DESTROY = 3;
    const DESTROY_ALL = 4;
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

    public function log($msg)
    {
        if ($this->logger) {
            $this->logger->debug($msg);
        }
    }

    public function getAddress()
    {
        if ($this->address) {
            return $this->address;
        }
        return $this->getNamespace() . '.server' . $this->serverId;
    }

    public function getNamespace()
    {
        if ($this->namespace) {
            return $this->namespace;
        }
        return str_replace('\\', '.', $this->appNamespaceId) . '.session';
    }

    public function getGcMap()
    {
        return getVertx()->sharedData()->getLocalMap($this->getNamespace() . '.gc');
    }

    public function getMap()
    {
        return getVertx()->sharedData()->getLocalMap($this->getNamespace());
    }

    public function getData($sid, callable $callback)
    {
        //read from local shared data map if not using session cluster mode
        $gcmap = $this->getGcMap();
        $map = $this->getMap();

        if ($map == null || $gcmap == null || $sid == null) {
            $callback(null);
            return;
        }

        $serializeObj = $map->get($sid);

        if ($serializeObj == null) {
            //if it's not on local server, try get from redis
            if (isset($this->redis) && isset($this->redis['address'])) {
                $options = new \Java("io.vertx.core.eventbus.DeliveryOptions");
                $options = $options->setSendTimeout($this->eventBusTimeout);

                getVertx()->eventBus()->send(
                    $this->redis['address'],
                    \jto(["command" => "get", "args" => [$sid]]),
                    $options,

                    g(function ($ar) use ($sid, $gcmap, $map, $callback) {
                        $rs = $ar->result()->body();

                        if ($ar->failed() || $rs['status'] != 'ok') {
                            $callback(null);
                        } else {
                            $serializeObj = $rs['value'];
                            if ($serializeObj == null) {
                                $callback(null);
                                return;
                            }

                            //update local server copy
                            $map->put($sid, $serializeObj);
                            $this->resetTimer($sid, $gcmap, $map);
                            $this->updateRedisTtl($sid);
                            $callback($serializeObj);
                        }
                    })
                );
            } else {
                $callback(null);
            }
        } else {
            //update timestamp when it's being accessed
            $this->resetTimer($sid, $gcmap, $map);

            //have to update TTL on Redis
            if (isset($this->redis) && isset($this->redis['address'])) {
                $this->updateRedisTtl($sid);
            }

            $callback($serializeObj);
        }
    }

    public function getDataFailOver($sid, $newSid, callable $callback)
    {
        $gcmap = $this->getGcMap();
        $map = $this->getMap();

        if ($gcmap->keySet()->contains($sid)) {
            getVertx()->cancelTimer($gcmap->get($sid));
            $gcmap->remove($sid);
        }

        //save to local server after read from redis
        if (isset($this->redis) && isset($this->redis['address'])) {
            $options = new \Java("io.vertx.core.eventbus.DeliveryOptions");
            $options = $options->setSendTimeout($this->eventBusTimeout);

            getVertx()->eventBus()->send(
                $this->redis['address'],
                \jto(["command" => "get", "args" => [$sid]]),
                $options,

                g(function ($ar) use ($sid, $newSid, $gcmap, $map, $callback, $options) {
                    $rs = $ar->result()->body();

                    if ($ar->failed() || $rs['status'] != 'ok') {
                        $callback(null);
                    } else {
                        $serializeObj = $rs['value'];

                        if ($serializeObj == null) {
                            $callback(null);
                            return;
                        }

                        //replace with new ID
                        $serializeObj = str_replace($sid, $newSid, $serializeObj);
                        $map->put($newSid, $serializeObj);
                        $map->remove($sid);
                        $this->resetTimer($newSid, $gcmap, $map);

                        //Update redis with new SID and remove the old one
                        getVertx()->eventBus()->send($this->redis['address'],
                            \jto(["command" => "setex", "args" => [$newSid, $this->timeout / 1000, $serializeObj]]),
                            $options, g(function ($ar) use ($sid) {
                                if ($ar->failed()) {
                                    $this->log('Redis: Failed to insert new SID ' . $sid);
                                }
                            }));
                        getVertx()->eventBus()->send($this->redis['address'],
                            \jto(["command" => "del", "args" => [$sid]]), $options, g(function ($ar) use ($sid) {
                                if ($ar->failed()) {
                                    $this->log('Redis: Failed to remove old SID ' . $sid);
                                }
                            }));

                        $callback($serializeObj);
                    }
                }));
        } else {
            $callback(null);
        }
    }

    public function updateRedisTtl($sid)
    {
        $options = new \Java("io.vertx.core.eventbus.DeliveryOptions");
        $options = $options->setSendTimeout($this->eventBusTimeout);

        getVertx()->eventBus()->send($this->redis['address'],
            \jto(["command" => "expire", "args" => [$sid, $this->timeout / 1000]]), $options, g(function ($ar) {
                if ($ar->failed()) {
                    $this->log('Redis: Failed to update session expiry');
                }
            }));
    }

    public function saveData($sid, $serializeObj, callable $callback = null)
    {
        if (!$serializeObj || empty($sid)) {
            if ($callback != null) {
                $callback(false);
            }
            return false;
        }

        //save to local shared data map if not using session cluster mode
        $gcmap = $this->getGcMap();
        $map = $this->getMap();
        $map->put($sid, $serializeObj);

        $this->resetTimer($sid, $gcmap, $map);

        //have to update TTL and serializeObj on Redis
        $this->saveDataFailOver($sid, $serializeObj);

        if ($callback != null) {
            $callback(true);
        }
    }

    public function saveDataFailOver($sid, $serializeObj, callable $callback = null)
    {
        if (!$serializeObj || empty($sid)) {
            $callback(false);
            return false;
        }

        if ($callback != null) {
            //save to local server first
            $this->saveData($sid, $serializeObj);
        }

        if (isset($this->redis) && isset($this->redis['address'])) {
            $options = new \Java("io.vertx.core.eventbus.DeliveryOptions");
            $options = $options->setSendTimeout($this->eventBusTimeout);

            getVertx()->eventBus()->send($this->redis['address'],
                \jto(["command" => "setex", "args" => [$sid, $this->timeout / 1000, $serializeObj]]), $options,
                g(function ($ar) use ($callback, $sid) {
                    if ($callback != null) {
                        $rs = $ar->result()->body();
                        if ($ar->failed() || $rs['status'] != 'ok') {
                            $callback(false);
                        } else {
                            $callback(true);
                        }
                    }
                }));
        }
    }

    public function resetTimer($sid, &$gcmap, &$map)
    {
        if ($gcmap->keySet()->contains($sid)) {
            getVertx()->cancelTimer($gcmap->get($sid));
        }

        $timerID = getVertx()->setTimer($this->timeout, g(function ($tid) use ($sid, $gcmap, $map) {
            $map->remove($sid);
            $gcmap->remove($sid);
        }));

        $gcmap->put($sid, $timerID);

        return $timerID;
    }

    public function destroyData($sid, callable $callback = null)
    {
        if (empty($sid)) {
            if ($callback != null) {
                $callback(false);
            }
            return false;
        }
        //remove session from store
        $gcmap = $this->getGcMap();
        $map = $this->getMap();
        $map->remove($sid);

        if ($gcmap->keySet()->contains($sid)) {
            getVertx()->cancelTimer($gcmap->get($sid));
        }

        $gcmap->remove($sid);

        $options = new \Java("io.vertx.core.eventbus.DeliveryOptions");
        $options = $options->setSendTimeout($this->eventBusTimeout);

        getVertx()->eventBus()->send($this->redis['address'], \jto(["command" => "del", "args" => [$sid]]), $options,
            g(function ($ar) use ($sid) {
                if ($ar->failed()) {
                    $this->log('Redis: Failed to remove old SID ' . $sid);
                }
            }));

        if ($callback != null) {
            $callback(true);
        }
    }

    public function destroyDataFailOver($sid, callable $callback = null)
    {
        $this->destroyData($sid);

        if ($callback != null) {
            $callback(true);
        }
    }


    public function destroyAll($callback = null)
    {
        //remove session from store
        $gcmap = $this->getGcMap();
        $map = $this->getMap();
        $map->clear();

        $size = $gcmap->size();
        if ($size > 0) {
            foreach ($gcmap as $sid => $timerId) {
                getVertx()->cancelTimer($timerId);
            }
        }

        $gcmap->clear();

        if ($callback != null) {
            $callback($size);
        }
    }
}