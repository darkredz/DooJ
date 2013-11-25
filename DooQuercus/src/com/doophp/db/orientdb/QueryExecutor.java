package com.doophp.db.orientdb;

import com.caucho.quercus.annotation.Optional;

import com.caucho.quercus.env.ArrayValue;
import com.caucho.quercus.env.ObjectValue;
import com.caucho.quercus.env.Env;
import com.caucho.quercus.env.Value;

import com.orientechnologies.orient.core.record.impl.ODocument;
import com.orientechnologies.orient.core.sql.query.OSQLAsynchQuery;
import com.orientechnologies.orient.core.sql.query.OSQLSynchQuery;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.Iterator;
import java.util.List;


/**
 * Created with IntelliJ IDEA.
 * User: leng
 * Date: 11/4/13
 * Time: 11:37 PM
 * To change this template use File | Settings | File Templates.
 */
public class QueryExecutor {

    private Env env;

    public static List<ODocument> execute(Env env, OSQLSynchQuery obj, @Optional ArrayValue arr){
        if(arr != null){
            Iterator<Value> iter = arr.getKeyIterator(env);
            HashMap map = new HashMap<String, Object>();
            while (iter.hasNext()) {
                Value key = iter.next();
                Value value = arr.get(key);
                if (value.isBoolean()) {
                    map.put(key.toString(), value.toBoolean());
                }
                else if (value.isDouble()) {
                    map.put(key.toString(), value.toJavaDouble());
                }
                else if (value.isNumeric()) {
                    map.put(key.toString(), value.toInt());
                }
                else if (value.isString()) {
                    map.put(key.toString(), value.toString());
                }
                else {
                    map.put(key.toString(), value.toJavaObject());
                }
            }
            return (List<ODocument>) obj.execute(map);
        }

        return (List<ODocument>) obj.execute();
    }


    public static void execute(Env env, OSQLAsynchQuery obj, @Optional ArrayValue arr){
        if(arr != null){
            Iterator<Value> iter = arr.getKeyIterator(env);
            HashMap map = new HashMap<String, Object>();
            while (iter.hasNext()) {
                Value key = iter.next();
                Value value = arr.get(key);
                if (value.isBoolean()) {
                    map.put(key.toString(), value.toBoolean());
                }
                else if (value.isDouble()) {
                    map.put(key.toString(), value.toJavaDouble());
                }
                else if (value.isNumeric()) {
                    map.put(key.toString(), value.toInt());
                }
                else if (value.isString()) {
                    map.put(key.toString(), value.toString());
                }
                else {
                    map.put(key.toString(), value.toJavaObject());
                }
            }

            obj.execute(map);
            return;
        }

        obj.execute();
    }
}
