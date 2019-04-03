<?php
/**
 * DooSmartModel class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */


/**
 * DooSmartModel is a smarter version of DooModel that provides smart caching of the Model data.
 *
 * <p>The model classes can extend DooSmartModel for more powerful ORM features which enable you to write shorter codes.
 * Extending this class is optional.</p>
 *
 * <p>All the extra ORM methods can be accessed in a static and non-static way. Example:</p>
 *
 * <code>
 * $food = new Food;
 * $food->getOne();
 *
 * $food->count();
 *
 * //Dynamic querying
 * $food->getById(14);
 * $food->getById_location(14, 'Malaysia');
 *
 * //Get only one item
 * $food->getById_first(14);
 * $food->getById_location_first(14, 'Malaysia');
 *
 * //Gets list of food with its food type
 * $food->relateFoodType();
 * $food->relateFoodType($food, array('limit'=>'first'));
 * $food->relateFoodType_first();
 *
 * </code>
 *
 * <code>
 *
 * class Model extends DooSmartModel{
 *     function __construct(){
 *         parent::$className = __CLASS__;
 *         //OR parent::setupModel(__CLASS__);
 *         //parent::setupModel(__CLASS__, false); #disable caching
 *         //parent::setupModel(__CLASS__, 'php); #enable caching with php file cache
 *     }
 * }
 * </code>
 *
 * <p>Cache are deleted automatically when Update/Insert/Delete operations occured.
 * If you need to manually clearing the Model cache, use purgeCache()
 * </p>
 * <code>
 * $food->purgeCache();
 * </code>
 *
 * Please check the database demo MainController's test() method for some example usage.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooSmartModel.php 1000 2009-08-28 11:43:26
 * @package doo.db
 * @since 1.2
 */
class DooSmartModel
{
    use DooDbLogTrait;

    /**
     * Determine whether the DB field names should be case sensitive.
     * @var bool
     */
    protected $caseSensitive = false;

    /**
     * The class name of the Model
     * @var string
     */
    protected $className = __CLASS__;

    /**
     * Data cache mode: file, apc, memcache, apcu, php.
     * @var string
     */
    public $cacheMode;

    /**
     * Cache duration, default to one year
     * @var int
     */
    public $cacheDuration = 31536000;

    /**
     * DB object
     * @var DooSqlMagic
     */
    protected $dbObject;

    /**
     * @var DooFileCache|DooPhpCache|DooFrontCache|DooApcCache|DooMemCache|DooApcuCache
     */
    protected $cacheInstance;

    /**
     * @var DooWebApp|DooEventBusApp
     */
    protected $app;

    /**
     * Constructor of a Model. Sets the model class properties with a list of keys & values.
     * @param DooSqlMagic $db DB object to be used instead of the default DooSqlMagic instance from Doo::db()
     * @param DooConfig $conf App config
     * @param DooFileCache|DooPhpCache|DooFrontCache|DooApcCache|DooMemCache|DooApcuCache $cacheInstance Cache instance
     * @param array $properties Array of data (keys and values) to set the model properties
     */
    public function __construct($db, $app, $properties = null, $cacheInstance = null)
    {
        if ($properties !== null) {
            foreach ($properties as $k => $v) {
                if (in_array($k, $this->_fields)) {
                    $this->{$k} = $v;
                }
            }
        }
        if ($db !== null) {
            $this->dbObject = $db;
        }
        $this->app = $app;
        $this->cacheInstance = $cacheInstance;
    }

    public function cache($cacheMode)
    {
        if ($this->cacheInstance != null) {
            return $this->cacheInstance;
        }

        switch ($cacheMode) {
            case 'php':
                $this->cacheInstance = new DooPhpCache($this->app->conf);
                break;
            case 'file':
                $this->cacheInstance = new DooFileCache($this->app->conf);
                break;
            case 'memcache':
                Doo::cache();
                $this->cacheInstance = new DooMemCache($this->app->conf);
                break;
            case 'apc':
                $this->cacheInstance = new DooApcCache();
                break;
            case 'apcu':
                $this->cacheInstance = new DooApcuCache();
                break;
        }

        return $this->cacheInstance;
    }

