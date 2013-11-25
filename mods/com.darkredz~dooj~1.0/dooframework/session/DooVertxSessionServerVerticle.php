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
require_once '../app/DooConfig.php';
require_once './DooVertxSession.php';
require_once './DooVertxSessionServer.php';

class DooVertxSessionServerVerticle extends DooVertxSessionServer {

    public static function start(){

        $config = Vertx::config();
        $server = new DooVertxSessionServer();

        if($config['log']!=null){
            $server->logger = Vertx::logger();
        }

        if($config['timeout']!=null){
            $server->timeout = $config['timeout'];
        }
        else{
            $server->timeout = 30 * 60 * 1000;
        }

        if($config['serverId']!=null){
            $server->serverId = $config['serverId'];
        }

        if($config['appNamespaceId']!=null){
            $server->appNamespaceId = $config['appNamespaceId'];
        }

        if($config['redis']!=null){
            $server->redis = $config['redis'];
        }

        if($config['namespace']!=null){
            $server->namespace = $config['namespace'];
        }

        if($config['address']!=null){
            $server->address = $config['address'];
        }

        $server->log("Session server started with eventbus address ". $server->getAddress());

        Vertx::eventBus()->registerHandler($server->getAddress(), function($message) use ($server) {
            $cmd = $message->body();

            $action = intval($cmd['act']);

            switch($action){
                case self::SAVE:
                    $rs = $server->saveData($cmd['id'], $cmd['data']);
                    $message->reply($rs);
                    break;
                case self::GET:
                    $rs = $server->getData($cmd['id'], function($rs) use ($message){
                        $message->reply($rs);
                    });
                    break;
                case self::DESTROY:
                    $rs = $server->destroyData($cmd['id']);
                    $message->reply($rs);
                    break;
                case self::DESTROY_ALL:
                    $rs = $server->destroyAll();
                    $message->reply($rs);
                    break;
                case self::SAVE_FAILOVER:
                    $server->saveDataFailOver($cmd['id'], $cmd['data'], function($rs) use ($message){
                        $message->reply($rs);
                    });
                    break;
                case self::GET_FAILOVER:
                    $server->getDataFailOver($cmd['id'], $cmd['newId'], function($rs) use ($message){
                        $message->reply($rs);
                    });
                    break;
                case self::DESTROY_FAILOVER:
                    $rs = $server->destroyDataFailOver($cmd['id']);
                    $message->reply($rs);
                    break;
            }

        });

    }

}



set_time_limit(0);
Vertx::logger()->info('Starting session server...');
DooVertxSessionServerVerticle::start();