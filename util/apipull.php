<?php

// Command line execution?
$cle = "cli" == php_sapi_name();
if (!$cle) return; // Prevent web execution

$base = dirname(__FILE__);
require_once "$base/../init.php";
require_once "$base/pheal/config.php";

function handleApiException($user_id, $char_id, $exception)
{
    global $dbPrefix;

    $code = $exception->getCode();
    $message = $exception->getMessage();
    $clearCharacter = false;
    $clearAllCharacters = false;
    $clearApiEntry = false;
    $updateCacheTime = false;
    $demoteCharacter = false;
    $cacheUntil = 0;
    switch ($code) {
        case 119: // Kills exhausted: retry after [{0}]
        case 120: // Expected beforeKillID [{0}] but supplied [{1}]: kills previously loaded.
            $cacheUntil = $exception->cached_until_unixtime + 30;
            $updateCacheTime = true;
            break;
        case 200: // Current security level not high enough.
            // Typically happens when a key isn't a full API Key
            $clearApiEntry = true;
            $code = 203; // Force it to go away, no point in keeping this key
            break;
        case 201: // Character does not belong to account.
            // Typically caused by a character transfer
            $clearCharacter = true;
            break;
        case 207: // Not available for NPC corporations.
        case 209:
            $demoteCharacter = true;
            break;
        case 211: // Login denied by account status
            // Remove characters, will revalidate with next doPopulate
            $clearAllCharacters = true;
            $clearApiEntry = true;
            break;
        case 202: // API key authentication failure.
        case 203: // Authentication failure - API is no good and will never be good again
        case 204: // Authentication failure.
        case 205: // Authentication failure (final pass).
        case 210: // Authentication failure.
        case 521: // Invalid username and/or password passed to UserData.LoginWebUser().
            $clearAllCharacters = true;
            $clearApiEntry = true;
            break;
        case 0: // API Date could not be read / parsed, original exception (Something is wrong with the XML and it couldn't be parsed)
        case 500: // Internal Server Error (More CCP Issues)
        case 520: // Unexpected failure accessing database. (More CCP issues)
        case 404: // URL Not Found (CCP having issues...)
        case 902: // Eve backend database temporarily disabled
            $updateCacheTime = true;
            $cacheUntil = time() + 3600; // Try again in an hour...
            break;
        default:
            echo "Unhandled error - Code $code - $message";
            $updateCacheTime = true;
            $clearApiEntry = true;
            $cacheUntil = time() + 3600;
    }

    if ($demoteCharacter && $char_id != 0) {
        Db::execute("update {$dbPrefix}api_characters set isDirector = 'F' where characterID = :char_id",
                    array(":char_id" => $char_id));
    }

    if ($clearCharacter && $char_id != 0) {
        Db::execute("delete from {$dbPrefix}api_characters where user_id = :user_id and characterID = :char_id", array(":user_id" => $user_id, ":char_id" => $char_id));
    }

    if ($clearAllCharacters) {
        Db::execute("delete from {$dbPrefix}api_characters where user_id = :user_id", array(":user_id" => $user_id));
    }

    if ($clearApiEntry) {
        Db::execute("update {$dbPrefix}api set error_code = :code where user_id = :user_id", array(":user_id" => $user_id, ":code" => $code));
    }

    if ($updateCacheTime && $cacheUntil != 0 && $char_id != 0) {
        Db::execute("update {$dbPrefix}api_characters set cachedUntil = :cacheUntil where characterID = :char_id",
                    array(":cacheUntil" => $cacheUntil, ":char_id" => $char_id));
    }
}

function doApiSummary() {
    global $dbPrefix;

    $charKills = Db::queryField("select contents count from {$dbPrefix}storage where locker = 'charKillsProcessed'", "count");
    $corpKills = Db::queryField("select contents count from {$dbPrefix}storage where locker = 'corpKillsProcessed'", "count");
    Db::execute("delete from {$dbPrefix}storage where locker in ('charKillsProcessed', 'corpKillsProcessed')");

    if ($charKills != null && $charKills > 0) Log::irc(pluralize($charKills, "Kill") . " total pulled from Character Keys in the last 60 minutes.");
    if ($corpKills != null && $corpKills > 0) Log::irc(pluralize($corpKills, "Kill") . " total pulled from Corporation Keys in the last 60 minutes.");
}

