<?php
/**
 * Created by IntelliJ IDEA.
 * User: leng
 * Date: 12/21/16
 * Time: 8:24 AM
 */

class DooDataMapper
{

    public function map($tables, $arr, $delimiter = '-', $nested = true, $objectListWithID = false)
    {
        $flattenResult = [];

        foreach ($tables as $tblAlias => $tblInfo) {
            $tblPrimeKey = $tblInfo['key'];

            if ($nested == true) {
                $tblRename = $tblAlias;
            } else {
                if (!empty($tblInfo['rename'])) {
                    $tblRename = $tblInfo['rename'];
                } else {
                    $tblRename = $tblAlias;
                }
            }

            $tblRows = [];
//            if (empty($list)) {
//                $flattenResult[$tblRename] = $tblRows;
//            }
//            else {
            $tblRows = $flattenResult[$tblRename];
//            }

            //map all row fields value to the appropriate table object based on the prefix.
            foreach ($arr as $row) {
                $rowPrimeKeyName = $tblAlias . $delimiter . $tblPrimeKey;
//                $tables[$tblAlias]['single'] = ($tblInfo['one_to_many'] || $tblInfo['one_to_one']);
                $tables[$tblAlias]['multiple_link'] = ($tblInfo['many_to_many'] || $tblInfo['one_to_many']);

                //if table is not many to many, dun care about the prime key, as rows of same id is reused to link between two tables
                if (!$tables[$tblAlias]['multiple_link']) {
                    $primeKeyVal = $row[$rowPrimeKeyName];

                    //if this table row already exists => check with primary key. then skip this row check for this table type.
                    if ($primeKeyVal == null || !empty($tblRows[$primeKeyVal])) {
                        continue;
                    }
                }

                //map all related fields to the table attribute
                $obj = [];
                foreach ($row as $field => $vaue) {
                    $fieldParts = explode($delimiter, $field);
                    $prefix = $fieldParts[0];
                    if ($prefix != $tblAlias) {
                        continue;
                    }

                    $attr = $fieldParts[1];
                    //exclude field in remove list
                    if (!$nested && !empty($tables[$tblAlias]['remove']) && in_array($attr,
                            $tables[$tblAlias]['remove'])) {
                        continue;
                    }
                    $obj[$attr] = $vaue;
                }

                if (!$tables[$tblAlias]['multiple_link']) {
                    $tblRows[$primeKeyVal] = $obj;
                } else {
                    $tblRows[sizeof($tblRows)] = $obj;
                }
            }

            $flattenResult[$tblRename] = $tblRows;
        }

        if (!$nested) {
            return $flattenResult;
        }

        if ($nested) {
            $tablesRenamed = [];
            foreach ($tables as $key => $va) {
                if (!empty($va['rename'])) {
//                        unset($va['rename']);
                    //also need to rename the move_under field if got rename set
                    if (!empty($tables[$va['move_under']]['rename'])) {
                        $va['move_under'] = $tables[$va['move_under']]['rename'];
                    }
                    $tablesRenamed[$va['rename']] = $va;
                }
            }

            if (!empty($tablesRenamed)) {
                //rename result table key to the renamed version
                foreach ($flattenResult as $key => $va) {
                    if (!empty($tables[$key]) && !empty($tables[$key]['rename'])) {
                        $flattenResult[$tables[$key]['rename']] = $va;
                        unset($flattenResult[$key]);
                    }
                }

                $tables = $tablesRenamed;
            }

            //make a list of nested objects, move objects under each other appropriately based on their relationships
            foreach ($tables as $tbl => $tblInfo) {
                if (!empty($tblInfo['move_under'])) {
                    $moveToTbl = &$flattenResult[$tblInfo['move_under']];

                    foreach ($moveToTbl as &$rows) {
                        // just move the original table result list/single, and park under the linked table object, no logic. remove them that are not there later
                        $oriTblResult = $flattenResult[$tbl];
                        $primeKeyName = $tables[$tbl]['key'];
                        $foreignKey = $rows[$primeKeyName];

                        if (!empty($tblInfo['foreign_key'])) {
                            $fk = $tblInfo['foreign_key'];
                            //loop self table and check if can link to the foreign table based on key => foreign key
//                            $selfTable = $flattenResult[$tbl];

                            //for many (many to many, one to many), remove those with same primary key (grouped since rows from query has a lot of same duplicates)
                            //many results are all stored, without group
                            if ($tblInfo['many_to_many'] || $tblInfo['one_to_many']) {
                                //remove those that are not suppose to linked with the foreign table result attr.
//                                $rows[$tbl] = $oriTblResult;
                                $manyResults = [];
                                foreach ($oriTblResult as $linkRow) {
                                    if ($foreignKey != $linkRow[$fk]) {
                                        continue;
                                    }
                                    $linkRowPrimeKey = $linkRow[$primeKeyName];
                                    if ($manyResults[$linkRowPrimeKey]) {
                                        continue;
                                    }

                                    //exclude field in remove list
                                    if (!empty($tables[$tbl]['remove'])) {
                                        foreach ($tables[$tbl]['remove'] as $fieldToRemove) {
                                            unset($linkRow[$fieldToRemove]);
                                        }
                                    }

                                    $manyResults[$linkRowPrimeKey] = $linkRow;
                                }
                                $rows[$tbl] = $manyResults;
                            } else {
                                if ($tblInfo['many_to_one'] || $tblInfo['one_to_one']) {
                                    $rows[$tbl] = $oriTblResult + [];
                                    //remove those that are not suppose to linked with the foreign table result attr.
                                    foreach ($rows[$tbl] as $linkRowKey => &$linkRow) {
                                        if ($foreignKey != $linkRow[$fk]) {
                                            unset($rows[$tbl][$linkRowKey]);
                                        }

                                        //exclude field in remove list
                                        if (!empty($tables[$tbl]['remove'])) {
                                            foreach ($tables[$tbl]['remove'] as $fieldToRemove) {
                                                unset($linkRow[$fieldToRemove]);
                                            }
                                        }
                                    }
                                }
                            }

                            //if the array is empty then just set as null
                            if (empty($rows[$tbl])) {
                                $rows[$tbl] = null;
                            } else {
                                //if only one is set, the attribute should be just the object itself instead of an array of one object.
                                if ($tblInfo['one_to_many'] || $tblInfo['one_to_one']) {
//                                  $rows[$tbl] = reset($oriTblResult);
                                    $rows[$tbl] = reset(array_values($rows[$tbl]));

                                    //exclude field in remove list
//                                  if (!empty($tables[$tbl]['remove'])) {
//                                      foreach ($tables[$tbl]['remove'] as $fieldToRemove) {
//                                          unset($rows[$tbl][$fieldToRemove]);
//                                      }
//                                  }
                                } else {
                                    if (!$objectListWithID) {
                                        if (\is_array($rows[$tbl])) {
                                            $rows[$tbl] = array_values($rows[$tbl]);
                                        } else {
                                            if (!$rows[$tbl]) {
                                                $rows[$tbl] = null;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                //exclude field in remove list, this is for those that are not moved under, since moved under are already duplicated nested in.
                if (!empty($tblInfo['remove'])) {
                    $tblToChk = &$flattenResult[$tbl];
                    foreach ($tblToChk as &$rows) {
                        foreach ($rows as $attr => $va) {
                            if (in_array($attr, $tblInfo['remove'])) {
                                unset($rows[$attr]);
                            }
                        }
                    }
                }
            }

        }

        //get the root table result since everything now is nested into objects.
        $resultFinal = reset($flattenResult);
        if (!$objectListWithID) {
            $resultFinal = array_values($resultFinal);
        }
        return $resultFinal;
    }
}