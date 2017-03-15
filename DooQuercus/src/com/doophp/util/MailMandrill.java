package com.doophp.util;

import com.caucho.quercus.env.*;
import io.vertx.core.http.HttpClient;
import io.vertx.core.http.HttpClientOptions;
import io.vertx.core.http.HttpClientRequest;
import io.vertx.core.impl.VertxImpl;
import io.vertx.core.json.JsonArray;
import io.vertx.core.json.JsonObject;
import io.vertx.core.logging.Logger;
import io.vertx.lang.php.util.PhpTypes;

import java.util.ArrayList;
import java.util.List;
import java.util.Map;

/**
 * Created by Leng on 06/05/2016.
 */
public class MailMandrill {

    public Logger logger;
    public JsonObject conf;
    public VertxImpl vertx;
    public Env env;
    public boolean debugEnabled = true;

    public String prefixLogInfo = "[INFO_MAIL_MANDRILL]: ";
    public String prefixLogDebug = "[DEBUG_MAIL_MANDRILL]: ";
    public String prefixLogError = "[ERROR_MAIL_MANDRILL]: ";

    public MailMandrill(Env env, VertxImpl vertx)
    {
        this.env = env;
        this.vertx = vertx;
    }

    public MailMandrill(Env env, VertxImpl vertx, JsonObject conf)
    {
        this.conf = conf;
        this.env = env;
        this.vertx = vertx;
    }

    public MailMandrill(Env env, VertxImpl vertx, ObjectExtValue conf)
    {
        this.env = env;
        this.vertx = vertx;
        this.setConfig(conf);
    }

    public void setConfig(ObjectExtValue conf) {
        JsonObject confJson = new JsonObject();

        for (Map.Entry<Value, Value> entry : conf.entrySet()) {
            Value val = entry.getValue();
            String className = val.getClassName().toLowerCase();

            //"com.caucho.quercus.env.LongCacheValue"
            if (className.indexOf("long") > -1 || className.indexOf("integer") > -1) {
                confJson.put(entry.getKey().toString(), val.toInt());
            }
            else if ( className.indexOf("decimal") > -1 || className.indexOf("double") > -1 || className.indexOf("float") > -1 || className.indexOf("number") > -1) {
                confJson.put(entry.getKey().toString(), val.toDouble());
            }
            else if (val.isBoolean()) {
                confJson.put(entry.getKey().toString(), val.toBoolean());
            }
            else {
                confJson.put(entry.getKey().toString(), val.toString());
            }
        }
        this.conf = confJson;
    }

    public void setConfig(ArrayValueImpl conf) {
        JsonObject confJson = new JsonObject();

        for (Map.Entry<Value, Value> entry : conf.entrySet()) {
            Value val = entry.getValue();
            String className = val.getClassName().toLowerCase();

            //"com.caucho.quercus.env.LongCacheValue"
            if (className.indexOf("long") > -1 || className.indexOf("integer") > -1) {
                confJson.put(entry.getKey().toString(), val.toInt());
            }
            else if ( className.indexOf("decimal") > -1 || className.indexOf("double") > -1 || className.indexOf("float") > -1 || className.indexOf("number") > -1) {
                confJson.put(entry.getKey().toString(), val.toDouble());
            }
            else if (val.isBoolean()) {
                confJson.put(entry.getKey().toString(), val.toBoolean());
            }
            else {
                confJson.put(entry.getKey().toString(), val.toString());
            }
        }
        this.conf = confJson;
    }

    public void setConfig(JsonObject conf) {
        this.conf = conf;
    }

    public void setLogger(Logger logger) {
        this.logger = logger;
    }

    public void logInfo(Object obj, Object obj2) {
        if (logger == null) return;
        logDebug(prefixLogInfo + obj, obj2);
    }

    public void logInfo(Object obj) {
        if (logger == null) return;
        logDebug(prefixLogInfo + obj);
    }

    public void logDebug(Object obj, Object obj2) {
        if (logger == null || !debugEnabled) return;
        logDebug(prefixLogDebug + obj, obj2);
    }

    public void logDebug(Object obj) {
        if (logger == null || !debugEnabled) return;
        logDebug(prefixLogDebug + obj);
    }

    public void logError(Object obj, Object obj2) {
        if (logger == null) return;
        logError(prefixLogError + obj, obj2);
    }

    public void logError(Object obj) {
        if (logger == null) return;
        logError(prefixLogError + obj);
    }

//    public static Value getFrom(ObjectExtValue obj, String key)
//    {
//        for (Map.Entry<Value, Value> entry : obj.entrySet()) {
//            if (entry.getKey().toString().equals(key)) {
//                return entry.getValue();
//            }
//        }
//        return null;
//    }
//
//    public static Object getFrom(ArrayValue obj, String key)
//    {
//        for (Map.Entry<Value, Value> entry : obj.entrySet()) {
//            if (entry.getKey().toString().equals(key)) {
//                return entry.getValue();
//            }
//        }
//        return null;
//    }

