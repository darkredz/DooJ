<?php
/**
 * DooModelGen class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */


/**
 * DooModelGen serves as a Model class file generator for rapid development
 *
 * <p>If you have your database configurations setup, call DooModelGen::gen_mysql() and
 * it will generate the Model files for all the tables in that database</p>
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooModelGen.php 1000 2009-07-7 18:27:22
 * @package doo.db
 * @since 1.0
 */
class DooModelGen
{

    const EXTEND_MODEL = 'DooModel';
    const EXTEND_SMARTMODEL = 'DooSmartModel';

    /**
     * @var DooSqlMagic
     */
    protected $db;

    /**
     * @var DooConfig
     */
    protected $conf;

    function __construct($db, $conf)
    {
        $this->db = $db;
        $this->conf = $conf;
    }

    public function exportRules($ruleArr)
    {
        $rule = preg_replace("/\d+\s+=>\s+/", '', var_export($ruleArr, true));
        $rule = str_replace("\n      ", ' ', $rule);
        $rule = str_replace(",\n    )", ' )', $rule);
        $rule = str_replace("array (", 'array(', $rule);
        $rule = str_replace("    array(", '                        array(', $rule);
        $rule = str_replace("=> \n  array(", '=> array(', $rule);
        $rule = str_replace("  '", "                '", $rule);
        $rule = str_replace("  ),", "                ),\n", $rule);
        $rule = str_replace(",\n\n)", "\n            );", $rule);
        $rule = str_replace("    '", "'", $rule);
        $rule = str_replace("    )", ")", $rule);
        $rule = str_replace("array( ", "[", $rule);
        $rule = str_replace(" ),", "],", $rule);
        $rule = str_replace("=> array(", "=> [", $rule);
        $rule = str_replace("        ],", "         ],", $rule);
        $rule = str_replace("                        ['", "                ['", $rule);
        $rule = str_replace(")", "]", $rule);
        $rule = str_replace("array(", "[", $rule);

        return $rule;
    }