function doPopulateCharactersTable($user_id = null)
{
    global $dbPrefix;

    $specificUserID = $user_id != null;
    //if ($user_id == null) Log::irc("Repopulating character API table.");
    //else Log::irc("Populating characters for a specific user_id.");
    $apiCount = 0;
    $totalKeys = 0;
    $numErrrors = 0;
    $directorCount = 0;
    $characterCount = 0;

    $apiTableCount = Db::queryField("select count(*) count from {$dbPrefix}api", "count");
    // 15 minutes per hour, 24 hours, mean 96 checks, how many keys per 96 checks to validate all within a 24 hours period?
    $limit = intval($apiTableCount / 96) + 1;

    if ($user_id == null) $apiKeys = Db::query("select * from {$dbPrefix}api where error_code != 203 order by lastValidation limit $limit", array(), 0);
    else $apiKeys = Db::query("select * from {$dbPrefix}api where user_id = :user_id", array(":user_id" => $user_id));

    foreach ($apiKeys as $apiKey) {
        $user_id = $apiKey['user_id'];
        $api_key = $apiKey['api_key'];

        $totalKeys++;
        $pheal = new Pheal($user_id, $api_key);
        try {
            $characters = $pheal->Characters();
        } catch (Exception $ex) {
            handleApiException($user_id, null, $ex);
            $numErrrors++;
            continue;
        }
        $apiCount++;
        // Clear the error code
        Db::execute("update {$dbPrefix}api set error_code = 0, lastValidation = now() where user_id = :user_id", array(":user_id" => $user_id));
        $characterIDs = array();
        $pheal->scope = 'char';
        foreach ($characters->characters as $character) {
            $characterCount++;
            $characterID = $character->characterID;
            $characterIDs[] = $characterID;
            $corporationID = $character->corporationID;

            $charSheet = $pheal->CharacterSheet(array('characterID' => $characterID));

            $isDirector = false;
            foreach ($charSheet->corporationRoles as $role) {
                $isDirector |= "roleDirector" === (string)$role->roleName;
            }
            if ($isDirector) $directorCount++;

            Db::execute("insert into {$dbPrefix}api_characters (user_id, characterID, corporationID, isDirector, cachedUntil)
								values (:user_id, :characterID, :corporationID, :isDirector, 0) on duplicate key update isDirector = :isDirector",
                        array(":user_id" => $user_id,
                             ":characterID" => $characterID,
                             ":corporationID" => $corporationID,
                             ":isDirector" => $isDirector ? "T" : "F",
                        ));

        }
        // Clear entries that are no longer tied to this account
        if (sizeof($characterIDs) == 0) Db::execute("delete from {$dbPrefix}api_characters where user_id = :userID", array(":userID" => $user_id));
        else Db::execute("delete from {$dbPrefix}api_characters where user_id = :userID and characterID not in (" . implode(",", $characterIDs) . ")",
                         array(":userID" => $user_id));
    }

    $apiCount = number_format($apiCount, 0);
    $directorCount = number_format($directorCount, 0);
    $characterCount = number_format($characterCount, 0);
    if (!$specificUserID) Log::irc("$apiCount keys revalidated: $directorCount Corp CEO/Directors, $characterCount Characters, and $numErrrors invalid keys.");
    else Log::irc("Specific user_id brought in " . pluralize($directorCount, "Corp CEO/Director")
                  . " and " . pluralize($characterCount, "Character"));
}

function doPullCharKills()
{
    global $dbPrefix;
    $numKillsProcessed = 0;

    $apiList = Db::query("select api.user_id, api_key, characterID from {$dbPrefix}api api, {$dbPrefix}api_characters chars where api.user_id = chars.user_id and error_code = 0 and isDirector = 'F' and cachedUntil < unix_timestamp() order by cachedUntil limit 50", array(), 0);

    foreach ($apiList as $api) {
        $user_id = $api['user_id'];
        $char_id = $api['characterID'];
        $api_key = $api['api_key'];
        try {
            $pheal = new Pheal($user_id, $api_key, 'char');
            // Prefetch the killlog API to get the cachedUntil
            $killlog = $pheal->Killlog(array('characterID' => $char_id));
            $cachedUntil = $killlog->cached_until_unixtime;

            $numKillsProcessed += processApiKills($user_id, $api_key, $char_id, 'char');
        } catch (Exception $ex) {
            handleApiException($user_id, $char_id, $ex);
            continue;
        }

        if ($cachedUntil != -1) {
            Db::execute("update {$dbPrefix}api_characters set cachedUntil = :cachedUntil where user_id = :user_id and characterID = :characterID",
                        array(":cachedUntil" => $cachedUntil, ":user_id" => $user_id, ":characterID" => $char_id));
        }
    }

    //if ($numKillsProcessed > 10) Log::irc(pluralize($numKillsProcessed, "Kill") . " pulled from Character Keys.");
    if ($numKillsProcessed > 0) Db::execute("insert into {$dbPrefix}storage values ('charKillsProcessed', $numKillsProcessed) on duplicate key update contents = contents + $numKillsProcessed");
}

