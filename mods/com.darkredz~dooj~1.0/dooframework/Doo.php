<?php
/**
 * Doo class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 * @version $Id: DooWebApp.php 1000 2009-06-22 18:27:22
 * @package doo
 * @since 1.0
 */

/**
 * Doo is a singleton class serving common framework functionalities.
 *
 * You can access Doo in every class to retrieve configuration settings,
 * DB connections, application properties, logging, loader utilities and etc.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: $class_name.php 1000 2009-07-7 18:27:22
 * @package doo
 * @since 1.0
 */
class Doo
{
    protected static $_app;
    protected static $_cliApp;
    protected static $_conf;
    protected static $_logger;
    protected static $_db;
    protected static $_useDbReplicate;
    protected static $_cache;
    protected static $_acl;
    protected static $_translator;
    protected static $_globalApps;
    protected static $_autoloadClassMap;

    /**
     * @return DooConfig configuration settings defined in <i>common.conf.php</i>, auto create if the singleton has not been created yet.
     */
    public static function conf()
    {
        if (self::$_conf === null) {
            self::$_conf = new DooConfig;
        }
        return self::$_conf;
    }

    /**
     * Set the list of Doo applications.
     * <code>
     * //by default, Doo::loadModelFromApp() will load from this application path
     * $apps['default'] = '/var/path/to/shared/app/'
     * $apps['app2'] = '/var/path/to/shared/app2/'
     * $apps['app3'] = '/var/path/to/shared/app3/'
     * </code>
     * @param array $apps
     */
    public static function setGlobalApps($apps)
    {
        self::$_globalApps = $apps;
    }

    /**
     * Imports the definition of Model class(es) from a Doo application
     * @param string|array $modelName Name(s) of the Model class to be imported
     * @param string $appName Name of the application to be loaded from
     * @param bool $createObj Determined whether to create object(s) of the class
     * @return mixed returns NULL by default. If $createObj is TRUE, it creates and return the Object(s) of the class name passed in.
     */
    public static function loadModelFromApp($modelName, $appName = 'default', $createObj = false)
    {
        return self::load($modelName, self::$_globalApps[$appName] . 'model/', $createObj);
    }

    /**
     * Imports the definition of User defined class(es) from a Doo application
     * @param string|array $className Name(s) of the Model class to be imported
     * @param string $appName Name of the application to be loaded from
     * @param bool $createObj Determined whether to create object(s) of the class
     * @return mixed returns NULL by default. If $createObj is TRUE, it creates and return the Object(s) of the class name passed in.
     */
    public static function loadClassFromApp($className, $appName = 'default', $createObj = false)
    {
        return self::load($className, self::$_globalApps[$appName] . 'class/', $createObj);
    }

    /**
     * Imports the definition of Controller class from a Doo application
     * @param string $class_name Name of the class to be imported
     */
    public static function loadControllerFromApp($controllerName, $appName = 'default')
    {
        return self::load($controllerName, self::$_globalApps[$appName] . 'controller/');
    }

    /**
     * @return DooWebApp the application instance.
     */
    public static function app()
    {
        if (self::$_app === null) {
            self::loadCore('app/DooWebApp');
            self::$_app = new DooWebApp;
        }
        return self::$_app;
    }

    /**
     * Set application type to be created.
     * @param string|object $type 'DooWebApp' or pass in any instance of your custom app class
     */
    public static function setAppType($type)
    {
        if (is_string($type)) {
            self::loadCore('app/' . $type);
            self::$_app = new $type;
        } else {
            self::$_app = $type;
        }
    }

    /**
     * @return DooCliApp the CLI application instance.
     */
    public static function cliApp()
    {
        if (self::$_cliApp === null) {
            self::loadCore('app/DooCliApp');
            self::$_cliApp = new DooCliApp;
        }
        return self::$_cliApp;
    }

