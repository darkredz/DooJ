<?php
/**
 * DooHttpClientBuilder class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */


/**
 * DooHttpClientBuilder creates asynchronuous http clients with connection pools to a http/https host
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @package doo.controller
 * @since 2.0
 */
class DooHttpClientBuilder {

    /**
     * Create Http client with options specified in $urls
     * Url array passed in should contain a key for the app mode.
     * <code>
     * $serviceUrls = [
     *     'staging' => [
     *         'example' => 'http://example.com',
     *         'fb' => [
     *             'url' => 'https://graph.facebook.com',
     *             'pool' => 20
     *         ],
     *         'wepay' => [
     *             'url' => 'https://stage.wepayapi.com',
     *             'timeout' => 120,
     *             'pool' => 20
     *         ]
     *     ],
     *
     *     'prod' => [
     *         'example' => 'http://example.com',
     *         'fb' => [
     *             'url' => 'https://graph.facebook.com',
     *             'pool' => 20
     *         ],
     *         'wepay' => [
     *             'url' => 'https://wepayapi.com',
     *             'timeout' => 120,
     *             'pool' => 20
     *         ]
     *     ]
     * ];
     *
     * //creates only one http client for service Wepay
     * $httpClients = DooHttpClientBuilder::build($serviceUrls, 'staging', ['wepay]);
     *
     * //Set Application's httpClients to the created list of clients.
     * $app->httpClients = &$httpClients;
     * </code>
     *
     * @param array $urls Array of services URL(scheme and domain)
     * @param string $mode Mode to be used. This should matches keys defined in $urls
     * @param array $createOnly Array of services name to be created (to filter out values in $urls)
     * @return array An associative array of Http clients with service name as its key
     */
    public static function build($urls, $mode, $createOnly=null, $defaultTimeout=25, $defaultPool=10){

        $clients = [];
        $urlList = [];

        if($createOnly){
            foreach($urls[$mode] as $serviceName => $urlData){
                if(in_array($serviceName, $createOnly)){
                    $urlList[$serviceName] = $urlData;
                }
            }
        }
        else{
            $urlList = $urls[$mode];
        }

        foreach($urlList as $serviceName => $urlData){
            if(is_array($urlData)){
                $url = $urlData['url'];
                $pool = (isset($urlData['pool'])) ? $urlData['pool'] : $defaultPool;
                $timeout = (isset($urlData['timeout'])) ? $urlData['timeout'] : $defaultTimeout;
                $keepAlive = (isset($urlData['keepAlive'])) ? $urlData['keepAlive'] : true;
            }
            else{
                $url = $urlData;
                $pool = 10;
                $timeout = 25;
                $keepAlive = true;
            }

            $urlInfo = \parse_url($url);

            $ssl = ($urlInfo['scheme']=='https');

            if($urlInfo['port']){
                $port = $urlInfo['port'];
            }
            else{
                $port = ($ssl) ? 443 : 80;
            }

//            \Vertx::logger()->info(var_export([
//                'host' => $urlInfo['host'],
//                'ssl' => $ssl,
//                'keepAlive' => $keepAlive,
//                'pool' => $pool,
//                'port' => $port,
//                'timeout' => $timeout
//            ], true));

            $clients[$serviceName] = \Vertx::createHttpClient()
                ->host($urlInfo['host'])
                ->ssl($ssl)
                ->keepAlive($keepAlive)
                ->maxPoolSize($pool)
                ->connectTimeout($timeout * 1000)
                ->port($port);
        }

        return $clients;
    }
} 