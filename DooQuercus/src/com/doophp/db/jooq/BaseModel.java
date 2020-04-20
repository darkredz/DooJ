package com.doophp.db.jooq;

import com.caucho.quercus.env.*;
import com.doophp.db.SQLClient;
import io.vertx.core.AsyncResult;
import io.vertx.core.Handler;
import io.vertx.core.impl.VertxImpl;
import io.vertx.core.json.JsonArray;
import io.vertx.core.json.JsonObject;
import io.vertx.ext.sql.ResultSet;
import io.vertx.ext.sql.SQLConnection;
import io.vertx.ext.sql.UpdateResult;
import io.vertx.lang.php.util.PhpTypes;
import org.jooq.*;
import org.jooq.impl.SQLDataType;
import org.jooq.impl.TableImpl;
import org.jooq.impl.TableRecordImpl;
import org.jooq.types.UByte;
import org.jooq.types.UInteger;
import org.jooq.types.ULong;
import org.jooq.types.UShort;

import java.math.BigDecimal;
import java.sql.Date;
import java.sql.Timestamp;
import java.time.Instant;
import java.time.ZoneId;
import java.time.ZonedDateTime;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.List;
import java.util.ListIterator;
import java.util.TimeZone;
import static org.jooq.impl.DSL.*;

/**
 * Created by leng on 12/27/16.
 */
public class BaseModel {

    protected SQLClient client;
    /**
     * Date format for sql statement. ISO 8601 (yyyy-MM-ddTHH:mm:ss.SSS) formatted strings. MySQL usually discards milliseconds, so you will regularly see .000.
     */
    protected final String dateTimeFormatPattern = "yyyy-MM-dd'T'HH:mm:ss.'000'";

    // =================== jooq feed fake data for sql generation =====================
    public Timestamp feedTime() {
        return Timestamp.valueOf("2010-10-10 10:10:10.000");
    }

    public Date feedDate() {
        return new Date(123);
    }

    public Byte feedByte() {
        return Byte.MAX_VALUE;
    }

    public Integer feedInt() {
        return 1;
    }

    public Long feedLong() {
        return 1l;
    }

    public Short feedShort() {
        return 1;
    }

    public UByte feedUByte() {
        return UByte.valueOf(0);
    }

    public UInteger feedUInt() {
        return UInteger.valueOf(0);
    }

    public ULong feedULong() {
        return ULong.valueOf(0);
    }

    public UShort feedUShort() {
        return UShort.valueOf(0);
    }

    public Double feedDouble() {
        return 0.0;
    }

    public Float feedFloat() {
        return 0.0f;
    }

    public BigDecimal feedDecimal() {
        return BigDecimal.ZERO;
    }

    public Object f(final TableField<?,?> field) {
        Class cls = field.getType();
        switch (cls.toString()) {
            case "Byte": return feedByte();
            case "Integer": return feedInt();
            case "Short": return feedShort();
            case "Long": return feedLong();
            case "UByte": return feedUByte();
            case "UInteger": return feedUInt();
            case "UShort": return feedUShort();
            case "ULong": return feedULong();
            case "Double": return feedDouble();
            case "Float": return feedFloat();
            case "BigDecimal": return feedDecimal();
            case "Timestamp": return feedTime();
            case "String": return "";
            case "Boolean": return true;
        }
        return null;
    }

    // ==================== Date time related helpers ================
    protected String timeNow() {
        return timeNow("UTC");
    }

    protected String timeNow(String timezone) {
        ZoneId zone = ZoneId.of(timezone);
        final DateTimeFormatter formatter = DateTimeFormatter.ofPattern(dateTimeFormatPattern).withZone(zone);
        return formatter.format(ZonedDateTime.now(zone));
    }

    protected String toDateTime(long timestampSec) {
        return toDateTime(timestampSec, "UTC");
    }

    protected String toDateTime(long timestampSec, String timezone) {
        ZoneId zone = ZoneId.of(timezone);
        ZonedDateTime time = ZonedDateTime.ofInstant(Instant.ofEpochSecond(timestampSec), zone);
        final DateTimeFormatter formatter = DateTimeFormatter.ofPattern(dateTimeFormatPattern).withZone(zone);
        return formatter.format(time);
    }

