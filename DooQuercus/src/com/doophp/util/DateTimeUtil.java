package com.doophp.util;

import java.time.LocalDateTime;
import java.time.ZoneOffset;
import java.time.ZonedDateTime;
import java.time.format.DateTimeFormatter;

/**
 * Created by leng on 1/19/17.
 */
public class DateTimeUtil {

    public static long parse(String dateTime) {
        return parse(dateTime, DateTimeFormatter.ISO_DATE_TIME);
    }

    public static long parse(String dateTime, String format) {
        return parse(dateTime, DateTimeFormatter.ofPattern(format));
    }

    public static long parseWithTimeZone(String dateTime) {
        //2011-12-03T10:15:30+01:00
        return parse(dateTime, DateTimeFormatter.ISO_DATE_TIME);
    }

    public static long parseWithLocalTime(String dateTime) {
        //2011-12-03T10:15:30
        return parse(dateTime, DateTimeFormatter.ISO_LOCAL_DATE_TIME);
    }

    public static long parse(String dateTime, DateTimeFormatter formatter) {
        try {
            ZonedDateTime dt = ZonedDateTime.parse(dateTime, formatter);
            return dt.toEpochSecond();
        }
        catch (Exception err) {
            System.out.println(dateTime);
            System.out.println(err.toString());
//            err.printStackTrace();
            return -1;
        }
    }

    public static long parseLocal(String dateTime, String format) {
        return parseLocal(dateTime, DateTimeFormatter.ofPattern(format));
    }

    public static long parseLocal(String dateTime, DateTimeFormatter formatter) {
        try {
            LocalDateTime dt = LocalDateTime.parse(dateTime, formatter);
            return dt.toEpochSecond(ZoneOffset.UTC);
        }
        catch (Exception err) {
            System.out.println(dateTime);
            System.out.println(err.toString());
//            err.printStackTrace();
            return -1;
        }
    }
}
