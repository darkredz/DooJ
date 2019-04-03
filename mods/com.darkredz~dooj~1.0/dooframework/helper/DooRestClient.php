<?php
/**
 * DooRestClient class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */

/**
 * A REST client to make requests to 3rd party RESTful web services such as Twitter.
 *
 * <p>You can send GET, POST, PUT and DELETE requests with DooRestClient.
 * Method chaining is also supported by this class.</p>
 * Example usage:
 * <code>
 * //Example usage in a Doo Controller
 * //import the class and create the REST client object
 * $client = $this->load()->helper('DooRestClient', true);
 * $client->connectTo("http://twitter.com/direct_messages.xml")
 *        ->auth('twituser', 'password', true)
 *        ->get()
 *
 * if($client->isSuccess()){
 *      echo "<pre>Received content-type: {$client->resultContentType()}<br/>";
 *      print_r( $client->result() );
 * }else{
 *      echo "<pre>Request unsuccessful, error {$client->resultCode()} return";
 * }
 * </code>
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooRestClient.php 1000 2009-07-7 18:27:22
 * @package doo.helper
 * @since 1.0
 */
class DooRestClient
{
    protected $serverUrl;
    protected $curlOpt;
    protected $authUser;
    protected $authPwd;
    protected $args;
    protected $result;
    protected $headerCodeReceived;
    protected $contentTypeReceived;
    protected $headerSizeReceived;
    protected $contentSizeReceived;
    protected $doneCallback;
    protected $errorCallback;
    protected $callbackResultFormat;
    protected $callbackErrorFormat;
    protected $callbackResultFormatOpt = true;
    protected $callbackErrorFormatOpt = true;


    //for sending Accept header, this is to be used with requests to REST server which is built with Doo Framework
    const HTML = 'text/html';
    const XML = 'application/xml';
    const JSON = 'application/json';
    const JS = 'application/javascript';
    const CSS = 'text/css';
    const RSS = 'application/rss+xml';
    const YAML = 'text/yaml';
    const ATOM = 'application/atom+xml';
    const PDF = 'application/pdf';
    const TEXT = 'text/plain';
    const PNG = 'image/png';
    const JPG = 'image/jpeg';
    const GIF = 'image/gif';
    const CSV = 'text/csv';

    function __construct($serverUrl = null)
    {
        if ($serverUrl != null) {
            $this->serverUrl = $serverUrl;
        }
        $this->curlOpt = [];
    }

    protected function setCurlOptBeforeOperation($ch)
    {
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    }

    public function reset()
    {
        $this->serverUrl = null;
        $this->curlOpt = [];
        $this->authUser = null;
        $this->authPwd = null;
        $this->args = null;
        $this->result = null;
        $this->headerCodeReceived = null;
        $this->contentTypeReceived = null;
        $this->headerSizeReceived = null;
        $this->contentSizeReceived = null;
        $this->doneCallback = null;
        $this->errorCallback = null;
        $this->callbackResultFormat = null;
        $this->callbackErrorFormat = null;
        $this->callbackResultFormatOpt = true;
        $this->callbackErrorFormatOpt = true;
    }

    /**
     * Get/set the REST server URL
     * @param string $server_url
     * @return DooRestClient
     */
    public function connectTo($server_url = null)
    {
        $this->reset();
        if ($server_url == null) {
            return $this->serverUrl;
        }
        $this->serverUrl = $server_url;
        return $this;
    }

