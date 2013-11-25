<?php
/**
 * DooVertxSession class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */


/**
 * DooVertxSession is the class where session data are store. When saved to Vertx shared map or Redis, the object will be serialized.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @package doo.session
 * @since 2.0
 */
class DooVertxSession {

    public $id;

    public $lastAccess;

    protected $_data;
    protected $_modified = false;

    public function isModified(){
        return $this->_modified;
    }

    public function resetModified($modified = false){
        $this->_modified = $modified;
    }

    public function __set($name, $value){
        $this->_data[$name] = $value;
        $this->_modified = true;
    }

    public function & __get($name){
        return $this->get($k);
    }

    /**
     * Get variable from namespace by reference
     *
     * @param string $name if that variable doesnt exist returns null
     *
     * @return mixed
     */
    public function &get($name) {
        if (!isset($this->_data[$name])){
            return null;
        } else {
            return $this->_data[$name];
        }
    }

    public function __isset($name) {
        return isset($this->_data[$name]);
    }

    public function __unset($name) {
        if (isset($this->_data[$name])) {
            unset($this->_data[$name]);
            return true;
        }
        return false;
    }

}