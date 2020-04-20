<?php
/**
 * Example Mock for a DooEventBusApp or DooWebApp, mocks app, request, response
 */

$appMock = \ArrMock::create('DooWebApp');
$logger = \LoggerFactory::getLogger("appMock");

$appMock->method('logDebug')->handle(function ($args) use ($logger) {
    $logger->debug($args[0]);
});
$appMock->method('logInfo')->handle(function ($args) use ($logger) {
    $logger->info($args[0]);
});
$appMock->method('logError')->handle(function ($args) use ($logger) {
    $logger->error($args[0]);
});
$appMock->method('trace')->handle(function ($args) use ($logger) {
    $logger->debug(print_r($args[0], true));
});

$appMock->conf = new stdClass();
$appMock->conf->APP_URL = 'http://localhost';