/**
 * This function should run no more often than every 72 hours
 *
 * @return void
 */
function doPullPrivateKillsforDirectors()
{
    global $dbPrefix;
    $numKillsProcessed = 0;

    $apiList = Db::query("select api.user_id, api_key, characterID from {$dbPrefix}api api, {$dbPrefix}api_characters chars where api.user_id = chars.user_id and error_code = 0 and isDirector = 'T' order by cachedUntil limit 50", array(), 0);

    foreach ($apiList as $api) {
        $user_id = $api['user_id'];
        $char_id = $api['characterID'];
        $api_key = $api['api_key'];
        try {
            $pheal = new Pheal($user_id, $api_key, 'char');
            // Prefetch the killlog API to get the cachedUntil
            $killlog = $pheal->Killlog(array('characterID' => $char_id));
            $cachedUntil = $killlog->cached_until_unixtime;

            $numKillsProcessed += processApiKills($user_id, $api_key, $char_id, 'char');
        } catch (Exception $ex) {
            handleApiException($user_id, $char_id, $ex);
            continue;
        }

        // Updating this will affect corp director pulls.  Since this fucntion is ran every 3 hours cache overlapping
        // on the character key will not happen.
        /*if ($cachedUntil != -1) {
            Db::execute("update {$dbPrefix}api_characters set cachedUntil = :cachedUntil where user_id = :user_id and characterID = :characterID",
                        array(":cachedUntil" => $cachedUntil, ":user_id" => $user_id, ":characterID" => $char_id));
        }*/
    }

    //if ($numKillsProcessed > 10) Log::irc(pluralize($numKillsProcessed, "Kill") . " pulled from Character Keys.");
    if ($numKillsProcessed > 0) Db::execute("insert into {$dbPrefix}storage values ('charKillsProcessed', $numKillsProcessed) on duplicate key update contents = contents + $numKillsProcessed");
}

function doPullCorpKills()
{
    global $dbPrefix;
    $numKillsProcessed = 0;

    $corpApiCountMap = array();
    $corpApiCountResult = Db::query("select corporationID, count(distinct characterID) count from {$dbPrefix}api_characters where isDirector = 'T' group by 1");
    foreach ($corpApiCountResult as $corpApi) {
        $corporationID = $corpApi['corporationID'];
        $corpApiCountMap["$corporationID"] = max(1, min(60, $corpApi['count']));
    }

    foreach ($corpApiCountMap as $corporationID => $directorCount) {
        $iterations = intval(60 / intval(60 / $directorCount));
        $intervals = intval(60 / $iterations);
        $minute = date("i");
        $limit = 0;
        while (($limit + 1) * $intervals < $minute) $limit++;

        $api = Db::queryRow("select * from {$dbPrefix}api api, {$dbPrefix}api_characters chars
							where api.user_id = chars.user_id and
								chars.corporationID = :corporationID and isDirector = 'T' and error_code = 0
							order by characterID limit $limit, 1",
                            array(":corporationID" => $corporationID), 0);
        if ($api == null) continue;
        if ($api['cachedUntil'] > time()) continue;
        $user_id = $api['user_id'];
        $api_key = $api['api_key'];
        $char_id = $api['characterID'];

        // Prefetch the killmail API and set the cachedUntil date
        $cachedUntil = -1;
        $pheal = new Pheal($user_id, $api_key, 'corp');
        try {
            $killlog = $pheal->Killlog(array('characterID' => $char_id));
            $cachedUntil = $killlog->cached_until_unixtime;
            $numKillsProcessed += processApiKills($user_id, $api_key, $char_id);
        } catch (Exception $ex) {
            handleApiException($user_id, $char_id, $ex);
            continue;
        }

        if ($cachedUntil != -1) {
            Db::execute("update {$dbPrefix}api_characters set cachedUntil = :cachedUntil where user_id = :user_id and characterID = :characterID",
                        array(":cachedUntil" => $cachedUntil, ":user_id" => $user_id, ":characterID" => $char_id));
        }
    }

    //if ($numKillsProcessed > 10) Log::irc(pluralize($numKillsProcessed, "Kill") . " pulled from Corporate Keys.");
    if ($numKillsProcessed > 0) Db::execute("insert into {$dbPrefix}storage values ('corpKillsProcessed', $numKillsProcessed) on duplicate key update contents = contents + $numKillsProcessed");
}

