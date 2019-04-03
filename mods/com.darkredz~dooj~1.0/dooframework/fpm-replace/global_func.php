<?php

function getVertx()
{
    return new stdClass();
}

function g($func){
    return $func();
}

function v($func){
    return $func();
}

function a($func){
    return $func();
}

function av($func){
    return $func();
}

function jfrom($json){
    return $json;
}

function jto($arr){
    return $arr;
}

function jtoarr($arr){
    return $arr;
}

function arrval($array, $key) {
    if ($array == null) {
        return null;
    }
    return (array_key_exists($key, $array)) ? $array[$key] : null;
}

function requireAll($path)
{
    $files = scandir($path);
    foreach ($files as $f) {
        if (strpos($f, '.php') > -1) {
            require_once $path . $f;
        }
    }
}
