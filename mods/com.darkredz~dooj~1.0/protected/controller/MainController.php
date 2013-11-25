<?php
/**
 * Description of MainController
 *
 * @author darkredz
 */
class MainController extends DooController{

	public $async = true;

    public function index(){
        $this->setContentType('html');
        $this->endReq('Hello! It works!');

        //if aysnc mode is false
        // echo "Hello! It works!"
    }

}