    /**
     * Check if a given URL exist.
     *
     * The url exists if the return HTTP code is 200
     * @param string $url Url of the page
     * @return boolean True if exists (200)
     */
    public static function checkUrlExist($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true); // set to HEAD request
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // don't output the response
        curl_exec($ch);
        $valid = curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200;
        curl_close($ch);
        return $valid;
    }

    /**
     * Send request to a URL and returns the HEAD request HTTP code.
     *
     * @param string $url Url of the page
     * @return int returns the HTTP code
     */
    public static function retrieveHeaderCode($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true); // set to HEAD request
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // don't output the response
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code;
    }

    /**
     * Get/set the connection timeout duration (seconds)
     * @param int $sec Timeout duration in seconds
     * @return DooRestClient
     */
    public function timeout($sec = null)
    {
        if ($sec === null) {
            return $this->curlOpt['CONNECTTIMEOUT'];
        } else {
            $this->curlOpt['CONNECTTIMEOUT'] = $sec;
        }
        return $this;
    }

    /**
     * Get/set data for the REST request.
     *
     * The data can either be a string of params <b>id=19&year=2009&filter=true</b>
     * or an assoc array <b>array('id'=>19, 'year'=>2009, 'filter'=>true)</b>
     *
     * <p>The data is returned when no data value is passed into the method.</p>
     *
     * @param string|array $data
     * @return DooRestClient
     */
    public function data($data = null)
    {
        if ($data == null) {
            return $this->args;
        }

        if (is_string($data)) {
            $this->args = $data;
        } else {
            $datastr = '';
            foreach ($data as $k => $v) {
                $datastr .= "$k=$v&";
            }
            $this->args = $datastr;
        }
        return $this;
    }

    /**
     * Get/Set options for executing the REST connection.
     *
     * This method prepares options for Curl to work.
     * Instead of setting CURLOPT_URL = value, you should use URL = value while setting various options
     *
     * The options are returned when no option array is passed into the method.
     *
     * <p>See http://www.php.net/manual/en/function.curl-setopt.php for the list of Curl options.</p>
     * Option keys are case insensitive. Example option input:
     * <code>
     * $client = new DooRestClient('http://somewebsite.com/api/rest');
     * $client->options(array(
     *                      'returnTransfer'=>true,
     *                      'header'=>true,
     *                      'SSL_VERIFYPEER'=>false,
     *                      'timeout'=>10
     *                  ));
     *
     * $client->execute('get');
     * //or $client->get();
     * </code>
     * @param array $optArr
     * @return DooRestClient
     */
    public function options($optArr = null)
    {
        if ($optArr == null) {
            return $this->curlOpt;
        }
        $this->curlOpt = array_merge($this->curlOpt, $optArr);
        return $this;
    }

    /**
     * Get/Set desired header fields.
     * The header fields are returned when no header array is passed into the method.
     *
     * Example fields input:
     * <code>
     * $client = new DooRestClient('http://somewebsite.com/api/rest');
     * $client->header(array(
     *                      'Access-Control-Allow-Methods'=>'POST, GET, PUT, DELETE',
     *                      'Content-Type'=>'application/json; charset=utf-8'
     *                  ));
     *
     * $client->execute('get');
     * //or $client->get();
     * </code>
     * @param array $headerArr
     * @return DooRestClient
     */

    public function header($headerArr = null)
    {
        if ($headerArr == null) {
            return $this->curlOpt['HTTPHEADER'];
        }

        foreach ($headerArr as $k => $v) {
            $this->curlOpt['HTTPHEADER'][] = $k . ': ' . $v;
        }

        return $this;
    }

    /**
     * Get/set authentication details for the RESTful call
     *
     * Authentication can be done with HTTP Basic or Digest. HTTP Auth Digest will be used by default.
     * If no values are passed into the method,
     * the auth details with be returned in an array consist of Username and Password.
     *
     * <p>If you are implementing your own RESTful api, you can handle authentication with DooDigestAuth::http_auth()
     * or setup authentication in your routes.</p>
     *
     * @param string $username Username
     * @param string $password Password
     * @param bool $basic to switch between HTTP Basic or Digest authentication
     * @return DooRestClient
     */
    public function auth($username = null, $password = null, $basic = false)
    {
        if ($username === null && $password === null) {
            return [$this->authUser, $this->authPwd];
        }

        $this->authUser = $username;
        $this->authPwd = $password;

        $this->curlOpt['HTTPAUTH'] = ($basic) ? CURLAUTH_BASIC : CURLAUTH_DIGEST;

        return $this;
    }

    /**
     * Get/set desired accept type.
     * <p>This should be used if the REST server analyze Accept header to parse what format
     * you're seeking for the result content. eg. json, xml, rss, atom.
     * DooRestClient provides a list of common used format to be used in your code.</p>
     * Example to retrieve result in JSON:
     * <code>
     * $client = new DooRestClient;
     * $client->connectTo("http://twitter.com/direct_messages")
     *        ->auth('username', 'password', true)
     *        ->accept(DooRestClient::JSON)
     *        ->get();
     * </code>
     * @param string $type
     * @return DooRestClient
     */
    public function accept($type = null)
    {
        if ($type == null) {
            if (isset($this->curlOpt['HTTPHEADER']) && $this->curlOpt['HTTPHEADER'][0]) {
                return str_replace('Accept: ', '', $this->curlOpt['HTTPHEADER'][0]);
            } else {
                return;
            }
        }

        $this->curlOpt['HTTPHEADER'][] = "Accept: $type";
        return $this;
    }

    /**
     * Get/set desired content type to be post to the server
     * <p>This should be used if the REST server analyze Content-Type header to parse what format
     * you're posting to the API. eg. json, xml, rss, atom.
     * DooRestClient provides a list of common used format to be used in your code.</p>
     * Example to retrieve result in JSON:
     * <code>
     * $client = new DooRestClient;
     * $client->connectTo("http://twitter.com/post_status")
     *        ->auth('username', 'password', true)
     *        ->setContentType(DooRestClient::JSON)
     *        ->post();
     * </code>
     * @param string $type
     * @return DooRestClient
     */
    public function setContentType($type = null)
    {
        if ($type == null) {
            if (isset($this->curlOpt['HTTPHEADER']) && $this->curlOpt['HTTPHEADER'][0]) {
                return str_replace('Content-Type: ', '', $this->curlOpt['HTTPHEADER'][0]);
            } else {
                return;
            }
        }

        $this->curlOpt['HTTPHEADER'][] = "Content-Type: $type";
        return $this;
    }

    /**
     * Execute the RESTful request through either GET, POST, PUT or DELETE request method
     * @param string $method Method string is case insensitive.
     */
    public function execute($method)
    {
        $method = strtolower($method);
        if ($method == 'get') {
            $this->get();
        } elseif ($method == 'post') {
            $this->post();
        } elseif ($method == 'put') {
            $this->put();
        } elseif ($method == 'delete') {
            $this->delete();
        }
    }

    public function processCallback()
    {
        if ($this->doneCallback != null && $this->isSuccess()) {
            if ($this->callbackResultFormat == 'json') {
                call_user_func_array($this->doneCallback,
                    [$this->resultCode(), $this->jsonResult($this->callbackResultFormatOpt)]);
            } else {
                if ($this->callbackResultFormat == 'xml') {
                    call_user_func_array($this->doneCallback,
                        [$this->resultCode(), $this->xmlResult($this->callbackResultFormatOpt)]);
                } else {
                    call_user_func_array($this->doneCallback, [$this->resultCode(), $this->result()]);
                }
            }
        } else if ($this->errorCallback != null ) {
            if ($this->callbackErrorFormat == 'json') {
                call_user_func_array($this->errorCallback,
                    [$this->resultCode(), $this->jsonResult($this->callbackErrorFormatOpt)]);
            } else {
                if ($this->callbackErrorFormat == 'xml') {
                    call_user_func_array($this->errorCallback,
                        [$this->resultCode(), $this->xmlResult($this->callbackErrorFormatOpt)]);
                } else {
                    call_user_func_array($this->errorCallback, [$this->resultCode(), $this->result()]);
                }
            }
        }
    }

    /**
     * Execute the request with HTTP GET request method
     * @return DooRestClient
     */
    public function get()
    {
        if ($this->args != null) {
            $serverurl = $this->serverUrl . '?' . $this->args;
        } else {
            $serverurl = $this->serverUrl;
        }

        $ch = curl_init($serverurl);

        $arr = [];
        foreach ($this->curlOpt as $k => $v) {
            $arr[constant('CURLOPT_' . strtoupper($k))] = $v;
        }

        //set HTTP auth username and password is found
        if (isset($this->authUser) || isset($this->authPwd)) {
            $arr[CURLOPT_USERPWD] = $this->authUser . ':' . $this->authPwd;
        }

        //set GET method
        $arr[CURLOPT_HTTPGET] = true;

        curl_setopt_array($ch, $arr);
        $this->setCurlOptBeforeOperation($ch);

        $this->result = curl_exec($ch);
        $this->headerCodeReceived = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->contentTypeReceived = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $this->headerSizeReceived = intval(curl_getinfo($ch, CURLINFO_HEADER_SIZE));
        $this->contentSizeReceived = intval(curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD));

        curl_close($ch);
        $this->processCallback();
        return $this;
    }

    /**
     * Execute the request with HTTP POST request method
     * @return DooRestClient
     */
    public function post()
    {
        $ch = curl_init($this->serverUrl);

        $arr = [];
        foreach ($this->curlOpt as $k => $v) {
            $arr[constant('CURLOPT_' . strtoupper($k))] = $v;
        }

        //set HTTP auth username and password is found
        if (isset($this->authUser) || isset($this->authPwd)) {
            $arr[CURLOPT_USERPWD] = $this->authUser . ':' . $this->authPwd;
        }

        //set POST method and fields
        $arr[CURLOPT_POST] = true;
        $arr[CURLOPT_POSTFIELDS] = $this->args;

        curl_setopt_array($ch, $arr);
        $this->setCurlOptBeforeOperation($ch);

        $this->result = curl_exec($ch);
        $this->headerCodeReceived = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->contentTypeReceived = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $this->headerSizeReceived = intval(curl_getinfo($ch, CURLINFO_HEADER_SIZE));
        $this->contentSizeReceived = intval(curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD));

        curl_close($ch);
        $this->processCallback();
        return $this;
    }

    /**
     * Execute the request with HTTP PUT request method
     * @return DooRestClient
     */
    public function put()
    {
        $ch = curl_init($this->serverUrl);

        $arr = [];
        foreach ($this->curlOpt as $k => $v) {
            $arr[constant('CURLOPT_' . strtoupper($k))] = $v;
        }

        //set HTTP auth username and password is found
        if (isset($this->authUser) || isset($this->authPwd)) {
            $arr[CURLOPT_USERPWD] = $this->authUser . ':' . $this->authPwd;
        }

        //set PUT method and fields
        $arr[CURLOPT_CUSTOMREQUEST] = 'PUT';
        $arr[CURLOPT_POSTFIELDS] = $this->args;

        curl_setopt_array($ch, $arr);
        $this->setCurlOptBeforeOperation($ch);

        $this->result = curl_exec($ch);
        $this->headerCodeReceived = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->contentTypeReceived = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $this->headerSizeReceived = intval(curl_getinfo($ch, CURLINFO_HEADER_SIZE));
        $this->contentSizeReceived = intval(curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD));

        curl_close($ch);
        $this->processCallback();
        return $this;
    }

    /**
     * Execute the request with HTTP DELETE request method
     * @return DooRestClient
     */
    public function delete()
    {
        $ch = curl_init($this->serverUrl);

        $arr = [];
        foreach ($this->curlOpt as $k => $v) {
            $arr[constant('CURLOPT_' . strtoupper($k))] = $v;
        }

        //set HTTP auth username and password is found
        if (isset($this->authUser) || isset($this->authPwd)) {
            $arr[CURLOPT_USERPWD] = $this->authUser . ':' . $this->authPwd;
        }

        //set DELETE method, delete methods don't have fields,ids should be set in server url
        $arr[CURLOPT_CUSTOMREQUEST] = 'DELETE';

        curl_setopt_array($ch, $arr);
        $this->setCurlOptBeforeOperation($ch);

        $this->result = curl_exec($ch);
        $this->headerCodeReceived = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->contentTypeReceived = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $this->headerSizeReceived = intval(curl_getinfo($ch, CURLINFO_HEADER_SIZE));
        $this->contentSizeReceived = intval(curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD));

        curl_close($ch);
        $this->processCallback();
        return $this;
    }

    //-------------- Result handlers --------------

    /**
     * Get result of the executed request
     * @return string
     */
    public function result()
    {
        return $this->result;
    }

    /**
     * Determined if it's a successful request
     * @return bool
     */
    public function isSuccess()
    {
        return ($this->headerCodeReceived >= 200 && $this->headerCodeReceived < 300);
    }

    public function onDone($callback, $format = null, $formatOpt = true)
    {
        $this->doneCallback = $callback;
        $this->callbackResultFormat = $format;
        $this->callbackResultFormatOpt = $formatOpt;
        return $this;
    }

    public function onError($errorCallback, $format = null, $formatOpt = true)
    {
        $this->errorCallback = $errorCallback;
        $this->callbackErrorFormat = $format;
        $this->callbackErrorFormat = $formatOpt;
        return $this;
    }

    /**
     * Get result's HTTP status code of the executed request
     * @return int
     */
    public function resultCode()
    {
        return $this->headerCodeReceived;
    }


    /**
     * Get result's content type of the executed request
     * @return int
     */
    public function resultContentType()
    {
        return $this->contentTypeReceived;
    }

    /**
     * Get result's header size of the executed request
     * @return int
     */
    public function resultHeaderSize()
    {
        return $this->headerSizeReceived;
    }

    /**
     * Get result's content size of the executed request
     * @return int
     */
    public function resultContentSize()
    {
        return $this->contentSizeReceived;
    }

    /**
     * Convert the REST result to XML object
     *
     * Returns a SimpleXMLElement object by default which consumed less memory than DOMDocument.
     * However if you need the result to be DOMDocument which is more flexible and powerful in modifying XML,
     * just passed in True to the function.
     *
     * @param bool $domObject convert result in to DOMDOcument if True
     * @return SimpleXMLElement|DOMDocument
     */
    public function xmlResult($domObject = false)
    {
        if ($domObject) {
            $d = new DOMDocument('1.0', 'UTF-8');
            $d->loadXML($this->result);
            return $d;
        } else {
            return simplexml_load_string($this->result);
        }
    }

    /**
     * Convert the REST result to JSON object
     * @param bool $toArray convert result into assoc array if True.
     * @return object
     */
    public function jsonResult($toArray = false)
    {
        return \JSON::decode($this->result, $toArray);
    }

}
