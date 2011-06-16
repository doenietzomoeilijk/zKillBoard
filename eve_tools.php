<?php

/*
 * This file has been my dumping ground for many utility functions that help drive many parts
 * of the site.
 *
 * TODO Refactor the code into classes with proper organization.
 */

/**
 * @param array $context
 * @param boolean $isVictim
 * @param null $additionalWhere
 * @param int $limit
 * @return array
 */
function getKills(&$context, $isVictim, $additionalWhere = null, $limit = 10)
{
    $query = getQuery($context, $isVictim, $additionalWhere, $limit);

    $results = Db::query($query["query"], $query["parameters"]);

    $kills = array();
    foreach ($results as $result) {
        $kills[] = $result['killID'];
    }
    return getKillInfo($kills);
}


/**
 * @param array $context Global scoped parameter
 * @param boolean $isVictim Define friendlies as the victim
 * @param null $additionalWhere Add this where statement to the whereclauses
 * @param int $limit Default the search result to this limit
 * @return array An array containing the "query" and its "parameters"
 */
function getQuery(&$context, $isVictim, $additionalWhere = null, $limit = 10)
{
    $queryInfo = buildQuery($context, $isVictim, $additionalWhere, $limit);
    $whereClauses = $queryInfo["whereClauses"];
    $tables = $queryInfo["tables"];
    $orderBy = $queryInfo["orderBy"];
    $queryParameters = $queryInfo["parameters"];
    $limit = $queryInfo["limit"];

    // Build the query
    $query = "select distinct kills.killID from ";
    $query .= implode(",", $tables);
    $query .= " where ";
    $query .= implode(" and ", $whereClauses);
    $query .= " order by kills.killID $orderBy";
    if ($limit != -1) $query .= " limit $limit";

    return array("query" => $query, "parameters" => $queryParameters);
}

/**
 * Returns a pre-built query as an array.
 *
 * @param array $context Global scoped parameter
 * @param boolean $isVictim Define "friendlies" as the victim
 * @param null $additionalWhere Add this where statement to the whereclauses
 * @param int $limit Default the search result to this limit
 * @return array ("tables" => $tables, "whereClauses" => $whereClauses, "orderBy" => $orderBy, "parameters" => $queryParameters, "limit" => $limit);
 */
