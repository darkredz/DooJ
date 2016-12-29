package com.doophp.util;

import com.caucho.quercus.env.Callable;
import com.caucho.quercus.env.Env;
import com.caucho.quercus.env.Value;
import io.vertx.core.impl.VertxImpl;

/**
 * Created by leng on 12/17/16.
 */
public class VertxUtil {

    public static void execBlock(Env env, VertxImpl vertx, Callable handlerFuture, Callable handlerResult) {
        vertx.executeBlocking(future -> {
            Value ret = handlerFuture.call(env);
            future.complete(ret.toJavaObject());
        }, result -> {
            handlerResult.call(env, env.wrapJava(result));
        });
    }

    public static void execBlock(Env env, VertxImpl vertx, Callable handlerFuture, Callable handlerResult, boolean ordered) {
        vertx.executeBlocking(future -> {
            Value ret = handlerFuture.call(env);
            future.complete(ret.toJavaObject());
        }, ordered, result -> {
            handlerResult.call(env, env.wrapJava(result));
        });
    }


    public static void execBlockParallel(Env env, VertxImpl vertx, Callable handlerFuture, Callable handlerResult) {
        vertx.executeBlocking(future -> {
            Value ret = handlerFuture.call(env);
            future.complete(ret.toJavaObject());
        }, false, result -> {
            handlerResult.call(env, env.wrapJava(result));
        });
    }

}