    protected String toDateTime(long timestampSec, String timezoneFrom, String timezoneInto) {
        ZoneId zone = ZoneId.of(timezoneFrom);
        ZonedDateTime time = ZonedDateTime.ofInstant(Instant.ofEpochSecond(timestampSec), zone);
        ZoneId zone2 = ZoneId.of(timezoneInto);
        final DateTimeFormatter formatter = DateTimeFormatter.ofPattern(dateTimeFormatPattern).withZone(zone2);
        return formatter.format(time);
    }

    // ====================== Query related =====================
    public BaseModel(SQLClient client) {
        this.client = client;
    }

    public DSLContext dsl() {
        return client.dsl();
    }

//    public void query(Env env, String sql, JsonArray params) {
//        client.query(env, sql, params, null, null);
//    }

//    public void query(Env env, String sql, JsonArray params, final Callable handler) {
//        client.query(env, sql, params, handler, null);
//    }

//    public void query(Env env, String sql, Value paramsArr) {
//        client.query(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), null, null);
//    }

//    public void query(Env env, String sql, final Callable handler) {
//        client.query(env, sql, null, handler, null);
//    }

    public void query(Env env, String sql, final Callable handler, final Callable errorHandler) {
        client.query(env, sql, null, handler, errorHandler);
    }

//    public void query(Env env, String sql, Value paramsArr, final Callable handler) {
//        if (paramsArr == null) {
//            client.query(env, sql, null, handler, null);
//        } else {
//            client.query(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), handler, null);
//        }
//    }

    public void query(Env env, String sql, JsonArray params, final Callable handler, final Callable errorHandler) {
        client.query(env, sql, params, handler, errorHandler);
    }

    public void update(Env env, String sql, JsonArray params) {
        client.update(env, sql, params, null, null);
    }

//    public void update(Env env, String sql, JsonArray params, final Callable handler) {
//        client.update(env, sql, params, handler, null);
//    }

    public void update(Env env, String sql, Value paramsArr) {
        client.update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), null, null);
    }

//    public void update(Env env, String sql, final Callable handler) {
//        client.update(env, sql, null, handler, null);
//    }

    public void update(Env env, String sql, final Callable handler, final Callable errorHandler) {
        client.update(env, sql, null, handler, errorHandler);
    }

//    public void update(Env env, String sql, Value paramsArr, final Callable handler) {
//        if (paramsArr == null) {
//            client.update(env, sql, null, handler, null);
//        } else {
//            client.update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), handler, null);
//        }
//    }

    public void update(Env env, String sql, JsonArray params, final Callable handler, final Callable errorHandler) {
        client.update(env, sql, params, handler, errorHandler);
    }

    public void insert(Env env, String sql, JsonArray params) {
        client.update(env, sql, params, null, null);
    }

//    public void insert(Env env, String sql, JsonArray params, final Callable handler) {
//        client.update(env, sql, params, handler, null);
//    }

    public void insert(Env env, String sql, Value paramsArr) {
        client.update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), null, null);
    }

//    public void insert(Env env, String sql, final Callable handler) {
//        client.update(env, sql, null, handler, null);
//    }

    public void insert(Env env, String sql, final Callable handler, final Callable errorHandler) {
        client.update(env, sql, null, handler, errorHandler);
    }

//    public void insert(Env env, String sql, Value paramsArr, final Callable handler) {
//        if (paramsArr == null) {
//            client.update(env, sql, null, handler, null);
//        } else {
//            client.update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), handler, null);
//        }
//    }

    public void insert(Env env, String sql, JsonArray params, final Callable handler, final Callable errorHandler) {
        client.update(env, sql, params, handler, errorHandler);
    }

    public void delete(Env env, String sql, JsonArray params) {
        client.update(env, sql, params, null, null);
    }

//    public void delete(Env env, String sql, JsonArray params, final Callable handler) {
//        client.update(env, sql, params, handler, null);
//    }

    public void delete(Env env, String sql, Value paramsArr) {
        client.update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), null, null);
    }

//    public void delete(Env env, String sql, final Callable handler) {
//        client.update(env, sql, null, handler, null);
//    }

    public void delete(Env env, String sql, final Callable handler, final Callable errorHandler) {
        client.update(env, sql, null, handler, errorHandler);
    }

