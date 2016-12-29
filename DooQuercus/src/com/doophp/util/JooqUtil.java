package com.doophp.util;

import com.caucho.quercus.env.Env;
import org.jooq.DSLContext;
import org.jooq.SQLDialect;
import org.jooq.conf.Settings;
import org.jooq.conf.StatementType;
import org.jooq.impl.DSL;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;

/**
 * Created by leng on 12/18/16.
 */
public class JooqUtil {

    public static DSLContext createWithDialect(String dialect) {
        Settings settings = new Settings().withStatementType(StatementType.PREPARED_STATEMENT);
        DSLContext jooq = DSL.using(SQLDialect.valueOf(dialect), settings);
        return jooq;
    }

    public static DSLContext create(Env env, String userName, String password, String url, String dialect) throws SQLException {
//      String userName = "root";
//      String password = "root";
//      String url = "jdbc:mysql://localhost:3306/mydatabase";

//      // Connection is the only JDBC resource that we need
//      // PreparedStatement and ResultSet are handled by jOOQ, internally
        Connection conn = DriverManager.getConnection(url, userName, password);
        return DSL.using(conn, SQLDialect.valueOf(dialect));

    }
}
