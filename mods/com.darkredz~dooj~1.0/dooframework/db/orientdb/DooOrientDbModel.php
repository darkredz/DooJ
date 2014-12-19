<?php

/**
 * DooOrientDbModel class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */


/**
 * DooOrientDbModel wraps around OrientDB Document database API to provide more convenient methods to handle DB operations.
 * Every model class should extend DooOrientDbModel to get the best out of OODB with OrientDB
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @package doo.db.orientdb
 * @since 2.0
 */
//
//import com.orientechnologies.orient.object.db.OObjectDatabaseTx;
//import com.orientechnologies.orient.core.db.document.ODatabaseDocumentTx;
//import com.orientechnologies.orient.core.record.impl.ODocument;
//import com.orientechnologies.orient.core.sql.query.OSQLSynchQuery;
//import com.orientechnologies.orient.core.sql.query.OSQLAsynchQuery;
//import com.orientechnologies.orient.core.db.document.ODatabaseDocumentPool;
//import com.orientechnologies.orient.core.metadata.schema.OType;
//import com.orientechnologies.orient.core.command.OCommandResultListener;
//import com.orientechnologies.orient.core.id.ORecordId;
//import com.orientechnologies.orient.core.sql.OCommandSQL;
//import com.orientechnologies.orient.core.intent.OIntentMassiveInsert;
//import com.orientechnologies.orient.core.storage.OStorage;
//import com.orientechnologies.orient.core.tx.OTransaction;
//
//import com.doophp.db.orientdb.AsyncQueryCallback;
//import com.doophp.db.orientdb.QueryExecutor;
//import com.doophp.db.orientdb.Transaction;

class DooOrientDbModel{

    const TYPE_BOOLEAN       = OType::BOOLEAN;
    const TYPE_INTEGER       = OType::INTEGER;
    const TYPE_SHORT         = OType::SHORT;
    const TYPE_LONG          = OType::LONG;
    const TYPE_FLOAT         = OType::FLOAT;
    const TYPE_DOUBLE        = OType::DOUBLE;
    const TYPE_DATETIME      = OType::DATETIME;
    const TYPE_STRING        = OType::STRING;
    const TYPE_BINARY        = OType::BINARY;
    const TYPE_EMBEDDED      = OType::EMBEDDED;
    const TYPE_EMBEDDEDLIST  = OType::EMBEDDEDLIST;
    const TYPE_EMBEDDEDSET   = OType::EMBEDDEDSET;
    const TYPE_EMBEDDEDMAP   = OType::EMBEDDEDMAP;
    const TYPE_LINK          = OType::LINK;
    const TYPE_LINKLIST      = OType::LINKLIST;
    const TYPE_LINKSET       = OType::LINKSET;
    const TYPE_LINKMAP       = OType::LINKMAP;
    const TYPE_BYTE          = OType::BYTE;
    const TYPE_TRANSIENT     = OType::TRANSIENT;
    const TYPE_DATE          = OType::DATE;
    const TYPE_CUSTOM        = OType::CUSTOM;
    const TYPE_DECIMAL       = OType::DECIMAL;

    const TXTYPE_OPTIMISTIC  = 'OPTIMISTIC';
    const TXTYPE_NOTX        = 'NOTX';
    const TXTYPE_PESSIMISTIC  = 'PESSIMISTIC';

    /**
     * PHP object type to LINK by default 1:1
     */
    const TYPE_OBJECT     = OType::LINK;

    /**
     * PHP array type to LINK by default 1:M
     */
    const TYPE_ARRAY     = OType::LINKLIST;


    protected $_class;
    protected $_field;
    protected $_fieldType;
    protected $_db;
    protected $_doc;
    protected $_namespace = true;
    protected $_classMap;
    protected $_debug;
    protected $_useCluster;

    public $rid;

    public function __construct($ORecordId=null){
        //if ORecordId not found, create new class
        if($this->_class!=null && ($ORecordId==null || $ORecordId=='#-1:-1' || $ORecordId=='-1:-1')){
            $this->_doc = new ODocument($this->_class);
        }
        else{
            if(gettype($ORecordId)=='string'){
                if($this->validateId($ORecordId)){
                    $this->_doc = new ODocument(new ORecordId($ORecordId));
                    $this->rid = $this->idStr();
                }
                else{
                    $this->_doc = new ODocument($this->_class);
                }
            }
            else if($ORecordId!=null){
                $this->_doc = new ODocument($ORecordId);
                $this->rid = $this->idStr();
            }
        }
    }

    public function setId($ORecordId){
        if(gettype($ORecordId)=='string'){
            if($this->validateId($ORecordId)){
                $this->_doc->setIdentity(new ORecordId($ORecordId));
                $this->rid = $this->idStr();
            }
            else{
                $this->_doc->setIdentity(new ORecordId(null));
                $this->rid = null;
            }
        }
        else if($ORecordId!=null){
            $this->_doc->setIdentity($ORecordId);
        }
        else{
            $this->_doc->setIdentity(new ORecordId(null));
            $this->rid = null;
        }
    }

    public static function _validateId($ORecordId){
        return strpos($ORecordId, ':') !== false && ctype_digit(str_replace(['#',':'], '', $ORecordId));
    }

    public function validateId($ORecordId){
        return self::_validateId($ORecordId);
    }