    /**
     * Setup the model. Use if needed with constructor.
     *
     * @param string $class Class name of the Model
     * @param string|boolean $cacheMode Data cache mode: file, apc, memcache, php, apcu. Pass in false to disable caching
     * @param bool $caseSensitive Determine whether the DB field names should be case sensitive.
     */
    protected function setupModel($className = __CLASS__, $cacheMode = 'php', $cacheDuration = 31536000, $caseSensitive = false)
    {
        $this->className = $className;
        $this->cacheMode = $cacheMode;
        $this->caseSensitive = $caseSensitive;
        $this->cacheDuration = $cacheDuration;
    }

    /**
     * Validate the Model with the rules defined in getVRules()
     *
     * @param string $checkMode Validation mode. all, all_one, skip
     * @param string $requireMode Require Check Mode. null, nullempty
     * @return array Return array of errors if exists. Return null if data passes the validation rules.
     */
    public function validate($checkMode = 'all', $requireMode = 'null')
    {
        //all, all_one, skip
        $v = new DooValidator;
        $v->checkMode = $checkMode;
        $v->requireMode = $requireMode;
        return $v->validate(get_object_vars($this), $this->getVRules());
    }

    /**
     * Generate unique ID for a certain query based on Model name and method & options.
     * @param object $model The model object to be query
     * @param string $accessMethod Accessed query method name
     * @param array $options Options used in the query
     * @return string Unique cache ID
     */
    public function toCacheId($model, $accessMethod, $options = null)
    {
        if ($options !== null) {
            ksort($options);
            $id = $this->getClassNameForCache() . '-' . $accessMethod . serialize($options);
        } else {
            $id = $this->getClassNameForCache() . '-' . $accessMethod;
        }

        $obj = get_object_vars($model);
        foreach ($obj as $o => $v) {
            if (isset($v) && in_array($o, $model->_fields)) {
                $id .= '#' . $o . '=' . $v;
            }
        }
        //echo '<h1>'.md5($id).'</h1>';
        return $id;
    }

    protected function getClassNameForCache($className = null)
    {
        if ($className) {
            return str_replace('\\', '-', $className);
        }
        return str_replace('\\', '-', $this->className);
    }

    /**
     * Retrieve cache by ID
     * @param string $id
     * @return mixed
     */
    public function getCache($id)
    {
        if (empty($this->cacheMode)) return;

        if ($this->cacheMode === true || $this->cacheMode == 'php') {
            if ($rs = $this->cache('php')->getIn('mdl_' . $this->getClassNameForCache(), $id)) {
                //echo '<br>PHP cached version<br>';
                return $rs;
            }
        } else {
            if ($this->cacheMode == 'file') {
                if ($rs = $this->cache('file')->getIn('mdl_' . $this->getClassNameForCache(), $id)) {
                    //echo '<br>File cached version<br>';
                    return $rs;
                }
            } else {
                //echo "<br>PHP {$this->cacheMode} version<br>";
                if ($rs = $this->cache($this->cacheMode)->get($this->app->conf->SITE_PATH . $this->app->conf->PROTECTED_FOLDER . $id)) {
                    if ($rs instanceof ArrayObject) {
                        return $rs->getArrayCopy();
                    }
                    return $rs;
                }
            }
        }
    }

    /**
     * Store cache with a unique ID
     * @param string $id
     * @param mixed $value
     */
    public function setCache($id, $value)
    {
        if (empty($this->cacheMode)) return;

        //if file based cache then store in seperate folder
        if ($this->cacheMode === true || $this->cacheMode == 'php') {
            $this->cache('php')->setIn('mdl_' . $this->getClassNameForCache(), $id, $value, $this->cacheDuration);
        } else {
            if ($this->cacheMode == 'file') {
                $this->cache('file')->setIn('mdl_' . $this->getClassNameForCache(), $id, $value, $this->cacheDuration);
            } else {
                //need to store the list of Model cache to be purged later on for Memory based cache.
                $keysId = $this->cache($this->cacheMode)->SITE_PATH . $this->app->conf->PROTECTED_FOLDER . 'mdl_' . $this->getClassNameForCache();
                if ($keys = $this->cache($this->cacheMode)->get($keysId)) {
                    $listOfModelCache = $keys->getArrayCopy();
                    $listOfModelCache[] = $this->app->conf->SITE_PATH . $this->app->conf->PROTECTED_FOLDER . $id;
                } else {
                    $listOfModelCache = [];
                    $listOfModelCache[] = $this->app->conf->SITE_PATH . $this->app->conf->PROTECTED_FOLDER . $id;
                }
                if (is_array($value)) {
                    $this->cache($this->cacheMode)->set($this->app->conf->SITE_PATH . $this->app->conf->PROTECTED_FOLDER . $id,
                        new ArrayObject($value), $this->cacheDuration);
                } else {
                    $this->cache($this->cacheMode)->set($this->app->conf->SITE_PATH . $this->app->conf->PROTECTED_FOLDER . $id,
                        $value, $this->cacheDuration);
                }
                $this->cache($this->cacheMode)->set($keysId, new ArrayObject($listOfModelCache), $this->cacheDuration);
            }
        }
    }

