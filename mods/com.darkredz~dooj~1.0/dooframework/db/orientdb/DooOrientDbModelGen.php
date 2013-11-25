<?php
/**
 * DooOrientDbModelGen class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */


/**
 * DooOrientDbModelGen provides functionalities to generate Model class files and its indexes schema from a OrientDB database and vice versa
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @package doo.db.orientdb
 * @since 2.0
 */

import com.orientechnologies.orient.object.db.OObjectDatabaseTx;
import com.orientechnologies.orient.core.db.document.ODatabaseDocumentTx;
import com.orientechnologies.orient.core.record.impl.ODocument;
import com.orientechnologies.orient.core.sql.query.OSQLSynchQuery;
import com.orientechnologies.orient.core.sql.query.OSQLAsynchQuery;
import com.orientechnologies.orient.core.db.document.ODatabaseDocumentPool;
import com.orientechnologies.orient.core.metadata.schema.OType;
import com.orientechnologies.orient.core.command.OCommandResultListener;
import com.orientechnologies.orient.core.id.ORecordId;
import com.orientechnologies.orient.core.sql.OCommandSQL;
import com.orientechnologies.orient.core.intent.OIntentMassiveInsert;
import com.orientechnologies.orient.core.metadata.schema.*;


class DooOrientDbModelGen {

    protected $db;
    protected $defaultPath;
    protected $conf;
    protected $createBase;
    protected $baseClassSuffix;
    protected $hidePublicVar;

    public function setup($conf, $db, $baseClassSuffix='Base', $hidePublicVar=false){
        $this->db = $db;
        $this->conf = $conf;
        $this->baseClassSuffix = $baseClassSuffix;
        $this->hidePublicVar = $hidePublicVar;
        $this->defaultPath = $conf->SITE_PATH . $conf->PROTECTED_FOLDER . 'model/';
    }

    public function genDb($dbmapClasses, $indexes=null, $forceDrop = false){
        $dbSchema = $this->db->getMetadata()->getSchema();
        $classes = array_keys($dbmapClasses);

        //drop classes
        if($forceDrop){
            $failedDrop = [];
            foreach($classes as $dropThisClass){
                $exists = $dbSchema->existsClass($dropThisClass);
                if($exists){
                    try{
                        $dbSchema->dropClass($dropThisClass);
                    }
                    catch(Exception $e){
                        $err = $e->__javaException->toString();
                        if(strpos($err, 'cannot be dropped because it has sub classes') > 0){
                            $failedDrop[] = $dropThisClass;
                        }
                    }
                }
            }
            if(!empty($failedDrop)){
                foreach($failedDrop as $dropThisClass){
                    $dbSchema->dropClass($dropThisClass);
                }
            }
        }

        //create class, Sub classes should be defined after super class in $dbmapClasses
        foreach($classes as $classname){
            $fullClassName = $dbmapClasses[$classname];
            $schema = call_user_func($fullClassName . '::_schema');

            $classInfo = json_decode($schema['classInfo']);

            //echo "===========\n Create $classname .. \n";

            if(!empty($classInfo->superClass)){
                $superClass = $dbSchema->getClass($classInfo->superClass);
                $classCreated = $dbSchema->createClass($classname, $superClass);
            }
            else{
                $classCreated = $dbSchema->createClass($classname);
            }

            $classCreated->setStrictMode($classInfo->strictMode);

            if(!empty($classInfo->shortName)) {
                $classCreated->setShortName($classInfo->shortName);
            }

            $classCreated->setOverSize($classInfo->overSize);

            if(!empty($classInfo->clusterIds) && sizeof($classInfo->clusterIds)>1){
                foreach($classInfo->clusterIds as $cid){
                    if($cid==$classInfo->defaultClusterId) continue;
                    $classCreated->addClusterId($cid);
                }
            }
//            $classCreated->setDefaultClusterId($classInfo->defaultClusterId);
        }

        //create properties for classes created
        foreach($classes as $classname){
            $classCreated = $dbSchema->getClass($classname);

            $fullClassName = $dbmapClasses[$classname];
            $schema = call_user_func($fullClassName . '::_schema');

            $classInfo = json_decode($schema['classInfo']);
            $properties = $classInfo->properties;

            $links = ['EMBEDDED','EMBEDDEDLIST','EMBEDDEDMAP','EMBEDDEDSET','LINK','LINKLIST','LINKMAP','LINKSET'];

            foreach($properties as $p){
                $ptype = \OType::getById($p->type);

                //if field type is a link, get class and link them
                if(in_array($ptype->toString(), $links)){
                    $linkedClass = $p->linkedClass;
                    if(!empty($linkedClass)){
                        $classlink = $dbSchema->getClass($linkedClass);
                        $prop = $classCreated->createProperty($p->name, $ptype, $classlink);
                    }else{
                        $prop = $classCreated->createProperty($p->name, $ptype);
                    }
                }
                else{
                    $prop = $classCreated->createProperty($p->name, $ptype);
                }

                if(isset($p->mandatory) && $p->mandatory===true){
                    $prop->setMandatory(true);
                }else{
                    $prop->setMandatory(false);
                }

                if(isset($p->notNull) && $p->notNull===true){
                    $prop->setNotNull(true);
                }else{
                    $prop->setNotNull(false);
                }

                if(isset($p->min)){
                    $prop->setMin($p->min);
                }

                if(isset($p->max)){
                    $prop->setMax($p->max);
                }

                if(isset($p->regexp)){
                    $prop->setRegexp($p->regexp);
                }
            }

            //create indexes
            $clsIndex = $indexes[$classname];
            if(!empty($clsIndex)){
                foreach($clsIndex as $idx){
                    $nm = $idx['name'];
                    $f = $idx['fields'];
                    $idxTyp = OClass::INDEX_TYPE->valueOf($idx['type'])->toString();
                    $fieldstr = implode(',', $f);
                    $sql = "CREATE INDEX $nm ON $classname ($fieldstr) $idxTyp";
                    $this->db->command( new OCommandSQL($sql) )->execute();
                }
            }
        }
    }