function doPullKills()
{
    global $dbPrefix;

    $killsParsedAndAdded = 0;
    $apiKeys = Db::query("select * from {$dbPrefix}api where error_code = 0");
    foreach ($apiKeys as $apiKey) {
        try {
            $killsParsedAndAdded += processKey($apiKey['user_id'], $apiKey['api_key'], 'char');
        } catch (Exception $ex) {
            echo $ex->getMessage() . "\n";
        }
    }
    return $killsParsedAndAdded;
}

function processKey($userID, $apiKey, $scope)
{
    // Get the characters on the key
    $pheal = new Pheal($userID, $apiKey);
    $characters = $pheal->Characters();
    $killsParsedAndAdded = 0;

    foreach ($characters->characters as $character) {
        $charID = $character['characterID'];
        try {
            $killsParsedAndAdded += processApiKills($userID, $apiKey, $charID, $scope);
        } catch (Exception $ex) {
            //echo "$userID, $apiKey, $charID, $scope Error: ",$ex->getCode(),$ex->getMessage(),"\n";
        }
    }
    return $killsParsedAndAdded;
}

/*
	Process Killmails pulled from the full API
	Ignores kills where NPC are the only attackers
*/
function processApiKills($userID, $userKey, $charID, $scope = "corp", $minKillID = -1)
{
    global $dbPrefix;
    $killsParsedAndAdded = 0;

    $pheal = new Pheal($userID, $userKey, $scope);
    $array = array();
    $array['characterID'] = $charID;
    if ($minKillID != -1) $array['beforeKillID'] = $minKillID;
    $killlog = $pheal->Killlog($array);
    if (sizeof($killlog->kills) == 0) return 0;

    $allKillIds = array();
    foreach ($killlog->kills as $kill) {
        $allKillIds[] = "(" . $kill->killID . ")";
    }

    $ids = implode(",", $allKillIds);
    Db::execute("create temporary table {$dbPrefix}kills_temp (killID int(16) primary key ) engine = memory");
    Db::execute("insert into {$dbPrefix}kills_temp values $ids");
    $result = Db::query("select temp.killID from {$dbPrefix}kills_temp temp left join {$dbPrefix}kills as kills on temp.killID = kills.killID where kills.killID is null",
                        array(), 0);
    Db::execute("drop temporary table {$dbPrefix}kills_temp");

    $idsToParse = array();
    foreach ($result as $row) {
        $idsToParse[] = $row['killID'];
    }

    if (sizeof($idsToParse) == 0) return 0;

    foreach ($killlog->kills as $kill) {
        $killID = $kill->killID;

        if (!in_array($killID, $idsToParse)) continue;

        if ($minKillID == -1) $minKillID = $killID;
        else $minKillID = min($minKillID, $killID);

        // Do some validation on the kill
        if (!validKill($kill)) continue;

        $json = json_encode($kill->toArray());
        Db::execute("insert into {$dbPrefix}killmail (killID, kill_json) values (:killID, :json) on duplicate key update kill_json = :json",
                    array(":killID" => $killID, ":json" => $json));

        $totalCost = 0;
        $itemInsertOrder = 0;
        foreach ($kill->items as $item) $totalCost += processItem($killID, $item, $itemInsertOrder++);
        $totalCost += processVictim($killID, $kill->victim);
        foreach ($kill->attackers as $attacker) processAttacker($killID, $attacker);
        processKill($kill, false, sizeof($kill->attackers), $totalCost);
        $killsParsedAndAdded++;
        if ($killsParsedAndAdded % 100 == 0) Log::irc("$killsParsedAndAdded parsed thus far in this run...");
    }
    try {
        // Backtrack for more kills/losses
        if ($minKillID != -1) $killsParsedAndAdded += processApiKills($userID, $userKey, $charID, $scope, $minKillID);
    } catch (Exception $ex) {
        // Kills exhausted or some other problem
    }

    // Set ship groupIDs for the kills just processed
    Db::execute("update {$dbPrefix}participants p,invTypes i set p.groupID = i.groupID where i.groupID is null and i.typeID = p.shipTypeID");

    if ($killsParsedAndAdded > 0) {
        Db::execute("update {$dbPrefix}participants p, invTypes i set p.groupID = i.groupID where p.shipTypeID = i.typeID and p.groupID is null");
        Db::execute("truncate {$dbPrefix}cache");
    }

    return $killsParsedAndAdded;
}