    public function useCluster($clusterName){
        $this->_useCluster = $clusterName;
    }

    public function getClusterUsed($clusterName){
        return $this->_useCluster;
    }

    public function setDebugMode($debug){
        $this->_debug = $debug;
    }

    public function getDebugMode(){
        return $this->_debug;
    }

    public function setFields($fieldVals, $strict = false){
        if($strict){
            $allFields = array_keys($this->_fieldType);

            foreach($fieldVals as $k=>$v){
                if(in_array($k, $allFields)){
                    $this->$k = $v;
                }
            }
        }
        else{
            foreach($fieldVals as $k=>$v){
                $this->$k = $v;
            }
        }
    }

    public function setClassMap($classMap){
        $this->_classMap = $classMap;
    }

    public function setClassName($classname){
        $this->_class = $classname;
    }

    public function classMap(){
        return $this->classMap;
    }

    public function setDoc($oDoc){
        $this->_doc = $oDoc;
    }

    public function doc(){
        return $this->_doc;
    }

    public static function connectDb($host, $username, $password, $pooled=true){
        if($pooled==false){
            return new ODatabaseDocumentTx($host).open($username, $password);
        }
        return ODatabaseDocumentPool::global()->acquire($host, $username, $password);
    }

    public static function _docToObj($oDoc, $class, $classMap=null){
        $cls = $oDoc->getClassName();

        $fields = $oDoc->fieldNames();
        $fieldVals = $oDoc->fieldValues();

        $obj = new $class();
        $obj->setClassMap($classMap);
        $obj->setDoc(null);

        for($i=0; $i < sizeof($fields); $i++ ){
            $fname = $fields[$i];
            $val = $fieldVals[$i];
            if($val!=null && is_object($val) && get_class($val) == "com.orientechnologies.orient.core.id.ORecordId"){
                continue;
            }
            else{
                $obj->$fname = $val;
            }
        }
        $obj->setDoc($oDoc);
        return $obj;
    }

    public function docToObj($oDoc){
        $class = get_class($this);
        return self::_docToObj($oDoc, $class, $this->_classMap);
    }

    public static function _schema(){}
    public function schema(){}

    public function getClassProperties($class=null){
        if($class==null){
            $class = $this->_class;
        }
        $classInfo = $this->_db->getMetadata()->getSchema()->getClass($class);
        if($classInfo!=null)
            return $classInfo->properties();
    }


    public function getClassSchema($class=null){
        if($class==null){
            $class = $this->_class;
        }
        $classInfo = $this->_db->getMetadata()->getSchema()->getClass($class);
        return $classInfo;
    }

    public function getClassesSchema(){
        return $this->_db->getMetadata()->getSchema()->getClasses();
    }

    public function db(){
        return $this->_db;
    }

    public function setDb($db){
        $this->_db = $db;
    }

    public function db(){
        return $this->_db;
    }

    public function getClass(){
        return $this->_class;
    }

    public function id(){
        return $this->_doc->getIdentity();
    }

    public function idStr(){
        return $this->_doc->getIdentity()->toString();
    }

    /**
     * Query database in sync mode.
     * @param string $sql SQL statement
     * @param array $params Associative array for prepared query if need. example: $params['name'] = 'leng';  select from user where name = :name
     * @param string $fetchPlan set fetch plan if needed, eg. "*:-1" for all nested links
     * @return array Returns the result list, an array of ODocument
     */
    public function query($sql, $params=null, $fetchPlan=null){

        if($this->_debug){
            Vertx::logger()->debug('SQL: ' . $sql);
        }

        $query = new OSQLSynchQuery($sql);
        if($fetchPlan!=null){
            $query->setFetchPlan($fetchPlan);   //"*:-1"
        }

        if(empty($params)){
            return $this->_db->query($query);
        }

        $query = $this->_db->command($query);
        return QueryExecutor::execute($query, $params);
    }

    /**
     * Query database in async mode. Results are pass into callbacks. example:  $callback = new AsyncQueryCallback($rsFunc, $endFunc);
     * @param string $sql SQL statement
     * @param callable $rsFunc Result handler callback function which will be executed on every record result
     * @param callable $endFunc Callback function when the query is done
     * @param array $params Associative array for prepared query if need. example: $params['name'] = 'leng';  select from user where name = :name
     * @param string $fetchPlan set fetch plan if needed, eg. "*:-1" for all nested links
     */
    public function queryAsync($sql, $rsFunc, $endFunc, $params=null, $fetchPlan=null){
        $callback = new \AsyncQueryCallback($rsFunc, $endFunc);
        $query = new OSQLAsynchQuery($sql, $callback);

        if($fetchPlan!=null){
            $query->setFetchPlan($fetchPlan);   //"*:-1"
        }

        if(empty($params)){
            try{
                $this->_db->command($query)->execute();
            }
            catch(Exception $err){}
            return;
        }

        $query = $this->_db->command( $query );
        try{
            QueryExecutor::execute($query, $params);
        }
        catch(Exception $err){}
    }

