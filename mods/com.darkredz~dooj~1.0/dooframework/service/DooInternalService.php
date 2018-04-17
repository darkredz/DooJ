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

    public function executePreCallHook($serviceName, $params, $donePreCallExecute)
    {
        if (isset($this->serviceParams['precallHook']) && is_callable($this->serviceParams['precallHook'])) {
            $this->serviceParams['precallHook']($serviceName, $this->serviceProvider, $params, $donePreCallExecute);
        } else {
            $donePreCallExecute(true);
        }
    }

    public function callWithArgsArray($params)
    {
        $serviceName = \array_shift($params);
        $this->executePreCallHook($serviceName, $params, function ($shouldContinue) use ($serviceName, $params) {
            if ($shouldContinue !== false) {
                call_user_func_array([$this->serviceProvider, $serviceName], $params);
            }
        });
    }

    public function call($serviceName)
    {
        $params = \func_get_args();
        $serviceName = \array_shift($params);
        $this->executePreCallHook($serviceName, $params, function ($shouldContinue) use ($serviceName, $params) {
            if ($shouldContinue !== false) {
                call_user_func_array([$this->serviceProvider, $serviceName], $params);
            }
        });
    }
}