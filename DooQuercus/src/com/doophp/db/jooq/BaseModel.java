package com.doophp.db.jooq;

import com.caucho.quercus.env.Callable;
import com.caucho.quercus.env.Env;
import com.caucho.quercus.env.Value;
import com.doophp.db.SQLClient;
import io.vertx.core.impl.VertxImpl;
import io.vertx.core.json.JsonArray;
import io.vertx.lang.php.util.PhpTypes;
import org.jooq.DSLContext;
import org.jooq.Field;
import org.jooq.TableField;
import org.jooq.impl.TableImpl;

import java.util.ArrayList;

/**
 * Created by leng on 12/27/16.
 */
public class BaseModel {

    protected SQLClient client;

    public BaseModel(SQLClient client) {
        this.client = client;
    }

    public DSLContext dsl() {
        return client.dsl();
    }

    public void query(Env env, String sql, JsonArray params) {
        client.query(env, sql, params, null, null);
    }

    public void query(Env env, String sql, JsonArray params, Callable handler) {
        client.query(env, sql, params, handler, null);
    }

    public void query(Env env, String sql, Value paramsArr) {
        client.query(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), null, null);
    }

    public void query(Env env, String sql, Callable handler) {
        client.query(env, sql, null, handler, null);
    }

    public void query(Env env, String sql, Callable handler, Callable errorHandler) {
        client.query(env, sql, null, handler, errorHandler);
    }

    public void query(Env env, String sql, Value paramsArr, Callable handler) {
        if (paramsArr == null) {
            client.query(env, sql, null, handler, null);
        } else {
            client.query(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), handler, null);
        }
    }

    public void query(Env env, String sql, JsonArray params, Callable handler, Callable errorHandler) {
        client.query(env, sql, params, handler, errorHandler);
    }

    public void update(Env env, String sql, JsonArray params) {
        client.update(env, sql, params, null, null);
    }

    public void update(Env env, String sql, JsonArray params, Callable handler) {
        client.update(env, sql, params, handler, null);
    }

    public void update(Env env, String sql, Value paramsArr) {
        client.update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), null, null);
    }

    public void update(Env env, String sql, Callable handler) {
        client.update(env, sql, null, handler, null);
    }

    public void update(Env env, String sql, Callable handler, Callable errorHandler) {
        client.update(env, sql, null, handler, errorHandler);
    }

    public void update(Env env, String sql, Value paramsArr, Callable handler) {
        if (paramsArr == null) {
            client.update(env, sql, null, handler, null);
        } else {
            client.update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), handler, null);
        }
    }

    public void update(Env env, String sql, JsonArray params, Callable handler, Callable errorHandler) {
        client.update(env, sql, params, handler, errorHandler);
    }

    public void insert(Env env, String sql, JsonArray params) {
        client.update(env, sql, params, null, null);
    }

    public void insert(Env env, String sql, JsonArray params, Callable handler) {
        client.update(env, sql, params, handler, null);
    }

    public void insert(Env env, String sql, Value paramsArr) {
        client.update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), null, null);
    }

    public void insert(Env env, String sql, Callable handler) {
        client.update(env, sql, null, handler, null);
    }

    public void insert(Env env, String sql, Callable handler, Callable errorHandler) {
        client.update(env, sql, null, handler, errorHandler);
    }

    public void insert(Env env, String sql, Value paramsArr, Callable handler) {
        if (paramsArr == null) {
            client.update(env, sql, null, handler, null);
        } else {
            client.update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), handler, null);
        }
    }

    public void insert(Env env, String sql, JsonArray params, Callable handler, Callable errorHandler) {
        client.update(env, sql, params, handler, errorHandler);
    }

    public void delete(Env env, String sql, JsonArray params) {
        client.update(env, sql, params, null, null);
    }

    public void delete(Env env, String sql, JsonArray params, Callable handler) {
        client.update(env, sql, params, handler, null);
    }

    public void delete(Env env, String sql, Value paramsArr) {
        client.update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), null, null);
    }

    public void delete(Env env, String sql, Callable handler) {
        client.update(env, sql, null, handler, null);
    }

    public void delete(Env env, String sql, Callable handler, Callable errorHandler) {
        client.update(env, sql, null, handler, errorHandler);
    }

    public void delete(Env env, String sql, Value paramsArr, Callable handler) {
        if (paramsArr == null) {
            client.update(env, sql, null, handler, null);
        } else {
            client.update(env, sql, PhpTypes.arrayToJsonArray(env, paramsArr), handler, null);
        }
    }

    public void delete(Env env, String sql, JsonArray params, Callable handler, Callable errorHandler) {
        client.update(env, sql, params, handler, errorHandler);
    }

    public Field alias(TableImpl table, TableField<?, ?> field) {
        return alias(table.getName(), field, "-");
    }

    public Field alias(String prefix, TableField<?, ?> field) {
        return alias(prefix, field, "-");
    }

    public Field alias(String prefix, TableField<?, ?> field, String delimiter) {
        return field.as(prefix + delimiter + field.getName());
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
            Field<?>[] fields =  table.fields();
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
}