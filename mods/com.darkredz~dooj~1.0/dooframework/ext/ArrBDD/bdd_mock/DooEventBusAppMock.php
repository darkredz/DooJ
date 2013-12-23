<?php
/**
 * Example Mock for a DooEventBusApp or DooWebApp, mocks app, request, response
 */
$anon = function($args){};

$respMock = ArrMock::create('DooEventBusResponse');
$respMock->ignoreNonExistentMethod = true;
$respMock->headers = [];
$respMock->method('putHeader')->handle(function($args) use ($respMock){
    list($key, $val) = $args;
    $respMock->headers[$key] = $val;
});

$reqMock = ArrMock::create('DooEventBusRequest');
$reqMock->ignoreNonExistentMethod = true;
$reqMock->response = $respMock;


// Mock App
$appMock = ArrMock::create('DooEventBusApp');
//$appMock->ignoreNonExistentMethod = true;
$appMock->varDumpException = true;

$appMock->method('setHeader')->handle($anon);

$appMock->method('logDebug')->handle(function($args){
    $this->app->logDebug($args[0]);
});
$appMock->method('logInfo')->handle(function($args){
    $this->app->logInfo($args[0]);
});
$appMock->method('trace')->handle(function($args){
    $this->app->trace($args[0]);
});

$appEndFunc = function($args) use ($appMock) {
    list($output) = $args;
    $appMock->endOutput = $output;
    $appMock->headers = $appMock->request->response->headers;
};

$appMock->method('end')->handle($appEndFunc);

$appMock->statusCode = 200;
$appMock->request = $reqMock;