// TODO Pass $p as a parameter
function buildQuery(&$context, $isVictim, $additionalWhere = null, $limit = 10)
{
    global $p, $dbPrefix, $subDomain, $subDomainEveID, $subDomainGroupID, $pModified;
    if (!isset($pModified)) $pModified = false;

    if (strlen($subDomain) > 0 && !$pModified) {
        $pModified = true;
        // Check the tickers first
        $subDomainEveID = Db::queryField("select itemID from eveNames where ticker = :ticker", "itemID", array(":ticker" => strtoupper($subDomain)), 7200);

        if ($subDomainEveID == null) {
            $subDomain = str_replace("_", " ", $subDomain);
            $subDomainEveID = Info::getEveIdFromTicker($subDomain);
        }

        if ($subDomainEveID == null) {
            $subDomain = str_replace("-", "'", $subDomain);
            $subDomainEveID = Info::getEveIdFromTicker($subDomain);
        }
        if ($subDomainEveID == null) {
            $subDomainEveID = Info::getEveIdFromTicker($subDomain . ".");
        }

        if ($subDomainEveID == null) {
            $subDomain = str_replace(".dot", ".", $subDomain);
            $subDomainEveID = Info::getEveIdFromTicker($subDomain);
        }
        if ($subDomainEveID == null) {
            $subDomain = str_replace("dot.", ".", $subDomain);
            $subDomainEveID = Info::getEveIdFromTicker($subDomain);
        }

        $row = Db::queryRow("select * from eveNames where itemID = :itemID", array(":itemID" => $subDomainEveID), 7200);
        if ($row == null || sizeof($row) == 0) {
            // No idea who they're looking for, therefore send them to our main URL.
            header('Location: http://killwhore.com/');
            exit;
        }

        $groupID = $row['groupID'];
        switch ($groupID) {
            case 32:
                $subDomainGroupID = $groupID;
                array_unshift($p, "with", "alli", $row['itemName']);
                $context['isAlliancePage'] = true;
                $context['subDomainPageType'] = 'alli';
                $context['pageTitle'] = $row['itemName'];
                break;
            case 2:
                $subDomainGroupID = $groupID;
                array_unshift($p, "with", "corp", $row['itemName']);
                $context['isCorpPage'] = true;
                $context['subDomainPageType'] = 'corp';
                $context['pageTitle'] = $row['itemName'];
                break;
            case 1:
                $subDomainGroupID = $groupID;
                array_unshift($p, "with", "pilot", $row['itemName']);
                $context['isPilotPage'] = true;
                $context['subDomainPageType'] = 'pilot';
                $context['pageTitle'] = $row['itemName'];
                break;
        }
    }
    if (!isset($context['subDomainPageType'])) $context['subDomainPageType'] = "all";


    $queryKey = "buildQuery_$isVictim $additionalWhere $limit";
    $retValue = Bin::get($queryKey, FALSE);
    if ($retValue !== FALSE) return $retValue;

    $whereClauses = array();
    $queryParameters = array();

    $coalition = false;
    $pilots = array();
    $corps = array();
    $allis = array();
    $ships = array();
    $tables = array();
    $year = null;
    $month = null;
    $context['SearchParameters'] = array();

    $specificMail = false;

    $pCount = sizeof($p);
    for ($i = 0; $i < $pCount; $i++) {
        $key = $p[$i];
        $value = $i < ($pCount - 1) ? $p[$i + 1] : null;
        switch ($key) {
            case "against":
                $coalition = false;
                break;
            case "with":
                $coalition = true;
                break;
            case "killmail":
                $specificMail = true;
                $whereClauses[] = "kills.killID = :killID";
                $queryParameters[":killID"] = $value;
            case "pilot":
                $context['SearchParameters']["$value"] = "P:$value";
                $pilots[Info::getEveID($value)] = $coalition;
                break;
            case "corp":
                $context['SearchParameters']["$value"] = "C:$value";
                $corps[Info::getEveID($value)] = $coalition;
                break;
            case "alli":
                $context['SearchParameters']["$value"] = "A:$value";
                $allis[Info::getEveID($value)] = $coalition;
                break;
            case "ship":
                $itemID = Info::getItemID($value);
                $context['SearchParameters'][] = "Ship: " . Info::getItemName($itemID);
                $ships[$itemID] = $coalition;
                break;
            case "system":
                $systemID = Info::getSystemID($value);
                $whereClauses[] = "solarSystemID = :systemID";
                $queryParameters[":systemID"] = $systemID;
                $context['SearchParameters'][] = "System: $value";
                break;
            case "shipTypeID":
                $context['SearchParameters'][] = "Ship: " . Info::getItemName($value);
                $whereClauses[] = " kills.killID in (select killID from {$dbPrefix}participants where shipTypeID = :shipTypeID)";
                $queryParameters[":shipTypeID"] = $value;
                break;
            case "year":
                $year = min(date("Y"), max(2003, (int)$value));
                break;
            case "month":
                $month = min(12, max(1, (int)$value));
                break;
            case "day":
                $day = min(31, max(1, (int)$value));
                $whereClauses[] = "day = :day";
                $queryParameters[":day"] = $day;
                $context['SearchParameters'][] = "Day: $day";
                $context['searchDay'] = $day;
                break;
            case "related":
                $split = explode(",", $value);

                $system = $split[0];
                $systemID = Info::getSystemID($system);
                $whereClauses[] = "solarSystemID = :systemID";
                $queryParameters[":systemID"] = $systemID;
                $context['SearchParameters'][] = "System: $system";

                $date = $split[1];
                $year = max(1, (int)substr($date, 0, 4));
                $month = max(1, (int)substr($date, 4, 2));
                $day = max(1, (int)substr($date, 6, 2));
                $hours = max(1, (int)substr($date, 8, 2));
                $time = mktime($hours, 0, 0, $month, $day, $year, 0);
                $prevHour = $time - 3600;
                $nextHour = $time + 7200;
                $whereClauses[] = "unix_timestamp >= $prevHour and unix_timestamp <= $nextHour";
                $context['SearchParameters'][] = "Related";
                $value = $date;
            // No break here, let it flow through
            case "date":
                $date = $value;
                $year = max(1, (int)substr($date, 0, 4));
                $month = max(1, (int)substr($date, 4, 2));
                $day = max(1, (int)substr($date, 6, 2));
                $whereClauses[] = "day = :day";
                $queryParameters[":day"] = $day;
                $whereClauses["limit"] = -1;
                $context['searchDay'] = $day;
                break;
            case "display_after":
                if (!$additionalWhere) {
                    $whereClauses[] = "kills.killID > :afterKillID";
                    $queryParameters[":afterKillID"] = $value;
                    $whereClauses["orderBy"] = "asc";
                }
                break;
            case "display_before":
                if (!$additionalWhere) {
                    $whereClauses[] = "kills.killID < :beforeKillID";
                    $queryParameters[":beforeKillID"] = $value;
                }
                break;
            case "before":
                $whereClauses[] = "kills.killID < :before";
                $queryParameters[":before"] = $value;
                break;
            case "after":
                $whereClauses[] = "kills.killID > :after";
                $queryParameters[":after"] = $value;
                break;
        }
    }

    addSubQuery($tables, $whereClauses, $pilots, $queryParameters, "characterID", ":charID");
    addSubQuery($tables, $whereClauses, $corps, $queryParameters, "corporationID", ":corpID");
    addSubQuery($tables, $whereClauses, $allis, $queryParameters, "allianceID", ":alliID");
    addSubQuery($tables, $whereClauses, $ships, $queryParameters, "shipTypeID", ":shipID");

    $whereClauses[] = "isVictim = :victim";
    $queryParameters[":victim"] = $isVictim ? "T" : "F";

    if ($additionalWhere != null) $whereClauses[] = $additionalWhere;

    if (!$specificMail) {
        // Add year and month
        if ($year == null) $year = date("Y");
        if ($month == null) $month = date("n");
        $whereClauses[] = "kills.year = :year";
        $whereClauses[] = "kills.month = :month";
        $queryParameters[":year"] = $year;
        $queryParameters[":month"] = $month;
        $context['searchYear'] = $year;
        $context['searchMonth'] = $month;
    }

    if (isset($context['searchYear']) || isset($context['searchMonth']) || isset($context['searchDay'])) {
        $months = array("Jan.", "Feb.", "March", "April", "May", "June",
                        "July", "Aug.", "Sep.", "Oct.", "Nov.", "Dec.");
        $month = isset($context['searchMonth']) ? $months[$context['searchMonth'] - 1] . ", " : "";
        $day = isset($context['searchDay']) ? " " . $context['searchDay'] : "";
        $context['SearchParameters'][] = " $month$day $year ";
    }

    $orderBy = isset($whereClauses["orderBy"]) ? $whereClauses["orderBy"] : "desc";
    unset($whereClauses["orderBy"]);

    $limit = isset($whereClauses["limit"]) ? $whereClauses["limit"] : $limit;
    unset($whereClauses["limit"]);

    $tables[] = "{$dbPrefix}kills kills left join {$dbPrefix}participants joined on (kills.killID = joined.killID)";

    $retValue = array("tables" => $tables, "whereClauses" => $whereClauses, "orderBy" => $orderBy, "parameters" => $queryParameters, "limit" => $limit);
    Bin::set($queryKey, $retValue);
    return $retValue;
}

