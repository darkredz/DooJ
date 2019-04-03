<?php
/**
 * DooMongoModel class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2011 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */

/**
 * DooMongoModel is the base class for all model classes that uses MongoDB which provides useful querying methods.
 *
 * <p>To create a model for MongoDB:</p>
 * <code>
 * class User extends DooMongoModel {
 *    public function __construct(){
 *        //database name, collection name
 *        parent::__construct('mydb', 'user');
 *    }
 * }
 * </code>
 *
 * <p>Querying:</p>
 * <code>
 * $u = new User;
 * $result = $u->findOne( array('username' => 'johnny') );
 * </code>
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @package doo.db.mongo
 * @since 2.0
 */
class DooMongoModel
{

    /**
     * Mongo Connection
     * @var DooMongo
     */
    protected $connection;

    /**
     * Database object
     * @var MongoDB
     */
    protected $db;

    /**
     * Collection of the model
     * @var MongoCollection
     */
    protected $collection;

    /**
     * Name of the Collection
     * @var string
     */
    protected $collectionName;

    /**
     * Name of the database
     * @var string
     */
    protected $dbName;

    /**
     * Constructor of DooMongoModel
     * @param string $dbName Name of the database
     * @param string $collectionName Name of the collection
     * @param DooMongo $mongo Mongo connection instance
     */
    public function __construct($dbName, $collectionName, $mongo = null)
    {
        $this->dbName = $dbName;
        $this->collectionName = $collectionName;
        if ($mongo === null) {
            $this->connection = DooMongo::getInstance(); //Doo::conf()->mongodb;
        } else {
            $this->connection = $mongo;
        }

        $this->collection = $this->connection->selectCollection($dbName, $collectionName);
        $this->db = $this->connection->selectDB($dbName);

        //set debugging details;
        if ($this->connection->isDebugEnabled() === true) {
            if ($this->db->getProfilingLevel() < 1) {
                $this->db->setProfilingLevel($this->connection->getProfilingLevel());
            }
        }
    }

    public function __toString()
    {
        return (string)$this->collection;
    }

    /**
     * Sanitize keys in query
     * @param array $doc
     * @param bool $removeDollar Whether to remove dollar sign. Default is false
     * @return array
     */
    public static function _sanitize($doc, $removeDollar = false)
    {
        if (!is_array($doc)) {
            return $doc;
        }

        $indexes = [];

        foreach ($doc as $key => $value) {
            if (is_string($key)) {
                if (!$removeDollar) {
                    $key = str_replace(chr(0), '', $key);
                } else {
                    $key = str_replace(['$', chr(0)], '', $key);
                }
            }
            $indexes[$key] = $value;
        }

        foreach ($indexes as $key => $value) {
            if (is_array($value)) {
                $indexes[$key] = self::_sanitize($value, $removeDollar);
            }
        }
        return $indexes;
    }

    /**
     * Sanitize keys in query
     * @param array $doc
     * @param bool $removeDollar Whether to remove dollar sign. Default is false
     * @return array
     */
    public function sanitize($doc, $removeDollar = false)
    {
        return self::_sanitize($doc, $removeDollar);
    }

