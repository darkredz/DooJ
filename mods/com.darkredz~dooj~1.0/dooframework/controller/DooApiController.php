<?php
/**
 * Created by IntelliJ IDEA.
 * User: leng
 * Date: 10/26/13
 * Time: 2:47 PM
 * To change this template use File | Settings | File Templates.
 */

class DooApiController extends DooController {

    /**
     * @var string Language for this session
     */
    public $lang = 'en_US';
    public $async = true;
    public $action;
    public $actionField;
    public $apiKey = 'AZ00B701J2pI90P84e7yrGhM401Z801';
    public $fieldDef;


    public function beforeRun($resource, $action){
        $exclude = ['getFieldSchema','getFieldRules','getApiInput','validateInput','sendResult','sendError'];

        if( in_array($action, $exclude) ) {
            $this->setContentType("json");
            $this->app->statusCode = 404;
            $this->endReq( '{"error":"Method Not Found"}' );
            return 404;
        }

        if($this->app->request->headers['Authority'] != $this->apiKey){
            $this->setContentType('json');
            $this->app->statusCode = 401;
            $this->endReq( '{"error":"Invalid API key"}' );
            return 401;
        }

        $this->action = $action;
        $this->actionField = 'field' . ucfirst($this->action);
    }

    protected function getFieldSchema(){
        if($this->actionField==null) return;
        return $this->{$this->actionField};
    }

    protected function getFieldRules(){
        $rules = [];
        $field = $this->{$this->actionField};
        foreach($field as $fname => $p){
            if(isset($p[1])){
                $rules[$fname] = $p[1];
            }
        }
        return $rules;
    }

    protected function getApiInput(){
        $field = array_keys($this->{$this->actionField});
        $method = strtoupper($this->app->_SERVER['REQUEST_METHOD']);
        if($method == 'GET' || $method == 'DELETE' || $method == 'HEAD'){
            $input = $this->getKeyParams($field);
        }
        else{
            $input = $this->getInput($field, $this->_POST);
        }

        if($this->app->conf->DEBUG_ENABLED){
            $this->app->logInfo($method . ' Request input:');
            $this->app->trace($input);
        }

        return $input;
    }

    protected function validateInput(){
        $input = $this->getApiInput();
        $rules = $this->getFieldRules();

        if($input==null){
            $this->sendError('No data input');
            return false;
        }

        if($rules==null){
            $this->sendError('Please set the field rules for this API action');
            return false;
        }

        $v = new DooValidator();
        $v->checkMode = DooValidator::CHECK_ALL;
        $err = $v->validate($input, $rules);

        if($err){
            $this->sendError($err);
            return false;
        }

        return $input;
    }

    protected function removeFields($opt, $removeTheseFields=null, $removeNull=true, $removeEmptyString=true){
        //remove fields that are not needed for where query
        if($removeTheseFields){
            $optFields = array_keys($opt);

            foreach ($removeTheseFields as $field){
                if(in_array($field, $optFields)){
                    unset($opt[$field]);
                }
            }
        }

        //remove null fields
        if($removeNull || $removeEmpty){
            foreach($opt as $k=>$v){
                if($removeNull && $v===null) {
                    unset($opt[$k]);
                    continue;
                }
                if($removeEmptyString && $v===''){
                    unset($opt[$k]);
                    continue;
                }
            }
        }
        return $opt;
    }

    protected function sendError($err){
        $this->setContentType('json');
        $this->app->statusCode = 400;
        $this->endReq( json_encode(['error' => 'Invalid field data', 'errorList' => $err]) );
    }

    protected function sendResult($jsonStr){
        $this->setContentType('json');
        if(is_string($jsonStr)){
            $this->endReq( $jsonStr );
        }else{
            $this->endReq( json_encode($jsonStr) );
        }
    }

}