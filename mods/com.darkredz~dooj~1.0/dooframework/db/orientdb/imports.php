<?php

import com.orientechnologies.orient.object.db.OObjectDatabaseTx;
import com.orientechnologies.orient.core.db.document.ODatabaseDocumentTx;
import com.orientechnologies.orient.core.record.impl.ODocument;
import com.orientechnologies.orient.core.sql.query.OSQLSynchQuery;
import com.orientechnologies.orient.core.sql.query.OSQLAsynchQuery;
import com.orientechnologies.orient.core.db.document.ODatabaseDocumentPool;
import com.orientechnologies.orient.core.metadata.schema.OType;
import com.orientechnologies.orient.core.command.OCommandResultListener;
import com.orientechnologies.orient.core.id.ORecordId;
import com.orientechnologies.orient.core.sql.OCommandSQL;
import com.orientechnologies.orient.core.intent.OIntentMassiveInsert;
import com.orientechnologies.orient.core.storage.OStorage;
import com.orientechnologies.orient.core.tx.OTransaction;

import com.doophp.db.orientdb.AsyncQueryCallback;
import com.doophp.db.orientdb.QueryExecutor;
import com.doophp.db.orientdb.Transaction;