    public function genModel($namespace=null, $path=null, $chmod=null){
        if($path===null){
            $path = $this->defaultPath;
        }

        $cls = $this->db->getMetadata()->getSchema()->getClasses();

        echo "<html><head><title>Dooj Model Generator</title></head><body style=\"font-size:24pt;\" bgcolor=\"#2e3436\">";

        if($this->hidePublicVar){
            echo '<span style="color:white;font-size:100%;font-family: \'Courier New\', Courier, monospace;">Awesome! Good to go live with these models</span><br/><br/>';
        }
        else{
            echo '<span style="color:yellow;font-size:100%;font-family: \'Courier New\', Courier, monospace;">* Remember to hide public properties with publicVar=false</span><br/><br/>';
        }

        echo '<pre>';

        $indexArr = [];
        $configMap = [];
        $putSubclassLastInMap = [];

        foreach($cls as $c){
            $clsName = $c->getName();
            if(in_array($clsName, ['ORIDs','OIdentity' ,'OFunction' ,'ORole' ,'OSchedule' ,'ORestricted' ,'OTriggered' ,'OUser'])){
                continue;
            }

            $indexes = $c->getIndexes();
            $indexArr[$clsName] = [];

            foreach($indexes as $idx){
//                var_dump(OClass::INDEX_TYPE->valueOf($idx->getType()) == 'UNIQUE');
//                var_dump($idx->getClusters());
                $clusters = $idx->getClusters();

                //skip writing index to this class if index belongs to super class. Only need to create index on the super class
                if(sizeof($clusters) > 1){
                    $superClass = $this->getSuperClass($c);
                    if($superClass->getName() != $c->getName()){
//                        var_dump('HAS SUPER');
//                        var_dump($superClass);
                        continue;
                    }
                }

                $keyArr = $idx->keys();
                $keys = [];
                foreach($keyArr as $ky){
                    $keys[] = $ky;
                }

                $fieldDetails = $idx->getConfiguration()->field('indexDefinition');
                //composite field index
                if(!empty($fieldDetails->field('indexDefinitions'))){
                    $fieldDetails = $fieldDetails->field('indexDefinitions');
                    $fields = [];
                    foreach($fieldDetails as $f){
                        $fields[] = $f->field('field');
                    }
                }else{
                    $fields = [$fieldDetails->field('field')];
                }

                $indexArr[$clsName][] = ['name' => $idx->getName(), 'type' => $idx->getType(), 'fields'=>$fields,'json' => $idx->getConfiguration()->toJson()];
            }

            //write model class, output to class map for config, model maps
            $ns = $this->writeModelFile($clsName, $path, $namespace, $chmod);
            $ns = explode("\\", str_replace(array("\n", ';', 'namespace '), '', $ns));
            $ns[] = $clsName;

            $superClass = $this->getSuperClass($c);
            //if has super class, put model in last on map for DB regen. Need create super class before subclass
            if($superClass->getName() != $c->getName()){
                $putSubclassLastInMap[$clsName] = implode("\\", $ns);
            }
            else{
                $configMap[$clsName] = implode("\\", $ns);
            }
        }

        //write indexes to base/indexes.php
        $indexStr = var_export($indexArr, true);
        $fileManager = new DooFile($chmod);

        $indexFile = <<<EOF
<?php
return $indexStr;
EOF;

        if ($fileManager->create("$path/base/indexes.php", $indexFile, 'w+')) {
            echo "<span style=\"font-size:100%;font-family: 'Courier New', Courier, monospace;\"><span style=\"color:#fff;\">Indexes for DB created at </span><strong><span style=\"color:#729fbe;\">indexes</span></strong><span style=\"color:#fff;\">.php</span></span><br/><br/>";
        } else {
            echo "<span style=\"font-size:100%;font-family: 'Courier New', Courier, monospace;\"><span style=\"color:#f00;\">Indexes for DB could not be created </span><strong><span style=\"color:#729fbe;\">indexes</span></strong><span style=\"color:#fff;\">.php</span></span><br/><br/>";
        }

        if(!empty($putSubclassLastInMap)){
            $configMap = array_merge($configMap, $putSubclassLastInMap);
        }

        $orientDbClasses = var_export($configMap, true);
        $configStr = <<<EOF
\$config['orientDbClasses'] = $orientDbClasses;
EOF;

        echo '<hr/><span style="font-size:100%;font-family: \'Courier New\', Courier, monospace;color:#fff;">Copy this to common.conf.php</span><br/><pre style="padding:8px;background-color:#fff;font-size:60%;display:block;width:100%;">'. $configStr . '</pre></body></html>';
    }

