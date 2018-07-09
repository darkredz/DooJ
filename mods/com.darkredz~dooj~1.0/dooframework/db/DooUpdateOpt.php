<?php
/**
 * Created by IntelliJ IDEA.
 * User: leng
 * Date: 01/05/2018
 * Time: 9:04 PM
 */

class DooUpdateOpt
{
    protected $options = [];

    public static function make()
    {
        return new DooUpdateOpt();
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

    public function field($arr)
    {
        $this->options['field'] = $arr;
        return $this;
    }

    public function param($arr)
    {
        $this->options['param'] = $arr;
        return $this;
    }

}