    public static Object getFrom(JsonObject obj, String key)
    {
        if (obj.containsKey(key) == false) {
            return null;
        }
        return obj.getValue(key);
    }


    public void sendMail(String to, String toName, String from, String fromName, String subject, String htmlBody, String textBody, ArrayValue tags) {
        List tagList = tags.toJavaList(env, ArrayList.class);
        JsonArray tagArr = new JsonArray(tagList);
        sendMail(to, toName, from, fromName, subject, htmlBody, textBody, tagArr, null, null);
    }

    public void sendMail(String to, String toName, String from, String fromName, String subject, String htmlBody, String textBody, ArrayValue tags, final Callable handler, final Callable errorHandler) {
        List tagList = tags.toJavaList(env, ArrayList.class);
        JsonArray tagArr = new JsonArray(tagList);
        sendMail(to, toName, from, fromName, subject, htmlBody, textBody, tagArr, handler, errorHandler);
    }

    public void sendMail(String to, String toName, String from, String fromName, String subject, String htmlBody, String textBody, JsonArray tags) {
        sendMail(to, toName, from, fromName, subject, htmlBody, textBody, tags, null, null);
    }

    public void sendMail(String to, String toName, String from, String fromName, String subject, String htmlBody, String textBody, JsonArray tags, final Callable handler, final Callable errorHandler) {
//        ArrayValue mail = (ArrayValue) getFrom(conf, "MAIL_SERVICE");
        JsonObject mail = this.conf;

        JsonObject mailJson = new JsonObject();
        mailJson.put("key", getFrom(mail, "apikey").toString());

        JsonObject msgJson = new JsonObject();
        if (htmlBody != null) {
            msgJson.put("html", htmlBody);
        }
        msgJson.put("subject", subject);

        if (from == null || fromName == null) {
            msgJson.put("from_email", getFrom(mail, "fromEmail").toString());
            msgJson.put("from_name",  getFrom(mail, "fromName").toString());
        } else {
            msgJson.put("from_email", from);
            msgJson.put("from_name",  fromName);
        }

        JsonObject recipient1 = new JsonObject();
        if (getFrom(conf, "toEmailDev")!=null && !getFrom(mail, "toEmailDev").toString().equals("")) {
            logDebug("SENT TO " + getFrom(mail, "toEmailDev").toString());
            recipient1.put("email", getFrom(mail, "toEmailDev").toString());
        }
        else {
            recipient1.put("email", to);
        }
        if (toName != null && !toName.isEmpty()) {
            recipient1.put("name", toName);
        }
        recipient1.put("type", "to");

        List recipientArr = new ArrayList<JsonObject>(){{
            add(recipient1);
        }};
        msgJson.put("to", recipientArr);

        msgJson.put("tags", tags);
        if (textBody == null) {
            msgJson.put("auto_text", true);
        }
        else {
            msgJson.put("text", textBody);
        }

        mailJson.put("message", msgJson);

        final String body = mailJson.toString();
        logDebug(body);

        HttpClientOptions httpOpt = new HttpClientOptions().setDefaultHost("mandrillapp.com").setDefaultPort(443).setSsl(true).setConnectTimeout(10000).setTrustAll(true).setTryUseCompression(true);

        if (getFrom(mail, "poolSize") != null) {
            httpOpt.setMaxPoolSize((int) getFrom(mail, "poolSize"));
        }

        HttpClient client = vertx.createHttpClient(httpOpt);

        HttpClientRequest request = client.post("/api/1.0/messages/send.json", response -> {
            logDebug("Received response with status code " + response.statusCode());

            response.bodyHandler(buffer -> {
                logDebug("ENDED email response");
                if (response.statusCode() > 199 && response.statusCode() < 300) {
                    if (handler != null) {
                        handler.call(env, env.wrapJava(buffer.toString("utf-8")));
                    }
                } else {
                    if (errorHandler != null) {
                        errorHandler.call(env, env.wrapJava(buffer.toString("utf-8")));
                    }
                }
            });
        });

        request.exceptionHandler(e -> {
            logError("Email http client exception: " + e.getMessage());
            e.printStackTrace();

            if (errorHandler != null) {
                errorHandler.call(env, env.wrapJava("Email http client exception"));
            }
        });

        request.putHeader("User-Agent", "Mandrill-Curl/1.0")
            .putHeader("Content-Type", "application/json")
            .putHeader("Content-Length", body.length() + "")
            .write(body).end();
        client.close();
    }
}
