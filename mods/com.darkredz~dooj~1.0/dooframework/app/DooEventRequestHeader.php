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
    protected $headConverted;

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

    public function getArrayConvertCase()
    {
        if (empty($this->headConverted)) {
            foreach ($this->headers as $key => $value) {
                $newKey = strtolower($key);
                $newKeyParts = explode('_', $newKey);
                foreach ($newKeyParts as &$parts) {
                    $parts = ucfirst($parts);
                }
                $newKey = implode('-', $newKeyParts);
                $this->headConverted[$newKey] = $value;
            }
        }

        return $this->headConverted;
    }

    public function get($key)
    {
        if (array_key_exists($key, $this->headers)) {
            return $this->headers[$key];
        }
        return null;
    }

    public function set($key, $value)
    {
        return $this->headers[$key] = $value;
    }

}