    public function buildSql($queryParam){
        $params = [];
        $orderStr = '';
        $limitStr = '';
        $skipStr = '';
        $groupByStr = '';

        $select = $queryParam['_select'];

        foreach($queryParam as $field=>&$v){
            if(strpos($field, '_') === 0){
                unset($queryParam[$field]);

                switch($field){
                    case '_groupBy':
                        $groupByStr = " GROUP BY $v";
                        break;
                    case '_orderBy':
                        if(is_string($v)){
                            $orderStr = " ORDER BY $v";
                        }
                        else if(is_array($v)){
                            $allsort = [];
                            foreach($v as $orderField => $sortDir){
                                $allsort[] = $orderField .' '. $sortDir;
                            }
                            $orderStr = " ORDER BY ". implode(', ', $allsort);
                        }
                        break;

                    case '_orderByDesc':
                        if(is_string($v)){
                            $orderStr = " ORDER BY $v desc";
                        }
                        else if(is_array($v)){
                            $orderFields = implode(',', $v);
                            $orderStr = " ORDER BY $orderFields desc";
                        }
                        break;

                    case '_orderByAsc':
                        if(is_string($v)){
                            $orderStr = " ORDER BY $v asc";
                        }
                        else if(is_array($v)){
                            $orderFields = implode(',', $v);
                            $orderStr = " ORDER BY $orderFields asc";
                        }
                        break;

                    case '_limit':
                        if(!is_int($v))
                            $v = intval($v);
                        $limitStr = " LIMIT $v";
                        break;

                    case '_skip':
                        if(!is_int($v))
                            $v = intval($v);
                        $skipStr = " SKIP $v";
                        break;
                }
            }
            else if($field=='rid' || $field=='@rid'){
                $field = 'rid';
                $params[] = "AND @rid = :$field";

                if(is_string($v) && !($v!=null && is_object($v) && get_class($v) == "com.orientechnologies.orient.core.id.ORecordId")){
                    if($this->validateId($v)){
                        $v = new ORecordId($v);
                    }
                    else{
                        $v = new ORecordId('#-1:-1');
                    }
                }
            }
            else{
                $andOr = 'and';
                $equal = '=';

                if(stripos($field, 'AND ') === 0){
                    $andOr = 'AND';
                }
                else if(stripos($field, 'OR ') === 0){
                    $andOr = 'OR';
                }

                $newFieldName = $oriField = $field;

                //if is special query, and name, or name, and name LIKE, etc.
                if($andOr!=='and'){
                    $fparts = explode(' ', $field, 3);

                    $queryParam[ $fparts[1] ] = $v;
                    unset($queryParam[$field]);

                    //queries : LIKE, IS NOT NULL etc.
                    if(sizeof($fparts) > 2){
                        $equal = strtoupper( $fparts[2] );
                        $oriField = $field = $fparts[1];
                        $newFieldName = $field;

                        if($equal == 'LIKE'){
                            $field .= '.toLowerCase()';
                        }
                        else if($equal == 'IS NULL'){
                            unset($queryParam[$field]);
                            $params[] = "$andOr $field $equal";
                            continue;
                        }
                        else if($equal == 'IS NOT NULL'){
                            unset($queryParam[$field]);
                            $params[] = "$andOr $field $equal";
                            continue;
                        }
                    }
                    else{
                        $field = $fparts[1];
                        $newFieldName = $field;
                    }
                }
                else{
                    $andOr = 'AND';
                }

                //replace . when using parameterized query
                if(strpos($field, '.') !== false){
                    $newFieldName = str_replace('.', '0', $oriField);

                    //replace the original parameter field with the new one
                    $queryParam[ $newFieldName ] = $v;
                    unset($queryParam[$oriField]);

                    $params[] = "$andOr $field $equal :$newFieldName";
                }
                else{
                    $params[] = "$andOr $field $equal :$newFieldName";
                }
            }
        }
        
        if(!empty($params)){
//            $paramStr = implode(' AND ', $params);
            $paramStr = implode(' ', $params);

            if(strtolower(substr($paramStr, 0, 4)) == 'and '){
                $paramStr = substr($paramStr, 4);
            }
            else if(strtolower(substr($paramStr, 0, 3)) == 'or '){
                $paramStr = substr($paramStr, 3);
            }

            $paramStr = " WHERE $paramStr";
        }else{
            $paramStr = '';
        }

        //if cluster is set, use cluster to select
        $class = (empty($this->_useCluster)) ? $this->_class : 'cluster:' . $this->_useCluster;

        if(is_array($select)){
            $sql = [];
            foreach($select as $field => $realField){
                if($field == $realField || $realField === null || $realField === ''){
                    $sql[] = $field;
                }else{
                    $sql[] = "$realField as $field";
                }
            }
            $select = implode(',', $sql);
        }

        if($select){
            $sql = "SELECT $select FROM $class". $paramStr . $groupByStr . $orderStr . $skipStr . $limitStr;
        }
        else{
            $sql = "SELECT FROM $class". $paramStr . $groupByStr . $orderStr . $skipStr . $limitStr;
        }

        return $sql;
    }

    public function encodeId($id){
        return bin2hex($id);
    }

    public function decodeId($id){
        return hex2bin($id);
    }

    /**
     * Find records with an array of RIDs of a class
     * @param $idArr Array of RIDs
     * @return array
     */
    public function findWithIDs($idArr){
        $class = (empty($this->_useCluster)) ? $this->_class : 'cluster:' . $this->_useCluster;
        return $this->find('SELECT FROM '. $class .' WHERE @rid IN ['. implode(',', $idArr) .']');
    }

