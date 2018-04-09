<?php
/**
 * Created by IntelliJ IDEA.
 * User: leng
 * Date: 10/27/16
 * Time: 5:35 PM
 */

class DooContainer {
    const SHARED_CONTAINER = 'container';
    const SHARED_VERTX = 'vertx';
    const SHARED_APP = 'app';
    const SHARED_DB = 'db';
    const SHARED_MAIL = 'mail';
    const SHARED_RBAC = 'rbac';
    const SHARED_ACL = 'acl';

    public $defaultBindNames = [
        self::SHARED_APP,
        self::SHARED_DB,
        self::SHARED_MAIL,
        self::SHARED_RBAC,
        self::SHARED_ACL,
        self::SHARED_VERTX
    ];

    public $customParamBinding = [];

    protected $binding = [];
    protected $bindingShared = [];

    public function clear($name) {
        if (isset($this->binding[$name])) {
            unset($this->binding[$name]);
        }
        if (isset($this->bindingShared[$name])) {
            unset($this->bindingShared[$name]);
        }
    }

    public function has($name) {
        if (isset($this->binding[$name])) {
            return true;
        }
        if (isset($this->bindingShared[$name])) {
            return true;
        }
    }

    public function alias($name, $aliasTo) {
        if ($this->has($aliasTo)) {
            $this->binding[$name] = $this->binding[$aliasTo];
        }
    }

    public function hasNotShared($name) {
        if (isset($this->binding[$name])) {
            return true;
        }
    }

    public function hasShared($name) {
        if (isset($this->bindingShared[$name]) && !is_callable($this->bindingShared[$name])) {
            return true;
        }
    }

    public function set($name, $closure) {
        $this->binding[$name] = $closure;
    }

    public function setShared($name, $closure) {
        $this->bindingShared[$name] = $closure;
    }

    public function get($name, array $params = []) {
        if (isset($this->binding[$name])) {
            if (is_callable($this->binding[$name])) {
                if (!empty($params)) {
                    return call_user_func_array($this->binding[$name], $params);
                }
                else {
                    return $this->binding[$name]();
                }
            }
            else {
                return $this->binding[$name];
            }
        }
        else {
            return $this->getShared($name, $params);
        }
        throw new Exception('Binding not found for name ' . $name);
    }

    public function getShared($name, array $params = []) {
        if (isset($this->bindingShared[$name])) {
            if (is_callable($this->bindingShared[$name])) {
                if (!empty($params)) {
                    $this->bindingShared[$name] = call_user_func_array($this->bindingShared[$name], $params);
                    return $this->bindingShared[$name];
                }
                else {
                    $this->bindingShared[$name] = $this->bindingShared[$name]();
                    return $this->bindingShared[$name];
                }
            }
            else {
                return $this->bindingShared[$name];
            }
        }
        throw new Exception('Binding not found for name ' . $name);
    }

    public function make($name, array $paramsInst = []) {
        $classRef = new \ReflectionClass($name);
        if (!empty($paramsInst)) {
            return $classRef->newInstanceArgs($paramsInst);
        }

        $constructor = $classRef->getConstructor();
        $param = $constructor->getParameters();

//        $this->app->logInfo("=== paramsAuto ===");
//        $this->app->logInfo($constructor->getNumberOfParameters());
//        $this->app->logInfo(print_r(array_keys($paramsAuto), true));
        $paramsAuto = $this->injectMethodArgs($name, $classRef, $param);
        return $classRef->newInstanceArgs($paramsAuto);
    }

    public function injectMethodArgsWithDocBlock($methodRef) {
        $docBlock = $methodRef->getDocComment();

        if (empty($docBlock)) {
            return null;
        }

        $matches = null;
        \preg_match_all('/.+ \* \@param ([a-zA-Z0-9_\\\]+) /', $docBlock, $matches);

        //if matches comment block with param type info
        if (sizeof($matches) == 2) {
            $paramToInject = [];
            foreach ($matches[1] as $param) {
                //inject app to controller constructor if its the default 3
                if ($param == 'DooWebApp' || $param == 'DooEventBusApp' || $param == 'DooAppInterface') {
                    $paramToInject[] = &$this->getShared('app');
                } else {
                    if ($param == 'DooContainer') {
                        $paramToInject[] = &$this;
                    } else {
                        if ($param == 'DooConfig') {
                            $paramToInject[] = &$this->getShared('app')->conf;
                        } //resolve class if container has it
                        else {
                            if ($this->has($param)) {
                                $obj = $this->get($param);
                                $paramToInject[] = $obj;
                            }
                        }
                    }
                }
            }
            if (empty($paramToInject)) {
                return null;
            }
            return $paramToInject;
        }
        return null;
    }

