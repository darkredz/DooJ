<?php
/**
 * DooVertxSessionServerVerticle class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */

/**
 * DooVertxSessionServerVerticle is the main Verticle class that should be deployed with vertx to get, save or destroy sessions. Failover is available for connected nodes in the cluster if Redis session is enabled.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @package doo.session
 * @since 2.0
 */

import io . vertx . core . logging . LoggerFactory;
import io . vertx . core . Vertx;
import io . vertx . lang . php .*;
import io . vertx . lang . php . util .*;
import io . vertx . core . buffer .*;
import com . doophp . util .*;

function g($func)
{
    return HandlerFactory::createGenericHandler($func);
}

function v($func)
{
    return HandlerFactory::createVoidHandler($func);
}

function a($func)
{
    return HandlerFactory::createAsyncGenericHandler($func);
}

function jfrom($json)
{
    return \PhpTypes::arrayFromJson($json);
}

function jto($arr)
{
    return \PhpTypes::arrayToJsonObject($arr);
}

require_once '../app/DooConfig.php';
require_once './DooVertxSession.php';
require_once './DooVertxSessionServer.php';
require_once './DooVertxSharedSessionServer.php';

class DooVertxSessionServerVerticle extends DooVertxSessionServer
{

    public static function start()
    {

        $config = jfrom(verticle()->config());

        if ($config['logger'] != null) {
            $logger = \LoggerFactory::getLogger($config['logger']);
            $logger->info('Starting session server...');
            $logger->info('Session config ' . print_r($config, true));
        }

        if ($config['shared'] != null && $config['shared'] === true) {
            $server = new DooVertxSharedSessionServer();
            $server->logger = $logger;
        } else {
            $server = new DooVertxSessionServer();
            $server->logger = $logger;
        }

        if ($config['timeout'] != null) {
            $server->timeout = $config['timeout'];
        } else {
            $server->timeout = 30 * 60 * 1000;
        }

        if ($config['serverId'] != null) {
            $server->serverId = $config['serverId'];
        }

        if ($config['appNamespaceId'] != null) {
            $server->appNamespaceId = $config['appNamespaceId'];
        }

        if ($config['redis'] != null) {
            $server->redis = $config['redis'];
        }

        if ($config['namespace'] != null) {
            $server->namespace = $config['namespace'];
        }

        if ($config['address'] != null) {
            $server->address = $config['address'];
        }

        $server->log("Session server started with eventbus address " . $server->getAddress());
//
        $consumer = getVertx()->eventBus()->consumer($server->getAddress());
        $consumer->handler(g(function ($message) use ($server) {
            $cmd = $message->body();
//            $server->logger->debug("[SSERVER]:I have received a message: " . $cmd);
            $cmd = \jfrom($cmd);

            $action = intval($cmd['act']);

            switch ($action) {
                case self::SAVE:
                    $server->saveData($cmd['id'], $cmd['data'], function ($rs) use ($message) {
                        $message->reply($rs);
                    });
                    break;
                case self::GET:
                    $server->getData($cmd['id'], function ($rs) use ($message) {
                        $message->reply($rs);
                    });
                    break;
                case self::DESTROY:
                    $server->destroyData($cmd['id'], function ($rs) use ($message) {
                        $message->reply($rs);
                    });
                    break;
                case self::DESTROY_ALL:
                    $server->destroyAll(function ($rs) use ($message) {
                        $message->reply($rs);
                    });
                    break;
                case self::SAVE_FAILOVER:
                    $server->saveDataFailOver($cmd['id'], $cmd['data'], function ($rs) use ($message) {
                        $message->reply($rs);
                    });
                    break;
                case self::GET_FAILOVER:
                    $server->getDataFailOver($cmd['id'], $cmd['newId'], function ($rs) use ($message) {
                        $message->reply($rs);
                    });
                    break;
                case self::DESTROY_FAILOVER:
                    $server->destroyDataFailOver($cmd['id'], function ($rs) use ($message) {
                        $message->reply($rs);
                    });
                    break;
            }

        }));

    }

}

set_time_limit(0);
DooVertxSessionServerVerticle::start();