    public function find($query=null, $fetchPlan="*:-1", $docToObj=true){
        if(is_string($query)){
            $rs = $this->query($query, null, $fetchPlan);
        }
        else if(is_array($query)){

            $sql = $this->buildSql(&$query);

            if($this->_debug && !empty($query)){
                $queryFlat = [];
                foreach($query as $key => $val){
                    if($val!=null && is_object($val) && get_class($val) == "com.orientechnologies.orient.core.id.ORecordId"){
                        $val = $val->toString();
                    }
                    $queryFlat[$key] = $val;
                }
                Vertx::logger()->debug('SQL params: ' . var_export($queryFlat, true));
            }

            $rs = $this->query( $sql, $query, $fetchPlan );
        }
        else if($query==null){
            $rs = $this->query('select from '. $this->_class, null, $fetchPlan);
        }

        if($docToObj && !empty($rs)){
            for($i=0; $i < sizeof($rs); $i++){
                $rs[$i] = $this->docToObj($rs[$i]);
            }
        }

        return $rs;
    }

    public function fetchOneAsync($query, $callback, $fetchPlan="*:-1") {
        $rs = [];
        $this->findAsync($query, function($itm, $count) use (&$rs) {
            $rs[] = $itm;
            return true;
        },
        function($count) use ( &$rs, $callback){
            if (!empty($rs)) {
                $callback($rs[0]);
            }
            else{
                $callback(null);
            }
        });
    }

    public function fetchAsync($query, $callback, $fetchPlan="*:-1") {
        $rs = [];
        $this->findAsync($query, function($itm, $count) use (&$rs) {
            $rs[] = $itm;
            return true;
        },
        function($count) use (&$rs, $callback){
            $callback($rs);
        });
    }

    public function findAsync($query, $rsFunc, $endFunc, $fetchPlan="*:-1"){
        if(is_string($query)){

            if($this->_debug){
                Vertx::logger()->debug('SQL: ' . $query);
            }

            $this->queryAsync($query, $rsFunc, $endFunc, null, $fetchPlan);
        }
        else if(is_array($query)){

            $sql = $this->buildSql(&$query);

            if($this->_debug && !empty($query)){
                $queryFlat = [];
                foreach($query as $key => $val){
                    if($val!=null && is_object($val) && get_class($val) == "com.orientechnologies.orient.core.id.ORecordId"){
                        $val = $val->toString();
                    }
                    $queryFlat[$key] = $val;
                }
                Vertx::logger()->debug('SQL params: ' . var_export($queryFlat, true));
            }

            if($this->_debug){
                Vertx::logger()->debug('SQL: ' . $sql);
            }

            $this->queryAsync($sql, $rsFunc, $endFunc, $query, $fetchPlan);
        }
        else if($query==null){
            $this->queryAsync('select from '. $this->_class, $rsFunc, $endFunc, null, $fetchPlan);
        }
    }

    /**
     * @param string|array $query SQL string or an array of field parameters with its values which will form AND condition sql.
     * @param string $fetchPlan By default fetch all nested "*:-1"
     * @param bool $docToObj
     * @return DooOrientDbModel
     */
    public function findOne($query=null, $fetchPlan="*:-1", $docToObj=true){
        //if empty, search by ID
        if(empty($query)){
            $id = $this->idStr();
            $params = ['rid' => $id];
            $sql = $this->buildSql($params);

            if($this->_debug && !empty($params)){
                $paramsFlat = [];
                foreach($params as $key => $val){
                    if($val!=null && is_object($val) && get_class($val) == "com.orientechnologies.orient.core.id.ORecordId"){
                        $val = $val->toString();
                    }
                    $queryFlat[$key] = $val;
                }
                Vertx::logger()->debug('SQL params: ' . var_export($paramsFlat, true));
            }

            $rs = $this->query($sql, $params, $fetchPlan);
//            $rs = $this->query("select from ". $id, null, $fetchPlan);
        }
        else{
            if(is_string($query)){
                $rs = $this->query($query, null, $fetchPlan);
            }
            else if(is_array($query)){

                $sql = $this->buildSql(&$query);

                if($this->_debug && !empty($query)){
                    $queryFlat = [];
                    foreach($query as $key => $val){
                        if($val!=null && is_object($val) && get_class($val) == "com.orientechnologies.orient.core.id.ORecordId"){
                            $val = $val->toString();
                        }
                        $queryFlat[$key] = $val;
                    }
                    Vertx::logger()->debug('SQL params: ' . var_export($queryFlat, true));
                }

                $rs = $this->query( $sql, $query, $fetchPlan );
            }
        }

        if(empty($rs)){
            return null;
        }

        if($docToObj){
            return $this->docToObj($rs[0]);
        }
        return $rs[0];
    }

