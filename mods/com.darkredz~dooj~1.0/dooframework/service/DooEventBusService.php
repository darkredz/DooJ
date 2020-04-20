<?php

class DooEventBusService implements \DooServiceInterface
{

    public $app;
    public $serviceProvider;
    public $serviceParams = [];

    /**
     * @var DooApiCaller
     */
    public $api;
    public $config = [];

    function __construct(DooAppInterface $app, $serviceProvider, array $config)
    {
        $this->app = $app;
        $this->config = $config;
        $this->serviceProvider = $serviceProvider;
    }

    public function setPreCallHook($callback)
    {
        $this->serviceParams['precallHook'] = $callback;
    }

    public function executePreCallHook($serviceName, $params) {
        if (isset($this->serviceParams['precallHook']) && is_callable($this->serviceParams['precallHook'])) {
            return $this->serviceParams['precallHook']($serviceName, $this->serviceProvider, $params);
        }
    }

    public function initService($params = null)
    {
        $this->serviceParams = $params;

        $this->api = new \DooApiCaller();
        $this->api->app = &$this->app;
        $this->api->apiKey($this->config['apiKey'])
            ->address($this->config['address']);

        if (!empty($this->config['timeout'])) {
            $this->api->timeout($this->config['timeout']);
        } else {
            $this->api->timeout(30 * 1000);
        }

        if (isset($this->config['debug'])) {
            $this->api->setDebug($this->config['debug']);
        }
    }


    public function callWithArgsArray($params)
    {
        $serviceName = \array_shift($params);
        $callbackFunc = \array_pop($params);
        $this->sendToEndpoint($serviceName, $params, $callbackFunc);
    }

    public function call($serviceName)
    {
        $params = \func_get_args();
        $serviceName = \array_shift($params);
        $callbackFunc = \array_pop($params);
        $this->sendToEndpoint($serviceName, $params, $callbackFunc);
    }

    protected function sendToEndpoint($serviceName, $params, $callbackFunc)
    {
        $params = ['serviceName' => $serviceName, 'params' => $params];

        $this->api->post($this->config['path'], $params, function ($msg) use ($callbackFunc) {
            if (isset($msg['error'])) {
                $callbackFunc(false);
                return;
            }
            $callbackFunc($msg['result']);
        });
    }
}