    /**
     * Delete all the cache on the Model
     * @param array $rmodels Related model names to be deleted from cache
     */
    public function purgeCache($rmodels = null)
    {
        if (empty($this->cacheMode)) return;

        $this->className = get_class($this);

        if ($this->cacheMode === true || $this->cacheMode == 'php') {
            $this->cache('php')->flushAllIn('mdl_' . $this->getClassNameForCache());
        } else {
            if ($this->cacheMode == 'file') {
                $this->cache('file')->flushAllIn('mdl_' . $this->getClassNameForCache());
            } else {
                //loop and get the list and delete those start with the Model name, then delete them
                $keysId = $this->app->conf->SITE_PATH . $this->app->conf->PROTECTED_FOLDER . 'mdl_' . $this->getClassNameForCache();
                if ($keys = $this->cache($this->cacheMode)->get($keysId)) {
                    $listOfModelCache = $keys->getArrayCopy();
                    foreach ($listOfModelCache as $k) {
                        //echo '<br>Deleting '. $k .' from memory';
                        $this->cache($this->cacheMode)->flush($k);
                    }
                }
                $this->cache($this->cacheMode)->flush($keysId);
            }
        }
        if ($rmodels !== null) {
            if ($this->cacheMode === true || $this->cacheMode == 'php') {
                foreach ($rmodels as $r) {
                    $this->cache('php')->flushAllIn('mdl_' . $this->getClassNameForCache(get_class($r)));
                }
            } else {
                foreach ($rmodels as $r) {
                    //loop and get the list and delete those start with the Model name, then delete them
                    $keysId = $this->app->conf->SITE_PATH . $this->app->conf->PROTECTED_FOLDER . 'mdl_' . $this->getClassNameForCache(get_class($r));
                    if ($keys = $this->cache($this->cacheMode)->get($keysId)) {
                        $listOfModelCache = $keys->getArrayCopy();
                        foreach ($listOfModelCache as $k) {
                            //echo '<br>Deleting '. $k .' from memory';
                            $this->cache($this->cacheMode)->flush($k);
                        }
                        $this->cache($this->cacheMode)->flush($keysId);
                    }
                }
            }
        }
    }

    /**
     * Change cache mode of the model.
     * @param string $cacheMode Data cache mode: file, apc, memcache, apcu, php
     */
    public function setCacheMode($cacheMode)
    {
        $this->cacheMode = $cacheMode;
    }

    /**
     * Set cache duration in seconds
     * @param int $duration Number of seconds to cache
     */
    public function setCacheDuration($duration)
    {
        $this->cacheDuration = $duration;
    }

    public function findOpt()
    {
        return DooFindOpt::make();
    }

    public function updateOpt()
    {
        return DooUpdateOpt::make();
    }

    //-------------- shorthands --------------------------

    /**
     * Shorthand for Doo::db()
     * @return DooSqlMagic
     */
    public function db()
    {
        return $this->dbObject;
    }

    public function getDataObject()
    {
        $obj = new stdClass();
        foreach ($this->_fields as $field) {
            $obj->{$field} = $this->{$field};
        }
        return $obj;
    }

    public function getDataArray()
    {
        $obj = [];
        foreach ($this->_fields as $field) {
            $obj[$field] = $this->{$field};
        }
        return $obj;
    }

    /**
     * Set DB object to be used instead of the default DooSqlMagic instance from Doo::db()
     * @param DooSqlMagic $dbOject DB object
     */
    public function setDb($dbObject)
    {
        $this->dbObject = $dbObject;
    }

    /**
     * Commits a transaction. Transactions can be nestable.
     */
    public function commit($callback = null)
    {
        $this->db()->commit();
        if ($callback) {
            $callback();
        }
    }

