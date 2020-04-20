<?php

interface DooServiceInterface
{
    public function initService($params);

    public function call($serviceName);

    public function callWithArgsArray($params);

    public function setPreCallHook($callback);

    public function executePreCallHook($serviceName, $params, $donePreCallExecute);
}