    /**
     * @param bool $recursive
     * @param bool $withRid
     * @param bool $removeNullField Remove fields with null value from JSON string.
     * @param array $exceptField Remove fields that are null except the ones in this list.
     * @param array $mustRemoveFieldList Remove fields in this list.
     */
    public function asJson($recursive=true, $withRid=true, $encodeRid=false, $removeNullField=false, $exceptField=null, $mustRemoveFieldList=null){
        $opt = [];
        if($recursive){
            $opt[] = "fetchPlan:*:-1";
        }
        if($withRid){
            $opt[] = "rid";
        }

        $rs = $this->toJSON(implode(',', $opt));

        if($encodeRid){
            $convertRidRegex = function ($matches) use ($encodeRid){
                if($encodeRid===true){
                    return '"@rid":"'. $this->encodeId($matches[1]) . '"';
                }
                else{
                    return '"'. $encodeRid .'":"'. $this->encodeId($matches[1]) . '"';
                }
            };
            $rs = preg_replace_callback('/\"\@rid\"\:\"(.*)\"/U', $convertRidRegex, $rs);
        }

        if($removeNullField){
            if($exceptField===null)
                $rs = preg_replace(array('/\,\"[^\"]+\"\:null/U', '/\{\"[^\"]+\"\:null\,/U'), array('','{'), $rs);
            else if(is_array($exceptField)){
                $funca1 =  create_function('$matches',
                    'if(in_array($matches[1], array(\''. implode("','",$exceptField) .'\'))===false){
                        return "";
                    }
                    return $matches[0];');

                $funca2 =  create_function('$matches',
                    'if(in_array($matches[1], array(\''. implode("','",$exceptField) .'\'))===false){
                        return "{";
                    }
                    return $matches[0];');

                $rs = preg_replace_callback('/\,\"([^\"]+)\"\:null/U', $funca1, $rs);
                $rs = preg_replace_callback('/\{\"([^\"]+)\"\:null\,/U', $funca2, $rs);
            }
        }

        //remove fields in this array
        if($mustRemoveFieldList!==null){
            $funcb1 =  create_function('$matches',
                'if(in_array($matches[1], array(\''. implode("','",$mustRemoveFieldList) .'\'))){
                    return "";
                }
                return $matches[0];');

            $funcb2 =  create_function('$matches',
                'if(in_array($matches[1], array(\''. implode("','",$mustRemoveFieldList) .'\'))){
                    return "{";
                }
                return $matches[0];');

            $rs = preg_replace_callback(array('/\,\"([^\"]+)\"\:\".*\"/U', '/\,\"([^\"]+)\"\:\{.*\}/U', '/\,\"([^\"]+)\"\:\[.*\]/U', '/\,\"([^\"]+)\"\:([false|true|0-9|\.\-|null]+)/'), $funcb1, $rs);

            $rs = preg_replace_callback(array('/\{\"([^\"]+)\"\:\".*\"\,/U','/\{\"([^\"]+)\"\:\{.*\}\,/U'), $funcb2, $rs);

            preg_match('/(.*)(\[\{.*)\"('. implode('|',$mustRemoveFieldList) .')\"\:\[(.*)/', $rs, $m);

            if($m){
                if( $pos = strpos($m[4], '"}],"') ){
                    if($pos2 = strpos($m[4], '"}]},{')){
                        $d = substr($m[4], $pos2+5);
                        if(substr($m[2],-1)==','){
                            $m[2] = substr_replace($m[2], '},', -1);
                        }
                    }
                    else if(strpos($m[4], ']},{')!==false){
                        $d = substr($m[4], strpos($m[4], ']},{')+3);
                        if(substr($m[2],-1)==','){
                            $m[2] = substr_replace($m[2], '},', -1);
                        }
                    }
                    else if(strpos($m[4], '],"')===0){
                        $d = substr($m[4], strpos($m[4], '],"')+2);
                    }
                    else if(strpos($m[4], '}],"')!==false){
                        $d = substr($m[4], strpos($m[4], '],"')+2);
                    }
                    else{
                        $d = substr($m[4], $pos+4);
                    }
                }
                else{
                    $rs = preg_replace('/(\[\{.*)\"('. implode('|',$mustRemoveFieldList) .')\"\:\[.*\]\}(\,)?/U', '$1}', $rs);
                    $rs = preg_replace('/(\".*\"\:\".*\")\,\}(\,)?/U', '$1}$2', $rs);
                }

                if(isset($d)){
                    $rs = $m[1].$m[2].$d;
                }
            }
        }

        return $rs;
    }

    public function asArray($recursive=true, $withRid=true, $encodeRid=false, $removeNullField=false, $exceptField=null, $mustRemoveFieldList=null){
        $rs = $this->asJson($recursive, $withRid, $encodeRid, $removeNullField, $exceptField, $mustRemoveFieldList);
        $rs = \JSON::decode($rs, true);
        return $rs;
    }

    public function asObject($recursive=true, $withRid=true, $encodeRid=false, $removeNullField=false, $exceptField=null, $mustRemoveFieldList=null){
        $rs = $this->asJson($recursive, $withRid, $encodeRid, $removeNullField, $exceptField, $mustRemoveFieldList);
        $rs = \JSON::decode($rs);
        return $rs;
    }

    public function listAsJson($resultList, $recursive=true, $withRid=true, $encodeRid=false, $removeNullField=false, $exceptField=null, $mustRemoveFieldList=null){
        $json = [];
        for($i = 0; $i < sizeof($resultList); $i++){
            $json[] = $resultList[$i]->asJson($recursive, $withRid, $encodeRid, $removeNullField, $exceptField, $mustRemoveFieldList);
        }
        return '['.  implode(',', $json) .']';
    }