/**
 * Builds a subquery for insertion into the larger query.
 * I'm quite sure there is a better way to do this as well, but with a dynamically built query the challenge
 * is a bit more difficult.  TODO See about converting this subqueries into LEFT JOINs
 *
 * @param  $tables
 * @param  $whereClauses
 * @param  $values
 * @param  $queryParameters
 * @param  $column
 * @param  $shortHand
 * @return void
 */
function addSubQuery(&$tables, &$whereClauses, &$values, &$queryParameters, $column, $shortHand)
{
    global $dbPrefix, $p;

    $indexCount = 1;
    asort($values);
    foreach ($values as $value => $coalition) {
        $victimMatch = $coalition ? "=" : "!=";
        $tCount = sizeof($tables);
        $finalBlow = in_array("finalBlow", $p);
        $finalBlow = $finalBlow ? " and finalBlow = 1 " : "";
        $tables[] = "(select distinct kills.killID from {$dbPrefix}participants joined, {$dbPrefix}kills kills
						where kills.killID = joined.killID and $column = $shortHand$indexCount $finalBlow and isVictim $victimMatch :victim and kills.year = :year and kills.month = :month) as t$tCount";
        $whereClauses[] = "kills.killID = t$tCount.killID";
        $queryParameters["$shortHand$indexCount"] = $value;
        $indexCount++;
    }
}

