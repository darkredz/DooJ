<?php


include './protected/config/common.conf.php';
include './protected/config/routes.conf.php';
include $config['BASE_PATH'].'Doo.php';
include $config['BASE_PATH'].'app/DooConfig.php';
include $config['BASE_PATH'].'app/DooWebApp.php';
include $config['BASE_PATH'].'uri/DooUriRouter.php';
include $config['BASE_PATH']."controller/DooController.php";


// =========== Configure server ==========

$serverConf = Vertx::config();
$port = $serverConf['port'];
$ip = $serverConf['ip'];

//change server ID if passed in from vertx -conf
if(isset($serverConf['serverId'])) {
    $config['SERVER_ID'] = $serverConf['serverId'];
    if(isset($config['SESSION_REDIS'])){
        $config['SESSION_REDIS']['address'] = $config['APP_NAMESPACE_ID'] . '.session.redis' . $config['SERVER_ID'];
    }
}

set_time_limit(0);
$server = Vertx::createHttpServer();
$server->acceptBacklog(100000);


$c = new DooConfig();
$c->set($config);

//autoload classes
spl_autoload_register(function($class) use ($c){
    if(class_exists($class,false)===false){
        Doo::autoload($class, $c);
    }
});


// =========== deploy session server ===========
if(!empty($config['SESSION_ENABLE'])){
    $sessionServerPath = str_replace(__DIR__ .'/', '', $config['BASE_PATH'] . 'session/DooVertxSessionServerVerticle.php');
    Vertx::logger()->info('Deploying ' . $sessionServerPath);

    $sessionConf = ['serverId' => $c->SERVER_ID, 'appNamespaceId' => $c->APP_NAMESPACE_ID, 'redis' => $c->SESSION_REDIS, 'timeout' => 60 * 60 * 1000];

    Vertx::deployVerticle($sessionServerPath, $sessionConf, 1, function($deployId, $error) use ($c){

        if($error){
            Vertx::logger()->info($error);
        }
        else{
            Vertx::logger()->info('Session server ID: '. $deployId .'. You can use session now');
        }

        if(isset($c->SESSION_REDIS)){
            Vertx::deployModule("io.vertx~mod-redis~1.1.2", $c->SESSION_REDIS, 1, function($deployId, $error) use ($c){
                if($error){
                    Vertx::logger()->info('Redis mod failed to start');
                    Vertx::logger()->info($error);
                }
            });
        }
    });
}

// =========== deploy http server ===========
Vertx::logger()->info("Http server deployed at port " . $port);
Vertx::logger()->info("Server ID " . $config['SERVER_ID']);

$server->requestHandler(function($request) use ($config, $route) {

    $conf = new DooConfig();
    $conf->set($config);
    
    $app = new DooWebApp();

    $exceptionHandler = function($msg, $filename='', $line='', $className='', $funcName='') use ($app, $conf){
        Vertx::logger()->info("exceptionHandler $className $funcName");
        // require $conf->BASE_PATH.'diagnostic/DooDiagnostic.php';
        // require $conf->BASE_PATH.'helper/DooTextHelper.php';
        // $d = new DooDiagnostic();
        // $d->setErrorHandler('Exception', $msg, $filename, $line, null, $app);
        if($app->ended){
            if(isset($app->db)){
                Vertx::logger()->info($app->db);
                Vertx::logger()->info('DB CLOSE');
                $app->db->close();
                $app->db = null;
            }
        }
        else{
            $app->end("<html><h2 style='font-family:Courier'>$msg</h2><br/><div style='font-family:Courier'>Line $line : $filename</div>");
        }
    };

    Vertx::exceptionHandler($exceptionHandler);

    //server handle request 30 sec timeout
    if($request->uri != '/favicon.ico'){
        $timerId = Vertx::setTimer(30*1000, function() use ($app){
            $app->logDebug('HTTP TIMEOUT END');
            $app->statusCode = 408;
            $app->end();
        });
    }

    //hook function that would be executed when the request is ended
    $app->endCallback = function($app) use ($timerId){
        if(isset($app->db)){
            $app->logDebug($app->db);
            $app->logDebug('DB CLOSE');
            $app->db->close();
            $app->db = null;
        }

        $app = null;
        if(is_int($timerId)){
            Vertx::cancelTimer($timerId);
        }
    };


    $app->exec($conf, $route, $request);

})->listen($port, $ip);


Vertx::eventBus()->registerHandler('myapp.web', function($message) use ($config, $route) {

    $app = new DooEventBusApp();

    //hook function that would be executed when the request is ended
    $app->endCallback = function($app){
        if(isset($app->db)){
            Vertx::logger()->info($app->db);
            Vertx::logger()->info('DB CLOSE');
            $app->db->close();
            $app->db = null;
        }
    };

    $app->exec($config, $route, $message);
});