    public function listAsArray($resultList, $recursive=true, $withRid=true, $encodeRid=false, $removeNullField=false, $exceptField=null, $mustRemoveFieldList=null){
        $json = [];
        for($i = 0; $i < sizeof($resultList); $i++){
            $json[] = $resultList[$i]->asJson($recursive, $withRid, $encodeRid, $removeNullField, $exceptField, $mustRemoveFieldList);
        }
        $arr = \JSON::decode('['. implode(',', $json) .']', true);
        return $arr;
    }

    public function listAsObject($resultList, $recursive=true, $withRid=true, $encodeRid=false, $removeNullField=false, $exceptField=null, $mustRemoveFieldList=null){
        $json = [];
        for($i = 0; $i < sizeof($resultList); $i++){
            $json[] = $resultList[$i]->asJson($recursive, $withRid, $encodeRid, $removeNullField, $exceptField, $mustRemoveFieldList);
        }
        $arr = \JSON::decode('['. implode(',', $json) .']');
        return $arr;
    }

    public function getDbSchema(){
        return $this->_db->getMetadata()->getSchema();
    }

    public function getClusters($includeDefault=true){
        $clustersThisClass = [];
        $classInfo = $this->_db->getMetadata()->getSchema()->getClass($this->_class);
        $clusters = $classInfo->clusterIds;

        foreach($clusters as $id){
            $clustersThisClass[$id] = $this->_db->getClusterNameById($id);

            if($includeDefault==false && strtolower($clustersThisClass[$id]) == strtolower($this->_class)){
                unset($clustersThisClass[$id]);
            }
        }
        return $clustersThisClass;
    }

    public function hasCluster($id){
        $classInfo = $this->_db->getMetadata()->getSchema()->getClass($this->_class);
        if(is_int($id)){
            $clusters = $classInfo->clusterIds;
        }
        else{
            $cluster = $this->_db->getClusterIdByName($id);
            return ($cluster > -1);
        }
        return ( array_search($id, $clusters)!==false );
    }

    public function dropCluster($id, $truncate=true){
        if(is_string($id)){
            $id = $this->getClusterIdByName($id);
        }
        $classInfo = $this->_db->getMetadata()->getSchema()->getClass($this->_class);
        $classInfo->removeClusterId($id);
        return $this->_db->dropCluster($id, $truncate);
    }

    public function addCluster($name){
        $cid = $this->_db->addCluster($name, OStorage::CLUSTER_TYPE::PHYSICAL);
        $classInfo = $this->_db->getMetadata()->getSchema()->getClass($this->_class);
        return $classInfo->addClusterId($cid);
    }

    public function getClusterIdByName($name){
        return $this->_db->getClusterIdByName($name);
    }

    public function getClusterNameById($id){
        return $this->_db->getClusterNameById($id);
    }

    public function findAll($preFetch=false){
        return $this->findAllInClass($preFetch);
    }

    public function findAllInCluster($preFetch=false){
        $rs = $this->_db->browseCluster($this->getClass());
        if($preFetch==false){
            return $rs;
        }
        if($rs!=null){
            $arr = [];
            foreach($rs as $r){
                $arr[] = $r;
            }
            return $arr;
        }
    }

    public function findAllInClass($preFetch=false){
        $rs = $this->_db->browseClass($this->getClass());
        if($preFetch==false){
            return $rs;
        }
        if($rs!=null){
            $arr = [];
            foreach($rs as $r){
                $arr[] = $r;
            }
            return $arr;
        }
    }

    public function count(){
        return $this->countAllInClass();
    }

    public function countAllInClass(){
        return $this->_db->countClass($this->getClass());
    }

    public function countAllInCluster($cluster=null){
        if($cluster==null){
            $rs = $this->command('select count(*) from cluster:'. $this->_class);
            if(empty($rs)){
                return 0;
            }
            $rs = $rs[0]->field('count');
            return $rs;
        }

        return $this->_db->countClusterElements($cluster);
    }

    public function getFieldType(){
        return $this->_fieldType;
    }

    public function setFieldType($fieldType){
        return $this->_fieldType = $fieldType;
    }

    public function getFields(){
        return $this->_field;
    }

    public function __set($k, $v){
        $this->_field[$k] = $v;
        $this->_mapFields($k, $v);
    }