/**
 * Returns kill details including the victim and the pilot that dealt the final blow.  Does not include all
 * involved pilots.
 *
 * @param  $killIds
 * @return array
 */
function getKillInfo($killIds)
{
    if (sizeof($killIds) == 0) return array();

    global $dbPrefix;

    if (!is_array($killIds)) {
        $killIds = array($killIds);
    }
    sort($killIds);

    $killInfo = array();
    $victims = array();
    $attackers = array();
    $imploded = implode(",", $killIds);

    $killDetail = Db::query("select * from {$dbPrefix}kills where killID in ($imploded) order by killID desc", array(), 3600);
    $involved = Db::query("select * from {$dbPrefix}participants where killID in ($imploded) and (isVictim = 'T' or finalBlow = '1')", array(), 3600);
    foreach ($involved as $pilot) {
        if ($pilot['isVictim'] == "T") $victims[] = $pilot;
        else if ($pilot['finalBlow'] == 1) $attackers[] = $pilot;
    }

    killMerge($killInfo, $killDetail, "detail");
    killMerge($killInfo, $victims, "victim");
    killMerge($killInfo, $attackers, "attacker");

    return $killInfo;
}

/**
 * Merges kill details into an array.
 *
 * @param  $killInfo
 * @param  $killRow
 * @param  $name
 * @return void
 */
function killMerge(&$killInfo, &$killRow, $name)
{
    foreach ($killRow as $row) {
        $killID = $row['killID'];
        if (!isset($killInfo["$killID"])) $killInfo["$killID"] = array();
        $killInfo["$killID"][$name] = $row;
    }
}

/**
 * Obtain the full kill detail for a single kill.
 *
 * @param  $killID
 * @return array
 */
function getKillDetail($killID)
{
    global $dbPrefix;

    $killDetail = Db::queryRow("select * from {$dbPrefix}kills where killID = :killID", array(":killID" => $killID));
    $victim = Db::queryRow("select * from {$dbPrefix}participants where isVictim = 'T' and killID = :killID", array(":killID" => $killID));
    $attackers = Db::query("select * from {$dbPrefix}participants where isVictim = 'F' and  killID = :killID order by damage desc", array(":killID" => $killID));
    $items = Db::query("select * from {$dbPrefix}items where killID = :killID order by insertOrder", array(":killID" => $killID));

    return array(
        "killID" => $killID,
        "detail" => $killDetail,
        "victim" => $victim,
        "attackers" => $attackers,
        "items" => $items,
    );
}