    /**
     * Initiates a transaction. Transactions can be nestable.
     */
    public function beginTransaction($callback = null)
    {
        $this->db()->autoconnect(true);
        $this->db()->beginTransaction();
        if ($callback) {
            $callback();
        }
    }

    /**
     * Rolls back a transaction. Transactions can be nestable.
     */
    public function rollBack($callback = null)
    {
        $this->db()->rollBack();
        if ($callback) {
            $callback();
        }
    }

    public function setTable($tableName)
    {
        $this->_table = $tableName;
    }

    public function getTable()
    {
        return $this->_table;
    }

    /**
     * Retrieve the total records in a table. COUNT()
     *
     * @param array $options Options for the query. Available options see @see find() and additional 'distinct' option
     * @return int total of records
     */
    public function count($options = null)
    {
        if (is_object($options) && get_class($options) == 'DooFindOpt') {
            $options = $options->getOptions();
        }

        $options['select'] = isset($options['having']) ? $options['select'] . ', ' : '';
        if (isset($options['distinct']) && $options['distinct'] == true) {
            $options['select'] = 'COUNT(DISTINCT ' . $this->_table . '.' . $this->_fields[0] . ') as _doototal';
        } else {
            $options['select'] = 'COUNT(' . $this->_table . '.' . $this->_fields[0] . ') as _doototal';
        }
        $options['asArray'] = true;
        $options['limit'] = 1;

        if (!empty($this->cacheMode)) {
            $id = $this->toCacheId($this, 'count', $options);
            if ($rs = $this->getCache($id)) {
                return $rs;
            }
        }

        $value = $this->db()->find($this, $options);
        $value = $value['_doototal'];

        //if is null or false or 0 then dun store it because the cache can't differentiate the Empty values
        if (!empty($this->cacheMode)) {
            if ($value) {
                $this->setCache($id, $value);
            }
        }
        return $value;
    }

    /**
     * Find a record. (Prepares and execute the SELECT statements)
     * @param array $opt Associative array of options to generate the SELECT statement. Supported: <i>where, limit, select, param, asc, desc, custom, asArray, groupby,</i>
     * @return mixed A model object or associateve array of the queried result
     */
    public function find($opt = null)
    {
        if (is_object($opt) && get_class($opt) == 'DooFindOpt') {
            $opt = $opt->getOptions();
        }

        if (!empty($this->cacheMode)) {
            $id = $this->toCacheId($this, 'find', $opt);
            if ($rs = $this->getCache($id)) {
                return $rs;
            }
        }

        $value = $this->db()->find($this, $opt);

        if (!empty($this->cacheMode)) {
            //if is null or false or 0 then dun store it because the cache can't differentiate the Empty values
            if ($value) {
                $this->setCache($id, $value);
            }
        }
        return $value;
    }

    /**
     * Retrieve model by one record.
     *
     * @param array $options Options for the query. Available options see @see find()
     * @return mixed A model object or associateve array of the queried result
     */
    public function getOne($opt = null)
    {
        if (is_object($opt) && get_class($opt) == 'DooFindOpt') {
            $opt = $opt->getOptions();
        }

        if ($opt !== null) {
            $opt['limit'] = 1;
            if (!empty($this->cacheMode)) {
                $id = $this->toCacheId($this, 'find', $opt);
                if ($rs = $this->getCache($id)) {
                    return $rs;
                }
            }
            $value = $this->db()->find($this, $opt);
        } else {
            if (!empty($this->cacheMode)) {
                $id = $this->toCacheId($this, 'find', ['limit' => 1]);
                if ($rs = $this->getCache($id)) {
                    return $rs;
                }
            }
            $value = $this->db()->find($this, ['limit' => 1]);
        }

        //if is null or false or 0 then dun store it because the cache can't differentiate the Empty values
        if (!empty($this->cacheMode)) {
            if ($value) {
                $this->setCache($id, $value);
            }
        }
        return $value;
    }

    public function timeNow($timestamp = null)
    {
        if ($timestamp) {
            return date('Y-m-d H:i:s', $timestamp);
        }
        return date('Y-m-d H:i:s');
    }

