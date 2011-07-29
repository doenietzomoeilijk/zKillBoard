<?php

class Tables
{
    /**
     * Ensures the table exists for a particular year and month
     *
     * @static
     * @param $year
     * @param $month
     * @return void
     */
    public static function ensureTableExist($year, $month)
    {
        global $table_array, $dbPrefix;
        if (!isset($table_array)) $table_array = array();

        if (isset($table_array["$year $month"])) return;
        $currentYear = date("Y");
        $currentMonth = date("m");
        $error = false;
        if ($year < 2003 or $year > $currentYear) $error = true;
        if ($year == $currentYear and $month > $currentMonth) $error = true;
        if ($month < 1 || $month > 12) $error = true;
        if (strlen("$month") != 2) $error = true;
        if ($error) {
            throw new Exception("Invalid year/month: $year $month (month should be in two digit format)");
        }

        Db::execute("create table if not exists {$dbPrefix}kills_{$year}_{$month} like {$dbPrefix}kills_base");
        Db::execute("create table if not exists {$dbPrefix}items_{$year}_{$month} like {$dbPrefix}items_base");
        Db::execute("create table if not exists {$dbPrefix}participants_{$year}_{$month} like {$dbPrefix}participants_base");

        $table_array["$year $month"] = true;
    }
}
