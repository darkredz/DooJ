<?php
/**
 * Description of MainController
 *
 * @author darkredz
 */
class MainController extends DooController{

    public function index(){
        $this->setContentType('html');
        $this->endReq('Hello! It works!');
    }


}