    public function toDateTime($timestamp, $timezoneFrom = null, $timezoneInto = null)
    {
        if (is_int($timestamp)) {
            $timestamp = date('Y-m-d H:i:s', $timestamp);
        }

        if ($timezoneFrom && !$timezoneInto) {
            return DooTimezone::convertToUTCTime($timestamp, $timezoneFrom);
        } else if ($timezoneFrom && $timezoneInto) {
            return DooTimezone::convertTime($timestamp, $timezoneFrom, $timezoneInto);
        }
        return date('Y-m-d H:i:s');
    }

    /**
     * Retrieve a list of paginated data. To be used with DooPager
     *
     * @param string $limit String for the limit query, eg. '6,10'
     * @param string $asc Fields to be sorted Ascendingly. Use comma to seperate multiple fields, eg. 'name,timecreated'
     * @param string $desc Fields to be sorted Descendingly. Use comma to seperate multiple fields, eg. 'name,timecreated'
     * @param array $options Options for the query. Available options see @see find()
     * @return mixed A model object or associateve array of the queried result
     */
    public function limit($limit = 1, $asc = '', $desc = '', $options = null)
    {
        if (is_object($options) && get_class($options) == 'DooFindOpt') {
            $options = $options->getOptions();
        }

        if ($asc != '' || $desc != '' || $options !== null) {
            $options['limit'] = $limit;
            if ($asc != '') {
                $options['asc'] = $asc;
            }
            if ($desc != '') {
                $options['desc'] = $desc;
            }
            if ($asc != '' && $desc != '') {
                $options['asc'] = $asc;
                $options['custom'] = ',' . $desc . ' DESC';
            }
            if (!empty($this->cacheMode)) {
                $id = $this->toCacheId($this, 'find', $options);
                if ($rs = $this->getCache($id)) {
                    return $rs;
                }
            }
            $value = $this->db()->find($this, $options);
        } else {
            if (!empty($this->cacheMode)) {
                $id = $this->toCacheId($this, 'find', ['limit' => $limit]);
                if ($rs = $this->getCache($id)) {
                    return $rs;
                }
            }
            $value = $this->db()->find($this, ['limit' => $limit]);
        }

        //if is null or false or 0 then dun store it because the cache can't differentiate the Empty values
        if (!empty($this->cacheMode)) {
            if ($value) {
                $this->setCache($id, $value);
            }
        }
        return $value;
    }

    /**
     * Find a record and its associated model. Relational search. (Prepares and execute the SELECT statements)
     * @param string $rmodel The related model class name.
     * @param array $opt Associative array of options to generate the SELECT statement. Supported: <i>where, limit, select, param, joinType, groupby, match, asc, desc, custom, asArray, include, includeWhere, includeParam</i>
     * @return mixed A list of model object(s) or associateve array of the queried result
     */
    public function relate($rmodel, $options = null)
    {
        if (is_object($options) && get_class($options) == 'DooFindOpt') {
            $options = $options->getOptions();
        }

        if (!empty($this->cacheMode)) {
            if (is_string($rmodel)) {
                $id = $this->toCacheId($this, 'relate' . $rmodel, $options);
            } else {
                $rcls = get_class($rmodel);
                $id = $this->toCacheId($this, 'relate' . $rcls, $options);
            }
            if ($rs = $this->getCache($id)) {
                return $rs;
            }
        }
        $value = $this->db()->relate($this, $rmodel, $options);

        //if is null or false or 0 then dun store it because the cache can't differentiate the Empty values
        if (!empty($this->cacheMode)) {
            if ($value) {
                $this->setCache($id, $value);
            }
        }
        return $value;
    }

    /**
     * Combine relational search results (combine multiple relates).
     *
     * Example:
     * <code>
     * $food = new Food;
     * $food->relateMany(array('Recipe','Article','FoodType'))
     * </code>
     *
     * @param array $rmodel The related models class names.
     * @param array $opt Array of options for each related model to generate the SELECT statement. Supported: <i>where, limit, select, param, joinType, groupby, match, asc, desc, custom, asArray, include, includeWhere, includeParam</i>
     * @return mixed A list of model objects of the queried result
     */
    public function relateMany($rmodel, $opt = null)
    {
        if (is_object($opt) && get_class($opt) == 'DooFindOpt') {
            $opt = $opt->getOptions();
        }
        return $this->db()->relateMany($this, $rmodel, $opt);
    }

