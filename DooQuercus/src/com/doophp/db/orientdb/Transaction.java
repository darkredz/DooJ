package com.doophp.db.orientdb;

import com.caucho.quercus.env.Callable;
import com.caucho.quercus.env.Env;
import com.caucho.quercus.env.Value;
import com.orientechnologies.orient.core.db.document.ODatabaseDocumentTx;
import com.orientechnologies.orient.core.record.impl.ODocument;

/**
 * Created with IntelliJ IDEA.
 * User: leng
 * Date: 11/23/13
 * Time: 11:20 PM
 * To change this template use File | Settings | File Templates.
 */
public class Transaction {
  /**
   * A Quercus environment.
   */
  private Env env;

  private ODatabaseDocumentTx db;
  private Callable handlerException;
  private Callable handlerDone;
  private Callable codeBlock;

  public Transaction(Env env, ODatabaseDocumentTx db, Callable codeBlock, Callable handlerDone, Callable handlerException) {
    this.env = env;
    this.db = db;
    this.codeBlock = codeBlock;
    this.handlerDone = handlerDone;
    this.handlerException = handlerException;
  }

  public void commit() {
    db.begin();
    try{
      codeBlock.call(env);
      db.commit();
      handlerDone.call(env);
    }
    catch(Exception e){
      db.rollback();
      handlerException.call(env, env.wrapJava(e));
    }
  }

}