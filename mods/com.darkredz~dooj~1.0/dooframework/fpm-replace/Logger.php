<?php
/**
 * Created by IntelliJ IDEA.
 * User: leng
 * Date: 15/05/2018
 * Time: 3:28 PM
 */


class Logger
{
    public function info($msg)
    {
        if (strpos($msg, '[INFO]') === false) {
            $this->println('[INFO]: ' . $msg);
        } else {
            $this->println($msg);
        }
    }

    public function debug($msg)
    {
        if (strpos($msg, '[DEBUG]') === false) {
            $this->println('[DEBUG]: ' . $msg);
        } else {
            $this->println($msg);
        }
    }

    public function error($msg)
    {
        if (strpos($msg, '[ERROR]') === false) {
            $this->println('[ERROR]: ' . $msg);
        } else {
            $this->println($msg);
        }
    }

    protected function println($msg)
    {
        error_log($msg);
//        echo $msg . "\n";
    }
}

class LoggerFactory {
    public static function getLogger($tag) {
        return new Logger;
    }
}
