<?php
/**
 * DooApiDiscoveryController class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */


/**
 * DooApiDiscoveryController enables easy discovery of all available api methods and its required field schema in a module
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @package doo.controller
 * @since 2.0
 */
class DooApiDiscoveryController extends DooController
{

    public $namespace;
    public $apiSuperClass;
    public $excludeClasses = [];
    public $modulePath;
    public $apiResultSampleFolder = 'api_result';

    public $async = true;

    public function listApi()
    {
        $fm = new DooFile();
//        $rs = $fm->getFilePathList($this->app->conf->SITE_PATH . $this->app->conf->PROTECTED_FOLDER);
        $rs = $fm->getFilePathList($this->modulePath);

        $superClass = $this->apiSuperClass; // __NAMESPACE__ .'\\ApiRootController';
        $superMethods = get_class_methods($superClass);
        $superMethods[] = '__construct';
        $superMethods[] = '__destruct';
        $superMethods[] = '__get';
        $superMethods[] = '__set';

        $htmlShow = $this->_GET['with_links'];
        $forJs = $this->_GET['for_js'];

        $classes = [];
        $oriClasses = [];
        foreach ($rs as $fname => $fpath) {

//            if(in_array($fname, ['ApiFormController.php', 'ApiRootController.php'])) continue;
            if (in_array($fname, $this->excludeClasses) || substr($fname, -4) != '.php') {
                continue;
            }

            $fname = substr($fname, 0, -4);
            $cls = $this->namespace . '\\' . $fname;

            if (in_array($fname, array_values($this->excludeClasses)) && empty($this->excludeClasses[$fname])) {
                continue;
            }


            $class = new ReflectionClass($cls);
            $methodsPub = $class->getMethods();
            $methods = [];
            $oriArr = [];

            foreach ($methodsPub as $mth) {
                $m = $mth->name;

                if ($mth->isPublic() && !in_array($mth->name, $superMethods) && !in_array($m,
                        $this->excludeClasses[$fname])) {
                    $mRename = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $m));
                    $methods[] = $mRename;

                    $doc = $mth->getDocComment();
                    $desc = $this->getDocComment($doc, 'explain', $htmlShow);
                    $oriArr[$mRename] = ['methodName' => $m, 'explain' => $desc];
                }
            }

