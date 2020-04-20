<?php

/**
 * Created by IntelliJ IDEA.
 * User: leng
 * Date: 1/18/17
 * Time: 6:58 PM
 *
 * Configuration can be pass through constructor 3rd argument $mailConf, $mailConf can be defined as app common.conf.php and inject to this class via container
 * $config = [
 *       'smtp' => 'smtp.mandrillapp.com',
 *       'port' => 465,
 *       'tls'  => true,
 *       'username' => 'xxxxx@xxxx.com',
 *       'password' => 'xxxxxxxxxxx',
 *       'fromEmail' => 'noreply@xxxx.com',
 *       'fromName' => 'My Company',
 *       'toEmailDev' => 'abc@gmail.com' //this will force all to be sent to abc@gmail.com for development purpose to avoid accidentally sending out to clients
 *   ];
 */
class DooMailSmtp implements DooMailInterface
{
    public $vertx;
    /**
     * @var \DooAppInterface
     */
    public $app;
    public $mailConf;
    public $smtpConfig;

    function __construct($vertx, $app, $mailConf = null)
    {
        $this->vertx = $vertx;
        $this->app = $app;
        if ($mailConf != null) {
            $this->mailConf = $mailConf;
        } else {
            $this->mailConf = $this->app->conf->MAIL;
        }
    }

    public function setConfig($mailConf)
    {
        $this->mailConf = $mailConf;
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

        if (empty($msgTxt)) {
            $msgTxt = \strip_tags($msgHtml);
        }

        $this->send($subject, $toEmail, $msgHtml, $msgTxt, $tags, $callback, $errorHandler);
    }

    public function setupSmtpConfig($mailConf)
    {
        $config = new \Java("io.vertx.ext.mail.MailConfig");
        $config->setHostname($mailConf['smtp']);

        if (!empty($mailConf['ssl'])) {
            $config->setSsl($mailConf['ssl']);
        }

        //defaults need login if not defined or defined as false
        if ($mailConf['login'] === false) {
            $config->setLogin(\LoginOption::NONE);
        } else {
            $config->setLogin(\LoginOption::REQUIRED);
            $config->setUsername($mailConf['username']);
            $config->setPassword($mailConf['password']);
        }

        $config->setKeepAlive($mailConf['keepAlive']);
        $config->setPort($mailConf['port']);
        $this->smtpConfig = $config;
    }

    public function sendAsText($subject, $toEmail, $msgTxt, $tags = null, $callback = null, $errorHandler = null)
    {
        $this->send($subject, $toEmail, null, $msgTxt, $tags, $callback, $errorHandler);
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

        if ($this->smtpConfig == null) {
            $this->setupSmtpConfig($this->mailConf);
        }

        $mailClient = \MailClient::createShared($this->vertx, $this->smtpConfig);

        $message = new \Java("io.vertx.ext.mail.MailMessage");
        $message->setFrom("{$this->mailConf['fromName']} <{$this->mailConf['fromEmail']}>");

        if (!empty($this->mailConf['toEmailDev'])) {
            $message->setTo($this->mailConf['toEmailDev']);
        } else {
            $message->setTo($toEmail);
        }

        $message->setSubject($subject);

        if (!empty($msgHtml)) {
            $message->setHtml($msgHtml);
        }

        if (!empty($msgTxt)) {
            $message->setText($msgTxt);
        }

        if (!empty($tags)) {
            $this->app->logDebug('Send with tagging ' . print_r($tags, true));
        }

        $mailClient->sendMail($message, a(function ($result, $error) use ($callback, $errorHandler) {
            if ($result) {
                if ($callback) {
                    $callback(['result' => $result->toString()]);
                }
            } else {
                if ($errorHandler) {
                    $errorHandler($error);
                }
            }
        }));
    }
}