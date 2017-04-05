package com.doophp.db;

import com.caucho.quercus.env.Callable;
import com.caucho.quercus.env.Env;
import com.caucho.quercus.env.NullValue;
import com.caucho.quercus.env.Value;
import io.vertx.core.AsyncResult;
import io.vertx.core.Handler;
import io.vertx.core.impl.VertxImpl;
import io.vertx.core.json.Json;
import io.vertx.core.json.JsonArray;
import io.vertx.core.json.JsonObject;
import io.vertx.core.logging.Logger;
import io.vertx.ext.asyncsql.AsyncSQLClient;
import io.vertx.ext.asyncsql.MySQLClient;
import io.vertx.ext.sql.ResultSet;
import io.vertx.ext.sql.SQLConnection;
import io.vertx.ext.sql.UpdateResult;
import io.vertx.lang.php.util.PhpTypes;
import org.jooq.DSLContext;
import org.jooq.SQLDialect;
import org.jooq.conf.Settings;
import org.jooq.conf.StatementType;
import org.jooq.impl.DSL;

import java.util.List;
import java.util.ListIterator;

/**
 * Created by leng on 12/27/16.
 */
public class SQLClient implements SQLClientInterface {

    protected VertxImpl vertx;
    protected Logger logger;
    protected DSLContext dsl;
    protected AsyncSQLClient sqlClient;
    public boolean debugEnabled = true;

    public String prefixLogInfo = "[INFO_DB]: ";
    public String prefixLogDebug = "[DEBUG_DB]: ";
    public String prefixLogError = "[ERROR_DB]: ";

    public DSLContext dsl() {
        return dsl;
    }

    public AsyncSQLClient sqlClient() {
        return sqlClient;
    }

    public void setLogger(Logger logger) {
        this.logger = logger;
    }

    public void logInfo(Object obj, Object obj2) {
        if (logger == null) return;
        logger.info(prefixLogInfo + obj, obj2);
    }

    public void logInfo(Object obj) {
        if (logger == null) return;
        logger.info(prefixLogInfo + obj);
    }

    public void logDebug(Object obj, Object obj2) {
        if (logger == null || !debugEnabled) return;
        logger.debug(prefixLogDebug + obj, obj2);
    }

    public void logDebug(Object obj) {
        if (logger == null || !debugEnabled) return;
        logger.debug(prefixLogDebug + obj);
    }

    public void logError(Object obj, Object obj2) {
        if (logger == null) return;
        logger.error(prefixLogError + obj, obj2);
    }

    public void logError(Object obj) {
        if (logger == null) return;
        logger.error(prefixLogError + obj);
    }

    public SQLClient(Env env, VertxImpl vertx, Value configValue, String poolName) {
        JsonObject config = PhpTypes.arrayToJsonObject(env, configValue);
        this.vertx = vertx;
        sqlClient = MySQLClient.createShared(vertx, config, poolName);
        Settings settings = new Settings().withStatementType(StatementType.PREPARED_STATEMENT);
        dsl = DSL.using(SQLDialect.valueOf(config.getString("sql_dialect").toUpperCase()), settings);
    }

    public SQLClient(VertxImpl vertx, String configStr, String poolName) {
        JsonObject config = new JsonObject(configStr);
        this.vertx = vertx;
        sqlClient = MySQLClient.createShared(vertx, config, poolName);
        Settings settings = new Settings().withStatementType(StatementType.PREPARED_STATEMENT);
        dsl = DSL.using(SQLDialect.valueOf(config.getString("sql_dialect").toUpperCase()), settings);
    }

    public SQLClient(VertxImpl vertx, JsonObject config, String poolName) {
//        JsonObject mySQLClientConfig = new JsonObject().put("sql_dialect", "MYSQL").put("host", "127.0.0.1").put("database", "test").put("username", "root").put("password", "root").put("charset", "UTF-8");
        this.vertx = vertx;
        sqlClient = MySQLClient.createShared(vertx, config, poolName);
        Settings settings = new Settings().withStatementType(StatementType.PREPARED_STATEMENT);
        dsl = DSL.using(SQLDialect.valueOf(config.getString("sql_dialect").toUpperCase()), settings);
    }

