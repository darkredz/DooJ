package com.doophp.util;

import com.caucho.json.JsonArray;
import com.caucho.quercus.env.Callable;
import com.caucho.quercus.env.Env;
import com.caucho.quercus.env.Value;
import com.doophp.db.SQLClient;
import io.vertx.core.http.HttpServerOptions;
import io.vertx.core.impl.VertxImpl;
import io.vertx.core.net.*;
import io.vertx.lang.php.util.PhpTypes;

/**
 * Created by leng on 12/17/16.
 */
public class VertxUtil {

    public static void initForPhp(Env env, VertxImpl vertx, Value configValue, String poolName, Callable handler) {
        SQLClient db = new SQLClient(env, vertx, configValue, poolName);
        db.initForPhp(env, handler);
        handler.call(env, PhpTypes.arrayFromJson(env, new io.vertx.core.json.JsonArray().add(true)));
    }

    public static HttpServerOptions createSslOptionWithJks(Env env, String certPath, String password, boolean trustOption) {
        HttpServerOptions httpOpts = new HttpServerOptions();
        JksOptions jks = createJksOptions(env, certPath, password);
        httpOpts.setSsl(true).setKeyStoreOptions(jks);
        if (trustOption) {
            httpOpts.setTrustStoreOptions(jks);
        }
        return httpOpts;
    }

    public static HttpServerOptions createSslOptionWithPKCS12(Env env, String certPath, String password, boolean trustOption) {
        HttpServerOptions httpOpts = new HttpServerOptions();
        PfxOptions pfx = new PfxOptions().setPath(certPath);

        if (password != null) {
            pfx.setPassword(password);
        }
        httpOpts.setSsl(true).setPfxKeyCertOptions(pfx);
        if (trustOption) {
            httpOpts.setPfxTrustOptions(pfx);
        }
        return httpOpts;
    }

    public static HttpServerOptions createSslOptionWithPEM(Env env, String certPath, String certKeyPath, boolean trustOption) {
        HttpServerOptions httpOpts = new HttpServerOptions();
        PemKeyCertOptions pem = new PemKeyCertOptions().setCertPath(certPath);
        if (certKeyPath != null) {
            pem.setKeyPath(certKeyPath);
        }
        httpOpts.setSsl(true).setPemKeyCertOptions(pem);
        if (trustOption) {
            httpOpts.setPemTrustOptions(new PemTrustOptions().addCertPath(pem.getCertPath()).addCertValue(pem.getCertValue()));
        }
        return httpOpts;
    }

    public static JksOptions createJksOptions(Env env, String certPath, String password) {
        if (password == null) {
            return new JksOptions().setPath(certPath);
        }
        return new JksOptions()
                .setPassword(password)
                .setPath(certPath);
    }

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
