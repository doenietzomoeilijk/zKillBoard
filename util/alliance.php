<?
// Command line execution?
$cle = "cli" == php_sapi_name();
if (!$cle) return; // Prevent web execution

$base = dirname(__FILE__);
require_once "$base/../init.php";
require_once "$base/pheal/config.php";
 
/**
 * Populates an alliance and corp table for easy reference.  (Not really needed but handy)
 *
 * @return void
 */
function populateAllianceList()
{
    global $dbPrefix;

    Log::irc("Repopulating alliance tables.");

    $allianceCount = 0;
    $corporationCount = 0;

    $pheal = new Pheal();
    $pheal->scope = "eve";
    $list = null;
    $exception = null;
    try {
        $list = $pheal->AllianceList();
    } catch (Exception $ex) {
        $exception = $ex;
    }
    if ($list != null && sizeof($list->alliances) > 0) {
        Db::execute("truncate {$dbPrefix}alliances");
        Db::execute("truncate {$dbPrefix}alliance_corporations");
        foreach ($list->alliances as $alliance) {
            $allianceCount++;
            $allianceID = $alliance['allianceID'];
            $shortName = $alliance['shortName'];
            $name = $alliance['name'];
            $executorCorpID = $alliance['executorCorpID'];
            $memberCount = $alliance['memberCount'];
            $parameters = array(":alliID" => $allianceID, ":shortName" => $shortName, ":name" => $name,
                                ":execID" => $executorCorpID, ":memberCount" => $memberCount);
            Db::execute("insert into {$dbPrefix}alliances (allianceID, shortName, name, executorCorpID, memberCount) values
												(:alliID, :shortName, :name, :execID, :memberCount) on duplicate key update memberCount = :memberCount", $parameters);
            Db::execute("update eveNames set ticker = :ticker where itemID = :itemID", array(":ticker" => $shortName, ":itemID" => $allianceID));
            foreach ($alliance->memberCorporations as $corporation) {
                $corporationCount++;
                $corporationID = $corporation['corporationID'];
                Info::getCorpName($corporationID, true);
                Db::execute("insert into {$dbPrefix}alliance_corporations (allianceID, corporationID) values (:alliID, :corpID)", array(":alliID" => $allianceID, ":corpID" => $corporationID));
                //Db::execute("update eveNames set ticker = :ticker where itemID = :itemID", array(":ticker" => null, ":itemID" => $corporationID));
            }
        }

        $allianceCount = number_format($allianceCount, 0);
        $corporationCount = number_format($corporationCount, 0);
        Log::irc("Alliance tables repopulated - $allianceCount Alliances with a total of $corporationCount Corporations");
    } else {
        Log::irc("Unable to pull Alliance XML from API.  Will try again later.");
        if ($exception != null) throw $exception;
        throw new Exception("Unable to pull Alliance XML from API.  Will try again later");
    }
}