    /**
     * Generates Model class files from a MySQL database
     * @param bool $comments Generate comments along with the Model class
     * @param bool $vrules Generate validation rules along with the Model class
     * @param string $extends make Model class to extend DooModel or DooSmartModel
     * @param bool $createBase Generate base model class, will not rewrite/replace model classes if True.
     * @param string $baseSuffix Suffix string for the base model.
     * @param int $chmod Chmod for file manager
     * @param string $path Path to write the model class files
     */
    public function genMySQL(
        $comments = true,
        $vrules = true,
        $extends = 'DooSmartModel',
        $namespace = '',
        $createBase = true,
        $baseSuffix = 'Base',
        $ignoreHashTables = true,
        $useAutoload = false,
        $chmod = null,
        $path = null
    )
    {
        if ($path === null) {
            $path = $this->conf->SITE_PATH . $this->conf->PROTECTED_FOLDER . 'model/';
        }
        if ($chmod === null) {
            $fileManager = new DooFile();
        } else {
            $fileManager = new DooFile($chmod);
        }

        $dbconf = $this->db->getDefaultDbConfig();
        if (!isset($dbconf) || empty($dbconf)) {
            echo "<html><head><title>DooPHP Model Generator - DB: Error</title></head><body bgcolor=\"#2e3436\"><span style=\"font-size:190%;font-family: 'Courier New', Courier, monospace;\"><span style=\"color:#fff;\">Please setup the DB first in index.php and db.conf.php</span></span>";
            exit;
        }

        $dbname = $dbconf[1];

        echo "<html><head><title>DooPHP Model Generator - DB: $dbname</title></head><body bgcolor=\"#2e3436\">";

        $smt = $this->db->query("SHOW TABLES");
        $tables = $smt->fetchAll();
        $clsExtendedNum = 0;
        $tableProcessed = [];

        if (!empty($namespace)) {
            $namespaceModel = "\n\nnamespace $namespace;\n\n";
            $namespaceBase = "\n\nnamespace $namespace\\base;\n\n";
        } else {
            $namespaceModel = '';
            $namespaceBase = '';
        }


        foreach ($tables as $tbl) {
            $tblname = $tbl['Tables_in_' . strtolower($dbname)];
            $oriTblName = $tblname;

            if ($ignoreHashTables) {
                $tblNoNumberSuffix = preg_replace('/[\_\d]+$/', '', $tblname);

                if ($tblNoNumberSuffix != $tblname) {
                    if (array_key_exists($tblNoNumberSuffix, $tableProcessed)) {
                        continue;
                    } else {
                        $tblname = $tblNoNumberSuffix;
                    }
                }

                $tableProcessed[$tblNoNumberSuffix] = true;
            }

            $smt2 = null;
            unset($smt2);
            $smt2 = $this->db->query("DESC `$oriTblName`");
            $fields = $smt2->fetchAll();
            //print_r($fields);
            $classname = '';
            $temptbl = $tblname;
            for ($i = 0; $i < strlen($temptbl); $i++) {
                if ($i == 0) {
                    $classname .= strtoupper($temptbl[0]);
                } else {
                    if ($temptbl[$i] == '_' || $temptbl[$i] == '-' || $temptbl[$i] == '.') {
                        $classname .= strtoupper($temptbl[($i + 1)]);
                        $arr = str_split($temptbl);
                        array_splice($arr, $i, 1);
                        $temptbl = implode('', $arr);
                    } else {
                        $classname .= $temptbl[$i];
                    }
                }
            }

            $useBase = '';

            if (!empty($extends)) {
                if ($createBase != true) {
                    $filestr = "<?php{$namespaceModel}\nclass $classname extends \\$extends\n{\n";
                } else {
                    $filestr = "<?php{$namespaceBase}\nclass {$classname}{$baseSuffix} extends \\$extends\n{\n";
                    $useBase = "use {$namespace}\\base\\{$classname}{$baseSuffix};\n";
                }
            } else {
                if ($createBase != true) {
                    $filestr = "<?php{$namespaceModel}\nclass $classname\n{\n";
                } else {
                    $filestr = "<?php{$namespaceBase}\nclass {$classname}{$baseSuffix}\n{\n";
                    $useBase = "use {$namespace}\\base\\{$classname}{$baseSuffix};\n";
                }
            }

            $pkey = '';
            $ftype = '';
            $fieldnames = [];

            $rules = [];
            foreach ($fields as $f) {
                $fstring = '';
                if ($comments && isset($f['Type']) && !empty($f['Type'])) {
                    preg_match('/([^\(]+)[\(]?([\d]*)?[\)]?(.+)?/', $f['Type'], $ftype);
                    $length = '';
                    $more = '';

                    if (isset($ftype[2]) && !empty($ftype[2])) {
                        $length = " Max length is $ftype[2].";
                    }
                    if (isset($ftype[3]) && !empty($ftype[3])) {
                        $more = " $ftype[3].";
                        $ftype[3] = trim($ftype[3]);
                    }

                    $fstring = "\n    /**\n     * @var {$ftype[1]}$length$more\n     */\n";

                    //-------- generate rules for the setupValidation() in Model ------
                    if ($vrules) {
                        $rule = [];
                        if ($rulename = DooValidator::dbDataTypeToRules(strtolower($ftype[1]))) {
                            $rule = [[$rulename]];
                        }

                        if (isset($ftype[3]) && $ftype[3] == 'unsigned') {
                            $rule[] = ['min', 0];
                        }
                        if (ctype_digit($ftype[2])) {
                            if ($ftype[1] == 'varchar' || $ftype[1] == 'char') {
                                $rule[] = ['maxlength', intval($ftype[2])];
                            } else {
                                if ($rulename == 'integer') {
                                    $rule[] = ['maxlength', intval($ftype[2])];
                                }
                            }
                        }

                        if (strtolower($f['Null']) == 'no' && (strpos(strtolower($f['Extra']),
                                    'auto_increment') === false)) {
                            $rule[] = ['notnull'];
                        } else {
                            $rule[] = ['optional'];
                        }

                        if (isset($rule[0])) {
                            $rules[$f['Field']] = $rule;
                        }
                    }
                }

                $filestr .= "$fstring    public \${$f['Field']};\n";
                $fieldnames[] = $f['Field'];
                if ($f['Key'] == 'PRI') {
                    $pkey = $f['Field'];
                }
            }

            $fieldnames = implode($fieldnames, "','");
            $filestr .= "\n    public \$_table = '$tblname';\n";
            $filestr .= "    public \$_primarykey = '$pkey';\n";
            $filestr .= "    public \$_fields = array('$fieldnames');\n";
            $filestr .= <<<EOF
                        
    function __construct(\DooSqlMagic \$db, \DooAppInterface \$app, array \$properties = null)
    {
        parent::__construct(\$db, \$app, \$properties);
    }

EOF;
            if ($vrules && !empty ($rules)) {
                $filestr .= "\n    public function getVRules()\n    {\n        return " . $this->exportRules($rules) . "\n    }\n\n";

                if (empty($extends)) {
                    $filestr .= "    public function validate(\$checkMode='all')\n    {
		//You do not need this if you extend DooModel or DooSmartModel
		//MODE: all, all_one, skip
		\$v = new DooValidator;
		\$v->checkMode = \$checkMode;
		return \$v->validate(get_object_vars(\$this), \$this->getVRules());
	}\n\n";
                }
            }

            $filestr .= "}";

            if ($createBase != true) {
                if ($fileManager->create($path . "$classname.php", $filestr, 'w+')) {
                    echo "<span style=\"font-size:190%;font-family: 'Courier New', Courier, monospace;\"><span style=\"color:#fff;\">Model for table </span><strong><span style=\"color:#e7c118;\">$tblname</span></strong><span style=\"color:#fff;\"> generated. File - </span><strong><span style=\"color:#729fbe;\">$classname</span></strong><span style=\"color:#fff;\">.php</span></span><br/><br/>";
                } else {
                    echo "<span style=\"font-size:190%;font-family: 'Courier New', Courier, monospace;\"><span style=\"color:#f00;\">Model for table </span><strong><span style=\"color:#e7c118;\">$tblname</span></strong><span style=\"color:#f00;\"> could not be generated. File - </span><strong><span style=\"color:#729fbe;\">$classname</span></strong><span style=\"color:#f00;\">.php</span></span><br/><br/>";
                }
            } else {

                if ($fileManager->create($path . "base/{$classname}{$baseSuffix}.php", $filestr, 'w+')) {
                    echo "<span style=\"font-size:190%;font-family: 'Courier New', Courier, monospace;\"><span style=\"color:#fff;\">Base model for table </span><strong><span style=\"color:#e7c118;\">$tblname</span></strong><span style=\"color:#fff;\"> generated. File - </span><strong><span style=\"color:#729fbe;\">{$classname}{$baseSuffix}</span></strong><span style=\"color:#fff;\">.php</span></span><br/><br/>";
                    $clsfile = $path . "$classname.php";
                    if (!file_exists($clsfile)) {
                        $constructorStr = <<<EOF
                        
    function __construct(\DooSqlMagic \$db, \DooAppInterface \$app, array \$properties = null)
    {
        parent::__construct(\$db, \$app, \$properties);
        parent::setupModel(__CLASS__, false);
    }
    
EOF;

                        $filestr = "<?php{$namespaceModel}{$useBase}\nclass $classname extends {$classname}{$baseSuffix}\n{" . $constructorStr . "\n}";
                        if ($fileManager->create($clsfile, $filestr, 'w+')) {
                            echo "<span style=\"font-size:190%;font-family: 'Courier New', Courier, monospace;\"><span style=\"color:#fff;\">Model for table </span><strong><span style=\"color:#e7c118;\">$tblname</span></strong><span style=\"color:#fff;\"> generated. File - </span><strong><span style=\"color:#729fbe;\">$classname</span></strong><span style=\"color:#fff;\">.php</span></span><br/><br/>";
                            $clsExtendedNum++;
                        } else {
                            echo "<span style=\"font-size:190%;font-family: 'Courier New', Courier, monospace;\"><span style=\"color:#f00;\">Model for table </span><strong><span style=\"color:#e7c118;\">$tblname</span></strong><span style=\"color:#f00;\"> could not be generated. File - </span><strong><span style=\"color:#729fbe;\">$classname</span></strong><span style=\"color:#f00;\">.php</span></span><br/><br/>";
                        }
                    }
                } else {
                    echo "<span style=\"font-size:190%;font-family: 'Courier New', Courier, monospace;\"><span style=\"color:#f00;\">Base model for table </span><strong><span style=\"color:#e7c118;\">$tblname</span></strong><span style=\"color:#f00;\"> could not be generated. File - </span><strong><span style=\"color:#729fbe;\">{$classname}{$baseSuffix}</span></strong><span style=\"color:#f00;\">.php</span></span><br/><br/>";
                }
            }
        }

