<?php

class Cron
{
    public static function executeCronJobs()
    {
        global $dbPrefix, $baseDir;

        // Create a cronlock table for non-blocking mutex equivalent.  yes, it is a hack but it works
        Db::execute("create table if not exists cronlock (a int(8) primary key, dttm timestamp not null) engine memory");
        // Not likely to happen, but just in case. Removes stale locks
        Db::execute("delete from cronlock where dttm < date_add(now(), interval -1 hour)");
        // An atomic operation at the database primary key level :)
        if (Db::execute("insert into cronlock values (1, current_timestamp)", array(), false) === false) return;

        set_time_limit(0); // No timing out allowed here

        $error = null;
        try {
            // Get the cron jobs that need execution
            $cronjobs = Db::query("select cronID, cronInterval, fileName, functionName from {$dbPrefix}cronjobs where (cronInterval + lastExecution) < unix_timestamp()", array(), 0);
            foreach ($cronjobs as $cronjob) {
                $cronID = $cronjob["cronID"];
                $fileName = $cronjob["fileName"];
                $functionName = $cronjob["functionName"];
                $cronInterval = $cronjob["cronInterval"];

                if ($cronInterval < 60) {
                    echo "CronID $cronID - A cronjob's cronInterval must be 60 or higher.  Will not execute this cron.\n";
                    continue;
                }

                try {
                    require_once("$baseDir/$fileName");
                    call_user_func($functionName);

                    // Successful execution!
                    Db::execute("update {$dbPrefix}cronjobs set lastExecution = unix_timestamp() where cronID = :cronID", array(":cronID" => $cronID));
                } catch (Exception $ex) {
                    echo "An error occurred while executing file: $fileName and function: $functionName:\n";
                    print_r($ex);

                    // Unsuccessful execution, try again in (cronInterval * 3) or 1 hour, whichever is less
                    $nextExecution = min(3600, $cronInterval * 3);
                    Db::execute("update {$dbPrefix}cronjobs set lastExecution = (unix_timestamp() - cronInterval + :nextExecution) where cronID = :cronID",
                                array(":cronID" => $cronID, ":nextExecution" => $nextExecution));
                }
            }
        } catch (Exception $ex) {
            $error = $ex;
        }

        // Removes the cron lock
        Db::execute("truncate cronlock");

        if ($error != null) throw $error;
    }


}
