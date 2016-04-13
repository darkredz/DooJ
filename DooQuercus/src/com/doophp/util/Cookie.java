package com.doophp.util;

import io.vertx.core.http.HttpServerResponse;

import java.util.HashSet;

/**
 * Created by leng on 4/8/16.
 */
public class Cookie {
    protected HashSet<String> clist = new HashSet<String>();

    public void addCookie(String item) {
        clist.add(item);
    }

    public HashSet<String> getCookies() {
        return clist;
    }

    public void putHeader(HttpServerResponse response) {
        response.putHeader("Set-Cookie", clist);
    }
}