    /**
     * @param string $class the class to use for ACL. Can be DooAcl or DooRbAcl
     * @return DooAcl|DooRbAcl the application ACL singleton, auto create if the singleton has not been created yet.
     */
    public static function acl($class = 'DooAcl')
    {
        if (self::$_acl === null) {
            self::loadCore('auth/' . $class);
            self::$_acl = new $class;
        }
        return self::$_acl;
    }

    /**
     * Call this method to use database replication instead of a single db server.
     */
    public static function useDbReplicate()
    {
        self::$_useDbReplicate = true;
    }

    /**
     * @return DooSqlMagic the database singleton, auto create if the singleton has not been created yet.
     */
    public static function db()
    {
        if (self::$_db === null) {
            if (self::$_useDbReplicate === null) {
                self::loadCore('db/DooSqlMagic');
                self::$_db = new DooSqlMagic;
            } else {
                self::loadCore('db/DooMasterSlave');
                self::$_db = new DooMasterSlave;
            }
        }

        if (!self::$_db->connected) {
            self::$_db->connect();
        }

        return self::$_db;
    }

    /**
     * @return DooTranslator
     */
    public static function translator($adapter, $data, $options = [])
    {
        if (self::$_translator === null) {
            self::loadCore('translate/DooTranslator');
            self::$_translator = new DooTranslator($adapter, $data, $options);
        }
        return self::$_translator;
    }

    /**
     * Simple accessor to Doo Translator class. You must be sure you have initialised it before calling. See translator(...)
     * @return DooTranslator
     */
    public static function getTranslator()
    {
        return self::$_translator;
    }

    /**
     * @return DooLog logging tool for logging, tracing and profiling, singleton, auto create if the singleton has not been created yet.
     */
    public static function logger()
    {
        if (self::$_logger === null) {
            self::loadCore('logging/DooLog');
            self::$_logger = new DooLog(self::conf()->DEBUG_ENABLED);
        }
        return self::$_logger;
    }