    public void initForPhp(Env env, final Callable handler) {
        connect(connRes -> {
            final SQLConnection conn = connRes.result();

            startTx(conn, res1 -> {
                updateRaw(conn, "SELECT 1", null, res2 -> {
                    commit(conn, resCommit -> {
                        handler.call(env, env.wrapJava(res2));
                    });
                }, null);
            });
        });

        query(env, "SELECT 2", handler);
//        insertWithHandler(env, "INSERT INTO NULL", null, updateResult -> {
//            handler.call(env, env.wrapJava(updateResult));
//        }, null);
        handler.call(env, env.wrapJava(new UpdateResult()));
    }

    public void query(Env env, String sql, JsonArray params) {
        query(env, sql, params, null, null);
    }

    public void query(Env env, String sql, JsonArray params, final Callable handler) {
        query(env, sql, params, handler, null);
    }

    public void query(Env env, String sql, Value paramsArr) {
        query(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), null, null);
    }

    public void query(Env env, String sql, final Callable handler) {
        query(env, sql, null, handler, null);
    }

    public void query(Env env, String sql, final Callable handler, final Callable errorHandler) {
        query(env, sql, null, handler, errorHandler);
    }

    public void query(Env env, String sql, Value paramsArr, final Callable handler) {
        if (paramsArr == null) {
            query(env, sql, null, handler, null);
        } else {
            query(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), handler, null);
        }
    }

    public void query(Env env, String sql, JsonArray params, final Callable handler, final Callable errorHandler) {
        sqlClient.getConnection(res -> {
            if (res.succeeded()) {
                logDebug("Executing SQL Query: " + sql);
                final SQLConnection conn = res.result();
                Handler<AsyncResult<ResultSet>> resultHandler = new Handler<AsyncResult<ResultSet>>() {
                    @Override
                    public void handle(final AsyncResult<ResultSet> res2) {
                        if (res2.succeeded()) {
                            ResultSet queryRes = res2.result();
                            JsonArray rows = null;
                            if (queryRes != null && queryRes.getNumRows() >= 0) {
//                                logDebug("No. of rows: " + queryRes.getNumRows());
                                rows = new JsonArray(queryRes.getRows());
//                            for (JsonObject row : rows) {
//                                logDebug(row.getInteger("id"));
//                                logDebug(row.getString("first_name"));
//                            }
                            }
                            if (handler != null) {
                                if (rows == null) {
                                    handler.call(env, NullValue.NULL);
                                } else {
//                                    logDebug("STRING: " + queryRes.toJson() + "");
                                    handler.call(env, PhpTypes.arrayFromJson(env, rows));
                                }
                            }

                        } else {
                            if (res2.failed()) {
                                logError("SQL Query Failed! " + sql, res2.cause());
                            }
                            if (errorHandler != null) {
                                errorHandler.call(env, env.wrapJava(res2.cause()));
                            }
                        }
                        conn.close();
                    }
                };

                if (params == null) {
                    conn.query(sql, resultHandler);
                } else {
                    logDebug("Query Params = " + params.encode());
                    conn.queryWithParams(sql, params, resultHandler);
                }
            } else {
                // Failed to get connection - deal with it
                if (res.failed()) {
                    logError("SQL Connection Failed!", res.cause());
                }
                if (errorHandler != null) {
                    errorHandler.call(env, env.wrapJava(res.cause()));
                }
            }
        });
    }

    public void queryWithHandler(Env env, String sql, JsonArray params, Handler<JsonArray> handler, final Callable errorHandler) {
        sqlClient.getConnection(res -> {
            if (res.succeeded()) {
                logDebug("Executing SQL Query: " + sql);
                final SQLConnection conn = res.result();
                Handler<AsyncResult<ResultSet>> resultHandler = new Handler<AsyncResult<ResultSet>>() {
                    @Override
                    public void handle(final AsyncResult<ResultSet> res2) {
                        if (res2.succeeded()) {
                            ResultSet queryRes = res2.result();
                            JsonArray rows = null;
                            if (queryRes != null && queryRes.getNumRows() >= 0) {
                                rows = new JsonArray(queryRes.getRows());
                            }
                            if (handler != null) {
                                handler.handle(rows);
                            }

                        } else {
                            if (res2.failed()) {
                                logError("SQL Query Failed! " + sql, res2.cause());
                            }
                            if (errorHandler != null) {
                                errorHandler.call(env, env.wrapJava(res2.cause()));
                            }
                        }
                        conn.close();
                    }
                };

                if (params == null) {
                    conn.query(sql, resultHandler);
                } else {
                    logDebug("Query Params = " + params.encode());
                    conn.queryWithParams(sql, params, resultHandler);
                }
            } else {
                // Failed to get connection - deal with it
                if (res.failed()) {
                    logError("SQL Connection Failed!", res.cause());
                }
                if (errorHandler != null) {
                    errorHandler.call(env, env.wrapJava(res.cause()));
                }
            }
        });
    }


    public void insert(Env env, String sql, JsonArray params) {
        update(env, sql, params, null, null);
    }

    public void insert(Env env, String sql, JsonArray params, final Callable handler) {
        update(env, sql, params, handler, null);
    }

    public void insert(Env env, String sql, Value paramsArr) {
        update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), null, null);
    }

    public void insert(Env env, String sql, final Callable handler) {
        update(env, sql, null, handler, null);
    }

    public void insert(Env env, String sql, final Callable handler, final Callable errorHandler) {
        update(env, sql, null, handler, errorHandler);
    }

    public void insert(Env env, String sql, Value paramsArr, final Callable handler) {
        if (paramsArr == null) {
            update(env, sql, null, handler, null);
        } else {
            update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), handler, null);
        }
    }

    public void delete(Env env, String sql, JsonArray params) {
        update(env, sql, params, null, null);
    }

    public void delete(Env env, String sql, JsonArray params, final Callable handler) {
        update(env, sql, params, handler, null);
    }

    public void delete(Env env, String sql, Value paramsArr) {
        update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), null, null);
    }

    public void delete(Env env, String sql, final Callable handler) {
        update(env, sql, null, handler, null);
    }

    public void delete(Env env, String sql, final Callable handler, final Callable errorHandler) {
        update(env, sql, null, handler, errorHandler);
    }

    public void delete(Env env, String sql, Value paramsArr, final Callable handler) {
        if (paramsArr == null) {
            update(env, sql, null, handler, null);
        } else {
            update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), handler, null);
        }
    }

    public void update(Env env, String sql, JsonArray params) {
        update(env, sql, params, null, null);
    }

    public void update(Env env, String sql, JsonArray params, final Callable handler) {
        update(env, sql, params, handler, null);
    }

    public void update(Env env, String sql, Value paramsArr) {
        update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), null, null);
    }

    public void update(Env env, String sql, final Callable handler) {
        update(env, sql, null, handler, null);
    }

    public void update(Env env, String sql, final Callable handler, final Callable errorHandler) {
        update(env, sql, null, handler, errorHandler);
    }

    public void update(Env env, String sql, Value paramsArr, final Callable handler) {
        if (paramsArr == null) {
            update(env, sql, null, handler, null);
        } else {
            update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), handler, null);
        }
    }

    public void update(Env env, String sql, JsonArray params, final Callable handler, final Callable errorHandler) {
        sqlClient.getConnection(res -> {
            if (res.succeeded()) {
                logDebug("Executing SQL Update Query : " + sql);
                final SQLConnection conn = res.result();
                Handler<AsyncResult<UpdateResult>> resultHandler = new Handler<AsyncResult<UpdateResult>>() {
                    @Override
                    public void handle(final AsyncResult<UpdateResult> res2) {
                        if (res2.succeeded()) {
                            UpdateResult queryRes = res2.result();
                            if (handler != null) {
                                handler.call(env, PhpTypes.arrayFromJson(env, queryRes.toJson()));
                            }
                        } else {
                            if (res2.failed()) {
                                logError("SQL Update Query Failed! " + sql, res2.cause());
                            }
                            if (errorHandler != null) {
                                errorHandler.call(env, env.wrapJava(res2.cause()));
                            }
                        }
                        conn.close();
                    }
                };

                if (params == null) {
                    conn.update(sql, resultHandler);
                } else {
                    logDebug("Query Params = " + params.encode());
                    conn.updateWithParams(sql, params, resultHandler);
                }
            } else {
                // Failed to get connection - deal with it
                if (res.failed()) {
                    logError("SQL Connection Failed!", res.cause());
                }
                if (errorHandler != null) {
                    errorHandler.call(env, env.wrapJava(res.cause()));
                }
            }
        });
    }

    public void deleteWithHandler(Env env, String sql, JsonArray params, final Handler<UpdateResult> callbackHandler, final Callable errorHandler) {
        updateWithHandler(env, sql, params, callbackHandler, errorHandler);
    }

    public void insertWithHandler(Env env, String sql, JsonArray params, final Handler<UpdateResult> callbackHandler, final Callable errorHandler) {
        updateWithHandler(env, sql, params, callbackHandler, errorHandler);
    }

    public void updateWithHandler(Env env, String sql, JsonArray params, final Handler<UpdateResult> callbackHandler, final Callable errorHandler) {
        sqlClient.getConnection(res -> {
            if (res.succeeded()) {
                logDebug("Executing SQL Update Query : " + sql);
                final SQLConnection conn = res.result();
                Handler<AsyncResult<UpdateResult>> resultHandler = new Handler<AsyncResult<UpdateResult>>() {
                    @Override
                    public void handle(final AsyncResult<UpdateResult> res2) {
                        if (res2.succeeded()) {
                            UpdateResult queryRes = res2.result();
                            callbackHandler.handle(queryRes);
                        } else {
                            if (res2.failed()) {
                                logError("SQL Update Query Failed! " + sql, res2.cause());
                            }
                            if (errorHandler != null) {
                                errorHandler.call(env, env.wrapJava(res2.cause()));
                            }
                        }
                        conn.close();
                    }
                };

                if (params == null) {
                    conn.update(sql, resultHandler);
                } else {
                    logDebug("Query Params = " + params.encode());
                    conn.updateWithParams(sql, params, resultHandler);
                }
            } else {
                // Failed to get connection - deal with it
                if (res.failed()) {
                    logError("SQL Connection Failed!", res.cause());
                }
                if (errorHandler != null) {
                    errorHandler.call(env, env.wrapJava(res.cause()));
                }
            }
        });
    }

    public void queryRaw(String sql, JsonArray params, Handler<JsonArray> handler, Handler<Throwable> errorHandler) {
        sqlClient.getConnection(res -> {
            if (res.succeeded()) {
                final SQLConnection conn = res.result();
                queryRaw(conn, sql, params, handler, errorHandler);
            } else {
                // Failed to get connection - deal with it
                if (res.failed()) {
                    logError("SQL Connection Failed!", res.cause());
                }
                if (errorHandler != null) {
                    errorHandler.handle(res.cause());
                }
            }
        });
    }

    public void queryRaw(SQLConnection conn, String sql, JsonArray params, Handler<JsonArray> handler, Handler<Throwable> errorHandler) {
        Handler<AsyncResult<ResultSet>> resultHandler = new Handler<AsyncResult<ResultSet>>() {
            @Override
            public void handle(final AsyncResult<ResultSet> res2) {
                if (res2.succeeded()) {
                    ResultSet queryRes = res2.result();
                    JsonArray rows = null;
                    if (queryRes != null && queryRes.getNumRows() >= 0) {
                        rows = new JsonArray(queryRes.getRows());
                    }
                    if (handler != null) {
                        if (rows == null) {
                            handler.handle(null);
                        } else {
                            handler.handle(rows);
                        }
                    }

                } else {
                    if (res2.failed()) {
                        logError("SQL Query Failed! " + sql, res2.cause());
                    }
                    if (errorHandler != null) {
                        errorHandler.handle(res2.cause());
                    }
                }
            }
        };

        logDebug("Executing SQL Query: " + sql);

        if (params == null) {
            conn.query(sql, resultHandler);
        } else {
            logDebug("Query Params = " + params.encode());
            conn.queryWithParams(sql, params, resultHandler);
        }
    }

    public void updateRaw(String sql, JsonArray params, Handler<JsonObject> handler, Handler<Throwable> errorHandler) {
        sqlClient.getConnection(res -> {
            if (res.succeeded()) {
                final SQLConnection conn = res.result();
                updateRaw(conn, sql, params, handler, errorHandler);
            } else {
                // Failed to get connection - deal with it
                if (res.failed()) {
                    logError("SQL Connection Failed!", res.cause());
                }
                if (errorHandler != null) {
                    errorHandler.handle(res.cause());
                }
            }
        });
    }



    public void updateRaw(SQLConnection conn, String sql, JsonArray params, Handler<JsonObject> handler, Handler<Throwable> errorHandler) {
        Handler<AsyncResult<UpdateResult>> resultHandler = new Handler<AsyncResult<UpdateResult>>() {
            @Override
            public void handle(final AsyncResult<UpdateResult> res2) {
                if (res2.succeeded()) {
                    UpdateResult queryRes = res2.result();
                    handler.handle(queryRes.toJson());
                } else {
                    if (res2.failed()) {
                        logError("SQL Update Query Failed! " + sql, res2.cause());
                    }
                    if (errorHandler != null) {
                        errorHandler.handle(res2.cause());
                    }
                }
            }
        };

        logDebug("Executing SQL Update Query : " + sql);

        if (params == null) {
            conn.update(sql, resultHandler);
        } else {
            logDebug("Query Params = " + params.encode());
            conn.updateWithParams(sql, params, resultHandler);
        }
    }

    public void connect(Handler<AsyncResult<SQLConnection>> res) {
        sqlClient.getConnection(res);
    }


    public void batchWithParams(Env env, String sql, List<JsonArray> batchParams, Handler<JsonArray> callbackHandler, final Callable errorHandler) {
//        List<JsonArray> batch = new ArrayList<>();
//        batch.add(new JsonArray().add("value 1"));
//        batch.add(new JsonArray().add("value 2"));

        //Current driver does not support this in vertx async mysql postgre
        connect(sqlConnectionAsyncResult -> {
            SQLConnection conn = sqlConnectionAsyncResult.result();
            conn.batchWithParams(sql, batchParams, res -> {
                //batch returns list of IDs
                if (res.succeeded()) {
                    List<Integer> result = res.result();
                    JsonArray arr = new JsonArray(result);
                    callbackHandler.handle(arr);
                } else {
                    if (res.failed()) {
                        logError("SQL Batch Query Failed! " + sql, res.cause());
                    }
                    if (errorHandler != null) {
                        errorHandler.call(env, env.wrapJava(res.cause()));
                    }
                }
                conn.close();
            });
        });
    }

    public void startTx(SQLConnection conn, Handler<ResultSet> done) {
        conn.setAutoCommit(false, res -> {
            if (res.failed()) {
                throw new RuntimeException(res.cause());
            }

            if (done != null) {
                done.handle(null);
            }
        });
    }

    public void rollbackTx(SQLConnection conn, Handler<ResultSet> done) {
        conn.rollback(res -> {
            conn.close();

            if (res.failed()) {
                throw new RuntimeException(res.cause());
            }

            if (done != null) {
                done.handle(null);
            }
        });
    }

    public void commit(SQLConnection conn, Handler<AsyncResult<Void>> done) {
        conn.commit(res -> {
            conn.close();

            if (done != null) {
                done.handle(null);
            }
        });

    }

    public Value toPhpArray(Env env, JsonArray rows) {
        return PhpTypes.arrayFromJson(env, rows);
    }

}