    /**
     * Expand related models (Tree Relationships).
     *
     * Example:
     * <code>
     * $recipe = new Recipe;
     * $recipe->relateExpand(array('Food','Article'))
     * </code>
     *
     * @param array $rmodel The related models class names.
     * @param array $opt Array of options for each related model to generate the SELECT statement. Supported: <i>where, limit, select, param, joinType, groupby, match, asc, desc, custom, asArray, include, includeWhere, includeParam</i>
     * @return mixed A list of model objects of the queried result
     */
    public function relateExpand($rmodel, $opt = null)
    {
        if (is_object($opt) && get_class($opt) == 'DooFindOpt') {
            $opt = $opt->getOptions();
        }
        return $this->db()->relateExpand($this, $rmodel, $opt);
    }

    //--------------- restore ----------
    public static function __set_state($arrayValues)
    {
        //$obj = new self::$className;
        $obj = new static();
        foreach ($arrayValues as $key => $value) {
            $obj->{$key} = $value;
        }
        return $obj;
    }

    //---- queries that clear the cache ----

    /**
     * Adds a new record. (Prepares and execute the INSERT statements)
     * @return int The inserted record's Id
     */
    public function insert()
    {
        if (!empty($this->cacheMode)) {
            $this->purgeCache();
        }
        return $this->db()->insert($this);
    }

    /**
     * Adds a new record with a list of keys & values (assoc array) (Prepares and execute the INSERT statements)
     * @param array $data Array of data (keys and values) to be insert
     * @return int The inserted record's Id
     */
    public function insertAttributes($data)
    {
        if (!empty($this->cacheMode)) {
            $this->purgeCache();
        }
        return $this->db()->insertAttributes($this, $data);
    }

    /**
     * Adds a new record with its associated models. Relational insert. (Prepares and execute the INSERT statements)
     * @param array $rmodels A list of associated model objects to be insert along with the main model.
     * @return int The inserted record's Id
     */
    public function relatedInsert($rmodels)
    {
        if (!empty($this->cacheMode)) {
            $this->purgeCache($rmodels);
        }
        return $this->db()->relatedInsert($this, $rmodels);
    }

    /**
     * Update an existing record. (Prepares and execute the UPDATE statements)
     * @param array $opt Associative array of options to generate the UPDATE statement. Supported: <i>where, limit, field, param</i>
     * @return int Number of rows affected
     */
    public function update($opt = null)
    {
        if (is_object($opt) && get_class($opt) == 'DooUpdateOpt') {
            $opt = $opt->getOptions();
        }

        if (!empty($this->cacheMode)) {
            $this->purgeCache();
        }
        return $this->db()->update($this, $opt);
    }

    /**
     * Update an existing record with a list of keys & values (assoc array). (Prepares and execute the UPDATE statements)
     * @param array $opt Associative array of options to generate the UPDATE statement. Supported: <i>where, limit, field, param</i>
     * @return int Number of rows affected
     */
    public function updateAttributes($data, $opt = null)
    {
        if (is_object($opt) && get_class($opt) == 'DooUpdateOpt') {
            $opt = $opt->getOptions();
        }

        if (!empty($this->cacheMode)) {
            $this->purgeCache();
        }
        return $this->db()->updateAttributes($this, $data, $opt);
    }

    /**
     * Update an existing record with its associated models. Relational update. (Prepares and execute the UPDATE statements)
     * @param array $rmodels A list of associated model objects to be updated or insert along with the main model.
     * @param array $opt Assoc array of options to update the main model. Supported: <i>where, limit, field, param</i>
     */
    public function relatedUpdate($rmodels, $opt = null)
    {
        if (is_object($opt) && get_class($opt) == 'DooUpdateOpt') {
            $opt = $opt->getOptions();
        }

        if (!empty($this->cacheMode)) {
            $this->purgeCache($rmodels);
        }
        return $this->db()->relatedUpdate($this, $rmodels, $opt);
    }

    /**
     * Returns the last inserted record's id
     * @return int
     */
    public function lastInsertId()
    {
        return $this->db()->lastInsertId();
    }

    /**
     * Delete ALL existing records. (Prepares and executes the DELETE statement)
     */
    public function deleteAll()
    {
        if (!empty($this->cacheMode)) {
            $this->purgeCache();
        }
        return $this->db()->deleteAll($this);
    }