/**
 * @param  $kill
 * @return bool
 */
function validKill(&$kill)
{
    $killID = $kill->killID;

    // Don't process the kill if it's NPC only
    $npcOnly = true;
    foreach ($kill->attackers as $attacker) {
        $npcOnly &= $attacker->characterID == 0;
    }
    if ($npcOnly) return false;

    // Let's make sure this kill is valid.  It is API, but sometimes it can be wrong

    // Make sure the victim has a valid shipTypeID
    if ($kill->victim->shipTypeID == 0) {
        echo "$killID Invalid shipTypeID\n";
        return false;
    }

    return true;
}

function processKill(&$kill, $npcOnly, $number_involved, $totalCost)
{
    global $dbPrefix;

    $date = $kill->killTime;

    $date = strtotime($date);
    $week = date("W", $date);
    $year = date("Y", $date);
    $month = date("m", $date);
    $day = date("d", $date);
    $unix = date("U", $date);

    Db::execute("
		insert into {$dbPrefix}kills
			(killID, solarSystemID, killTime, moonID, year, month, week, day,
				unix_timestamp, npcOnly, number_involved, total_price, processed_timestamp)
		values
			(:killID, :solarSystemID, :killTime, :moonID, :year, :month, :week, :day,
				:unix_timestamp, :npcOnly, :number_involved, :total_price, :unix_timestamp)",
        (array(
            ":killID" => $kill->killID,
            ":solarSystemID" => $kill->solarSystemID,
            ":killTime" => $kill->killTime,
            ":moonID" => $kill->moonID,
            ":year" => $year,
            ":month" => $month,
            ":week" => $week,
            ":day" => $day,
            ":unix_timestamp" => $unix,
            ":npcOnly" => $npcOnly,
            ":number_involved" => $number_involved,
            ":total_price" => $totalCost,
        )));
    Memcached::set("LAST_KILLMAIL_PROCESSED", $unix);
}

function processVictim($killID, &$victim)
{
    global $dbPrefix;

    $shipPrice = Price::getItemPrice($victim->shipTypeID);

    Db::execute("
		insert into {$dbPrefix}participants
			(killID, isVictim, shipTypeID, shipPrice, damage, factionName, factionID, allianceName, allianceID,
			corporationName, corporationID, characterName, characterID)
		values
			(:killID, 'T', :shipTypeID, :shipPrice, :damageTaken, :factionName, :factionID, :allianceName, :allianceID,
			:corporationName, :corporationID, :characterName, :characterID)
		on duplicate key update shipPrice = :shipPrice",
        (array(
            ":killID" => $killID,
            ":shipTypeID" => $victim->shipTypeID,
            ":shipPrice" => $shipPrice,
            ":damageTaken" => $victim->damageTaken,
            ":factionName" => $victim->factionName,
            ":factionID" => $victim->factionID,
            ":allianceName" => $victim->allianceName,
            ":allianceID" => $victim->allianceID,
            ":corporationName" => $victim->corporationName,
            ":corporationID" => $victim->corporationID,
            ":characterName" => $victim->characterName,
            ":characterID" => $victim->characterID,
        )));

    if ($victim->characterID != 0) addName($victim->characterID, $victim->characterName, 1, 1, null);
    if ($victim->corporationID != 0) addName($victim->corporationID, $victim->corporationName, 1, 2, 2);
    if ($victim->allianceID != 0) addName($victim->allianceID, $victim->allianceName, 1, 32, 16159);

    /*queueUp(array(
         Queue::$KILLMAIL => $killID,
         Queue::$PILOT => $vicitm->characterID,
         Queue::$CORP => $victim->corporationID,
         Queue::$ALLI => $victim->allianceID,
         Queue::$FACTION => $victim->factionID,
         ));*/

    return $shipPrice;
}