/**
 * @param  $eve_id int The eveID of the entity
 * @param  $name The name of the entity
 * @param  $catID The catID of the entity
 * @param  $groupID The groupID of the entity
 * @param  $typeID The typeID of the entity
 * @return void
 */
function addName($eve_id, $name, $catID, $groupID, $typeID)
{
    $upperItemName = strtoupper($name);
    Db::execute("insert into eveNames (itemID, itemName, categoryID, groupID, typeID, upperItemName)
                    values (:id, :name, :catID, :groupID, :typeID, :upperItemName)
                on duplicate key update itemName = :name",
                array(":id" => $eve_id, ":name" => $name, ":catID" => $catID,
                     ":groupID" => $groupID, ":typeID" => $typeID, ":upperItemName" => $upperItemName));
}

/**
 * A helper function that converts a shorthand string into a full column name.
 *
 * @param  $type The shorthand string.
 * @return null|string The full column name if found, null otherwise.
 */
function getColumnType($type)
{
    switch ($type) {
        case "pilot":
            $typeID = "characterID";
            break;
        case "corp":
            $typeID = "corporationID";
            break;
        case "alli":
            $typeID = "allianceID";
            break;
        case "price":
            $typeID = "total_price";
            break;
        case "ship":
            $typeID = "shipTypeID";
            break;
        case "faction":
            $typeID = "factionID";
            break;
        default:
            $typeID = null;
    }
    return $typeID;
}

/**
 * @param  $context
 * @param  $type
 * @param bool $isVictim
 * @param int $limit
 * @return array|Returns
 */
function topDogs(&$context, $type, $isVictim = false, $limit = 5)
{
    global $subDomainEveID, $subDomainGroupID;

    $typeID = getColumnType($type);
    if ($typeID == null) return array();

    $queryInfo = buildQuery($context, $isVictim, null, $limit);

    $pilotID = null;
    $corpID = null;
    $alliID = null;

    $whereClauses = $queryInfo["whereClauses"];
    switch ($subDomainGroupID) {
        case 32: // Alli Board
            $alliID = $subDomainEveID;
            break;
        case 2: // Corp Board
            $corpID = $subDomainEveID;
            break;
        case 1: // Pilot Board
            $pilotID = $subDomainEveID;
            break;
    }
    $whereClauses[] = "characterID " . ($pilotID == null ? "!= 0" : " = $pilotID");
    $whereClauses[] = "corporationID " . ($corpID == null ? "!= 0" : " = $corpID");
    if ($subDomainGroupID == null) $whereClauses[] = "allianceID " . ($alliID == null ? "!= 0" : " = $alliID");

    $tables = $queryInfo["tables"];
    $queryParameters = $queryInfo["parameters"];

    // TODO Look into optimizing this query
    $query = "	select * from ( select $typeID, count(distinct kills.killID) count
				from " . implode(", ", $tables) . "
				where " . implode(" and ", $whereClauses) . "
				group by 1 ) as topRows order by count desc";
    if ($limit > 0) $query .= " limit $limit";

    $result = Db::query($query, $queryParameters, 120);
    return $result;
}

function calculateFirstAndPrevious(&$kills, &$context)
{
    global $p;

    $firstLast = findFirstAndLastKill($kills);
    $first = $firstLast["first"];
    $last = $firstLast["last"];
    $firstKillID = $first["detail"]["killID"];
    $lastKillID = $last["detail"]["killID"];
    if ($firstKillID != $lastKillID) {
        if (in_array("before", $p) || in_array("after", $p)) $context['display_after'] = $firstKillID;
        $context['display_before'] = $lastKillID;
    }
}

function findFirstAndLastKill(&$kills)
{
    $firstKill = null;
    $lastKill = null;
    foreach ($kills as $kill) {
        if ($firstKill == null) $firstKill = $kill;
        $lastKill = $kill;
    }
    return array("first" => $firstKill, "last" => $lastKill);
}

