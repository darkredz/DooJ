<?php
/**
 * AppFaker to provide some useful methods mock api calls/http client, etc
 * User: leng
 * Date: 1/1/14
 * Time: 10:43 PM
 */

trait AppFaker {

    public function postReq($appMock, $postData, $controller, $method, $api = null, $getData = null ){
        $appMock->_SERVER['REQUEST_METHOD'] = 'POST';
        $appMock->_POST = $postData;
        if($api){
            $api->app = &$appMock;
        }

        $controller = '\\booster\\module\\api\\controller\\' . $controller;
        $uc = new $controller();
        $uc->app = &$appMock;

        if(!empty($appMock->_POST))
            $uc->_POST = &$appMock->_POST;

        if(!empty($appMock->_GET))
            $uc->_GET = &$appMock->_GET;

        $uc->api = $api;

        if(!empty($getData)){
            $getParams = [];
            foreach($getData as $k=>$v){
                $getParams[] = $k;
                $getParams[] = $v;
            }
            $uc->params = $getParams;
        }

        $uc->beforeRun($controller, $method);
        $uc->{$method}();
        return $uc;
    }

    public function getReq($appMock, $getData, $controller, $method, $api = null ){
        $appMock->_SERVER['REQUEST_METHOD'] = 'GET';
        if($api){
            $api->app = &$appMock;
        }

        $getParams = [];
        foreach($getData as $k=>$v){
            $getParams[] = $k;
            $getParams[] = $v;
        }

        $controller = '\\booster\\module\\api\\controller\\' . $controller;
        $uc = new $controller();
        $uc->app = &$appMock;
        $uc->api = $api;
        $uc->params = $getParams;
        $uc->beforeRun($controller, $method);
        $uc->{$method}();
        return $uc;
    }

    public function apiPostHandler($apiEntries, $apiCallerMock = null, $defaultError = null){
        $this->apiHandler('post', $apiEntries, $apiCallerMock, $defaultError);
    }

    public function apiGetHandler($apiEntries, $apiCallerMock = null, $defaultError = null){
        $this->apiHandler('get', $apiEntries, $apiCallerMock , $defaultError);
    }

    public function apiPutHandler($apiEntries, $apiCallerMock = null, $defaultError = null){
        $this->apiHandler('put', $apiEntries, $apiCallerMock , $defaultError);
    }

    public function apiDeleteHandler($apiEntries, $apiCallerMock = null, $defaultError = null){
        $this->apiHandler('delete', $apiEntries, $apiCallerMock , $defaultError);
    }

    /**
     * Mock Api Caller callback
     * @param string $method Method for the api called. Supported are get, post, put, delete
     * @param array $apiEntries An array of URI with its details for API caller
     * <code>
     * $apiEntries = [
     *     [
     *         'uri' => '/tax-rate',
     *         'fields' => ['shipToZip' => '123456'],
     *         'result' => '{"statusCode":400,"error":{"error":"Invalid field data","errorList":{"zip":"Invalid zip code"}}}'
     *     ],
     *     [
     *         'uri' => '/tax-rate',
     *         'fields' => ['shipToZip' => '90210'],
     *         'result' => '{"postal_code":"90210","state":"California","state_code":"CA","tax_rate":0.0}'
     *     ]
     * ]
     * </code>
     * @param ArrMock $apiCallerMock Mock object for api caller (api through event bus). If passed in, handler will be auto set to the closure return
     * @param string $defaultError Default error message when no http entry is matched for the test
     * @return callable Returns a mocked closure for the API caller handler. $this->apiGet($uri, $handler);
     */
    public function apiHandler($method, $apiEntries, $apiCallerMock = null, $defaultError = null){
        if($defaultError===null){
            $defaultError = '{"error":"No api entry was matched for the test"}';
        }

        $postFunc = function($args) use ($apiEntries, $defaultError){
            list($uri, $data, $callback) = $args;

            foreach($apiEntries as $entry){
                if($uri==$entry['uri'] && @json_encode($data)==@json_encode($entry['fields'])){
                    if(is_string($entry['result'])){
                        $res = json_decode($entry['result'], true);
                    }
                    else{
                        $res = $entry['result'];
                    }

                    if($res['statusCode'] > 299){
                        $callback($res);
                    }
                    else{
                        $callback($res);
                    }
                    return;
                }
            }
            //if not found, show default error
            var_dump($defaultError);
//            $callback(json_decode($defaultError, true));
        };

        if($apiCallerMock){
            $apiCallerMock->method($method)->handle($postFunc);
        }

        return $postFunc;
    }

    /**
     * @param array $httpEntries An array of URI with its details:
     * <code>
     * $httpEntries = [
     *     [
     *         'uri' => '/post_to_me',
     *         'requestBody' => 'name=Leng&age=26&gender=male',
     *         'status' => 200,
     *         'result' => "User leng found"
     *     ],
     *     [
     *         'uri' => '/tax_rate/?postal_code=123456',
     *         'requestBody' => null,
     *         'status' => 404,
     *         'result' => "Postal Code '123456' could not be found."
     *     ],
     *     [
     *         'uri' => '/tax_rate/?postal_code=90210',
     *         'requestBody' => null,
     *         'status' => 200,
     *         'result' => '{"postal_code":"90210","state":"California","state_code":"CA","tax_rate":0.0}'
     *     ]
     * ]
     * </code>
     * @param string $method Request method of the http post. Supported, get, getNow, post, put, delete, options, head
     * @param string $defaultError Default error message when no http entry is matched for the test
     * @return ArrMock Returns a Mocked HttpClient that will return status and result body based on the URI and requestBody matched
     */
    public function httpHandler($httpEntries, $method, $defaultError = null){
        if($defaultError==null){
            $defaultError = '{"error":"No http entry was matched for the test"}';
        }

        $hc = ArrMock::create('HttpClient');
        $hc->ignoreNonExistentMethod = true;

        $httpHandleFunc = function($args) use ($hc, $httpEntries, $defaultError){
            list($uri, $callback) = $args;

            //custom ink http returns
            $hr = ArrMock::create('HttpResponse');
            $hr->ignoreNonExistentMethod = true;


            //matches request based on URI and request body sent against http entries to return mock HTTP request body and status
            $data = $hc->httpBodyToSend;
            $res = null;
            $found = false;

            foreach($httpEntries as $entry){
                if($uri==$entry['uri'] && @json_encode($data)==@json_encode($entry['requestBody'])){
                    $res = $entry['result'];
                    $hr->method('statusCode')->returns($entry['status']);
                    $found = true;
                    break;
                }
            }

            if($found === false){
                var_dump($defaultError);
            }

            $bodyHandlerFunc = function($args) use ($hr, $httpEntries, $uri, $res){
                list($callback) = $args;
                $buffer = ArrMock::create('Buffer');
                $buffer->method('toString')->returns($res);
                $callback($buffer);

                return $hr;
            };
            $hr->method('bodyHandler')->handle($bodyHandlerFunc);

            $callback($hr);
            return $hc;
        };

        $hc->method($method)->handle($httpHandleFunc);
        $hc->method('putHeader')->autoChain();
        $hc->method('exceptionHandler')->autoChain();
        $hc->method('end')->handle(function($args) use ($hc){
            list($body) = $args;
            $hc->httpBodyToSend = $body;
        });

        return $hc;
    }

} 