package com.doophp.db.orientdb;

/**
 * Created with IntelliJ IDEA.
 * User: leng
 * Date: 11/3/13
 * Time: 5:30 PM
 * To change this template use File | Settings | File Templates.
 */
import com.caucho.quercus.env.Callable;
import com.caucho.quercus.env.Env;
import com.caucho.quercus.env.Value;
import com.caucho.quercus.lib.spl.ArrayAccess;
import com.caucho.quercus.lib.spl.Countable;

import com.orientechnologies.orient.core.command.OCommandResultListener;
import com.orientechnologies.orient.core.record.impl.ODocument;

public class AsyncQueryCallback implements OCommandResultListener{
    /**
     * A Quercus environment.
     */
    private Env env;

    private Callable handlerResult;
    private Callable handlerEnd;

    public long resultCount = 0;


    public AsyncQueryCallback(Env env, Callable handlerResult, Callable handlerEnd) {
        this.env = env;
        this.handlerResult = handlerResult;
        this.handlerEnd = handlerEnd;
    }

    @Override
    public boolean result(Object iRecord) {
        resultCount++;
        ODocument doc = (ODocument) iRecord;
        Value va = handlerResult.call(env, env.wrapJava(doc), env.wrapJava(resultCount));
        Boolean ret = va.toJavaBoolean();
        return ret;
    }

    @Override
    public void end() {
        this.handlerEnd.call(env, env.wrapJava(resultCount));
    }
}
