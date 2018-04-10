<?php
/**
 * Created by IntelliJ IDEA.
 * User: leng
 * Date: 1/21/17
 * Time: 4:02 AM
 */


class DooEventRequestHeader
{

    protected $headers;

    function __construct($headers)
    {
        $this->headers = $headers;
    }

    public function fromArray($arr)
    {
        return $this->headers = $arr;
    }

    public function getArray()
    {
        return $this->headers;
    }

    public function get($key)
    {
        return $this->headers[$key];
    }

    public function set($key, $value)
    {
        return $this->headers[$key] = $value;
    }

}