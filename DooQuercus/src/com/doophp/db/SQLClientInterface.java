package com.doophp.db;

import com.caucho.quercus.env.Callable;
import com.caucho.quercus.env.Env;
import io.vertx.core.AsyncResult;
import io.vertx.core.Handler;
import io.vertx.core.json.JsonArray;
import io.vertx.core.json.JsonObject;
import io.vertx.ext.sql.ResultSet;
import io.vertx.ext.sql.SQLConnection;
import io.vertx.ext.sql.UpdateResult;

/**
 * Created by leng on 1/18/17.
 */
public interface SQLClientInterface {

    public void query(Env env, String sql, JsonArray params, Callable handler, Callable errorHandler);

    public void delete(Env env, String sql, Callable handler, Callable errorHandler);

    public void update(Env env, String sql, JsonArray params, Callable handler, Callable errorHandler);

    public void queryRaw(SQLConnection conn, String sql, JsonArray params, Handler<JsonArray> handler, Handler<Throwable> errorHandler);

    public void updateRaw(SQLConnection conn, String sql, JsonArray params, Handler<JsonObject> handler, Handler<Throwable> errorHandler);

    public void connect(Handler<AsyncResult<SQLConnection>> res);

    public void startTx(SQLConnection conn, Handler<ResultSet> done);

    public void rollbackTx(SQLConnection conn, Handler<ResultSet> done);

    public void commit(SQLConnection conn, Handler<AsyncResult<Void>> done);

    public void queryWithHandler(Env env, String sql, JsonArray params, Handler<JsonArray> callbackHandler, Callable errorHandler);

    public void deleteWithHandler(Env env, String sql, JsonArray params, Handler<UpdateResult> callbackHandler, Callable errorHandler);

    public void insertWithHandler(Env env, String sql, JsonArray params, Handler<UpdateResult> callbackHandler, Callable errorHandler);

    public void updateWithHandler(Env env, String sql, JsonArray params, Handler<UpdateResult> callbackHandler, Callable errorHandler);

}
