<?php
/**
 * Created by IntelliJ IDEA.
 * User: leng
 * Date: 11/03/2017
 * Time: 10:43 PM
 */

/**
 * An implementation of the Promise pattern based on Sabre event promise.
 * Modified to used with Vertx and DooJ framework which runs on JVM
 *
 * A promise represents the result of an asynchronous operation.
 * At any given point a promise can be in one of three states:
 *
 * 1. Pending (the promise does not have a result yet).
 * 2. Fulfilled (the asynchronous operation has completed with a result).
 * 3. Rejected (the asynchronous operation has completed with an error).
 * 4. Start (first call of promise to start the call)
 *
 * Usage
 *
 * # Repeat a the same async call X times based on an array of values
 *        $this->resultArr = [];
 *        $this->errorHandler = function ($error, $promise) {
 *            $this->app->logDebug('PROMISE ERROR');
 *            $promise->reject($error);
 *            $this->sendError($error);
 *        };
 *
 *        \DooPromise::repeat($ids, [
 *            //on each promise, doing the same thing based on the Array of parameters passed in
 *            'then' => function ($param, $result, $nextPromise) {
 *                $id = $param;
 *                if (!\is_bool($result)) {
 *                    $this->resultArr[] = $result;
 *                }
 *
 *                $this->serviceCall('getCatalogItemDetails', $id, function($result) use ($id, $nextPromise) {
 *                    $this->app->logDebug('Fulfill ' . $id);
 *                    $nextPromise->fulfill($result);
 *                }, $this->defaultErrorHandler($this->errorHandler, $nextPromise, true, true));
 *            },
 *
 *            //just logging the reason where the promise failed (here is only bcoz of DB error)
 *            'otherwise' => function($reason) {
 *                $this->app->logDebug('otherwise');
 *                $this->app->logDebug($reason);
 *                $this->app->trace($reason);
 *            },
 *
 *            //final last call on the list of repeat promise, should now return result to client
 *            'final' => function ($result) {
 *                $this->resultArr[] = $result;
 *                $this->app->logDebug('LAST THEN');
 *                $this->sendResult($this->resultArr);
 *            }
 *        ]);
 *
 *        # ------- 2nd type of Promise usage, using a for loop -----
 *        $promise = new Promise();
 *        $subPromise = null;
 *
 *        $this->errorHandler = function ($error, $promise) {
 *            $this->app->logDebug('PROMISE ERROR');
 *            $promise->reject($error);
 *            $this->sendError($error);
 *        };
 *
 *        foreach ($ids as $id) {
 *            $p = ($subPromise) ? $subPromise : $promise;
 *
 *            $subPromise = $p->then(function($result) use ($id, $promise) {
 *                if (!\is_bool($result)) {
 *                    $this->resultArr[] = $result;
 *                }
 *
 *                $nextPromise = new Promise();
 *                $this->serviceCall('getCatalogItemDetails', $id, function($result) use ($nextPromise) {
 *                    $this->app->logDebug('Fulfill');
 *                    $nextPromise->fulfill($result);
 *                }, $this->defaultErrorHandler($this->errorHandler, $nextPromise , true, true));
 *
 *                return $nextPromise;
 *            })
 *            ->otherwise(function($reason) {
 *                $this->app->logDebug('otherwise');
 *                $this->app->logDebug($reason);
 *                $this->app->trace($reason);
 *            });
 *        }
 *
 *        //last
 *        $subPromise->then(function($result) {
 *            $this->resultArr[] = $result;
 *            $this->app->logDebug('LAST THEN');
 *            $this->sendResult($this->resultArr);
 *        });
 *
 *        $promise->start(true);
 *
 *
 *        # 3rd type, most common usage, $promise->then()->then()->then(); and call $promise->start() to start()
 *        $promise->then(function($firstPromise) use ($ids) {
 *            $this->app->logDebug('CALL 1');
 *
 *            $this->serviceCall('getCatalogItemDetails', $ids[0], function($result) use ($firstPromise) {
 *                $this->app->logDebug('Fulfill 1');
 *                $firstPromise->fulfill($result);
 *            }, $this->defaultErrorHandler());
 *
 *            return $firstPromise;
 *        })
 *        ->then(function($result) use ($ids) {
 *
 *            $nextPromise = new Promise();
 *            $this->resultArr[] = $result;
 *            $this->app->logDebug('CALL 2');
 *
 *            $this->serviceCall('getCatalogItemDetails', $ids[1], function($result) use ($nextPromise) {
 *                $this->app->logDebug('Fulfill 2');
 *                $nextPromise->fulfill($result);
 *            }, $this->defaultErrorHandler());
 *
 *            return $nextPromise;
 *        })
 *        ->then(function($result) {
 *            $this->resultArr[] = $result;
 *            $this->app->logDebug('LAST THEN NOW FINAL ' . \sizeof($this->resultArr));
 *            $this->sendResult($this->resultArr);
 *        });
 *
 *        $promise->start();
 */