            $fname2 = substr($fname, 0, -10);
            $fname2 = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $fname2));
            $classes[$fname2] = array_values($methods);

            $oriClasses[$fname2] = ['class' => $fname, 'methods' => $oriArr];
        }

        if ($htmlShow) {
            $html = '<html><head><title>API Discovery</title><style>body{font-family: "Fira Sans", "Source Sans Pro", Helvetica, Arial, sans-serif;}.desc{display:block;min-height: 26px;border: 1px #d0d0d0 solid;padding: 10 5 8 10px;margin-top: 4px;margin-bottom: 16px;background-color: #fdfdfd;width: 96%;}</style></head><body>';
            $lastClass = null;
            foreach ($classes as $className => $methods) {
                if ($lastClass != $className) {
                    $html .= '<hr/>';
                    $html .= '<h3>' . ucfirst($className) . '</h3>';
                    $lastClass = $className;
                }

                $li = [];
                foreach ($methods as $method) {
                    $desc = $oriClasses[$className]['methods'][$method]['explain'];
//                    $li[] = '<li><a href="/api/api-explain/schema/'. $className .'/'. $method .'">'. $method.'</a><p>'. $desc .'</p></li>';
                    $li[] = <<<EOF
<li><span style="font-weight:bold;"><a href="/api/api-explain/schema/{$className}/{$method}">{$method}</a></span>
  <div class="desc">{$desc}</div></li>
EOF;
                }

                $html .= '<ul>' . \implode("\n", $li) . '</ul>';
            }

            $html .= '</body></html>';
            $this->setContentType('html');
            $this->endReq($html);
        } else {
            if ($forJs) {
                $this->setContentType('json');

                $methodsAll = ['GET' => [], 'POST' => []];

                foreach ($classes as $className => $methods) {
                    $fullClassName = $this->namespace . '\\' . $oriClasses[$className]['class'];
                    $apiClass = $this->container->resolveConstructor($fullClassName);

                    foreach ($methods as $method) {
                        $func = $oriClasses[$className]['methods'][$method]['methodName'];

                        $apiClass->action = $func;
                        $apiClass->actionField = 'field' . ucfirst($func);
                        $fieldData = $apiClass->getFieldSchema();
                        $httpMethod = strtoupper($fieldData['_method']);
                        if (empty($httpMethod)) {
                            $httpMethod = 'ALL';
                        }
                        $methodsAll[$httpMethod][] = [$className . '/' . $method];
                    }
                }

                $this->endReq(\JSON::encode($methodsAll));
            } else {
                $this->setContentType('json');
                $this->endReq(\JSON::encode($classes));
            }
        }
    }

    protected function getDocComment($str, $annotate, $htmlShow = false)
    {
        $matches = [];
//        preg_match('/\*\s+\@'. $annotate .'\s+([^\n\r\*]+)/', $str, $matches);
        preg_match('/\*\s+\@' . $annotate . '\s+([.\s\S\n\r]+)(?=\* \@)/gm', $str, $matches);

        if (empty($matches)) {
            preg_match('/\*\s+\@' . $annotate . '\s+([.\s\S\n\r]+)(?=\*\/)/gm', $str, $matches);
        }

        if (isset($matches[1])) {
            $doc = trim($matches[1]);
            $replace = "\n";
            if ($htmlShow) {
                $replace = "<br/>";
            }
            $doc = preg_replace('/(\s+\* )/', $replace, $doc);
            return $doc;
        }

        return '';
    }

    public function schema()
    {
        $resource = $this->params[0];
//        $sectionClass = __NAMESPACE__ .'\\' . ucfirst($section) . 'Controller';
        $section = preg_replace('/-(.?)/e', "strtoupper('$1')", strtolower($resource));
        $sectionClass = $this->namespace . '\\' . ucfirst($section) . 'Controller';
        $actionName = $this->params[1];

        $func = preg_replace('/-(.?)/e', "strtoupper('$1')", strtolower($actionName));

        if (class_exists($sectionClass)) {
            $apiClass = $this->container->resolveConstructor($sectionClass);

            if (!method_exists($apiClass, $func)) {
                $this->setContentType('json');
                $this->app->statusCode = 501;
                $this->endReq(\JSON::encode(['error' => "Invalid API method $func. Method $func for section $section not found"]));
                return 501;
            }

            $apiClass->action = $func;
            $apiClass->actionField = 'field' . ucfirst($func);
            $fieldData = $apiClass->getFieldSchema();

            if (is_null($fieldData)) {
                $this->setContentType('json');
                $this->app->statusCode = 422;
                $this->endReq(\JSON::encode(['error' => "No fields definition for method $func."]));
                return 422;
            }

            $actionType = 'ALL';

            if (isset($fieldData['_method'])) {
                $actionType = $fieldData['_method'];
                unset($fieldData['_method']);
            }

            $method = new ReflectionMethod($sectionClass, $func);
            $doc = $method->getDocComment();
            $desc = $this->getDocComment($doc, 'explain');
            $titleDoc = $this->getDocComment($doc, 'title');
            $actionTypeDoc = $this->getDocComment($doc, 'action');

            if (!empty($actionTypeDoc)) {
                $actionType = $actionTypeDoc;
            }

            $actionType = strtoupper($actionType);

            if (empty($titleDoc)) {
                $schema['title'] = ucfirst($section) . ' - ' . $func;
            } else {
                $schema['title'] = $titleDoc;
            }

            if ($desc) {
                $schema['description'] = $desc;
            } else {
                $schema['description'] = 'Perform action ' . $func;
            }

            $schema['method'] = $actionType;
            $schema['resource'] = ucfirst($section);
            $schema['action'] = $func;

            if (isset($fieldData['_return_type'])) {
                $schema['result_type'] = $fieldData['_return_type'];
                unset($fieldData['_return_type']);
            } else {
                $schema['result_type'] = 'array';
            }

            //generate JSON schema to use for automated JS form
            $schema2 = DooJsonSchema::convert($fieldData);

            $json = ['schema' => \array_merge($schema, $schema2)];

            $apiResultJson = $this->getApiResultFormat($resource, $actionName);
            if ($apiResultJson) {
                $json['result_format'] = $apiResultJson;
                if (is_object($apiResultJson)) {
                    $json['schema']['result_type'] = 'object';
                }
            }

            $this->setContentType('json');

            if (!empty($this->_GET['swagger'])) {
                $swagger = $this->convertToSwaggerSchema($resource, $actionName, $actionType, $json, $schema);
                $this->endReq(\JSON::encode($swagger));
            } else {
                $this->endReq(\JSON::encode($json));
            }
        } else {
            $this->setContentType('json');
            $this->app->statusCode = 501;
            $this->endReq(\JSON::encode(['error' => "Invalid API section $section. Class $sectionClass not found"]));
            return 501;
        }
    }

    protected function convertToSwaggerSchema($resource, $actionName, $actionType, $json, $schema)
    {
        $tag = explode('/', $resource)[0];
        $parameters = $json['schema']['properties'];
        $swaggerParams = [];

        foreach ($parameters as $key => $prm) {
            $newPrm = [
                'name' => $key,
                'in' => 'formData',
                'required' => (!empty($prm['required'])) ? true : false,
                'type' => $prm['type'],
                'description' => $prm['title'],
            ];
            $swaggerParams[] = $newPrm;
        }

        $actionSchema = [
            'tags' => [$tag],
            'summary' => $schema['title'],
            'description' => $schema['description'],
            'operationId' => $schema['resource'] .'-'. $schema['action'],
            'consumes' => ['application/json', 'application/x-www-form-urlencoded'],
            'produces' => ['application/json'],
            'parameters' => $swaggerParams,
        ];

        if (!empty($json['result_format'])) {
            $responses = [];
            $httpCodes = \array_keys($json['result_format']);
            foreach ($httpCodes as $code) {
                $code = $code . '';
                if (ctype_digit($code)) {
                    $responseResult = null;
                    if (is_array($json['result_format'][$code])) {
                        $responseResult = \JSON::encode($json['result_format'][$code]);
                    } else {
                        $responseResult = $json['result_format'][$code];
                    }

                    $description = ($code >= 200 && $code < 300) ? 'success' : 'error';

                    $responses[$code] = [
                        'description' => $description,
                        'schema' => [
                            'type' => 'object'
                        ],
                        'examples' => [
                            'application/json' => $responseResult
                        ]
                    ];
                }
            }
            $actionSchema['responses'] = $responses;
        }

        return [
            'swagger' => '2.0',
            'info' => [
                'description' => 'Auto generated partially',
                'title' => 'API'
            ],
            'host' => str_replace(['http://', 'https://'], '', $this->app->conf->APP_URL),
            'basePath' => 'api',
            'tags' => [
                ['name' => $tag],
            ],
            'paths' => [
                "/$resource/$actionName" => [
                    strtolower($actionType) => $actionSchema
                ]
            ]
        ];
    }

    protected function getApiResultFormat($section, $func)
    {
        $resFile = $this->app->getProtectedRootPath() . 'config/' . $this->apiResultSampleFolder . '/' . $section . '/' . $func;

        if (file_exists($resFile . '.json')) {
            $result = file_get_contents($resFile . '.json');
            return ['200' => \JSON::decode($result)];
        }
        else if (file_exists($resFile . '.php')) {
            $result = include($resFile . '.php');
            return $result;
        }
    }
}