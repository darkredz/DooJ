<?php
/**
 * Created by IntelliJ IDEA.
 * User: leng
 * Date: 01/05/2018
 * Time: 9:04 PM
 */

//where, limit, select, param, asc, desc, custom, asArray, groupby
class DooFindOpt
{
    protected $options = [];

    public static function make()
    {
        return new DooFindOpt();
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function where($arr)
    {
        $this->options['where'] = $arr;
        return $this;
    }

    public function limit($num)
    {
        $this->options['limit'] = $num;
        return $this;
    }

    public function select($arr)
    {
        $this->options['select'] = $arr;
        return $this;
    }

    public function param($arr)
    {
        $this->options['param'] = $arr;
        return $this;
    }

    public function asc($field)
    {
        $this->options['asc'] = $field;
        return $this;
    }

    public function desc($field)
    {
        $this->options['desc'] = $field;
        return $this;
    }

    public function asArray($bool)
    {
        $this->options['asArray'] = $bool;
        return $this;
    }

    public function groupBy($arr)
    {
        $this->options['groupby'] = $arr;
        return $this;
    }

    public function custom($arr)
    {
        $this->options['custom'] = $arr;
        return $this;
    }

}