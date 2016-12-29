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

/**
 * Created by leng on 12/27/16.
 */
public class SQLClient {

    protected VertxImpl vertx;
    protected Logger logger;
    protected DSLContext dsl;
    protected AsyncSQLClient sqlClient;

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
        logger.info(obj, obj2);
    }

    public void logInfo(Object obj) {
        if (logger == null) return;
        logger.info(obj);
    }

    public void logDebug(Object obj, Object obj2) {
        if (logger == null) return;
        logger.debug(obj, obj2);
    }

    public void logDebug(Object obj) {
        if (logger == null) return;
        logger.debug(obj);
    }

    public void logError(Object obj, Object obj2) {
        if (logger == null) return;
        logger.error(obj, obj2);
    }

    public void logError(Object obj) {
        if (logger == null) return;
        logger.error(obj);
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

    public void query(Env env, String sql, JsonArray params) {
        query(env, sql, params, null, null);
    }

    public void query(Env env, String sql, JsonArray params, Callable handler) {
        query(env, sql, params, handler, null);
    }

    public void query(Env env, String sql, Value paramsArr) {
        query(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), null, null);
    }

    public void query(Env env, String sql, Callable handler) {
        query(env, sql, null, handler, null);
    }

    public void query(Env env, String sql, Callable handler, Callable errorHandler) {
        query(env, sql, null, handler, errorHandler);
    }

    public void query(Env env, String sql, Value paramsArr, Callable handler) {
        if (paramsArr == null) {
            query(env, sql, null, handler, null);
        } else {
            query(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), handler, null);
        }
    }

    public void query(Env env, String sql, JsonArray params, Callable handler, Callable errorHandler) {
        sqlClient.getConnection(res -> {
            if (res.succeeded()) {
                logDebug("Executing SQL Query: " + sql);
                final SQLConnection conn = res.result();
                Handler<AsyncResult<ResultSet>> resultHandler = new Handler<AsyncResult<ResultSet>>() {
                    @Override
                    public void handle(AsyncResult<ResultSet> res2) {
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
                    conn.queryWithParams(sql, params, resultHandler);
                }
            } else {
                // Failed to get connection - deal with it
                if (res.failed()) {
                    logDebug("SQL Connection Failed!", res.cause());
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

    public void insert(Env env, String sql, JsonArray params, Callable handler) {
        update(env, sql, params, handler, null);
    }

    public void insert(Env env, String sql, Value paramsArr) {
        update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), null, null);
    }

    public void insert(Env env, String sql, Callable handler) {
        update(env, sql, null, handler, null);
    }

    public void insert(Env env, String sql, Callable handler, Callable errorHandler) {
        update(env, sql, null, handler, errorHandler);
    }

    public void insert(Env env, String sql, Value paramsArr, Callable handler) {
        if (paramsArr == null) {
            update(env, sql, null, handler, null);
        } else {
            update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), handler, null);
        }
    }

    public void delete(Env env, String sql, JsonArray params) {
        update(env, sql, params, null, null);
    }

    public void delete(Env env, String sql, JsonArray params, Callable handler) {
        update(env, sql, params, handler, null);
    }

    public void delete(Env env, String sql, Value paramsArr) {
        update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), null, null);
    }

    public void delete(Env env, String sql, Callable handler) {
        update(env, sql, null, handler, null);
    }

    public void delete(Env env, String sql, Callable handler, Callable errorHandler) {
        update(env, sql, null, handler, errorHandler);
    }

    public void delete(Env env, String sql, Value paramsArr, Callable handler) {
        if (paramsArr == null) {
            update(env, sql, null, handler, null);
        } else {
            update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), handler, null);
        }
    }

    public void update(Env env, String sql, JsonArray params) {
        update(env, sql, params, null, null);
    }

    public void update(Env env, String sql, JsonArray params, Callable handler) {
        update(env, sql, params, handler, null);
    }

    public void update(Env env, String sql, Value paramsArr) {
        update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), null, null);
    }

    public void update(Env env, String sql, Callable handler) {
        update(env, sql, null, handler, null);
    }

    public void update(Env env, String sql, Callable handler, Callable errorHandler) {
        update(env, sql, null, handler, errorHandler);
    }

    public void update(Env env, String sql, Value paramsArr, Callable handler) {
        if (paramsArr == null) {
            update(env, sql, null, handler, null);
        } else {
            update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), handler, null);
        }
    }

    public void update(Env env, String sql, JsonArray params, Callable handler, Callable errorHandler) {
        sqlClient.getConnection(res -> {
            if (res.succeeded()) {
                logDebug("Executing SQL Update Query : " + sql);
                final SQLConnection conn = res.result();
                Handler<AsyncResult<UpdateResult>> resultHandler = new Handler<AsyncResult<UpdateResult>>() {
                    @Override
                    public void handle(AsyncResult<UpdateResult> res2) {
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
                    conn.updateWithParams(sql, params, resultHandler);
                }
            } else {
                // Failed to get connection - deal with it
                if (res.failed()) {
                    logDebug("SQL Connection Failed!", res.cause());
                }
                if (errorHandler != null) {
                    errorHandler.call(env, env.wrapJava(res.cause()));
                }
            }
        });
    }

}
