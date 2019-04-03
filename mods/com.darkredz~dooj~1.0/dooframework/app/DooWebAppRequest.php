<?php
/**
 * DooEventBusRequest class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */


/**
 * DooEventBusRequest mimics HTTP requst API to allow easy conversion from a HTTP based app to eventbus.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @package doo.app
 * @since 2.0
 */
class DooWebAppRequest
{

    public $absoluteUri;
    public $uri;
    public $method;
    /**
     * @var DooEventRequestHeader
     */
    public $headerObj;
    public $body;
    public $remoteAddress;

    /**
     * @var DooEventBusResponse
     */
    public $response;

    function __construct()
    {
        $this->remoteAddress = (isset($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
        $this->headerObj = new DooEventRequestHeader($_SERVER);
        $this->uri = (isset($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : '/';
        $this->response = new DooWebAppResponse();
    }

    public function mutateUri($callback)
    {
        $this->uri = $callback($this->uri);
    }

    public function prepareCLI(DooAppInterface $app, $argv)
    {
        if (isset($argv[1])) {
            parse_str($argv[1], $serverVars);

            if (sizeof($serverVars) == 1 && array_values($serverVars)[0] == '') {
                $serverVars['REQUEST_URI'] = array_keys($serverVars)[0];
            }

            if (!empty($serverVars)) {
                $_SERVER = array_merge($_SERVER, $serverVars);
            }
        }

        if (empty($_SERVER['HTTP_HOST'])) {
            if (!empty($app->conf->VHOST)) {
                $vhosts = array_keys($app->conf->VHOST);
                $_SERVER['HTTP_HOST'] = $vhosts[sizeof($vhosts) - 1];
            } else {
                $_SERVER['HTTP_HOST'] = 'localhost';
            }
        }

        if (empty($_SERVER['REQUEST_METHOD'])) {
            $_SERVER['REQUEST_METHOD'] = 'GET';
        }

        if (empty($_SERVER['REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = '/';
        } else {
            $urlData = parse_url($_SERVER['REQUEST_URI']);
            if (!empty($urlData['query'])) {
                 parse_str($urlData['query'], $input);
                if ($_SERVER['REQUEST_METHOD'] == 'GET') {
                    $_GET = $input;
                } else {
                    $_POST = $input;
                }
            }
        }
    }

    public function response()
    {
        return $this->response;
    }

    public function params()
    {
        $q = explode('?', $this->uri(), 2);
        if (sizeof($q) < 2) {
            return [];
        }
        $q = $q[1];
        parse_str($q, $queryVars);
//        Vertx::logger()->info( var_export($queryVars, true) );
        return $queryVars;
    }

    public function createHeader($arr)
    {
        $this->headerObj = new DooEventRequestHeader($arr);
    }

    public function headers()
    {
        return $this->headerObj;
    }

    public function absoluteURI()
    {
        return (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$this->uri()}";
    }

    public function host()
    {
        return $_SERVER['HTTP_HOST'];
    }

    public function path()
    {
        return $this->uri;
    }

    public function uri()
    {
        return $this->uri;
    }

    public function method()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function remoteAddress()
    {
        return new class {
            public function host() {
                return $_SERVER['REMOTE_ADDR'];
            }
        };
    }

    public function version()
    {
        return $_SERVER['HTTP_CONNECTION'];
    }

    public function setExpectMultipart($expect)
    {
    }

    public function uploadHandler($callback)
    {
        $callback();
    }

    public function handler($callback)
    {
        $callback();
    }

    public function endHandler($callback)
    {
        $callback();
    }

}