<?php
/**
 * ErrorController
 * Feel free to change this and customize your own error message
 *
 * @author darkredz
 */
class ErrorController extends DooController{

    public function index(){
        $uri = $this->app->request->uri;
        $parts = explode('/',$this->app->conf->WEB_STATIC_PATH);
        array_pop($parts);
        array_pop($parts);
        $view = implode('/', $parts) .'/scrap'. $uri;

        if(file_exists($view)){
            if(is_dir($view)){
                if(file_exists($view.'/index.html')){
                    include $view.'/index.html';
                    return 200;
                }
            }else{
                include $view;
                return 200;
            }
        }



        echo '<h1>ERROR 404 not found</h1>';
        echo '<p>This is handler by an internal Route as defined in common.conf.php $config[\'ERROR_404_ROUTE\']</p>

<p>Your error document needs to be more than 512 bytes in length. If not IE will display its default error page.</p>

<p>Give some helpful comments other than 404 :(
Also check out the links page for a list of URLs available in this demo.</p>';
    }
	

}
?>