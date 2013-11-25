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

if(empty($port)){
    $port = 80;
}

if(empty($ip)){
    $ip = '0.0.0.0';
}

$config['SERVER_ID'] = 'proxy';

$server = Vertx::createHttpServer();
$server->acceptBacklog(100000);

set_time_limit(0);
$c = new DooConfig();
$c->set($config);

//autoload classes
spl_autoload_register(function($class) use ($c){
    if(class_exists($class,false)===false){
        Doo::autoload($class, $c);
    }
});

// =========== deploy http proxy server ===========
Vertx::logger()->info("Proxy server deployed at port " . $port);
Vertx::logger()->info("Server ID " . $config['SERVER_ID']);

$server->requestHandler(function($request) use ($config, $route) {

    $conf = new DooConfig();
    $conf->set($config);

    $app = new DooWebApp();
    $app->proxy = 'myapp.web';

    //hook function that would be executed when the request is ended
//    $app->endCallback = function($app){
//        Vertx::logger()->info('==== ended ==== '. $app->_SERVER['URI_REQUEST']);
//    };

    $app->exec($conf, $route, $request);

})->listen($port, $ip);
