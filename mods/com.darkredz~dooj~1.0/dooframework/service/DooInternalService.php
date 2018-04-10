<?php

class DooInternalService implements \DooServiceInterface
{
    public $app;
    public $serviceProvider;
    public $serviceParams = [];

    function __construct(DooAppInterface $app, $serviceProvider)
    {
        $this->app = $app;
        $this->serviceProvider = $serviceProvider;
    }

    public function initService($params = null)
    {
        $this->serviceParams = $params;
    }

    public function setPreCallHook($callback)
    {
        $this->serviceParams['precallHook'] = $callback;
    }

    public function executePreCallHook($serviceName, $params)
    {
        if (isset($this->serviceParams['precallHook']) && is_callable($this->serviceParams['precallHook'])) {
            return $this->serviceParams['precallHook']($serviceName, $this->serviceProvider, $params);
        }
    }

    public function callWithArgsArray($params)
    {
        $serviceName = \array_shift($params);
        $shouldContinue = $this->executePreCallHook($serviceName, $params);
        if ($shouldContinue !== false) {
            call_user_func_array([$this->serviceProvider, $serviceName], $params);
        }
    }

    public function call($serviceName)
    {
        $params = \func_get_args();
        $serviceName = \array_shift($params);
        $shouldContinue = $this->executePreCallHook($serviceName, $params);
        if ($shouldContinue !== false) {
            call_user_func_array([$this->serviceProvider, $serviceName], $params);
        }
    }
}