    protected function getSuperClass($class){
        if($c = $class->getSuperClass()){
            return $this->getSuperClass($c);
        }
        return $class;
    }

    protected function writeModelFile($clsName, $path, $namespace, $chmod){
        $fileManager = new DooFile($chmod);

        $classInfo = $this->db->getMetadata()->getSchema()->getClass($clsName);

        $properties = $classInfo->properties();

        $schema = ['classInfo'=>null, 'allProperties'=>null];
        $schema['classInfo'] = $classInfo->toStream()->toJson();

        $allFields = '';
        $allFieldType = '';
        $schemaStr = '';

        if(!empty($properties)){
            $allFieldType = [];

            $plist = [];

            foreach($properties as $s){
                $field = $s->getName();
                $type = $s->getType();

                $doc = $s->toStream();
                $plist[$field] = $doc->toJson();
                $allFields .= "    public \$$field;\n";
                $allFieldType[] = "        '$field' => \\DooOrientDbModel::TYPE_$type";
            }

            if(!empty($allFieldType)){
                $allFieldType = implode(",\n", $allFieldType);
            }
        }


        $schema['allProperties'] = $plist;

        $schemaStr = var_export($schema, true);
        $schemaStr = <<<EOF
    public static function _schema(){
        return $schemaStr;
    }

    public function schema(){
        return self::_schema();
    }
EOF;

        $fieldStr = '';

        if($namespace===true){
            if(isset($this->conf->PROTECTED_FOLDER_ORI)){
                $protected = $this->conf->PROTECTED_FOLDER_ORI;
            }
            else{
                $protected = $this->conf->PROTECTED_FOLDER;
            }

            if(strpos($path, $this->conf->SITE_PATH . $protected) === 0){
                $relativePathToProtected = str_replace($this->conf->SITE_PATH . $protected, '', $path);
                $namespace = "namespace {$this->conf->APP_NAMESPACE_ID}\\$relativePathToProtected";
                $namespace = str_replace('/','\\',$namespace);
                $namespace = substr($namespace, 0, -1);
                $namespaceBase = "\n". $namespace . "\\base;\n";
                $namespace = "\n". $namespace . ";\n";
            }
        }
        else if(is_string($namespace)){
            $namespaceBase = "\nnamespace $namespace\\base;\n";
            $namespace = "\nnamespace $namespace;\n";
        }
        else{
            $namespace = '';
        }

        $clsBase = $clsName . $this->baseClassSuffix;

        if($this->hidePublicVar){
            $allFields = "/**\n" . $allFields . "\n**/";
        }

        if($allFields!=''){
            $fieldStr = <<<EOF

$allFields
    protected \$_fieldType = [
$allFieldType
    ];
EOF;
        }

        $filestr = <<<EOF
<?php
$namespaceBase
class $clsBase extends \DooOrientDbModel{

    protected \$_class = '$clsName';
$fieldStr

$schemaStr
}
EOF;

        $useNamespace = '';

        if($namespace!=''){
            $namespaceRem = str_replace(['namespace ', ';', "\n"], '', $namespaceBase);
            $useNamespace = "\nuse $namespaceRem\\$clsBase;\n";
        }

        $filestr2 = <<<EOF
<?php
$namespace $useNamespace
class $clsName extends $clsBase {

}
EOF;

        if ($fileManager->create("$path/base/$clsName{$this->baseClassSuffix}.php", $filestr, 'w+')) {
            if(file_exists("$path$clsName.php")===false){
                $fileManager->create("$path$clsName.php", $filestr2, 'w+');
            }
            echo "<span style=\"font-size:100%;font-family: 'Courier New', Courier, monospace;\"><span style=\"color:#fff;\">Model for class </span><strong><span style=\"color:#e7c118;\">$clsName</span></strong><span style=\"color:#fff;\"> generated. File - </span><strong><span style=\"color:#729fbe;\">$clsName</span></strong><span style=\"color:#fff;\">.php</span></span><br/><br/>";
        } else {
            echo "<span style=\"font-size:100%;font-family: 'Courier New', Courier, monospace;\"><span style=\"color:#f00;\">Model for class </span><strong><span style=\"color:#e7c118;\">$clsName</span></strong><span style=\"color:#f00;\"> could not be generated. File - </span><strong><span style=\"color:#729fbe;\">$clsName</span></strong><span style=\"color:#f00;\">.php</span></span><br/><br/>";
        }
        return $namespace;
    }

}