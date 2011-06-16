<?php

class Log
{

    public function __construct()
    {
        trigger_error('The class "log" may only be invoked statically.', E_USER_ERROR);
    }

    public static function log($text)
    {
        error_log(date("Ymd H:i:s") . " $text \n", 3, "/var/log/kb/kb.log");
    }

    /*
         Mapped by Eggdrop to log into #evechatter
     */
    public static function irc($text)
    {
        global $ircLogFile;

        if (isset($ircLogFile)) error_log("KillWhore - $text\n", 3, $ircLogFile);
    }

    public static function error($text)
    {
        error_log(date("Ymd H:i:s") . " $text \n", 3, "/var/log/kb/kb_error.log");
    }
}