    /**
     * @param $classname
     * @param ReflectionClass $classRef
     * @param array $param array of ReflectionParameter of the method needed to inject
     * @return array|null
     * @throws Exception
     */
    public function injectMethodArgs($classname, ReflectionClass $classRef, array $param) {
        $paramsAuto = [];
        $error = [];

        foreach ($param as $p) {
            $argName = $p->getName();
            $declaredClass = $p->getClass();

            //get class object from container
            if (!empty($declaredClass)) {
                $declaredClassName = $declaredClass->getName();
                if ($this->has($declaredClassName)) {
                    $paramsAuto[] = $this->get($declaredClassName);
                } else {
                    if ($declaredClassName == 'DooContainer') {
                        $paramsAuto[] = $this;
                    } else if ($declaredClassName == 'DooAppInterface' || $declaredClassName == 'DooWebApp' || $declaredClassName == 'DooEventBusApp') {
                        $paramsAuto[] = $this->getShared(self::SHARED_APP);
                    } else {
                        if ($this->has($declaredClassName)) {
                            $paramsAuto[] = $this->get($declaredClassName);
                        } else {
                            $declaredClassNameParts = explode('\\', $declaredClassName);
                            $declaredClassNameShort = $declaredClassNameParts[sizeof($declaredClassNameParts) - 1];

                            if ($this->has($declaredClassNameShort)) {
                                $paramsAuto[] = $this->get($declaredClassNameShort);
                            } else {
                                if ($p->isOptional()) {
                                    $paramsAuto[] = $p->getDefaultValue();
                                } else {
                                    $error[$argName] = "Parameter not found for arg name $argName when creating $classname";
                                }
                            }
                        }
                    }
                }
            } else {
                //if is typical php type without class, check the name and try to match from container
                if (in_array($argName, $this->defaultBindNames)) {
                    if ($this->has($argName)) {
                        $paramsAuto[] = $this->get($argName);
                    } else if ($argName == self::SHARED_VERTX) {
                        $paramsAuto[] = \getVertx();
                    } else {
                        if ($p->isOptional()) {
                            $paramsAuto[] = $p->getDefaultValue();
                        } else {
                            $error[$argName] = "Parameter not found for untyped arg name $argName when creating $classname";
                        }
                    }
                } else {
                    if ($argName == self::SHARED_CONTAINER) {
                        $paramsAuto[] = $this;
                    } else if (!empty($this->customParamBinding[$argName])) {
                        $manualParamBind = $this->customParamBinding[$argName];
                        if (is_string($manualParamBind) && $this->has($manualParamBind)) {
                            $paramsAuto[] = $this->get($manualParamBind);
                        } else {
                            if (is_callable($manualParamBind)) {
                                $finalName = $manualParamBind($classname, $classRef->getName(), $classRef->getShortName(), $classRef->getNamespaceName());
                                if ($this->has($finalName)) {
                                    $paramsAuto[] = $this->get($finalName);
                                } else {
                                    if ($p->isOptional()) {
                                        $paramsAuto[] = $p->getDefaultValue();
                                    } else {
                                        $error[$argName] = "Parameter not found for untyped(closure return $finalName) arg name $argName when creating $classname";
                                    }
                                }
                            } else {
                                if ($p->isOptional()) {
                                    $paramsAuto[] = $p->getDefaultValue();
                                } else {
                                    $error[$argName] = "Parameter not found for untyped arg name $argName when creating $classname";
                                }
                            }
                        }
                    } else {
                        if ($p->isOptional()) {
                            $paramsAuto[] = $p->getDefaultValue();
                        } else {
                            $error[$argName] = "Parameter not found for untyped arg name $argName when creating $classname";
                        }
                    }
                }
            }
        }

        if (!empty($error)) {
            $errMsg = \print_r($error, true);
            $logger = \LoggerFactory::getLogger(__CLASS__);
            $logger->error('[DI ERROR] ' . $errMsg);
            return null;
        }
        return $paramsAuto;
    }

    public function invokeMethod($obj, $className, $classRef, $methodName) {
        $methodRef = $classRef->getMethod($methodName);

        if ($methodRef->getNumberOfParameters() > 0) {
            $paramToInject = $this->injectMethodArgsWithDocBlock($methodRef);
            if (!empty($paramToInject)) {
                return $methodRef->invokeArgs($obj, $paramToInject);
            } else {
                $param = $methodRef->getParameters();
                $paramsAuto = $this->injectMethodArgs($className, $classRef, $param);
                return $methodRef->invokeArgs($obj, $paramsAuto);
            }
        }
        else {
            return $obj->$methodName();
        }
    }

    public function resolveConstructor($className, $retReflection = false) {
        //IoC
        $reflect = new \ReflectionClass($className);
        $constructor = $reflect->getConstructor();
        $controller = null;

        if ($constructor != null) {
            $paramsInConstruct = $constructor->getNumberOfParameters();
            if ($paramsInConstruct > 0) {
                $paramToInject = $this->injectMethodArgsWithDocBlock($constructor);
                if (!empty($paramToInject)) {
                    $controller = $reflect->newInstanceArgs($paramToInject);
                } else {
                    $param = $constructor->getParameters();
                    $paramsAuto = $this->injectMethodArgs($className, $reflect, $param);
                    $controller = $reflect->newInstanceArgs($paramsAuto);
                }
            }
            else {
                $controller = $reflect->newInstanceArgs();
            }
        }
        else {
            $controller = new $className();
        }

        if (!$retReflection) {
            return $controller;
        }

        return [$controller, $reflect];
    }
}