    protected function _mapFields($k, $v){
        $type = strtoupper(gettype($v));

        //if field type is defined, override default automated type mapping
        if(!empty($this->_fieldType[$k])){
            $type = strtoupper($this->_fieldType[$k]);
        }

        //map to constant OType
        $cnst = 'TYPE_' . $type;
        if($v===null && $this->_doc!=null){
            if($type!='NULL'){
                ODoc::set($this->_doc, $k, null, constant("DooOrientDbModel::$cnst"));
            }
            else{
                //no type defined, nothing, value is also null? WHat to do? set to TYPE String by default
                ODoc::set($this->_doc, $k, null, self::TYPE_STRING);
            }
        }
        else if($this->_doc!=null){
            //if it's a linked, use model's doc() to get its ODocument object.
            if(in_array($type, ['ARRAY','OBJECT','EMBEDDED','EMBEDDEDLIST','EMBEDDEDMAP','EMBEDDEDSET','LINK','LINKLIST','LINKMAP','LINKSET'])){
                if(is_object($v)){
                    $cls = get_class($v);
    //                $ref = new ReflectionClass($v);
    //                Vertx::logger()->debug("$k =parent= ". $ref->getParentClass()->getName());
                    //if model class, set field with the ODocument value of the model
                    if($cls != 'com.orientechnologies.orient.core.record.impl.ODocument'){
                        ODoc::set($this->_doc, $k, $v->doc(), constant("DooOrientDbModel::$cnst"));
                        return;
                    }
                }
                else if(is_array($v)){
                    if($type == 'LINKLIST' || $type == 'LINKSET' ){
                        $this->setLinkList($k, $v);
                    }
                    else if($type == 'EMBEDDEDLIST' || $type == 'EMBEDDEDSET'){
                        $this->setEmbeddedList($k, $v, $type);
                    }
                    else if($type == 'EMBEDDEDMAP' || $type == 'LINKMAP'){
                        $this->setEmbeddedMap($k, $v, $type);
                    }
                    return;
                }
            }

//            var_dump(constant("DooOrientDbModel::$cnst"));return;
//            $this->_doc->field($k, $v, constant("DooOrientDbModel::$cnst"));
            ODoc::set($this->_doc, $k, $v, constant("DooOrientDbModel::$cnst"));
            return;
        }
    }

    public function isIndexedArray($array){
        return ctype_digit(implode('', array_keys($array)));
    }

    public function setEmbeddedSet($field, $listArr){
        return $this->setEmbeddedList($field, $listArr, 'EMBEDDEDSET');
    }

    public function setEmbeddedList($field, $listArr, $embedType = 'EMBEDDEDLIST'){
        if($this->isIndexedArray($listArr)){
            $list = new java('java.util.ArrayList');

            foreach($listArr as $itm){
                if(is_object($itm)){
                    $cls = get_class($itm);
                    if($cls != 'com.orientechnologies.orient.core.record.impl.ODocument'){
                        $list->add($itm->doc());
                    }else{
                        $list->add($itm);
                    }
                }
                else if(is_array($itm) && !$this->isIndexedArray($itm)){
                    $jmap = new java('java.util.HashMap');
                    foreach($itm as $k2=>$v2){
                        $jmap->put($k2, $v2);
                    }
                    $list->add($jmap);
                }
                else{
                    $list->add($itm);
                }
            }

            $embedType = strtoupper($embedType);
            ODoc::set($this->_doc, $field, $list, constant("DooOrientDbModel::TYPE_$embedType"));
            return true;
        }
        return false;
    }

    public function setLinkSet($field, $listArr){
        return $this->setLinkList($field, $listArr, 'LINKSET');
    }

    public function addLink($field, DooOrientDbModel $linkedObj) {
        if (!empty($this->{$field})) {
            $this->{$field}->add($linkedObj->doc());
        }
        else {
            $this->{$field} = [$linkedObj->doc()];
        }
    }

    public function setLinkList($field, $listArr, $linkType = 'LINKLIST'){
        if(ODoc::isType($listArr, 'com.orientechnologies.orient.core.db.record.ORecordLazyList') || $this->isIndexedArray($listArr)){
            $list = new java('java.util.ArrayList');

            foreach($listArr as $itm){
                //if is object ODocument or a Model class, add as a LINKLIST to the object
                if(is_object($itm)){
                    $cls = get_class($itm);
                    if($cls != 'com.orientechnologies.orient.core.record.impl.ODocument'){
                        $list->add($itm->doc());
                    }else{
                        $list->add($itm);
                    }
                }
                else if(is_array($itm) && !$this->isIndexedArray($itm)){
                    $doc =  new ODocument();
                    foreach($itm as $k2=>$v2){
                        $type = strtoupper(gettype($v2));
                        $cnst = 'TYPE_' . $type;
                        ODoc::set($doc, $k2, $v2, constant("DooOrientDbModel::$cnst"));
                    }
                    $list->add($doc);
                }
            }

            $linkType = strtoupper($linkType);
            ODoc::set($this->_doc, $field, $list, constant("DooOrientDbModel::TYPE_$linkType"));
            return true;
        }
        return false;
    }

    public function setLinkMap($field, $map){
        return $this->setEmbeddedMap($field, $map, 'LINKMAP');
    }

    public function setEmbeddedMap($field, $map, $mapType = 'EMBEDDEDMAP'){
        $jmap = new java('java.util.HashMap');
        $mapType = strtoupper($mapType);

        foreach($map as $k=>$v){
            //if array, check if it's map or linklist
            if(is_array($v)){
                if($this->isIndexedArray($v)){
                    $jmap2 = new java('java.util.ArrayList');
                    foreach($v as $v2){
                        $jmap2->add($v2);
                    }
                    $jmap->put($k, $jmap2);
                }
                else if($mapType == 'EMBEDDEDMAP'){
                    $jmap2 = new java('java.util.HashMap');
                    foreach($v as $k2=>$v2){
                        $jmap2->put($k2, $v2);
                    }
                    $jmap->put($k, $jmap2);
                }
                else if($mapType == 'LINKMAP'){
                    $doc =  new ODocument();
                    foreach($v as $k2=>$v2){
                        $type = strtoupper(gettype($v2));
                        $cnst = 'TYPE_' . $type;
                        ODoc::set($doc, $k2, $v2, constant("DooOrientDbModel::$cnst"));
                    }
                    $jmap->put($k, $doc);
                }
            }
            else{
                $jmap->put($k, $v);
            }
        }
        ODoc::set($this->_doc, $field, $jmap, constant("DooOrientDbModel::TYPE_$mapType"));
        return true;
    }

