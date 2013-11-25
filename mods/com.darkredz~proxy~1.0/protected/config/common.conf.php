<?php
/* 
 * Common configuration that can be used throughout the application
 * Please refer to DooConfig class in the API doc for a complete list of configurations
 * Access via Singleton, eg. Doo::conf()->BASE_PATH;
 */
error_reporting(E_ALL | E_STRICT);
date_default_timezone_set('UTC');

/**
 * for benchmark purpose, call Doo::benchmark() for time used.
 */
//$config['START_TIME'] = microtime(true);


//For framework use. Must be defined. Use full absolute paths and end them with '/'      eg. /var/www/project/
$path = explode('/', __DIR__);
array_pop($path);
array_pop($path);

$config['SITE_PATH'] = implode('/', $path) . '/';
$config['BASE_PATH'] = $config['SITE_PATH'] . 'dooframework/';

Vertx::logger()->info('Running from ' . $config['SITE_PATH']);

array_pop($path);
array_pop($path);
$config['WEB_STATIC_PATH'] = implode('/', $path) . '/webroot/';
Vertx::logger()->info('Web assets served from ' . $config['WEB_STATIC_PATH']);


//for production mode use 'prod'
$config['APP_MODE'] = 'prod';

//$config['SUBFOLDER'] = '/app/';
$config['APP_URL'] = 'http://localhost/';
$config['AUTOROUTE'] = TRUE;
$config['DEBUG_ENABLED'] = TRUE;


// $config['TEMPLATE_COMPILE_ALWAYS'] = TRUE;

//register functions to be used with your template files
//$config['TEMPLATE_GLOBAL_TAGS'] = array('url', 'url2', 'time', 'isset', 'empty');

/**
 * Path to store logs/profiles when using with the logger tool. This is needed for writing log files and using the log viewer tool
 */
//$config['LOG_PATH'] = '/var/logs/';


/**
 * defined either Document or Route to be loaded/executed when requested page is not found
 * A 404 route must be one of the routes defined in routes.conf.php (if autoroute on, make sure the controller and method exist)
 * Error document must be more than 512 bytes as IE sees it as a normal 404 sent if < 512b
 */
//$config['ERROR_404_DOCUMENT'] = 'error.php';
$config['ERROR_404_ROUTE'] = '/error';


/**
 * Unique string ID of the application to be used with PHP 5.3 namespace and auto loading of namespaced classes
 * If you wish to use namespace with the framework, your classes must have a namespace starting with this ID.
 * Example below is located at /var/www/app/protected/controller/test and can be access via autoroute http://localhost/test/my/method
 * <?php
 * namespace myapp\controller\test;
 * class MyController extends \DooController {
 *     .....
 * } ?>
 *
 * You would need to enable autoload to use Namespace classes in index.php 
 * spl_autoload_register('Doo::autoload');
 *
 * $config['APP_NAMESPACE_ID'] = 'myapp';
 *
 */
$config['APP_NAMESPACE_ID'] = 'myapp';

$config['SERVER_ID'] = 'proxy';

