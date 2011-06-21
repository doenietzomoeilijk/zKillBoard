<?
class PostPage extends Page
{

    function getMenuOptions()
    {
        return array("Post" => "post");
    }

    function controllerMidPane()
    {
        global $dbPrefix, $subDomainEveID;
        buildQuery($this->context, true);

        $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : "";
        $api_key = isset($_POST['api_key']) ? $_POST['api_key'] : "";
        if ($user_id != "" && $api_key != "") {
            // Do some basic verification first
            if (strlen($api_key) != 64) $this->context['error'] = "Api Key must be 64 characters long... you failed to enter it properly.";
            if ((int)$user_id != $user_id) $this->context['error'] = "User ID must be a number, you failed to enter it properly.";

            if (isset($this->context['error'])) return;

            require_once dirname(__FILE__) . "/../util/pheal/config.php";
            $pheal = new Pheal($user_id, $api_key);
            try {
                $characters = $pheal->Characters();
            } catch (Exception $ex) {
                $this->context['error'] = "Attempting to validate your API Key gave us the following error:<br/>" . $ex->getMessage();
                return;
            }

            // Ensure there is at least one character on this API Key
            if (sizeof($characters->characters) == 0) {
                $this->context['error'] = "This API Key has no Characters!";
                return;
            }

            // OK, so we know it's a valid API at this point, but is it a FULL API?
            // Attempt to pull a character kill log
            $characterID = $characters->characters[0]->characterID;
            $pheal->scope = 'char';
            try {
                $pheal->Killlog(array("characterID" => $characterID));
            } catch (Exception $ex) {
                $this->context['error'] = "Attempting to verify the key you provided is a FULL API Key failed with the error message:<br/>" . $ex->getMessage();
                return;
            }

            // It's a Full API! Woot!  Now add it to the database
            Db::execute("insert into {$dbPrefix}api (user_id, api_key) values (:user_id, :api_key) on duplicate key update api_key = :api_key, insert_dttm = now(), error_code = 0",
                        array(":user_id" => $user_id, ":api_key" => $api_key));

            // Populate the characters api table
            require_once dirname(__FILE__) . "/../util/apipull.php";
            doPopulateCharactersTable($user_id);

            $this->context['api_message'] = "Your API has been successfully entered, thank you!<br/>Kills will be pulling from it soon.";
            Log::irc("Someone just added a new API.  Killmails should be processing Soon(TM).");
        }

        if ($subDomainEveID == 0) {
            $this->context['corpCount'] = Db::queryField("select count(distinct corporationID) count from {$dbPrefix}api_characters where isDirector = 'T'", "count");
            $this->context['charCount'] = Db::queryField("select count(*) count from {$dbPrefix}api_characters", "count");
        } else {
            $pageType = isset($this->context['subDomainPageType']) ? $this->context['subDomainPageType'] : null;
            switch ($pageType) {
                case 'alli':
                    $this->context['alliCorpList'] = Db::query("select corps.corporationID corpsID, chars.corporationID charsID, isDirector from zz_alliance_corporations corps left join zz_api_characters chars on (corps.corporationID = chars.corporationID and chars.isDirector = 'T') where  allianceID = :alliID group by 1,2", array(":alliID" => $subDomainEveID));
                    break;
                case 'corp':
                    $this->context['directorCount'] = Db::queryField("select count(characterID) count from {$dbPrefix}api_characters where isDirector = 'T' and corporationID = :corpID",
                                                                     "count", array(":corpID" => $subDomainEveID));
                    break;
            }
        }
    }

    function viewMidPane($xml)
    {
        global $subDomainEveID;

        $context = $this->context;
        if (isset($context['api_message'])) {
            $message = $context['api_message'];
            echo "<span class='notification largeCorner'>$message</span>";
        }

        if (isset($context['error'])) {
            $error = $context['error'];
            echo "<span class='error largeCorner'>$error</span>";
        }

        echo "<span class='infoSection'>";
        echo "This killboard only accepts kills via API.  If your API hasn't been added then we won't know a darn thing about your kills.";
        echo "</span>";

        echo "<span class='postArea smallCorner infoSection'>";
        echo "<form method='post' action=''>"; // Posts to current page
        echo "<span class='infoRequestSpan'>User ID:</span><span class='inputSpan'><input type='text' name='user_id' maxlength='8' size='8'/></span><br/>\n";
        echo "<span class='infoRequestSpan'>API Key:</span><span class='inputSpan'><input type='text' name='api_key' maxlength='64' size='64'/></span><br/>\n";
        echo "<span class='infoRequestSpan'>&nbsp;</span><span class='inputSpan'><input type='submit' value='Add API' /></span><br/>\n";
        echo "<br/><br/>You can retrieve your API <a target='_blank' href='https://www.eveonline.com/api/default.asp'>here</a>.\n";
        echo "</form>";
        echo "</span>";

        echo "<br/><br/>";

        if ($subDomainEveID == 0) {
            $corpCount = $context['corpCount'];
            $charCount = $context['charCount'];
            echo "<span class='infoSection'>";
            echo "We currently have API keys for $corpCount Corporations and $charCount characters.";
            echo "</span>";
        } else {
            $pageType = isset($context['subDomainPageType']) ? $context['subDomainPageType'] : null;
            echo "<span class='infoSection'>";
            switch ($pageType) {
                case 'corp':
                    $directorCount = $context['directorCount'];
                    $plural = $directorCount == 1 ? "" : "s";
                    if ($context['directorCount'] == 0)
                        echo "We do not have an API key on record for ";
                    else
                        echo "We have $directorCount API key$plural for <br/>";
                    $corpName = Info::getEveName($subDomainEveID);
                    echo "<span class='iconWithName'>";
                    eveImageLink($subDomainEveID, "corp", $corpName);
                    kblink("corp", $corpName);
                    echo "</span>";
                    break;
                case 'alli':
                    $alliCorpList = $context['alliCorpList'];
                    $haveKeys = array();
                    $noKeys = array();
                    foreach ($alliCorpList as $corp) {
                        $corpID = $corp['corpsID'];
                        $isDirector = $corp['isDirector'] == 'T';
                        $name = Info::getCorpName($corpID, true);
                        if ($isDirector) $haveKeys[$name] = $corpID;
                        else $noKeys[$name] = $corpID;
                    }
                    ksort($haveKeys);
                    ksort($noKeys);
                    if (sizeof($haveKeys)) echo "The following corps have provided API keys:<br/><br/>";
                    foreach ($haveKeys as $corpName => $corpID) {
                        echo "<span class='iconWithName'>";
                        eveImageLink($corpID, "corp", $corpName);
                        kblink("corp", $corpName);
                        echo "</span>";
                    }
                    if (sizeof($noKeys)) {
                        echo "<span class='error'>";
                        echo "The following corps have NOT provided API keys:<br/><br/>";
                        foreach ($noKeys as $corpName => $corpID) {
                            echo "<span class='iconWithName'>";
                            eveImageLink($corpID, "corp", $corpName);
                            kblink("corp", $corpName);
                            echo "</span>";
                        }
                        echo "</span>";
                    }
                    break;
            }
            echo "</span>";
        }

    }

}