    public function __get($name){
        if($name=='_rid'){
            return $this->id();
        }
        return $this->getProp($name);
    }

    public function getProp($name, $setPublicVar=false){
        if(isset($this->_field[$name])){
            return $this->_field[$name];
        }

        if($this->_doc!=null && isset($this->_doc->containsField($name)) ){
            $class = get_class($this);
            $d = $this->_doc->field($name);

            if(empty($d)) return;

            $obj = null;

            if(isset($this->_classMap)){
                $obj = self::_docToObj($d, $this->_classMap[$d->getClassName()], $this->_classMap);
            }
            else if(is_bool($this->_namespace) && $this->_namespace==true){
                $ns = explode('\\', $class);
                array_pop($ns);
                $obj = self::_docToObj($d, implode('\\', $ns) .'\\'. $d->getClassName());
            }
            else if(is_string($this->_namespace)){
                $obj = self::_docToObj($d, $this->_namespace .'\\'. $d->getClassName());
            }

            if($setPublicVar){
                $this->$name = $obj;
            }else{
                $this->_field[$name] = $obj;
            }
            return $obj;
        }
    }

    public function __toString(){
        if(empty($this->_doc)) return 'null';
        return $this->_doc->toString();
    }

    public function __call($name, $p){
        if(!empty($this->_doc)){
            switch(sizeof($p)) {
                case 0: return $this->_doc->{$name}(); break;
                case 1: return $this->_doc->{$name}($p[0]); break;
                case 2: return $this->_doc->{$name}($p[0], $p[1]); break;
                case 3: return $this->_doc->{$name}($p[0], $p[1], $p[2]); break;
                case 4: return $this->_doc->{$name}($p[0], $p[1], $p[2], $p[3]); break;
                case 5: return $this->_doc->{$name}($p[0], $p[1], $p[2], $p[3], $p[4]); break;
//                default: call_user_func_array(array($c, $a), $p);  break;  not working good with quercus passback java
            }
        }
    }

    public function prepareFields(){
        if(!empty($this->_fieldType)){
            $fixedFields = array_keys($this->_fieldType);
            foreach($fixedFields as $fname){
                $this->_field[$fname] = &$this->$fname;
            }
        }
        if(!empty($this->_field)){
            foreach($this->_field as $k=>$v){
                $this->_mapFields($k, $v);
            }
        }
    }

    /**
     * @return string ORID
     */
    public function save($clusterId=null){
        if(!empty($this->_fieldType)){
            $this->prepareFields();
        }

        if($clusterId==null){
            if(empty($this->_useCluster)){
                $rs = $this->_doc->save();                
            }
            else{
                $rs = $this->_doc->save($this->_useCluster);
            }
            $this->rid = $this->idStr();
            return $rs;
        }

        $rs = $this->_doc->save($clusterId);
        $this->rid = $this->idStr();
        return $rs;
    }

    public function transactionBlock(callable $codeBlockFunc, callable $successHandler, callable $exceptionHandler){
        $t = new java('com.doophp.db.orientdb.Transaction', $this->_db, $codeBlockFunc, $successHandler, $exceptionHandler);
        $t->commit();
    }

    public function delete(){
        $this->rid = null;
        return $this->_doc->delete();
    }

    public function deleteAll($class=null){
        $this->deleteAllInClass($class);
    }

    public function deleteAllInClass($class=null){
        if($class==null){
            $class = $this->_class;
        }
        $this->rid = null;
        $this->command('truncate class '. $class);
    }

    public function deleteAllInCluster($class){
        if($class==null){
            $class = $this->_class;
        }
        $this->rid = null;
        $this->command('truncate cluster '. $class);
    }

    public function command($sql){
        return $this->_db->command( new OCommandSQL($sql) )->execute();
    }

    public function enableStrictMode($forceDeleteNonStrict=false){
        if($forceDeleteNonStrict==true && $this->countAllInCluster() > 0){
            $this->deleteAllInCluster();
        }
        $this->command('ALTER CLASS '. $this->_class .' STRICTMODE true');
    }

    public function disableStrictMode(){
        $this->command('ALTER CLASS '. $this->_class .' STRICTMODE false');
    }

    public static function _toDateTime($dateTime){
        if(is_string($dateTime)){
            $dateTime = strtotime($dateTime);
        }
        return date('Y-m-d H:i:s', $dateTime);
    }

    public static function _toDate($dateTime){
        if(is_string($dateTime)){
            $dateTime = strtotime($dateTime);
        }
        return date('Y-m-d', $dateTime);
    }

    public function toDateTime($dateTime){
        return self::_toDateTime($dateTime);
    }

    public function toDate($dateTime){
        return self::_toDate($dateTime);
    }

    public function now(){
        return self::_toDateTime(time());
    }

    public function startMassiveInsert(){
        $this->_db->declareIntent( new OIntentMassiveInsert() );
    }

    public function stopMassiveInsert(){
        $this->_db->declareIntent( null );
    }
}