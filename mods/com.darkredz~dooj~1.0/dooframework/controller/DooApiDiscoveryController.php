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
class DooApiDiscoveryController extends DooController {

    public $namespace;
    public $apiSuperClass;
    public $excludeClassFiles = [];
    public $modulePath;

    public $async = true;

    public function listApi(){
        $fm = new DooFile();
//        $rs = $fm->getFilePathList($this->app->conf->SITE_PATH . $this->app->conf->PROTECTED_FOLDER);
        $rs = $fm->getFilePathList($this->modulePath);

        $superClass = $this->apiSuperClass; // __NAMESPACE__ .'\\ApiRootController';
        $superMethods = get_class_methods($superClass);
        $superMethods[] = '__construct';
        $superMethods[] = '__destruct';
        $superMethods[] = '__get';
        $superMethods[] = '__set';

        $classes = [];
        foreach($rs as $fname=>$fpath){
//            if(in_array($fname, ['ApiFormController.php', 'ApiRootController.php'])) continue;
            if(in_array($fname, $this->excludeClassFiles)) continue;

            $fname = substr($fname,0,-4);
            $cls = $this->namespace .'\\'. $fname;

            $methods = get_class_methods($cls);

            $methods = array_diff($methods, $superMethods);

            foreach($methods as &$m){
                $m = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $m));
            }

            $fname = substr($fname, 0, -10);
            $fname = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $fname));
            $classes[$fname] = array_values($methods);
        }

        $this->setContentType('json');
        $this->endReq( json_encode($classes) );
    }

    protected function getDocComment($str, $annotate){
        $matches = array();
        preg_match('/\*\s+\@'. $annotate .'\s+([^\n\r\*]+)/', $str, $matches);
//            preg_match_all('/\*\s+\@([a-zA-Z0-9\_]+)\s+([^\n\r]+)/m', $doc, $matches);

        if (isset($matches[1])) {
            return trim($matches[1]);
        }

        return '';
    }

    public function schema(){
        $section = $this->params[0];
//        $sectionClass = __NAMESPACE__ .'\\' . ucfirst($section) . 'Controller';
        $section = preg_replace( '/-(.?)/e',"strtoupper('$1')", strtolower($section));
        $sectionClass = $this->namespace .'\\' . ucfirst($section) . 'Controller';
        $func = $this->params[1];

        $func = preg_replace( '/-(.?)/e',"strtoupper('$1')", strtolower($func));

        if(class_exists($sectionClass)){
            $apiClass = new $sectionClass;

            if(!method_exists($apiClass, $func)){
                $this->setContentType('json');
                $this->app->statusCode = 501;
                $this->endReq( json_encode(['error' => "Invalid API method $func. Method $func for section $section not found"]) );
                return 501;
            }

            $apiClass->action = $func;
            $apiClass->actionField = 'field' . ucfirst($func);
            $fieldData = $apiClass->getFieldSchema();

            if(is_null($fieldData)){
                $this->setContentType('json');
                $this->app->statusCode = 422;
                $this->endReq( json_encode(['error' => "No fields definition for method $func."]) );
                return 422;
            }

            $actionType = 'ALL';

            if(isset($fieldData['_method'])){
                $actionType = $fieldData['_method'];
                unset($fieldData['_method']);
            }

            //generate JSON schema to use for automated JS form
            $schema = DooJsonSchema::convert($fieldData);

            $method = new ReflectionMethod($sectionClass, $func);
            $doc = $method->getDocComment();
            $desc = $this->getDocComment($doc, 'explain');

            $schema['title'] = 'Resource ' . ucfirst($section);
            $schema['description'] = 'Action ' . $func;

            if($desc){
                $schema['description'] .= ': '. $actionType . ' - '. $desc;
            }

            $json = ['schema' => $schema];

            $this->setContentType('json');
            $this->endReq( json_encode($json) );
        }
        else{
            $this->setContentType('json');
            $this->app->statusCode = 501;
            $this->endReq( json_encode(['error' => "Invalid API section $section. Class $sectionClass not found"]) );
            return 501;
        }
    }
}