class DooPromise
{
    /**
     * The asynchronous operation is pending.
     */
    const PENDING = 0;
    /**
     * The asynchronous operation has completed, and has a result.
     */
    const FULFILLED = 1;
    /**
     * The asynchronous operation has completed with an error.
     */
    const REJECTED = 2;

    /**
     * The asynchronous operation has started.
     */
    const START = 3;

    /**
     * The current state of this promise.
     *
     * @var int
     */
    public $state = self::PENDING;

    /**
     * Creates the promise.
     *
     * The passed argument is the executor. The executor is automatically
     * called with two arguments.
     *
     * Each are callbacks that map to $this->fulfill and $this->reject.
     * Using the executor is optional.
     */
    function __construct(callable $executor = null)
    {
        if ($executor) {
            $executor(
                [$this, 'fulfill'],
                [$this, 'reject']
            );
        }
    }

    /**
     * This method allows you to specify the callback that will be called after
     * the promise has been fulfilled or rejected.
     *
     * Both arguments are optional.
     *
     * This method returns a new promise, which can be used for chaining.
     * If either the onFulfilled or onRejected callback is called, you may
     * return a result from this callback.
     *
     * If the result of this callback is yet another promise, the result of
     * _that_ promise will be used to set the result of the returned promise.
     *
     * If either of the callbacks return any other value, the returned promise
     * is automatically fulfilled with that value.
     *
     * If either of the callbacks throw an exception, the returned promise will
     * be rejected and the exception will be passed back.
     */
    function then(callable $onFulfilled = null, callable $onRejected = null)
    {//: DooPromise {
        // This new subPromise will be returned from this function, and will
        // be fulfilled with the result of the onFulfilled or onRejected event
        // handlers.
//        \LoggerFactory::getLogger("app")->info('THEN');
        $subPromise = new DooPromise();
        switch ($this->state) {
            case self::PENDING :
                // The operation is pending, so we keep a reference to the
                // event handlers so we can call them later.
                $this->subscribers[] = [$subPromise, $onFulfilled, $onRejected];
//                array_unshift($this->subscribers, [$subPromise, $onFulfilled, $onRejected]);
                break;
            case self::FULFILLED :
                // The async operation is already fulfilled, so we trigger the
                // onFulfilled callback asap.
                $this->invokeCallback($subPromise, $onFulfilled);
                break;
            case self::REJECTED :
                // The async operation failed, so we call teh onRejected
                // callback asap.
                $this->invokeCallback($subPromise, $onRejected);
                break;
        }
        return $subPromise;
    }

    /**
     * Add a callback for when this promise is rejected.
     *
     * Its usage is identical to then(). However, the otherwise() function is
     * preferred.
     */
    function otherwise(callable $onRejected)
    {//} : DooPromise {
        return $this->then(null, $onRejected);
    }

    /**
     * Marks this promise as fulfilled and sets its return value.
     *
     * @param mixed $value
     * @return void
     */
    function fulfill($value = null)
    {
        if ($this->state !== self::PENDING) {
//            throw new \Exception('This promise is already resolved, and you\'re not allowed to resolve a promise more than once');
            \LoggerFactory::getLogger("app")->info('FULFILL:This promise is already resolved, and you\'re not allowed to resolve a promise more than once');
            return;
        }
        $this->state = self::FULFILLED;
        $this->value = $value;
        $this->subscribers = array_reverse($this->subscribers);
        foreach ($this->subscribers as $subscriber) {
            $this->invokeCallback($subscriber[0], $subscriber[1]);
        }
    }

    /**
     * Marks this promise as rejected, and set it's rejection reason.
     *
     * @return void
     */
    function reject($reason)
    {
        if ($this->state !== self::PENDING && $this->state !== self::START) {
//            throw new \Exception('This promise is already resolved, and you\'re not allowed to resolve a promise more than once');
            \LoggerFactory::getLogger("app")->info('REJECT: This promise is already resolved, and you\'re not allowed to resolve a promise more than once');
            return;
        }
        $this->state = self::REJECTED;
        $this->value = $reason;
        $this->subscribers = array_reverse($this->subscribers);
        foreach ($this->subscribers as $subscriber) {
            $this->invokeCallback($subscriber[0], $subscriber[2]);
        }
    }

//Useless in Vertx which blocks the eventloop thread
//    /**
//     * Stops execution until this promise is resolved.
//     *
//     * This method stops exection completely. If the promise is successful with
//     * a value, this method will return this value. If the promise was
//     * rejected, this method will throw an exception.
//     *
//     * This effectively turns the asynchronous operation into a synchronous
//     * one. In PHP it might be useful to call this on the last promise in a
//     * chain.
//     *
//     * @return mixed
//     */
//    function wait() {
//        $hasEvents = true;
//        while ($this->state === self::PENDING) {
//            if (!$hasEvents) {
//                throw new \LogicException('There were no more events in the loop. This promise will never be fulfilled.');
//            }
//            // As long as the promise is not fulfilled, we tell the event loop
//            // to handle events, and to block.
//            $hasEvents = Loop\tick(true);
//        }
//        if ($this->state === self::FULFILLED) {
//            // If the state of this promise is fulfilled, we can return the value.
//            return $this->value;
//        } else {
//            // If we got here, it means that the asynchronous operation
//            // errored. Therefore we need to throw an exception.
//            throw $this->value;
//        }
//    }