        $total = sizeof($tables) + $clsExtendedNum;
        echo "<span style=\"font-size:190%;font-family: 'Courier New', Courier, monospace;color:#fff;\">Total $total file(s) generated.</span></body></html>";
    }

    /**
     * Generates Model class files from a SQLite database
     * @param string $extends make Model class to extend DooModel or DooSmartModel
     * @param bool $createBase Generate base model class, will not rewrite/replace model classes if True.
     * @param bool $addmaps Writes table relation map in Model class analyze with foreign keys available (You do not need to define in the maps in db.conf.php)
     * @param string $filenameModelPrefix Add a prefix for the model class name
     * @param string $baseSuffix Suffix string for the base model.
     * @param int $chmod Chmod for file manager
     * @param string $path Path to write the model class files
     */
    public function genSqlite(
        $extends = '',
        $namespace = '',
        $createBase = false,
        $addmaps = false,
        $filenameModelPrefix = '',
        $baseSuffix = 'Base',
        $chmod = null,
        $path = null
    )
    {
        if ($path === null) {
            $path = $this->conf->SITE_PATH . $this->conf->PROTECTED_FOLDER . 'model/';
        }

        if ($chmod === null) {
            $fileManager = new DooFile();
        } else {
            $fileManager = new DooFile($chmod);
        }

        // get database info
        $dbconf = $this->db->getDefaultDbConfig();
        $dbname = $dbconf[0];

        if (!empty($namespace)) {
            $namespace = "\n\nnamespace $namespace\n\n;";
        }

        // print debug information about inspected db file
        echo "<html><head><title>DooPHP Model Generator - Sqlite filename: $dbname</title></head><body bgcolor=\"#2e3436\">";

        // get table names
        $tables = $this->db->query("SELECT name FROM sqlite_master where (type='table' or type='view') and name<>'sqlite_sequence'")->fetchAll(PDO::FETCH_COLUMN);

        $clsExtendedNum = 0;

        // cycle tables to compute one model file for each one
        foreach ($tables as $tblname) {

            // get table fields and try to search for a primary key
            $res = $this->db->query("PRAGMA table_info('" . $tblname . "')")->fetchAll();

            // init primary key and fields
            $tblkey = null;
            $tblfields = [];

            foreach ($res as $column) {
                $tblfields[] = $column['name'];

                // try to set pk
                if (intval($column['pk']) && is_null($tblkey)) {
                    $tblkey = $column['name'];
                }
            }

            // get foreign keys
            $res = $this->db->query("PRAGMA foreign_key_list('" . $tblname . "')")->fetchAll();

            // init db map
            $dbmaps = [];

            $classname = '';
            $temptbl = $tblname;
            for ($i = 0; $i < strlen($temptbl); $i++) {
                if ($i == 0) {
                    $classname .= strtoupper($temptbl[0]);
                } else {
                    if ($temptbl[$i] == '_' || $temptbl[$i] == '-' || $temptbl[$i] == '.') {
                        $classname .= strtoupper($temptbl[($i + 1)]);
                        $arr = str_split($temptbl);
                        array_splice($arr, $i, 1);
                        $temptbl = implode('', $arr);
                    } else {
                        $classname .= $temptbl[$i];
                    }
                }
            }

            $classname = $filenameModelPrefix . $classname;

            // start model filename content
            $filestr = "<?php\n\n";

            // add db maps
            if ($addmaps && !empty($res)) {
                foreach ($res as $column) {
                    $dbtableid = $column['from'];
                    $reference_dbtablemodel = $column['table'];
                    $reference_dbtableid = $column['to'];

                    $dbmaps[] = '$dbmap["' . $classname . '"]["has_many"]["' . $reference_dbtablemodel . '"]   = array("foreign_key"=>"' . $reference_dbtableid . '");';
                    $dbmaps[] = '$dbmap["' . $reference_dbtablemodel . '"]["belongs_to"]["' . $classname . '"] = array("foreign_key"=>"' . $dbtableid . '");';
                }
                $filestr .= '$dbmap = array();' . "\n" . implode("\n",
                        $dbmaps) . "\n" . '$this->db->appendMap( $dbmap );' . "\n\n";
            }

            if (!empty($extends)) {
                if ($createBase != true) {
                    if ($extends == DooModelGen::EXTEND_MODEL || $extends == DooModelGen::EXTEND_SMARTMODEL) {
                        $filestr .= "$namespace\n\nclass $classname extends $extends\n{\n";
                    } else {
                        $filestr .= "$namespace\n\nclass $classname extends $extends\n{\n";
                    }
                } else {
                    if ($extends == DooModelGen::EXTEND_MODEL || $extends == DooModelGen::EXTEND_SMARTMODEL) {
                        $filestr .= "$namespace\n\nclass {$classname}Base extends $extends\n{\n";
                    } else {
                        $filestr .= "$namespace\n\nclass {$classname}Base extends $extends\n{\n";
                    }
                }
            } else {
                if ($createBase != true) {
                    $filestr .= "$namespaceclass $classname\n{\n";
                } else {
                    $filestr .= "$namespaceclass {$classname}{$baseSuffix}\n{\n";
                }
            }

            // export class variables
            foreach ($tblfields as $f) {
                $filestr .= "    public \${$f};\n";
            }

            // export class fields list
            $filestr .= "    public \$_table = '$tblname';\n";
            $filestr .= "    public \$_primarykey = '$tblkey';\n";
            $filestr .= "    public \$_fields = array('" . implode($tblfields, "','") . "');\n";
            $filestr .= "}\n?>";

            // write content
            if ($createBase != true) {
                if ($fileManager->create($path . "$classname.php", $filestr, 'w+')) {
                    echo "<span style=\"font-size:100%;font-family: 'Courier New', Courier, monospace;\"><span style=\"color:#fff;\">Model for table </span><strong><span style=\"color:#e7c118;\">$tblname</span></strong><span style=\"color:#fff;\"> generated. File - </span><strong><span style=\"color:#729fbe;\">$classname</span></strong><span style=\"color:#fff;\">.php</span></span><br/><br/>";
                } else {
                    echo "<span style=\"font-size:100%;font-family: 'Courier New', Courier, monospace;\"><span style=\"color:#f00;\">Model for table </span><strong><span style=\"color:#e7c118;\">$tblname</span></strong><span style=\"color:#f00;\"> could not be generated. File - </span><strong><span style=\"color:#729fbe;\">$classname</span></strong><span style=\"color:#f00;\">.php</span></span><br/><br/>";
                }

            } else {
                if ($fileManager->create($path . "base/{$classname}{$baseSuffix}.php", $filestr, 'w+')) {
                    echo "<span style=\"font-size:100%;font-family: 'Courier New', Courier, monospace;\"><span style=\"color:#fff;\">Base model for table </span><strong><span style=\"color:#e7c118;\">$tblname</span></strong><span style=\"color:#fff;\"> generated. File - </span><strong><span style=\"color:#729fbe;\">{$classname}{$baseSuffix}</span></strong><span style=\"color:#fff;\">.php</span></span><br/><br/>";
                    $clsfile = $path . "$classname.php";
                    if (!file_exists($clsfile)) {
                        $filestr = "<?php\n\nclass $classname extends {$classname}{$baseSuffix}{\n}\n?>";
                        if ($fileManager->create($clsfile, $filestr, 'w+')) {
                            $clsExtendedNum++;
                            echo "<span style=\"font-size:100%;font-family: 'Courier New', Courier, monospace;\"><span style=\"color:#fff;\">Model for table </span><strong><span style=\"color:#e7c118;\">$tblname</span></strong><span style=\"color:#fff;\"> generated. File - </span><strong><span style=\"color:#729fbe;\">$classname</span></strong><span style=\"color:#fff;\">.php</span></span><br/><br/>";
                        } else {
                            echo "<span style=\"font-size:100%;font-family: 'Courier New', Courier, monospace;\"><span style=\"color:#f00;\">Model for table </span><strong><span style=\"color:#e7c118;\">$tblname</span></strong><span style=\"color:#f00;\"> could not be generated. File - </span><strong><span style=\"color:#729fbe;\">$classname</span></strong><span style=\"color:#f00;\">.php</span></span><br/><br/>";
                        }
                    }
                } else {
                    echo "<span style=\"font-size:100%;font-family: 'Courier New', Courier, monospace;\"><span style=\"color:#f00;\">Base model for table </span><strong><span style=\"color:#e7c118;\">$tblname</span></strong><span style=\"color:#f00;\"> could not be generated. File - </span><strong><span style=\"color:#729fbe;\">{$classname}{$baseSuffix}</span></strong><span style=\"color:#f00;\">.php</span></span><br/><br/>";
                }
            }
        }

        $total = sizeof($tables) + $clsExtendedNum;
        echo "<span style=\"font-size:100%;font-family: 'Courier New', Courier, monospace;color:#fff;\">Total " . $total . " file(s) generated.</span></body></html>";
    }

}