//    public void delete(Env env, String sql, Value paramsArr, final Callable handler) {
//        if (paramsArr == null) {
//            client.update(env, sql, null, handler, null);
//        } else {
//            client.update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), handler, null);
//        }
//    }

    public void delete(Env env, String sql, JsonArray params, final Callable handler, final Callable errorHandler) {
        client.update(env, sql, params, handler, errorHandler);
    }

    public Field alias(TableImpl table, TableField<?, ?> field) {
        return alias(table.getName(), field, "-");
    }

    public Field aliasMax(TableImpl table, TableField<?, ?> field) {
        return max(field).as(table.getName() + "-" + field.getName());
    }

    public Field aliasMin(TableImpl table, TableField<?, ?> field) {
        return min(field).as(table.getName() + "-" + field.getName());
    }

    public Field aliasCount(TableImpl table, TableField<?, ?> field) {
        return count(field).as(table.getName() + "-" + field.getName());
    }

    public Field aliasSum(TableImpl table, TableField<?, Number> field) {
        return sum(field).as(table.getName() + "-" + field.getName());
    }

    public Field aliasAvg(TableImpl table, TableField<?, Number> field) {
        return avg(field).as(table.getName() + "-" + field.getName());
    }

    public Field aliasMedian(TableImpl table, TableField<?, Number> field) {
        return median(field).as(table.getName() + "-" + field.getName());
    }

    public Field alias(String prefix, TableField<?, ?> field) {
        return alias(prefix, field, "-");
    }

    public Field alias(String prefix, TableField<?, ?> field, String delimiter) {
        return field.as(prefix + delimiter + field.getName());
    }

    public Field alias(TableField<?, ?> field, TableImpl tablePrefix, TableField<?, ?> becomeField) {
        return  alias(field, tablePrefix.getName(), becomeField, "-");
    }

    public Field aliasMax(TableField<?, ?> field, TableImpl tablePrefix, TableField<?, ?> becomeField) {
        return max(field).as(tablePrefix.getName() + "-" + becomeField.getName());
    }

    public Field aliasMin(TableField<?, ?> field, TableImpl tablePrefix, TableField<?, ?> becomeField) {
        return min(field).as(tablePrefix.getName() + "-" + becomeField.getName());
    }

    public Field aliasCount(TableField<?, ?> field, TableImpl tablePrefix, TableField<?, ?> becomeField) {
        return count(field).as(tablePrefix.getName() + "-" + becomeField.getName());
    }

    public Field aliasSum(TableField<?, Number> field, TableImpl tablePrefix, TableField<?, ?> becomeField) {
        return sum(field).as(tablePrefix.getName() + "-" + becomeField.getName());
    }

    public Field aliasAvg(TableField<?, Number> field, TableImpl tablePrefix, TableField<?, ?> becomeField) {
        return avg(field).as(tablePrefix.getName() + "-" + becomeField.getName());
    }

    public Field aliasMedian(TableField<?, Number> field, TableImpl tablePrefix, TableField<?, ?> becomeField) {
        return median(field).as(tablePrefix.getName() + "-" + becomeField.getName());
    }

    public Field alias(TableField<?, ?> field, String prefix, TableField<?, ?> becomeField) {
        return  alias(field, prefix, becomeField, "-");
    }

    public Field alias(TableField<?, ?> field, String prefix, TableField<?, ?> becomeField, String delimiter) {
        return field.as(prefix + delimiter + becomeField.getName());
    }


    public Field alias(TableField<?, ?> field, TableImpl tablePrefix, String becomeFieldName) {
        return  alias(field, tablePrefix.getName(), becomeFieldName, "-");
    }

    public Field alias(TableField<?, ?> field, String prefix, String becomeFieldName) {
        return  alias(field, prefix, becomeFieldName, "-");
    }

    public Field alias(TableField<?, ?> field, String prefix, String becomeFieldName, String delimiter) {
        return field.as(prefix + delimiter + becomeFieldName);
    }

    public ArrayList getAllFieldAlias(TableImpl[] tables, ArrayList<String> renames) {
        return getAllFieldAlias(tables, "-", (String[]) renames.toArray());
    }

    public ArrayList getAllFieldAlias(TableImpl[] tables, String[] renames) {
        return getAllFieldAlias(tables, "-", renames);
    }

    public ArrayList getAllFieldAlias(TableImpl[] tables) {
        return getAllFieldAlias(tables, "-", null);
    }

    public ArrayList getAllFieldAlias(TableImpl[] tables, String delimiter, String[] renames) {
        ArrayList all = new ArrayList<>();
        for (int j = 0; j < tables.length; j++) {
            TableImpl table = tables[j];
            Field<?>[] fields = table.fields();
            String prefix = table.getName();

            for (int i = 0; i < fields.length; i++) {
                Field f = fields[i];
                if (renames != null) {
                    prefix = renames[j];
                    all.add(f.as(prefix + delimiter + f.getName()));
                } else {
                    all.add(f.as(prefix + delimiter + f.getName()));
                }
            }
        }
        return all;
    }

    public void updateRaw(SQLConnection conn, String sql, JsonArray params, Handler<JsonObject> handler, Handler<Throwable> errorHandler) {
        client.updateRaw(conn, sql, params, handler, errorHandler);
    }

    public void updateRaw(String sql, JsonArray params, Handler<JsonObject> handler, Handler<Throwable> errorHandler) {
        client.updateRaw(sql, params, handler, errorHandler);
    }


    public void deleteRaw(SQLConnection conn, String sql, JsonArray params, Handler<JsonObject> handler, Handler<Throwable> errorHandler) {
        client.updateRaw(conn, sql, params, handler, errorHandler);
    }

    public void deleteRaw(String sql, JsonArray params, Handler<JsonObject> handler, Handler<Throwable> errorHandler) {
        client.updateRaw(sql, params, handler, errorHandler);
    }

    public void insertRaw(SQLConnection conn, String sql, JsonArray params, Handler<JsonObject> handler, Handler<Throwable> errorHandler) {
        client.updateRaw(conn, sql, params, handler, errorHandler);
    }

    public void insertRaw(String sql, JsonArray params, Handler<JsonObject> handler, Handler<Throwable> errorHandler) {
        client.updateRaw(sql, params, handler, errorHandler);
    }

    public void queryRaw(SQLConnection conn, String sql, JsonArray params, Handler<JsonArray> handler, Handler<Throwable> errorHandler) {
        client.queryRaw(conn, sql, params, handler, errorHandler);
    }

    public void queryRaw(String sql, JsonArray params, Handler<JsonArray> handler, Handler<Throwable> errorHandler) {
        client.queryRaw(sql, params, handler, errorHandler);
    }


    public void connect(Handler<AsyncResult<SQLConnection>> res) {
        client.connect(res);
    }

    public void startTx(SQLConnection conn, Handler<ResultSet> done) {
        client.startTx(conn, done);
    }

    public void rollbackTx(SQLConnection conn, Handler<ResultSet> done) {
        client.rollbackTx(conn, done);
    }


    public void commit(SQLConnection conn, Handler<AsyncResult<Void>> done) {
        client.commit(conn, done);
    }


    public void deleteWithHandler(Env env, String sql, JsonArray params, Handler<UpdateResult> callbackHandler, final Callable errorHandler) {
        client.deleteWithHandler(env, sql, params, callbackHandler, errorHandler);
    }

    public void insertWithHandler(Env env, String sql, JsonArray params, Handler<UpdateResult> callbackHandler, final Callable errorHandler) {
        client.insertWithHandler(env, sql, params, callbackHandler, errorHandler);
    }

    public void updateWithHandler(Env env, String sql, JsonArray params, Handler<UpdateResult> callbackHandler, final Callable errorHandler) {
        client.updateWithHandler(env, sql, params, callbackHandler, errorHandler);
    }

    public void queryWithHandler(Env env, String sql, JsonArray params, Handler<JsonArray> callbackHandler, final Callable errorHandler) {
        client.queryWithHandler(env, sql, params, callbackHandler, errorHandler);
    }

    //============== Batch operations ===============
    public void batchWithParams(Env env, String sql, List<JsonArray> batchParams, Handler<JsonArray> callbackHandler, final Callable errorHandler) {
        client.batchWithParams(env, sql, batchParams, callbackHandler, errorHandler);
    }

    public void batchInsertWithParams(Env env, String sql, List<JsonArray> batchParams, final Callable handler, final Callable errorHandler) {
        connect(connRes -> {
            final SQLConnection conn = connRes.result();

            Handler<Throwable> defaultErr = getDefaultErrorTx(env, conn, errorHandler);
            final List<Integer> batchInsertIDs = new ArrayList<>();

            startTx(conn, res1 -> {
                nextBatchInsertOp(batchParams.listIterator(), batchInsertIDs, conn, sql, defaultErr, res2 -> {
                    commit(conn, resCommit -> {
                        if (handler != null) {
                            JsonArray arr = new JsonArray(res2);
                            handler.call(env, client.toPhpArray(env, arr));
                        }
                    });
                });
            });
        });
    }

    protected void nextBatchInsertOp(ListIterator<JsonArray> iterator, List<Integer> batchInsertIDs, SQLConnection conn, String sql, Handler<Throwable> errorHandler, Handler<List<Integer>> doneBatchHandler) {
        if (iterator.hasNext()) {
            JsonArray param = iterator.next();

            insertRaw(conn, sql, param, res -> {
                if (batchInsertIDs != null) {
                    batchInsertIDs.add(res.getJsonArray("keys").getInteger(0));
                }
                nextBatchInsertOp(iterator, batchInsertIDs, conn, sql, errorHandler, doneBatchHandler);
            }, errorHandler);
        }
        //end of list
        else {
            doneBatchHandler.handle(batchInsertIDs);
        }
    }

    public void batchUpdateWithParams(Env env, String sql, List<JsonArray> batchParams, final Callable handler, final Callable errorHandler) {
        connect(connRes -> {
            final SQLConnection conn = connRes.result();

            Handler<Throwable> defaultErr = getDefaultErrorTx(env, conn, errorHandler);

            startTx(conn, res1 -> {
                nextBatchUpdateOp(batchParams.listIterator(), conn, sql, defaultErr, res2 -> {
                    commit(conn, resCommit -> {
                        handler.call(env, env.wrapJava(res2));
                    });
                });
            });
        });
    }

    protected void nextBatchUpdateOp(ListIterator<JsonArray> iterator, SQLConnection conn, String sql, Handler<Throwable> errorHandler, Handler<Boolean> doneBatchHandler) {
        if (iterator.hasNext()) {
            JsonArray param = iterator.next();

            updateRaw(conn, sql, param, res -> {
                nextBatchUpdateOp(iterator, conn, sql, errorHandler, doneBatchHandler);
            }, errorHandler);
        }
        //end of list
        else {
            doneBatchHandler.handle(true);
        }
    }

    public void batchDeleteWithParams(Env env, String sql, List<JsonArray> batchParams, final Callable handler, final Callable errorHandler) {
        connect(connRes -> {
            final SQLConnection conn = connRes.result();

            Handler<Throwable> defaultErr = getDefaultErrorTx(env, conn, errorHandler);

            startTx(conn, res1 -> {
                nextBatchDeleteOp(batchParams.listIterator(), conn, sql, defaultErr, res2 -> {
                    commit(conn, resCommit -> {
                        handler.call(env, env.wrapJava(res2));
                    });
                });
            });
        });
    }

    protected void nextBatchDeleteOp(ListIterator<JsonArray> iterator, SQLConnection conn, String sql, Handler<Throwable> errorHandler, Handler<Boolean> doneBatchHandler) {
        if (iterator.hasNext()) {
            JsonArray param = iterator.next();

            deleteRaw(conn, sql, param, res -> {
                nextBatchDeleteOp(iterator, conn, sql, errorHandler, doneBatchHandler);
            }, errorHandler);
        }
        //end of list
        else {
            doneBatchHandler.handle(true);
        }
    }

    // ===================== call back related ====================
    public Handler<Throwable> getDefaultErrorTx(Env env, SQLConnection conn, final Callable errorHandler) {
        return new Handler<Throwable>() {
            @Override
            public void handle(Throwable error) {
                rollbackTx(conn, null);
                Throwable cause = error.getCause();
                if (cause == null) {
                    cause = error;
                }
                errorHandler.call(env, env.wrapJava(cause));
            }
        };
    }

    public Handler<Throwable> getDefaultError(Env env, SQLConnection conn, final Callable errorHandler) {
        return new Handler<Throwable>() {
            @Override
            public void handle(Throwable error) {
                conn.close();
                Throwable cause = error.getCause();
                if (cause == null) {
                    cause = error;
                }
                errorHandler.call(env, env.wrapJava(cause));
            }
        };
    }

    public Handler<Throwable> getDefaultError(Env env, final Callable errorHandler) {
        return new Handler<Throwable>() {
            @Override
            public void handle(Throwable error) {
                Throwable cause = error.getCause();
                if (cause == null) {
                    cause = error;
                }
                errorHandler.call(env, env.wrapJava(cause));
            }
        };
    }

}