    /**
     * Delete an existing record. (Prepares and execute the DELETE statements)
     * @param array $opt Associative array of options to generate the UPDATE statement. Supported: <i>where, limit, param</i>
     */
    public function delete($opt = null)
    {
        if (is_object($opt) && get_class($opt) == 'DooUpdateOpt') {
            $opt = $opt->getOptions();
        }

        if (!empty($this->cacheMode)) {
            $this->purgeCache();
        }
        return $this->db()->delete($this, $opt);
    }

    public function asyncInsert($callback, $errorCallback)
    {
        if ($this->app->isJVM === false) {
            try {
                $id = $this->insert();
                $callback(['updated' => 1, 'keys' => [$id]]);
            } catch (Exception $exception) {
                $errorCallback($exception->getCode(), $exception->getMessage(), $exception);
            }
        } else {
            $this->db()->disableDbOperation();
            $sql = $this->insert();
            //execute sql with async library on vertx, then return the same results
//            $callback(['updated' => 1, 'keys' => [$id]]);

        }
    }

    public function asyncUpdate($callback, $errorCallback = null, $opt = null)
    {
        if ($this->app->isJVM === false) {
            try {
                $res = $this->update($opt);
                $callback(['updated' => 1, 'keys' => [$res]]);
            } catch (Exception $e) {
                $errorCallback($e->getCode(), $e->getMessage(), $e);
            }
        } else {
            $this->db()->disableDbOperation();
            $sql = $this->update($opt);
            //execute sql with async library on vertx, then return the same results, first item in query result
//            $callback($res[0]);
        }
    }

    public function asyncDelete($callback, $errorCallback = null, $opt = null)
    {
        if ($this->app->isJVM === false) {
            try {
                $res = $this->delete($opt);
                $callback(['updated' => 1, 'keys' => [$res]]);
            } catch (Exception $e) {
                $errorCallback($e->getCode(), $e->getMessage(), $e);
            }
        } else {
            $this->db()->disableDbOperation();
            $sql = $this->delete($opt);
            //execute sql with async library on vertx, then return the same results, first item in query result
//            $callback($res[0]);
        }
    }

    public function asyncFind($callback, $errorCallback = null, $opt = null)
    {
        if ($this->app->isJVM === false) {
            try {
                $res = $this->find($opt);
                $callback($res);
            } catch (Exception $e) {
                $errorCallback($e->getCode(), $e->getMessage(), $e);
            }
        } else {
            $this->db()->disableDbOperation();
            $sql = $this->find($opt);
            //execute sql with async library on vertx, then return the same results, first item in query result
//            $callback($res[0]);
        }
    }

    public function asyncGetOne($callback, $errorCallback = null, $opt = null)
    {
        if ($this->app->isJVM === false) {
            try {
                $res = $this->getOne($opt);
                $callback($res);
            } catch (Exception $e) {
                $errorCallback($e->getCode(), $e->getMessage(), $e);
            }
        } else {
            $this->db()->disableDbOperation();
            $sql = $this->getOne();
            //execute sql with async library on vertx, then return the same results, first item in query result
//            $callback($res[0]);
        }
    }

    /**
     * Execute a query to the connected database
     * @param string $query SQL query prepared statement
     * @param array $param Values used in the prepared SQL
     * @return PDOStatement
     */
    public function asyncQuery($query, $param = null, $callback = null, $errorCallback = null)
    {
        try {
            $stmt = $this->db()->query($query, $param);
            if ($callback) {
//                $res = $stmt->fetchObject(PrepaidTopup::class, [$this->repoPrepaid->db(), $this->app]);
//                $res = $stmt->fetchAll(\PDO::FETCH_CLASS, PrepaidTopup::class, [$this->repoPrepaid->db(), $this->app]);

                $stmt->setFetchMode(\PDO::FETCH_ASSOC);
                $res = $stmt->fetchAll();
                $callback($res);
            }
        }
        catch (\Exception $e) {
            $errorCallback($e->getCode(), $e->getMessage(), $e);
        }

    }

    public function asyncExec($query, $param = null, $callback = null, $errorCallback = null, $fetch = false)
    {
        try {
            $stmt = $this->db()->exec($query, $param);
            if ($callback) {
                if ($fetch) {
                    $stmt->setFetchMode(\PDO::FETCH_ASSOC);
                    $res = $stmt->fetchAll();
                    $callback($res);
                } else {
                    $callback($stmt);
                }
            }
        }
        catch (\Exception $e) {
            $errorCallback($e->getCode(), $e->getMessage(), $e);
        }

    }
}
