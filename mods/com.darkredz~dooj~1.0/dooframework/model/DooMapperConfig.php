<?php
/**
 * Created by IntelliJ IDEA.
 * User: leng
 * Date: 2/17/17
 * Time: 4:44 PM
 */

class DooMapperConfig
{
    protected $tableInfo = [];
    protected $latestTable;
    protected $defaultPrimaryKey;
    protected $autoRemoveLinkKey;

    function __construct($defaultPrimaryKey = null, $autoRemoveLinkKey = false)
    {
        $this->defaultPrimaryKey = $defaultPrimaryKey;
        $this->autoRemoveLinkKey = $autoRemoveLinkKey;
    }

    public function setDefaultPrimaryKey($defaultPrimaryKey)
    {
        $this->defaultPrimaryKey = $defaultPrimaryKey;
        return $this;
    }

    public function setAutoRemoveLinkKey($autoRemoveLinkKey)
    {
        $this->autoRemoveLinkKey = $autoRemoveLinkKey;
        return $this;
    }

    public function table($tableName, $defaultPrimaryKey = null)
    {
        $this->tableInfo[$tableName] = [];
        $this->latestTable = $tableName;
        if (!empty($defaultPrimaryKey)) {
            $this->primaryKey($defaultPrimaryKey);
        } else {
            if ($this->defaultPrimaryKey) {
                $this->primaryKey($this->defaultPrimaryKey);
            }
        }
        return $this;
    }

    public function &currentTable()
    {
        return $this->tableInfo[$this->latestTable];
    }

    public function removeLinkKey()
    {
        return $this->remove($this->currentTable()['foreign_key']);
    }

    public function remove($fieldName)
    {
        $table = &$this->currentTable();
        if (empty($table['remove'])) {
            $table['remove'] = [];
        }
        if (is_array($fieldName)) {
            $table['remove'] = array_merge($table['remove'], $fieldName);
        } else {
            $table['remove'][] = $fieldName;
        }
        return $this;
    }

    public function primaryKey($fieldName)
    {
        $table = &$this->currentTable();
        $table['key'] = $fieldName;
        return $this;
    }

    public function rename($tableName)
    {
        $table = &$this->currentTable();
        $table['rename'] = $tableName;
        return $this;
    }

    public function foreignKey($fieldName)
    {
        $table = &$this->currentTable();
        $table['foreign_key'] = $fieldName;
        if ($this->autoRemoveLinkKey) {
            return $this->removeLinkKey();
        }
        return $this;
    }

    public function oneToOne($bool)
    {
        $table = &$this->currentTable();
        $table['one_to_one'] = $bool;
        return $this;
    }

    public function manyToMany($bool)
    {
        $table = &$this->currentTable();
        $table['many_to_many'] = $bool;
        return $this;
    }

    public function oneToMany($bool)
    {
        $table = &$this->currentTable();
        $table['one_to_many'] = $bool;
        return $this;
    }

    public function manyToOne($bool)
    {
        $table = &$this->currentTable();
        $table['many_to_one'] = $bool;
        return $this;
    }


    public function moveUnder($fieldName)
    {
        $table = &$this->currentTable();
        $table['move_under'] = $fieldName;
        return $this;
    }


    public function toArrayMap()
    {
        $this->tableInfo = $this->sortTableInfo($this->tableInfo);
        return $this->tableInfo;
    }

    public function getTableInfo()
    {
        $this->tableInfo = $this->sortTableInfo($this->tableInfo);
        return $this->tableInfo;
    }

    protected function sortTableInfo($tables)
    {
        $tableList = \array_keys($tables);
        $firstTable = $tableList[0];

        foreach ($tableList as $selfTblIndex => $tbl) {
            $moveUnderTbl = $tables[$tbl]['move_under'];

            //no need to sort if linked table is the root table
            if (!empty($moveUnderTbl) && $moveUnderTbl != $firstTable) {

                if (!empty($tables[$tbl]['rename'])) {
                    $moveUnderTbl = $tables[$tbl]['rename'];
                }

                $linkTblIndex = \array_search($moveUnderTbl, $tableList);
                //if move_under table was before the table(self) to link to, then resort, move it before
                if ($selfTblIndex > $linkTblIndex) {
                    //first table remains the same
                    $tables = [$tbl => $tables[$tbl]] + $tables;
                    $tables = [$firstTable => $tables[$firstTable]] + $tables;
                    return $this->sortTableInfo($tables);
                }
            }
        }
        return $tables;
    }

}