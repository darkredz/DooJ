<?php

/**
 * Created by IntelliJ IDEA.
 * User: leng
 * Date: 15/03/2017
 * Time: 1:39 PM
 *
 * Configuration can be pass through constructor 3rd argument $mailConf, $mailConf can be defined as app common.conf.php and inject to this class via container
 * $config = [
 *       'apikey' => 'xxxxxxxxxxx',
 *       'fromEmail' => 'noreply@xxxx.com',
 *       'fromName' => 'My Company',
 *       'toEmailDev' => 'abc@gmail.com' //this will force all to be sent to abc@gmail.com for development purpose to avoid accidentally sending out to clients
 *   ];
 */
class DooMailMandrill implements DooMailInterface
{
    public $vertx;
    /**
     * @var \DooAppInterface
     */
    public $app;
    public $mailConf;
    public $smtpConfig;
    public $javaMailObj;

    function __construct($vertx, DooAppInterface $app, $javaMailObj, $mailConf = null)
    {
        $this->vertx = $vertx;
        $this->app = $app;
        $this->javaMailObj = $javaMailObj;

        if ($mailConf != null) {
            $this->mailConf = $mailConf;
        } else {
            $this->mailConf = $this->app->conf->MAIL_MANDRILL;
        }
        $this->javaMailObj->setConfig($this->mailConf);
    }

    public function setConfig($mailConf)
    {
        $this->javaMailObj->setConfig($this->mailConf);
    }

    public function getEmailAndName($toEmail)
    {
        //if pass in toEmail is with format My Name <abc@gmail.com>
        preg_match('/\<(.+)\>/', $toEmail, $matches);

        //set the toEmail and toName from the string
        if ($matches && sizeof($matches) > 1) {
            $toEmail = trim($matches[1]);
            $toName = trim(str_replace($matches[0], '', $toEmail));
            return ['email' => $toEmail, 'name' => $toName];
        }
        return ['email' => $toEmail, 'name' => null];
    }

    public function execSuccessOrError($json, $callback)
    {
        if (!$callback) {
            return;
        }

        $json = \JSON::decode($json, true);

        if ($json == null) {
            return;
        }

//        $this->app->trace($json);
//        if ($json[0] && sizeof($json) == 1) {
//            if ($json[0]['status'] == 'rejected') {
//                $errorHandler(['error' => $json[0]]);
//            } else{
//                $callback(['result' => $json]);
//            }
//        }
//        else {
        //Mandrill by default if status http ok, will always send back an array of items with status depending on the toList,
        // need check in call back loop if any is 'rejected' , 'reject_reason'
        // if ok status is 'sent'
        $callback(['result' => $json]);
//        }
    }

    public function sendAsHtml(
        $subject,
        $toEmail,
        $msgHtml,
        $msgTxt = null,
        $tags = null,
        $callback = null,
        $errorHandler = null
    )
    {
        $to = $this->getEmailAndName($toEmail);
        $toName = $to['name'];
        $toEmail = $to['email'];

        $fromEmail = $this->mailConf['fromEmail'];
        $fromName = $this->mailConf['fromName'];

        if ($callback) {
            $this->javaMailObj->sendMail($toEmail, $toName, $fromEmail, $fromName, $subject, $msgHtml, $msgTxt, $tags,
                function ($mailRes) use ($callback, $errorHandler) {
                    $this->execSuccessOrError($mailRes, $callback);
                }, $errorHandler);
        } else {
            $this->javaMailObj->sendMail($toEmail, $toName, $fromEmail, $fromName, $subject, $msgHtml, $msgTxt, $tags);
        }
    }

    public function sendAsText($subject, $toEmail, $msgTxt, $callback = null, $tags = null, $errorHandler = null)
    {
        $to = $this->getEmailAndName($toEmail);
        $toName = $to['name'];
        $toEmail = $to['email'];

        $fromEmail = $this->mailConf['fromEmail'];
        $fromName = $this->mailConf['fromName'];

        if ($callback) {
            $this->javaMailObj->sendMail($toEmail, $toName, $fromEmail, $fromName, $subject, null, $msgTxt, $tags,
                function ($mailRes) use ($callback, $errorHandler) {
                    $this->execSuccessOrError($mailRes, $callback);
                }, $errorHandler);
        } else {
            $this->javaMailObj->sendMail($toEmail, $toName, $fromEmail, $fromName, $subject, null, $msgTxt, $tags);
        }
    }

    public function send(
        $subject,
        $toEmail,
        $msgHtml = null,
        $msgTxt = null,
        $tags = null,
        $callback = null,
        $errorHandler = null
    )
    {
        $to = $this->getEmailAndName($toEmail);
        $toName = $to['name'];
        $toEmail = $to['email'];

        $fromEmail = $this->mailConf['fromEmail'];
        $fromName = $this->mailConf['fromName'];

        if ($callback) {
            $this->javaMailObj->sendMail($toEmail, $toName, $fromEmail, $fromName, $subject, $msgHtml, $msgTxt, $tags,
                function ($mailRes) use ($callback, $errorHandler) {
                    $this->execSuccessOrError($mailRes, $callback);
                }, $errorHandler);
        } else {
            $this->javaMailObj->sendMail($toEmail, $toName, $fromEmail, $fromName, $subject, $msgHtml, $msgTxt, $tags);
        }
    }
}