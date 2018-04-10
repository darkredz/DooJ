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
class DooVertxSharedSessionServer extends DooVertxSessionServer
{

    public function getData($sid, callable $callback)
    {
        \SharedJson::get(getVertx(), $this->getNamespace(), $sid, function ($result) use ($callback, $sid) {
            if (empty($result)) {
                $callback(null);
                return;
            }
            $serializeObj = \jfrom($result);
            if ($serializeObj == null) {
                $callback(null);
            } else {
                //update timestamp when it's being accessed
                $this->resetTimer($sid);
                $callback($serializeObj['php']);
            }
        });
    }

    public function resetTimer($sid)
    {
        \SharedJson::get(getVertx(), $this->getNamespace() . '.gc', $sid, function ($result) {
            if (empty($result)) {
                return;
            }
            $serializeObj = \jfrom($result);
            if ($serializeObj == null) {
            } else {
                getVertx()->cancelTimer($serializeObj['timerID']);
            }
        });

        $timerID = getVertx()->setTimer($this->timeout, g(function ($tid) use ($sid) {
            \SharedJson::remove(getVertx(), $this->getNamespace(), $sid, function ($result) use ($sid) {
                $this->log('Session delete expired ' . $result . ' ' . $sid);
            });

            \SharedJson::remove(getVertx(), $this->getNamespace() . '.gc', $sid, function ($result) use ($sid) {
                $this->log('Session delete expired GC ' . $result . ' ' . $sid);
            });
        }));

        \SharedJson::put(getVertx(), $this->getNamespace() . '.gc', $sid, \jto(['timerID' => $timerID]),
            function ($result) use ($sid) {
                $this->log('Session resetTimer GC ' . $result . ' ' . $sid);
            });

        return $timerID;
    }

    public function saveData($sid, $serializeObj, callable $callback = null)
    {
        if (!$serializeObj || empty($sid)) {
            if ($callback != null) {
                $callback(false);
            }
            return false;
        }

        \SharedJson::put(getVertx(), $this->getNamespace(), $sid, \jto(['php' => $serializeObj]),
            function ($result) use ($sid) {
                $this->log('Session put data ' . $result . ' ' . $sid);
                $this->resetTimer($sid);
            });

        if ($callback != null) {
            $callback(true);
        }
        return true;
    }

    public function destroyData($sid, callable $callback = null)
    {
        if (empty($sid)) {
            if ($callback != null) {
                $callback(false);
            }
            return false;
        }

        \SharedJson::remove(getVertx(), $this->getNamespace(), $sid, function ($result) use ($sid) {
            $this->log('delete session ' . $result . '  ' . $sid);

            \SharedJson::remove(getVertx(), $this->getNamespace() . '.gc', $sid, function ($result) use ($sid) {
                if ($result != null) {
                    $serializeObj = \jfrom($result);
                    if ($serializeObj == null) {
                    } else {
                        getVertx()->cancelTimer($serializeObj['timerID']);
                    }
                    $this->log('delete session gc' . $result . '  ' . $sid . '  ' . $serializeObj['timerID']);
                } else {
                    $this->log('delete session gc' . $result . '  ' . $sid);
                }
            });
        });

        if ($callback != null) {
            $callback(true);
        }
        return true;
    }

    public function destroyAll(callable $callback = null)
    {
        //remove session from store
//        $gcmap = $this->getGcMap();
//        $map = $this->getMap();
//        $map->clear();
//
//        $size = $gcmap->size();
//        if($size > 0){
//            foreach($gcmap as $sid=>$timerId){
//                getVertx()->cancelTimer($timerId);
//            }
//        }
//
//        $gcmap->clear();

        if ($callback != null) {
            $callback(0);
        }
    }
}