    /**
     * A list of subscribers. Subscribers are the callbacks that want us to let
     * them know if the callback was fulfilled or rejected.
     *
     * @var array
     */
    protected $subscribers = [];
    /**
     * The result of the promise.
     *
     * If the promise was fulfilled, this will be the result value. If the
     * promise was rejected, this property hold the rejection reason.
     *
     * @var mixed
     */
    protected $value = null;

    /**
     * This method is used to call either an onFulfilled or onRejected callback.
     *
     * This method makes sure that the result of these callbacks are handled
     * correctly, and any chained promises are also correctly fulfilled or
     * rejected.
     *
     * @param DooPromise $subPromise
     * @param callable $callBack
     * @return void
     */
    private function invokeCallback(DooPromise $subPromise, callable $callBack = null)
    {
        // This makes the order of execution more predictable.
//        \LoggerFactory::getLogger("app")->info("invokeCallback");

        $this->nextTick($callBack, $subPromise);
//        Loop\nextTick(function() use ($callBack, $subPromise) {
//            if (is_callable($callBack)) {
//                try {
//                    $result = $callBack($this->value);
//                    if ($result instanceof Promise) {
//                        // If the callback (onRejected or onFulfilled)
//                        // returned a promise, we only fulfill or reject the
//                        // chained promise once that promise has also been
//                        // resolved.
//                        $result->then([$subPromise, 'fulfill'], [$subPromise, 'reject']);
//                    } else {
//                        // If the callback returned any other value, we
//                        // immediately fulfill the chained promise.
//                        $subPromise->fulfill($result);
//                    }
//                } catch (Throwable $e) {
//                    // If the event handler threw an exception, we need to make sure that
//                    // the chained promise is rejected as well.
//                    $subPromise->reject($e);
//                }
//            } else {
//                if ($this->state === self::FULFILLED) {
//                    $subPromise->fulfill($this->value);
//                } else {
//                    $subPromise->reject($this->value);
//                }
//            }
//        });
    }

    protected function nextTick($callBack, $subPromise)
    {
        if (is_callable($callBack)) {
//                try {
            $result = @$callBack($this->value);
            if ($result instanceof DooPromise) {
                // If the callback (onRejected or onFulfilled)
                // returned a promise, we only fulfill or reject the
                // chained promise once that promise has also been
                // resolved.
//                if ($result->state == self::REJECTED) return;

                $result->then([$subPromise, 'fulfill'], [$subPromise, 'reject']);
            } else {
                // If the callback returned any other value, we
                // immediately fulfill the chained promise.
                $subPromise->fulfill($result);
            }
//                } catch (Throwable $e) {
//                    // If the event handler threw an exception, we need to make sure that
//                    // the chained promise is rejected as well.
//                    $subPromise->reject($e);
//                }
        } else {
            if ($this->state === self::FULFILLED) {
                $subPromise->fulfill($this->value);
            } else {
                $subPromise->reject($this->value);
            }
        }
//        }
    }


    /**
     * Marks this promise as fulfilled and sets its return value.
     *
     * @param mixed $value
     * @return void
     */
    function start($value = null)
    {
        if ($this->state !== self::PENDING && $this->state !== self::START) {
//            throw new \Exception('This promise is already resolved, and you\'re not allowed to resolve a promise more than once');
            \LoggerFactory::getLogger("app")->info('START: This promise is already resolved, and you\'re not allowed to resolve a promise more than once');
            return;
        }
        $this->state = self::START;
//        $this->value = $value;

        if (!\is_null($value)) {
            $this->value = $value;
        } else {
            $this->value = new DooPromise();
        }

        $this->subscribers = array_reverse($this->subscribers);
        foreach ($this->subscribers as $subscriber) {
            if ($this->state == self::REJECTED) {
                break;
            }
            $this->invokeCallback($subscriber[0], $subscriber[1]);
        }
    }

    public static function repeat($paramList, $allCallbacks, $startValue = true)
    {
        $promise = new DooPromise();
        $subPromise = null;

        $nextCallback = $allCallbacks['then'];
        $otherwiseCallback = $allCallbacks['otherwise'];
        if (empty($allCallbacks['otherwise'])) {
            $otherwiseCallback = function ($reason) {
            };
        } else {
            $otherwiseCallback = $allCallbacks['otherwise'];
        }
        $finalCallback = $allCallbacks['final'];

        foreach ($paramList as $param) {
            $p = ($subPromise) ? $subPromise : $promise;

            $subPromise = $p->then(function ($result) use ($param, $nextCallback) {
                $nextPromise = new DooPromise();
                $nextCallback($param, $result, $nextPromise);
                return $nextPromise;
            });
        }
        $subPromise = $subPromise->then($finalCallback)->otherwise($otherwiseCallback);

        if ($startValue === null) {
            return $promise;
        }
        $promise->start($startValue);
    }
}