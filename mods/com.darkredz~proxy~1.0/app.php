<?php

import com.doophp.util.UUID;
import io.vertx.lang.php.util.JSON;

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

if(empty($port)){
    $port = 80;
}

if(empty($ip)){
    $ip = '0.0.0.0';
}

if (empty($config['SERVER_ID'])) {
    $config['SERVER_ID'] = 'proxy';
}

//change server ID if passed in from vertx -conf
if(isset($serverConf['serverId'])) {
    $config['SERVER_ID'] = $serverConf['serverId'];
}


set_time_limit(0);
$c = new DooConfig();
$c->set($config);

//autoload classes
spl_autoload_register(function($class) use ($c){
    if(class_exists($class,false)===false){
        Doo::autoload($class, $c);
    }
});

$httpReqHandler = function($request) use ($config, $route) {

    $conf = new DooConfig();
    $conf->set($config);

    $app = new DooWebApp();
    $app->proxy = 'tc.migrate';
//    $app->proxy = [
////        '^/admin/.?' => 'myapp.admin',
////        '^/api/user' => 'myapp.api.user',
//        '^/migrate/.?' => 'tc.migrate',
//        '_others'  => 'tc.frontend'
//    ];

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
        $app = null;

        if(is_int($timerId)){
            Vertx::cancelTimer($timerId);
        }
    };

    $app->exec($conf, $route, $request);
};

$server = Vertx::createHttpServer();
$server->acceptBacklog(100000)
    ->setCompressionSupported(true);

// if SSL enabled
if (isset($serverConf['sslKeyCert'])) {
    $server->ssl(true);
    $server->keyStorePath($serverConf['sslKeyCert']);

    if (isset($serverConf['sslKeyPassword'])) {
        $server->keyStorePassword($serverConf['sslKeyPassword']);
    }
    if (isset($serverConf['sslTrustCert'])) {
        $server->trustStorePath($serverConf['sslTrustCert']);
        if (isset($serverConf['sslTrustPassword'])) {
            $server->trustStorePassword($serverConf['sslTrustPassword']);
        }
    }
    $server->requestHandler($httpReqHandler)->listen($serverConf['portSsl'], $ip);

    $serverNormal = Vertx::createHttpServer();
    $serverNormal->acceptBacklog(100000)->setCompressionSupported(true);
    $serverNormal->requestHandler($httpReqHandler)->listen($port, $ip);
}
else {
    $server->requestHandler($httpReqHandler)->listen($port, $ip);
}

// =========== deploy http proxy server ===========
Vertx::logger()->info("Proxy server deployed at port " . $port);
Vertx::logger()->info("Server ID " . $config['SERVER_ID']);