    /**
     * Prepare a document array with a specific list of fields.
     * <code>
     * $fields = array('id'=>'_id', 'desc'=>'string', 'amount'=>'float', 'date_create'=>'date', 'unit'=>'int', 'category');
     * print_r( $t->prepareDoc($_POST, $fields) );
     * </code>
     * @param array $postedData Data to be prepared
     * @param array $fieldsToUse Fields to use
     * @param bool $trim If true, values in $postedData will be trim
     * @return array Prepared document
     */
    public function prepareDoc($postedData, $fieldsToUse = null, $trim = true)
    {
        foreach ($postedData as $k => $v) {
            if (empty($fieldsToUse) === true) {
                if ($trim === true) {
                    $doc[$k] = trim($v);
                } else {
                    $doc[$k] = $v;
                }
            } else {
                if (in_array($k, $fieldsToUse) === true) {
                    if ($trim === true) {
                        $doc[$k] = trim($v);
                    } else {
                        $doc[$k] = $v;
                    }
                } else {
                    if (isset($fieldsToUse[$k]) === true) {
                        if ($trim === true) {
                            $v = trim($v);
                        }

                        if ($fieldsToUse[$k] == '_id' || $fieldsToUse[$k] == 'MongoId') {
                            $doc['_id'] = new MongoId($v);
                        } else {
                            if ($fieldsToUse[$k] == 'date' || $fieldsToUse[$k] == 'MongoDate') {
                                if (ctype_digit($v)) {
                                    $doc[$k] = new MongoDate($v);
                                } else {
                                    if (strtotime($v) > 0) {
                                        $doc[$k] = new MongoDate(strtotime($v));
                                    }
                                }
                            } else {
                                if ($fieldsToUse[$k] == 'float') {
                                    $doc[$k] = $v + 0.0;
                                } else {
                                    if ($fieldsToUse[$k] == 'int') {
                                        $doc[$k] = (int)$v;
                                    } else {
                                        if ($fieldsToUse[$k] == 'string') {
                                            $doc[$k] = (string)$v;
                                        } else {
                                            if ($fieldsToUse[$k] == 'bool') {
                                                $doc[$k] = (bool)$v;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return $doc;
    }

    /**
     * Update a document by its ID
     * @param array $doc Document array with MongoId '_id'
     * @return bool
     */
    public function updateDoc($doc)
    {
        if (!isset($doc['_id'])) {
            return false;
        }
        $id = $doc['_id'];
        unset($doc['_id']);
        return $this->update(['_id' => $id], ['$set' => $doc]);
    }

    /**
     * Set database object for the model
     * @param MongoDB $database
     */
    public function setDB($database)
    {
        $this->db = $database;
    }

    /**
     * Set collection object for the model
     * @param MongoCollection $database
     */
    public function setCollection($collection)
    {
        $this->collection = $collection;
    }

    /**
     * Returns the database object of the model
     * @return MongoDB
     */
    public function db()
    {
        return $this->db;
    }

    /**
     * Get database name
     * @return string
     */
    public function getDbName()
    {
        return $this->dbName;
    }

    /**
     * Get collection name
     * @return string
     */
    public function getCollectionName()
    {
        return $this->collectionName;
    }

    public function getDBProfilingData()
    {
        return $this->connection->getProfilingData([$this->dbName]);
    }

    /**
     * Returns the Mongo connection object
     * @return DooMongo
     */
    public function connection()
    {
        return $this->connection;
    }


    /**
     * Returns the collection object of the model
     * @return MongoCollection
     */
    public function collection()
    {
        return $this->collection;
    }

    /**
     * Find all documents in collection
     * @param array $options Options for the find query.
     * @param array $fields Fields of the results to return.
     * @param array $sort Sort by fields.
     * @param int $limit Number of results to limit
     * @param int $skip Number of results to skip. Use for paging
     * @return mixed Result of the query
     */
    public function findAll($options = [], $fields = [], $sort = null, $limit = null, $skip = null)
    {
        if ($options === null) {
            $options = [];
        }
        if ($fields === null) {
            $fields = [];
        }

        $cursor = $this->collection->find($options, $fields);

        if ($sort !== null) {
            $cursor->sort($sort);
        }

        if ($limit !== null) {
            $cursor->limit($limit);
        }

        if ($skip !== null) {
            $cursor->skip($skip);
        }

        $result = null;
        foreach ($cursor as $r) {
            $result[] = $r;
        }
        return $result;
    }

    /**
     * Find documents in collection
     * @param array $options Options for the find query.
     * @param array $fields Fields of the results to return.
     * @return mixed Result of the query
     */
    public function find($options = [], $fields = [])
    {
        if ($options === null) {
            $options = [];
        } else {
            $options = self::_sanitize($options);
        }

        if ($fields === null) {
            $fields = [];
        }
        return $this->collection->find($options, $fields);
    }

    /**
     * Querys the model's collection, returning a single element
     * @param array $options Options for the find query.
     * @param array $fields Fields of the results to return.
     * @return array|null Returns record matching the search or NULL.
     */
    public function findOne($options = [], $fields = [])
    {
        if ($options === null) {
            $options = [];
        } else {
            $options = self::_sanitize($options);
        }

        if ($fields === null) {
            $fields = [];
        }
        return $this->collection->findOne($options, $fields);
    }

    /**
     * Counts the number of documents in the model's collection
     * @param array $options Options for the count query.
     * @param array $limit Specifies an upper limit to the number returned
     * @param array $skip Specifies a number of results to skip before starting the count.
     * @return int Returns the number of documents matching the query.
     */
    public function count($query = [], $limit = 0, $skip = 0)
    {
        if ($query === null) {
            $query = [];
        } else {
            $query = self::_sanitize($query);
        }

        return $this->collection->count($query, $limit, $skip);
    }

    /**
     * Insert a document into the model's collection
     * @param array $document Array of data fields of the document
     * @param array $options Options for the insert see @link http://www.php.net/manual/en/mongocollection.insert.php
     * @param bool $returnId return the inserted document mongo id
     * @return mixed If safe was set, returns an array containing the status of the insert. Otherwise, returns a boolean representing if the array was not empty (an empty array will not be inserted).
     * Throws MongoCursorException if the "safe" option is set and the save fails. Throws MongoCursorTimeoutException if the "safe" option is set and the operation takes longer than MongoCursor::$timeout milliseconds to complete. This does not kill the operation on the server, it is a client-side timeout.
     */
    public function insert($document, $options = [], $returnId = true)
    {
        if ($options === null) {
            $options = [];
        } else {
            $options = self::_sanitize($options);
        }

        $document = self::_sanitize($document);

        if ($returnId) {
            $this->collection->insert($document, $options);
            return $document['_id'];
        }
        return $this->collection->insert($document, $options);
    }

    /**
     * Adds value to the array only if its not in the array already, if field is an existing array, otherwise sets field to the array value if field is not present.
     * @param array $field Field to update
     * @param mixed $value Value to be saved
     * @param array $where Options for the update query
     * @return boolean Returns true if the update was successfully sent to the database.
     */
    public function addToSet($field, $value, $where = [])
    {
        if ($where === null) {
            $where = [];
        } else {
            $where = self::_sanitize($where);
        }

        $field = self::_sanitize($field);
        $value = self::_sanitize($value);

        return $this->update($where, ['$addToSet' => [$field => $value]]);
    }

    /**
     * Deletes a document from the model's collection
     * @param array $criteria Description of records to remove.
     * @param array $options Options for remove. see @link http://www.php.net/manual/en/mongocollection.remove.php
     * @return mixed If "safe" is set, returns an associative array with the status of the remove ("ok"), the number of items removed ("n"), and any error that may have occured ("err"). Otherwise, returns TRUE if the remove was successfully sent, FALSE otherwise.
     * Throws MongoCursorException if the "safe" option is set and the remove fails. Throws MongoCursorTimeoutException if the "safe" option is set and the operation takes longer than MongoCursor::$timeout milliseconds to complete. This does not kill the operation on the server, it is a client-side timeout.
     */
    public function remove($criteria, $options = [])
    {
        if ($options === null) {
            $options = [];
        }
        $criteria = self::_sanitize($criteria);
        return $this->collection->remove($criteria, $options);
    }

    /**
     * Saves a document into the model's collection. If the object exists, update the existing database object, otherwise insert this object.
     * @param array $document Array of data fields of the document
     * @param array $options Options for the insert see @link http://www.php.net/manual/en/mongocollection.save.php
     * @param bool $returnId return the saved document mongo id
     * @return mixed If safe was set, returns an array containing the status of the save. Otherwise, returns a boolean representing if the array was not empty (an empty array will not be inserted).
     * Throws MongoCursorException if the "safe" option is set and the save fails. Throws MongoCursorTimeoutException if the "safe" option is set and the operation takes longer than MongoCursor::$timeout milliseconds to complete. This does not kill the operation on the server, it is a client-side timeout.
     */
    public function save($document, $options = [], $returnId = true)
    {
        if ($options === null) {
            $options = [];
        }

        $document = self::_sanitize($document);

        if ($returnId) {
            $this->collection->save($document, $options);
            return $document['_id'];
        }
        return $this->collection->save($document, $options);
    }

    /**
     * Update documents in collection based on a given criteria
     * @param array $criteria Array of data fields of the document to be update
     * @param array $newobj The object with which to update the matching records.
     * @param array $options Options for the update operation. see @link http://www.php.net/manual/en/mongocollection.update.php
     * @return boolean Returns true if the update was successfully sent to the database.
     */
    public function update($criteria, $newobj = [], $options = [])
    {
        if ($options === null) {
            $options = [];
        }
        if ($newobj === null) {
            $newobj = [];
        } else {
            $newobj = self::_sanitize($newobj);
        }

        $criteria = self::_sanitize($criteria);

        return $this->collection->update($criteria, $newobj, $options);
    }

    /**
     * Get a list with distinct field
     * @param string $distinctField The unique key to be queried
     * @param array $query Query options (optional)
     * @return mixed
     */
    public function distinct($distinctField, $query = null)
    {
        $cmd = ['distinct' => $this->collectionName, 'key' => $distinctField];
        if ($query !== null) {
            $cmd['query'] = $query;
        }

        $rs = $this->db()->command($cmd);
        if (empty($rs['ok']) === false) {
            return $rs['values'];
        }
    }

    /**
     * Execute Map reduce command
     * @param string $mapJs JS code for the map function. (you can specify a JS file to be loaded in, eg. user.map.js)
     * @param string $reduceJs JS code for the reduce function. (you can specify a JS file to be loaded in, eg. user.map.js)
     * @param array $query Query option. (optional)
     * @return MongoCollection Returns the map reduce collection
     */
    public function runMapReduce(
        $mapJs,
        $reduceJs,
        $out,
        $query = null,
        $limit = null,
        $sort = null,
        $finalizeJs = null
    )
    {
        $jsNames = [];
        if (substr($mapJs, -3) === '.js') {
            $jsNames[] = $mapJs;
            $mapJs = file_get_contents(Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . 'model/mongojs/' . $mapJs);
        }
        if (substr($reduceJs, -3) === '.js') {
            $jsNames[] = $reduceJs;
            $reduceJs = file_get_contents(Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . 'model/mongojs/' . $reduceJs);
        }
        if ($finalizeJs !== null) {
            if (substr($finalizeJs, -3) === '.js') {
                $jsNames[] = $finalizeJs;
                $finalizeJs = file_get_contents(Doo::conf()->SITE_PATH . Doo::conf()->PROTECTED_FOLDER . 'model/mongojs/' . $finalizeJs);
            } else {
                $finalizeJs = new MongoCode($finalizeJs);
            }
        }

        $map = new MongoCode($mapJs);
        $reduce = new MongoCode($reduceJs);

        $cmd = [
            "mapreduce" => $this->collectionName,
            "map" => $map,
            "reduce" => $reduce,
            "out" => $out,
        ];
        if ($limit !== null) {
            $cmd['limit'] = $limit;
        }

        if ($sort !== null) {
            $cmd['sort'] = $sort;
        }

        if (!empty($finalizeJs)) {
            $cmd['finalize'] = $finalizeJs;
        }

        if ($query !== null) {
            $query = self::_sanitize($query);
            $cmd['query'] = $query;
        }

        $rs = $this->db()->command($cmd);

        if (isset($rs['result']) === true || isset($rs['results']) === true) {
            return $rs;
        }

        return false;
    }

    public function __get($name)
    {
        switch ($name) {
            case 'collection':
                return $this->collection;
            case 'db':
                return $this->db;
        }
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([&$this->collection, $name], $arguments);
    }

    /**
     * Retrieve MongoId object as string
     * @param array $result A single or a list of mongo result array
     * @return array|string Returns the ID/a list of IDs
     */
    public function getStringId($result)
    {
        if (isset($result[0])) {
            foreach ($result as $v) {
                $return[] = "{$v['_id']}";
            }
            return $return;
        } elseif (isset($result['_id'])) {
            return "{$result['_id']}";
        }
    }

}
