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
        $convertKeys = [
            'authorization' => 'HTTP_AUTHORIZATION',
            'accept-language' => 'HTTP_ACCEPT_LANGUAGE',
            'accept-encoding' => 'HTTP_ACCEPT_ENCODING',
            'accept' => 'HTTP_ACCEPT',
            'cache-control' => 'HTTP_CACHE_CONTROL',
            'user-agent' => 'HTTP_USER_AGENT',
            'connection' => 'HTTP_CONNECTION',
            'host' => 'HTTP_HOST',
        ];

        if (array_key_exists($key, $this->headers)) {
            return $this->headers[$key];
        }

        if (in_array(strtolower($key), array_keys($convertKeys))) {
            $phpHeaderKey = $convertKeys[strtolower($key)];
            if (array_key_exists($phpHeaderKey, $this->headers)) {
                return $this->headers[$phpHeaderKey];
            }
        }
        return null;
    }

    public function set($key, $value)
    {
        return $this->headers[$key] = $value;
    }

}