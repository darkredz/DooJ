<?php
/**
 * DooMongo class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2011 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */

/**
 * DooMongo class is used to create and manage MongoDB connections in the framework.
 *
 * <p>To config a Mongo connection in db.conf.php, a typical use is:</p>
 * <code>
 * $dbconfig['dev'] = array('server' => 'mongodb://localhost:27017');
 * </code>
 *
 * <p>More options(persist connection, username, password) in connection:</p>
 * <code>
 * $dbconfig['dev'] = array(
 *      'server' => 'mongodb://username:password@localhost:27017',
 *      'options' => array(
 *                      'connect' => false,
 *                      'persist'=>'mongo'
 *                  )
 * ); 
 * </code>
 *
 * <p>Setting up the connection in index.php bootstrap or some where appropriate:</p>
 * <code>
 * DooMongo::setup($dbconfig[$config['APP_MODE']]['server'], $dbconfig[$config['APP_MODE']]['options'], 0);
 * </code>
 *
 * <p>Refer to http://www.php.net/manual/en/mongo.construct.php for more options in setting up MongoDB connection.</p>
 * 
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @package doo.db.mongo
 * @since 2.0
 */
class DooMongo extends Mongo{
    
    protected $profileSec;
    protected $profileMiliSec;
    protected static $mongo;
    protected $profilingLevel;
    
    /**
     * Debug mode for Mongo connection
     * @var bool
     */
    public $debugMode=false;
    
    /**
     * Creates a new Mongo database connection
     * @param string $server Mongo connection string, eg. mongodb://localhost:27017
     * @param array $options Refer to http://www.php.net/manual/en/mongo.construct.php
     * @param int $debugProfileLevel Default is 0 (Off)
     */
    public function __construct($server, $options=null, $debugProfileLevel=0) {
        parent::__construct($server, $options);
        if($debugProfileLevel > 0){
            $this->enableDebug($debugProfileLevel);
        }
    }
        
    /**
     * Enable debug mode
     * @param int $level Profiling level. MongoDB::PROFILING_OFF, MongoDB::PROFILING_SLOW (on for slow operations >100 ms), MongoDB::PROFILING_ON 
     */
    public function enableDebug($level=2){        
        $this->profilingLevel = $level;
        $this->debugMode = true;
        $this->profileSec = time();
        $this->profileMiliSec = round(microtime() * 1000000);  
    }   
        
    /**
     * Checks if debug mode is enabled
     * @return bool
     */
    public function isDebugEnabled(){        
        return $this->debugMode;            
    }       
    
    /**
     * Get profiling level
     * @return int
     */
    public function getProfilingLevel(){
        return $this->profilingLevel;
    }
    
    /**
     * Set profiling level
     * @param int $level
     */
    public function setProfilingLevel($level){
        $this->profilingLevel = $level;
    }
    
    /**
     * Get database profiled data
     * @param array $databaseNames An array of database names
     * @return array 
     */
    public function getProfilingData($databaseNames){   
        $timedate = new MongoDate($this->profileSec, $this->profileMiliSec);
        $result = null;
            
        foreach($databaseNames as $db){
            $cursor = $this->selectDB($db)->selectCollection('system.profile')->find(array('ts' => array('$gte'=>$timedate) ));            
            $cursor->sort(array('$natural'=>-1));

            foreach($cursor as $r){
                $dt['time'] = date( 'Y-m-d h:i:s', $r['ts']->sec);
                                
                preg_match('/query ([a-zA-Z0-9\.\-\_ \$]+) ntoreturn\:/', $r['info'], $collection);
                if(sizeof($collection)>1){
                    $collection = $collection[1];
                    $collection = explode('.', $collection);
                    $db = $collection[0];
                    unset($collection[0]);
                    $collection = implode('.', $collection);
                    $dt['collection'] = $collection;
                }

                $dt['db'] = $db;            

                preg_match('/query\: \{ (.+) \}/', $r['info'], $query);

                if(!empty($query)){
                    $dt['query'] = '{'.$query[1].'}';
                    $dt['info'] = $r['info'];
                }
                $result[] = $dt;
            }
            array_pop($result);
        }
        return $result;
    }    
        
    public function getDebugTime(){        
        return array($this->profileSec, $this->profileMiliSec);    
    }   
    
    /**
     * @return DooMongo
     */
    public static function getInstance(){
        return self::$mongo;
    }
    
    /**
     * @param DooMongo $mongo 
     */
    public static function setInstance($mongo){
        self::$mongo = $mongo;
    }    
    
    /**
     * Setup a new Mongo database connection
     * @param string $server Mongo connection string, eg. mongodb://localhost:27017
     * @param array $options Refer to http://www.php.net/manual/en/mongo.construct.php
     * @param int $debugProfileLevel Default is 0 (Off)
     */
    public static function setup($server, $options=null, $debugProfileLevel=0){
        self::$mongo = new DooMongo($server, $options, $debugProfileLevel);
    }    
    
}
