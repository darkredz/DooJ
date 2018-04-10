<?php

interface DooAppInterface
{

    public function run();

    public function autorun();

    public function routeTo();

    public function reroute($routeUri, $is404 = false);

    public function redirect($url);

    public function throwHeader($code);

    public function end($output = null);

    public function endBlock($result);

    public function setHeader($name, $content);

    public function trace($obj);

    public function logInfo($msg);

    public function logError($msg);

    public function logDebug($msg);
}