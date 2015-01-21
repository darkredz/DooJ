package com.doophp.util;


import com.caucho.quercus.env.BooleanValue;
import com.caucho.quercus.env.Env;
import com.caucho.quercus.env.StringValue;
import com.caucho.quercus.env.Value;
import com.orientechnologies.orient.core.db.record.ORecordLazyList;
import com.orientechnologies.orient.core.metadata.schema.OType;
import com.orientechnologies.orient.core.record.impl.ODocument;

/**
 * Created by leng on 12/2/14.
 */
public class ODoc {
    public static void set(ODocument doc, String field, Object value, OType type)
    {
        doc.field(field, value, type);
    }

    public static BooleanValue isType(Env env, Object obj, String type)
    {
        return BooleanValue.create(obj.getClass().toString().contains(type));
    }

    public static BooleanValue isTypeEq(Env env, Object obj, String type)
    {
        return BooleanValue.create(obj.getClass().toString().equals(type));
    }
}
