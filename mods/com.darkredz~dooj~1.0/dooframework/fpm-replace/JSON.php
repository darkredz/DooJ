<?php
/**
 * Created by IntelliJ IDEA.
 * User: leng
 * Date: 15/05/2018
 * Time: 3:27 PM
 */

class JSON
{
    public static function decode($json, $asArray = false) {
        return json_decode($json, $asArray);
    }

    public static function encode($json) {
        return json_encode($json);
    }
}