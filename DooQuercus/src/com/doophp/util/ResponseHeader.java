package com.doophp.util;

import io.vertx.core.http.HttpServerResponse;

import java.util.HashSet;

/**
 * Created by leng on 4/8/16.
 */
public class ResponseHeader {

    public static void put(HttpServerResponse response, String headerKey, HashSet<String> headerList) {
        response.putHeader(headerKey, headerList);
    }
}
