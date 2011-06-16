<?php

class Db
{
    /**
     * @var int Stores the number of Query executions and inserts
     */
    protected static $queryCount = 0;

    /**
     * @static
     * @param string $query The query.
     * @param array $parameters The parameters
     * @return string The query and parameters as a hashed value.
     */
    public static function getKey($query, $parameters)
    {
        $key = $query;
        foreach ($parameters as $k => $v) {
            $key .= "|$k|$v";
        }
        return "Db:" . md5($key);
    }

    /**
     * Creates and returns a PDO object.
     *
     * @static
     * @return PDO
     */
    protected static function getPDO()
    {
        global $dbUser, $dbPassword, $dbName, $dbHost, $pdo;

        if (isset($pdo)) return $pdo;

        $dsn = "mysql:dbname=$dbName;host=$dbHost";

        $pdo = new PDO($dsn, $dbUser, $dbPassword);
        return $pdo;
    }

    /**
     * Logs a query, its parameters, and the amount of time it took to execute.
     * The original query is modified through simple search and replace to create
     * the query as close to the execution as PDO would have the query.  This
     * logging function doesn't take any care to escape any parameters, so take
     * caution if you attempt to execute any logged queries.
     *
     * @param string $query The query.
     * @param array $parameters A key/value array of parameters
     * @param int $duration The length of time it took for the query to execute.
     * @return void
     */
    public static function log($query, $parameters, $duration = 0)
    {
        foreach ($parameters as $k => $v) {
            $query = str_replace($k, "'" . $v . "'", $query);
        }
        Log::log(($duration != 0 ? $duration . "ms. " : "") . " Query: \n$query;\n");
    }

    /**
     * @static
     * @throws Exception
     * @param  $statement
     * @param  string $query
     * @param  array $parameters
     * @return void
     */
    private static function processError($statement, $query, $parameters)
    {
        $errorCode = $statement->errorCode();
        $errorInfo = $statement->errorInfo();
        Db::log("$errorCode - " . $errorInfo[2] . "\n$query", $parameters);
        throw new Exception($errorInfo[0] . " - " . $errorInfo[1] . " - " . $errorInfo[2]);
    }

    /**
     * @static
     * @param string $query The query to be executed.
     * @param array $params (optional) A key/value array of parameters.
     * @param int $cacheTime The time, in seconds, to cache the result of the query.  Default: 30
     * @return Returns the full resultset as an array.
     */
    public static function query($query, $params = array(), $cacheTime = 30)
    {
        // cacheTime of 0 or less means skip all caches and just do the query
        if ($cacheTime > 0) {
            $key = Db::getKey($query, $params);

            // First, check our local storage bin
            $result = Bin::get($key, FALSE);
            if ($result !== FALSE) return $result;

            // Second, check MemCache
            $result = Memcached::get($key);
            if ($result !== FALSE) return $result;
        }

        // OK, hit up the database, but let's time it too
        $timer = new Timer();

        Db::$queryCount++;
        $pdo = Db::getPDO();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        if ($stmt->errorCode() != 0) Db::processError($stmt, $query, $params);

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if ($cacheTime > 0) {
            Bin::set($key, $result);
            Memcached::set($key, $result, min(3600, $cacheTime));
        }
        $duration = $timer->stop();
        if ($duration > 10) Db::log($query, $params, $duration);
        return $result;
    }

    /**
     * @static
     * @param string $query The query to be executed
     * @param array $parameters (optional) A key/value array of parameters
     * @param int $cacheTime The time, in seconds, to cache the result of the query.  Default: 30
     * @return Returns the first row of the result set. Returns null if there are no rows.
     */
    public static function queryRow($query, $parameters = array(), $cacheTime = 30)
    {
        $result = Db::query($query, $parameters, $cacheTime);
        if (sizeof($result) >= 1) return $result[0];
        return null;
    }

    /**
     * @static
     * @param string $query The query to be executed
     * @param string $field The name of the field to return
     * @param array $parameters (optional) A key/value array of parameters
     * @param int $cacheTime The time, in seconds, to cache the result of the query.  Default: 30
     * @return null Returns the value of $field in the first row of the resultset. Returns null if there are no rows.
     */
    public static function queryField($query, $field, $parameters = array(), $cacheTime = 30)
    {
        $result = Db::query($query, $parameters, $cacheTime);
        if (sizeof($result) == 0) return null;
        $resultRow = $result[0];
        return $resultRow[$field];
    }


    /**
     * Executes a SQL command and returns the number of rows affected.
     * Good for inserts, updates, deletes, etc.
     *
     * @static
     * @param string $query The query to be executed.
     * @param array $parameters (optional) A key/value array of parameters.
     * @param boolean $reportErrors Log the query and throw an exception if the query fails. Default: true
     * @return int The number of rows affected by the sql query.
     */
    public static function execute($query, $parameters = array(), $reportErrors = true)
    {
        Db::$queryCount++;
        $pdo = Db::getPDO();
        $statement = $pdo->prepare($query);
        $statement->execute($parameters);

        if ($statement->errorCode() != 0) {
            if ($reportErrors) Db::processError($statement, $query, $parameters);
            return FALSE;
        }

        $rowCount = $statement->rowCount();
        $statement->closeCursor();
        return $rowCount;
    }

    /**
     * Retrieve the number of queries executed so far.
     *
     * @static
     * @return int Number of queries executed so far
     */
    public static function getQueryCount()
    {
        return Db::$queryCount;
    }
}
