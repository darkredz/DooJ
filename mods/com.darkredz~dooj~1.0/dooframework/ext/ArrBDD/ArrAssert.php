<?php
/**
 * Created by IntelliJ IDEA.
 * User: leng
 * Date: 08/04/2018
 * Time: 11:49 PM
 */

class ArrAssert
{
    protected $assertionResults = [];
    protected $var;

    function __construct($var)
    {
        $this->var = $var;
    }

    public static function check($var)
    {
        $assert = new ArrAssert($var);
        return $assert;
    }

    // -------------- Assertion functions ----------------
    public function arrayHas($field)
    {
        if (is_null($this->var)) {
            $this->assertionResults[] = false;
            return $this;
        }

        if (is_array($field)) {
            $ok = true;
            foreach ($field as $f) {
                if (!array_key_exists($f, $this->var)) {
                    $ok = false;
                    break;
                }
            }
            $this->assertionResults[] = $ok;
        } else {
            $this->assertionResults[] = array_key_exists($field, $this->var);
        }
        return $this;
    }

    public function arrayHasType($field)
    {
        if (is_null($this->var)) {
            $this->assertionResults[] = false;
            return $this;
        }

        if (is_array($field)) {
            $ok = true;
            foreach ($field as $f => $type) {
                if (is_string($type)) {
                    if (!array_key_exists($f, $this->var) || gettype($this->var[$f]) !== $type) {
                        $ok = false;
                        break;
                    }
                } else {
                    if (is_array($type)) {
                        foreach ($type as $ty) {
                            if (!array_key_exists($f, $this->var) || gettype($this->var[$f]) !== $ty) {
                                $ok = false;
                                break 2;
                            }
                        }
                    } else {
                        if (!array_key_exists($f, $this->var)) {
                            $ok = false;
                            break;
                        }
                    }
                }
            }
            $this->assertionResults[] = $ok;
        } else {
            $this->assertionResults[] = array_key_exists($field, $this->var);
        }
        return $this;
    }

    public function arrayHasEq($field)
    {
        if (is_null($this->var)) {
            $this->assertionResults[] = false;
            return $this;
        }

        if (is_array($field)) {
            $ok = true;
            foreach ($field as $f => $val) {
                if (!array_key_exists($f, $this->var) || $this->var[$f] !== $val) {
                    $ok = false;
                    break;
                }
            }
            $this->assertionResults[] = $ok;
        } else {
            $this->assertionResults[] = array_key_exists($field, $this->var);
        }
        return $this;
    }

    public function eq($val)
    {
        $this->assertionResults[] = $this->var === $val;
        return $this;
    }

    public function isArray()
    {
        $this->assertionResults[] = is_array($this->var);
        return $this;
    }

    public function isArraySize($size)
    {
        $this->assertionResults[] = is_array($this->var) && sizeof($this->var) === $size;
        return $this;
    }

    public function isArrayWithinSize($size)
    {
        $this->assertionResults[] = is_array($this->var) && sizeof($this->var) <= $size;
        return $this;
    }

    public function size($size)
    {
        $this->assertionResults[] = sizeof($this->var) === $size;
        return $this;
    }

    public function sizeMoreThan($size)
    {
        $this->assertionResults[] = sizeof($this->var) > $size;
        return $this;
    }

    public function sizeLessThan($size)
    {
        $this->assertionResults[] = sizeof($this->var) < $size;
        return $this;
    }

    public function sizeMoreThanEq($size)
    {
        $this->assertionResults[] = sizeof($this->var) >= $size;
        return $this;
    }

    public function sizeLessThanEq($size)
    {
        $this->assertionResults[] = sizeof($this->var) <= $size;
        return $this;
    }

    public function moreThan($size)
    {
        $this->assertionResults[] = $this->var > $size;
        return $this;
    }

    public function lessThan($size)
    {
        $this->assertionResults[] = $this->var < $size;
        return $this;
    }

    public function moreThanEq($size)
    {
        $this->assertionResults[] = $this->var >= $size;
        return $this;
    }

    public function lessThanEq($size)
    {
        $this->assertionResults[] = $this->var <= $size;
        return $this;
    }

    public function result()
    {
        foreach ($this->assertionResults as $result) {
            if (!$result) {
                return false;
            }
        }
        return true;
    }
}