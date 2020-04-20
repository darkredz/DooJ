<?php

/**
 * Created by IntelliJ IDEA.
 * User: leng
 * Date: 15/03/2017
 * Time: 1:35 PM
 */
interface DooMailInterface
{
    function setConfig($mailConf);

    function sendAsHtml(
        $subject,
        $toEmail,
        $msgHtml,
        $msgTxt = null,
        $tags = null,
        $callback = null,
        $errorHandler = null
    );

    function sendAsText($subject, $toEmail, $msgTxt, $tags = null, $callback = null, $errorHandler = null);

    function send(
        $subject,
        $toEmail,
        $msgHtml = null,
        $msgTxt = null,
        $tags = null,
        $callback = null,
        $errorHandler = null
    );
}