function shipGroupSort($a, $b)
{
    $split1 = explode("|", $a);
    $split2 = explode("|", $b);
    $shipTypeID1 = $split1[0];
    $shipTypeID2 = $split2[0];

    $volumeID1 = Db::queryField("select volume from invTypes where typeID = :typeID", "volume", array(":typeID" => $shipTypeID1), 3600);
    $volumeID2 = Db::queryField("select volume from invTypes where typeID = :typeID", "volume", array(":typeID" => $shipTypeID2), 3600);

    if ($volumeID1 != $volumeID2) return $volumeID2 - $volumeID1;

    if ($shipTypeID1 == $shipTypeID2) {
        if ($split1[1] == $split2[1]) {
            return $split1[2] - $split2[2];
        }
        return $split1[1] - $split2[1];
    }

    $groupID1 = Db::queryField("select groupID from invTypes where typeID = :typeID", "groupID", array(":typeID" => $shipTypeID1), 3600);
    $groupID2 = Db::queryField("select groupID from invTypes where typeID = :typeID", "groupID", array(":typeID" => $shipTypeID2), 3600);
    if ($groupID1 == $groupID2) {
        return $shipTypeID2 - $shipTypeID1;
    }
    if ($groupID1 == $groupID2) return 0;
    return $groupID2 - $groupID1;
}

/**
 * @param array $context
 * @param string $type
 * @param int $eveID
 * @return mixed|null|Returns
 */
function getStatistics($context, $type, $eveID)
{
    global $p, $dbPrefix;

    $builtQuery = buildQuery($context, false);
    $tables = $builtQuery["tables"];
    $whereClauses = $builtQuery["whereClauses"];
    $parameters = $builtQuery["parameters"];

    $hash = md5(implode("/", $p));
    if ($eveID == null) $eveID = 0;

    // See if we already have this query in the statistics table
    $statsQuery = "select uncompress(result) result from {$dbPrefix}cache where type = 'stat' and year = :year and month = :month and query = :hash and eve_id = :eveID";
    $statsParameters = array(":year" => $parameters[":year"], ":month" => $parameters[":month"], ":eveID" => $eveID, ":hash" => $hash);
    $statistics = Db::queryField($statsQuery, "result", $statsParameters, 120);

    if ($statistics == null) {
        // Get the full column name
        $typeID = getColumnType($type);

        // And build the query.
        $query = "select year, month, joined.groupID,
                     sum(if(finalBlow=1,1,0)) kills_num, sum(if(finalBlow = 1,total_price,0)) kills_value,
                     sum(if(isVictim='T',1,0)) losses_num,sum(if(isVictim='T',total_price,0)) losses_value\n";
        $query .= " from " . implode(", ", $tables);
        $query .= " where " . implode(" and ", $whereClauses);
        if ($type != "all") $query .= " and $typeID = $eveID ";
        $query .= " group by year, month, joined.groupID";

        // Remove the isVictim whereClause and parameter.  We don't need to limit
        // to just victims when doing statistics.  Otherwise we would have to
        // execute the query twice, once for victims and once for kills.
        unset($parameters[":victim"]);
        $query = str_replace("and isVictim = :victim", "", $query);
        $query = str_replace("and isVictim != :victim", "", $query); //

        $statistics = Db::query($query, $parameters);

        $json = json_encode($statistics);
        Db::execute("replace into {$dbPrefix}cache (eve_id, type, year, month, query, result) values (:eveID, 'stat', :year, :month, :hash, compress(:result))",
                    array(":year" => $parameters[":year"], ":month" => $parameters[":month"], ":eveID" => $eveID, ":hash" => $hash, ":result" => $json));
        $key = Db::getKey($statsQuery, $statsParameters);
        Log::log("Removing Key");
        Memcached::delete($key);
    } else {
        $statistics = json_decode($statistics);
    }

    return $statistics;
}