function processAttacker(&$killID, &$attacker)
{
    global $dbPrefix;

    Db::execute("
		insert into {$dbPrefix}participants
			(killID, isVictim, characterID, characterName, corporationID, corporationName, allianceID, allianceName,
			 factionID, factionName, securityStatus, damage, finalBlow, weaponTypeID, shipTypeID)
		values
			(:killID, 'F', :characterID, :characterName, :corporationID, :corporationName, :allianceID, :allianceName,
			 :factionID, :factionName, :securityStatus, :damageDone, :finalBlow, :weaponTypeID, :shipTypeID)
		on duplicate key update damage = :damageDone",
        (array(
            ":killID" => $killID,
            ":characterID" => $attacker->characterID,
            ":characterName" => $attacker->characterName,
            ":corporationID" => $attacker->corporationID,
            ":corporationName" => $attacker->corporationName,
            ":allianceID" => $attacker->allianceID,
            ":allianceName" => $attacker->allianceName,
            ":factionID" => $attacker->factionID,
            ":factionName" => $attacker->factionName,
            ":securityStatus" => $attacker->securityStatus,
            ":damageDone" => $attacker->damageDone,
            ":finalBlow" => $attacker->finalBlow,
            ":weaponTypeID" => $attacker->weaponTypeID,
            ":shipTypeID" => $attacker->shipTypeID,
        )));

    if ($attacker->characterID != 0) addName($attacker->characterID, $attacker->characterName, 1, 1, 1);
    if ($attacker->corporationID != 0) addName($attacker->corporationID, $attacker->corporationName, 1, 2, 2);
    if ($attacker->allianceID != 0) addName($attacker->allianceID, $attacker->allianceName, 1, 32, 16159);

    /*queueUp(array(
         Queue::$PILOT => $attacker->characterID,
         Queue::$CORP => $attacker->corporationID,
         Queue::$ALLI => $attacker->allianceID,
         Queue::$FACTION => $attacker->attacker->factionID,
         ));*/
}

function processItem(&$killID, &$item, $itemInsertOrder)
{
    global $dbPrefix;

    $price = Price::getItemPrice($item->typeID);

    Db::execute("
		insert into {$dbPrefix}items
			(killID, typeID, flag, qtyDropped, qtyDestroyed, insertOrder, price)
		values
			(:killID, :typeID, :flag, :qtyDropped, :qtyDestroyed, :insertOrder, :price)
		on duplicate key update price = :price",
        (array(
            ":killID" => $killID,
            ":typeID" => $item->typeID,
            ":flag" => $item->flag,
            ":qtyDropped" => $item->qtyDropped,
            ":qtyDestroyed" => $item->qtyDestroyed,
            ":insertOrder" => $itemInsertOrder,
            ":price" => $price,
        )));

    return $price;
}

function queueUp($array)
{
    $types = array(Queue::$PILOT, Queue::$CORP, Queue::$ALLI, Queue::$FACTION, Queue::$KILLMAIL);
    foreach ($types as $type) {
        if (isset($array[$type]) && $array[$type] != 0) queueInsert($type, $array[$type]);
    }
}

function queueInsert($type, $type_id)
{
    global $dbPrefix;

    Db::execute("insert into {$dbPrefix}queue (type, type_id) values (:type, :type_id) on duplicate key update processed = 'N'",
        (array(
            ":type" => $type,
            ":type_id" => $type_id,
        )));
}

/*function addName($eve_id, $name, $catID, $groupID, $typeID) {
	global $prefix;

	$upperItemName = strtoupper($name);
	Db::execute("insert into eveNames (itemID, itemName, categoryID, groupID, typeID, upperItemName)
					values (:id, :name, :catID, :groupID, :typeID, :upperItemName)
				on duplicate key update itemName = :name",
				array(":id" => $eve_id, ":name" => $name, ":catID" => $catID,
					":groupID" => $groupID, ":typeID" => $typeID, ":upperItemName" => $upperItemName));
}*/
