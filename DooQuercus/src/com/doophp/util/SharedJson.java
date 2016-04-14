package com.doophp.util;

import com.caucho.quercus.env.Env;
import com.caucho.quercus.env.Value;
import io.vertx.core.Vertx;
import io.vertx.core.json.JsonObject;
import io.vertx.core.shareddata.AsyncMap;
import io.vertx.core.shareddata.SharedData;

/**
 * Created by leng on 4/15/16.
 */
public class SharedJson {

    public static void get(Vertx vertx, final String mapName, final String sharedKey, final Value value)
    {
        SharedData sd = vertx.sharedData();
        sd.<String, JsonObject>getClusterWideMap(mapName, res -> {
            final Env env = Env.getCurrent();

            if (res.succeeded()) {
                AsyncMap<String, JsonObject> map = res.result();
                map.get(sharedKey, resGet -> {
                    if (resGet.succeeded()) {
                        // Successfully got the value
                        Object val = resGet.result();
//                        System.out.println("val is " + val);
//                        System.out.println("val class is " + val.getClass());
                        value.toCallable(env, false).call(env, env.wrapJava(val));
                    } else {
                        // Something went wrong!
//                        System.out.println("CANNOT get "+ sharedKey +" "+ res.cause());
                        res.cause().printStackTrace();
                        value.toCallable(env, false).call(env, env.wrapJava(null));
                    }
                });
            } else {
//                System.out.println("MAP RETRIEVE FAILED. " + res.cause());
//                res.cause().printStackTrace();
                value.toCallable(env, false).call(env, env.wrapJava(null));
            }
        });
    }

    public static void put(Vertx vertx, final String mapName, final String sharedKey, final JsonObject json, final Value value)
    {
        SharedData sd = vertx.sharedData();
        sd.<String, JsonObject>getClusterWideMap(mapName, res -> {
            final Env env = Env.getCurrent();

            if (res.succeeded()) {
                AsyncMap<String, JsonObject> map = res.result();
                map.put(sharedKey, json, resPut -> {
                    if (resPut.succeeded()) {
//                        System.out.println("done setting "+ sharedKey +" "+ json);
                        value.toCallable(env, false).call(env, env.wrapJava(true));
                    } else {
//                        System.out.println("CANNOT set "+ sharedKey +" "+ resPut.cause());
//                        resPut.cause().printStackTrace();
                        value.toCallable(env, false).call(env, env.wrapJava(false));
                    }
                });
            } else {
//                System.out.println("MAP RETRIEVE FAILED. " + res.cause());
//                res.cause().printStackTrace();
                value.toCallable(env, false).call(env, env.wrapJava(false));
            }
        });
    }
}