    /**
     * @param string $cacheType Cache type: file, php, front, apc, memcache, xcache, eaccelerator. Default is file based cache.
     * @return DooFileCache|DooPhpCache|DooFrontCache|DooApcCache|DooMemCache|DooApcuCache file/php/apc/memcache/apcu & frontend caching tool, singleton, auto create if the singleton has not been created yet.
     */
    public static function cache($cacheType = 'php')
    {
        if ($cacheType == 'php') {
            if (isset(self::$_cache['php'])) {
                return self::$_cache['php'];
            }

            self::$_cache['php'] = new DooPhpCache;
            return self::$_cache['php'];
        } else {
            if ($cacheType == 'file') {
                if (isset(self::$_cache['file'])) {
                    return self::$_cache['file'];
                }

                self::$_cache['file'] = new DooFileCache;
                return self::$_cache['file'];
            } else {
                if ($cacheType == 'front') {
                    if (isset(self::$_cache['front'])) {
                        return self::$_cache['front'];
                    }

                    self::$_cache['front'] = new DooFrontCache;
                    return self::$_cache['front'];
                } else {
                    if ($cacheType == 'apc') {
                        if (isset(self::$_cache['apc'])) {
                            return self::$_cache['apc'];
                        }

                        self::$_cache['apc'] = new DooApcCache;
                        return self::$_cache['apc'];
                    } else {
                        if ($cacheType == 'apcu') {
                            if (isset(self::$_cache['apcu'])) {
                                return self::$_cache['apcu'];
                            }

                            self::$_cache['apcu'] = new DooApcuCache;
                            return self::$_cache['apcu'];
                        } else {
                            if ($cacheType == 'memcache') {
                                if (isset(self::$_cache['memcache'])) {
                                    return self::$_cache['memcache'];
                                }

                                self::$_cache['memcache'] = new DooMemCache(Doo::conf());
                                return self::$_cache['memcache'];
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Imports the definition of class(es) and tries to create an object/a list of objects of the class.
     * @param string|array $class_name Name(s) of the class to be imported
     * @param string $path Path to the class file
     * @param bool $createObj Determined whether to create object(s) of the class
     * @return mixed returns NULL by default. If $createObj is TRUE, it creates and return the Object of the class name passed in.
     */
    protected static function load($class_name, $path, $createObj = false)
    {
        if (is_string($class_name) === true) {
            $pure_class_name = basename($class_name);
            class_exists($pure_class_name, false) === true || require_once($path . "$class_name.php");
            if ($createObj) {
                return new $pure_class_name;
            }
        } else {
            if (is_array($class_name) === true) {
                //if not string, then a list of Class name, require them all.
                //make sure the class_name has array with is_array
                if ($createObj) {
                    $obj = [];
                }

                foreach ($class_name as $one) {
                    $pure_class_name = basename($one);
                    class_exists($pure_class_name, false) === true || require_once($path . "$class_name.php");
                    if ($createObj) {
                        $obj[] = new $pure_class_name;
                    }
                }

                if ($createObj) {
                    return $obj;
                }
            }
        }
    }

    /**
     * Imports the definition of User defined class(es). Class file is located at <b>SITE_PATH/protected/class/</b>
     * @param string|array $class_name Name(s) of the class to be imported
     * @param bool $createObj Determined whether to create object(s) of the class
     * @return mixed returns NULL by default. If $createObj is TRUE, it creates and return the Object(s) of the class name passed in.
     */
    public static function loadClass($class_name, $createObj = false)
    {
        return self::load($class_name, self::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . "classes/",
            $createObj);
    }

    /**
     * Imports the definition of Controller class. Class file is located at <b>SITE_PATH/protected/controller/</b>
     * @param string $class_name Name of the class to be imported
     */
    public static function loadController($class_name)
    {
        return self::load($class_name, self::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . 'controller/', false);
    }

    /**
     * Imports the definition of Model class(es). Class file is located at <b>SITE_PATH/protected/model/</b>
     * @param string|array $class_name Name(s) of the Model class to be imported
     * @param bool $createObj Determined whether to create object(s) of the class
     * @return mixed returns NULL by default. If $createObj is TRUE, it creates and return the Object(s) of the class name passed in.
     */
    public static function loadModel($class_name, $createObj = false)
    {
        return self::load($class_name, self::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . 'model/', $createObj);
    }

    /**
     * Imports the definition of Helper class(es). Class file is located at <b>BASE_PATH/protected/helper/</b>
     * @param string|array $class_name Name(s) of the Helper class to be imported
     * @param bool $createObj Determined whether to create object(s) of the class
     * @return mixed returns NULL by default. If $createObj is TRUE, it creates and return the Object(s) of the class name passed in.
     */
    public static function loadHelper($class_name, $createObj = false)
    {
        return self::load($class_name, self::conf()->BASE_PATH . "helper/", $createObj);
    }

    /**
     * Imports the definition of Doo framework core class. Class file is located at <b>BASE_PATH</b>.
     * @example If the file is in a package, called <code>loadCore('auth/DooLog')</code>
     * @param string $class_name Name of the class to be imported
     */
    public static function loadCore($class_name, $conf = null)
    {
        require_once $conf->BASE_PATH . "$class_name.php";
    }

    /**
     * Imports the definition of Model class(es) in a certain module or from the main app.
     *
     * @param string|array $class_name Name(s) of the Model class to be imported
     * @param string $path module folder name. Default is the main app folder.
     * @param bool $createObj Determined whether to create object(s) of the class
     * @return mixed returns NULL by default. If $createObj is TRUE, it creates and return the Object(s) of the class name passed in.
     */
    public static function loadModelAt($class_name, $moduleFolder = null, $createObj = false)
    {
        if ($moduleFolder === null) {
            $moduleFolder = Doo::getAppPath();
        } else {
            $moduleFolder = Doo::getAppPath() . 'module/' . $moduleFolder;
        }
        return self::load($class_name, $moduleFolder . "/model/", $createObj);
    }

    /**
     * Imports the definition of Controller class(es) in a certain module or from the main app.
     *
     * @param string|array $class_name Name(s) of the Controller class to be imported
     * @param string $path module folder name. Default is the main app folder.
     */
    public static function loadControllerAt($class_name, $moduleFolder = null)
    {
        if ($moduleFolder === null) {
            $moduleFolder = Doo::getAppPath();
        } else {
            $moduleFolder = Doo::getAppPath() . 'module/' . $moduleFolder;
        }
        require_once $moduleFolder . '/controller/' . $class_name . '.php';
    }

    /**
     * Imports the definition of User defined class(es) in a certain module or from the main app.
     *
     * @param string|array $class_name Name(s) of the class to be imported
     * @param string $path module folder name. Default is the main app folder.
     * @param bool $createObj Determined whether to create object(s) of the class
     * @return mixed returns NULL by default. If $createObj is TRUE, it creates and return the Object(s) of the class name passed in.
     */
    public static function loadClassAt($class_name, $moduleFolder = null, $createObj = false)
    {
        if ($moduleFolder === null) {
            $moduleFolder = Doo::getAppPath();
        } else {
            $moduleFolder = Doo::getAppPath() . 'module/' . $moduleFolder;
        }
        return self::load($class_name, $moduleFolder . "/class/", $createObj);
    }

    /**
     * Loads template tag class from plugin directory for both main app and modules
     *
     * @param string $class_name Template tag class name
     * @param string $moduleFolder Folder name of the module. If Null, the class will be loaded from main app.
     */
    public static function loadPlugin($class_name, $moduleFolder = null)
    {
        if ($moduleFolder === null) {
            require_once Doo::getAppPath() . 'plugin/' . $class_name . '.php';
        } else {
            require_once Doo::getAppPath() . 'module/' . $moduleFolder . '/plugin/' . $class_name . '.php';
        }
    }

    /**
     * Provides auto loading feature. To be used with the Magic method __autoload
     * @param string $classname Class name to be loaded.
     */
    public static function autoload($classname, $conf)
    {
//        if( class_exists($classname, false) === true )
//			return;
        $class['DooDiagnostic'] = 'diagnostic/DooDiagnostic';
        $class['DooDebugException'] = 'diagnostic/DooDebugException';


        //app
        $class['DooConfig'] = 'app/DooConfig';
        $class['DooSiteMagic'] = 'app/DooSiteMagic';
        $class['DooAppInterface'] = 'app/DooAppInterface';
        $class['DooWebApp'] = 'app/DooWebApp';
        $class['DooEventBusApp'] = 'app/DooEventBusApp';
        $class['DooEventBusRequest'] = 'app/DooEventBusRequest';
        $class['DooEventRequestHeader'] = 'app/DooEventRequestHeader';
        $class['DooEventBusResponse'] = 'app/DooEventBusResponse';
        $class['DooJava'] = 'app/DooJava';
        $class['DooContainer'] = 'app/DooContainer';
        $class['DooWebAppRequest'] = 'app/DooWebAppRequest';
        $class['DooWebAppResponse'] = 'app/DooWebAppResponse';

        //auth
        $class['DooAcl'] = 'auth/DooAcl';
        $class['DooAuth'] = 'auth/DooAuth';
        $class['DooDigestAuth'] = 'auth/DooDigestAuth';
        $class['DooRbAcl'] = 'auth/DooRbAcl';

        //cache
        $class['DooApcCache'] = 'cache/DooApcCache';
        $class['DooApcuCache'] = 'cache/DooApcuCache';
        $class['DooFileCache'] = 'cache/DooFileCache';
        $class['DooFrontCache'] = 'cache/DooFrontCache';
        $class['DooMemCache'] = 'cache/DooMemCache';
        $class['DooPhpCache'] = 'cache/DooPhpCache';

        //controller
        $class['DooController'] = 'controller/DooController';
        $class['DooCliController'] = 'controller/DooCliController';
        $class['DooBDDController'] = 'controller/DooBDDController';
        $class['DooApiController'] = 'controller/DooApiController';
        $class['DooApiDiscoveryController'] = 'controller/DooApiDiscoveryController';

        //ext/ArrBdd
        $class['ArrBDD'] = 'ext/ArrBDD/ArrBDD';
        $class['ArrBDDSpec'] = 'ext/ArrBDD/ArrBDDSpec';
        $class['ArrMock'] = 'ext/ArrBDD/ArrMock';
        $class['ArrAssert'] = 'ext/ArrBDD/ArrAssert';
        $class['ArrAssertStatement'] = 'ext/ArrBDD/ArrAssertStatement';

        //service
        $class['DooServiceModel'] = 'model/DooServiceModel';
        $class['DooServiceInterface'] = 'service/DooServiceInterface';
        $class['DooInternalService'] = 'service/DooInternalService';
        $class['DooEventBusService'] = 'service/DooEventBusService';

        $class['DooDataMapper'] = 'model/DooDataMapper';
        $class['DooMapperConfig'] = 'model/DooMapperConfig';
        $class['DooInputValidator'] = 'model/DooInputValidator';

        //db
        $class['DooDbLogTrait'] = 'db/DooDbLogTrait';
        $class['DooDbExpression'] = 'db/DooDbExpression';
        $class['DooMasterSlave'] = 'db/DooMasterSlave';
        $class['DooModelGen'] = 'db/DooModelGen';
        $class['DooSmartModel'] = 'db/DooSmartModel';
        $class['DooFindOpt'] = 'db/DooFindOpt';
        $class['DooUpdateOpt'] = 'db/DooUpdateOpt';
        $class['DooSqlMagic'] = 'db/DooSqlMagic';

        $class['DooMongo'] = 'db/DooMongo';
        $class['DooMongoModel'] = 'db/DooMongoModel';

        $class['DooOrientDbModel'] = 'db/orientdb/DooOrientDbModel';
        $class['DooOrientDbModelGen'] = 'db/orientdb/DooOrientDbModelGen';

        //db/manage
        $class['DooDbUpdater'] = 'db/manage/DooDbUpdater';
        $class['DooManageDb'] = 'db/manage/DooManageDb';
        $class['DooManageMySqlDb'] = 'db/manage/adapters/DooManageMySqlDb';
        $class['DooManagePgSqlDb'] = 'db/manage/adapters/DooManagePgSqlDb';
        $class['DooManageSqliteDb'] = 'db/manage/adapters/DooManageSqliteDb';

        //helper
        $class['DooBenchmark'] = 'helper/DooBenchmark';
        $class['DooFile'] = 'helper/DooFile';
        $class['DooFlashMessenger'] = 'helper/DooFlashMessenger';
        $class['DooForm'] = 'helper/DooForm';
        $class['DooGdImage'] = 'helper/DooGdImage';
        $class['DooPager'] = 'helper/DooPager';
        $class['DooRestClient'] = 'helper/DooRestClient';
        $class['DooTextHelper'] = 'helper/DooTextHelper';
        $class['DooTimezone'] = 'helper/DooTimezone';
        $class['DooUrlBuilder'] = 'helper/DooUrlBuilder';
        $class['DooValidator'] = 'helper/DooValidator';
        $class['DooJsonSchema'] = 'helper/DooJsonSchema';
        $class['DooApiCaller'] = 'helper/DooApiCaller';
        $class['DooHttpClientBuilder'] = 'helper/DooHttpClientBuilder';
        $class['DooCountryCode'] = 'helper/DooCountryCode';
        $class['DooMailer'] = 'helper/DooMailer';

        //mail
        $class['DooMailInterface'] = 'helper/mail/DooMailInterface';
        $class['DooMailSmtp'] = 'helper/mail/DooMailSmtp';
        $class['DooMailMandrill'] = 'helper/mail/DooMailMandrill';


        //logging
        $class['DooLog'] = 'logging/DooLog';

        //session
        $class['DooVertxSession'] = 'session/DooVertxSession';
        $class['DooVertxSessionId'] = 'session/DooVertxSessionId';
        $class['DooVertxSessionManager'] = 'session/DooVertxSessionManager';
        $class['DooVertxSessionServer'] = 'session/DooVertxSessionServer';


        //translate
        $class['DooTranslator'] = 'translate/DooTranslator';

        //uri
        $class['DooLoader'] = 'uri/DooLoader';
        $class['DooUriRouter'] = 'uri/DooUriRouter';

        //view
        $class['DooView'] = 'view/DooView';
        $class['DooViewBasic'] = 'view/DooViewBasic';

        //logging
        $class['DooPromise'] = 'promise/DooPromise';

        if (isset($class[$classname])) {
            self::loadCore($class[$classname], $conf);
        } else {
            if (isset($conf->PROTECTED_FOLDER_ORI) === true) {
                $path = $conf->SITE_PATH . $conf->PROTECTED_FOLDER_ORI;
            } else {
                $path = $conf->SITE_PATH . $conf->PROTECTED_FOLDER;
            }


            //autoloading namespaced class, give namespaced classes the priority
            if (isset($conf->APP_NAMESPACE_ID) === true && strpos($classname, '\\') !== false) {
                $pos = strpos($classname, $conf->APP_NAMESPACE_ID);
                if ($pos === 0) {
                    $classname = str_replace('\\', '/', substr($classname, strlen($conf->APP_NAMESPACE_ID) + 1));
                    if (file_exists($path . $classname . '.php')) {
                        require_once $path . $classname . '.php';
                        return true;
                    }
                }
            }

            if (empty($conf->AUTOLOAD) === false) {
                if ($conf->APP_MODE == 'dev') {
                    $includeSub = $conf->AUTOLOAD;
                    $rs = [];
                    foreach ($includeSub as $sub) {
                        if (file_exists($sub) === false) {
                            if (file_exists($path . $sub) === true) {
                                $rs = array_merge($rs, DooFile::getFilePathList($path . $sub . '/'));
                            }
                        } else {
                            $rs = array_merge($rs, DooFile::getFilePathList($sub . '/'));
                        }
                    }

                    $autoloadConfigFolder = $path . 'config/autoload/';

                    $rsExisting = null;

                    if (file_exists($autoloadConfigFolder . 'autoload.php') === true) {
                        $rsExisting = include($autoloadConfigFolder . 'autoload.php');
                    }

                    if ($rs != $rsExisting) {
                        if (!file_exists($autoloadConfigFolder)) {
                            mkdir($autoloadConfigFolder);
                        }
                        file_put_contents($autoloadConfigFolder . 'autoload.php',
                            '<?php return ' . var_export($rs, true) . ';');
                    }
                } else {
                    if (file_exists($path . 'config/autoload/autoload.php')) {
                        if (isset(self::$_autoloadClassMap) === false) {
                            $rs = self::$_autoloadClassMap = include_once($path . 'config/autoload/autoload.php');
                        } else {
                            $rs = self::$_autoloadClassMap;
                        }
                    }
                }

                if (isset($rs[$classname . '.php']) === true) {
                    require_once $rs[$classname . '.php'];
                    return true;
                }
            }
        }
    }

    /**
     * Get the path where the Application source is located.
     * @return string
     */
    public static function getAppPath()
    {
        if (isset(Doo::conf()->PROTECTED_FOLDER_ORI) === true) {
            return Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER_ORI;
        } else {
            return Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER;
        }
    }

    public static function getCurrentModulePath()
    {
        return Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER;
    }

    /**
     * Simple benchmarking. To used this, set <code>$config['START_TIME'] = microtime(true);</code> in <i>common.conf.php</i> .
     * @param bool $html To return the duration as string in HTML comment.
     * @return mixed Duration(sec) of the benchmarked process. If $html is True, returns string <!-- Generated in 0.002456 seconds -->
     */
    public static function benchmark($html = false)
    {
        if (!isset(self::conf()->START_TIME)) {
            return 0;
        }
        $duration = microtime(true) - self::conf()->START_TIME;
        if ($html) {
            return '<!-- Generated in ' . $duration . ' seconds -->';
        }
        return $duration;
    }

    public static function powerby()
    {
        return 'Powered by <a href="http://www.doophp.com/">DooPHP Framework</a>.';
    }

    public static function version()
    {
        return '1.4.1';
    }
}
