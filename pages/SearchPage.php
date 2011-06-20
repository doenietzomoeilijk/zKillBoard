<?
class SearchPage extends Page
{
    protected $isVictim = false;

    function initialize($p, &$context)
    {
        parent::initialize($p, $context);
        buildQuery($this->context, true);
        $this->isVictim = in_array("losses", $p);
    }

    function getMenuOptions()
    {
        return array(
            "Kills" => "kills",
            "Losses" => "losses",
        );
    }

    function controllerLeftPane()
    {
        $limit = 5;
        $this->context['top_corps'] = topDogs($this->context, "corp", $this->isVictim, $limit);
        $this->context['top_allis'] = topDogs($this->context, "alli", $this->isVictim, $limit);
    }

    function controllerRightPane()
    {
        global $dbPrefix;

        $year = isset($this->context['searchYear']) ? $this->context['searchYear'] : date("Y");
        $month = isset($this->context['searchMonth']) ? $this->context['searchMonth'] : date("n");
        $day = isset($this->context['searchDay']) ? $this->context['searchDay'] : null;

        if ($day == null) {
            $nextMonthTime = mktime(0, 0, 0, $month + 1, 1, $year, 0);
            $nextMonth = Db::queryRow("select year, month from {$dbPrefix}kills where unix_timestamp >= :time order by unix_timestamp limit 1",
                                      array(":time" => $nextMonthTime), 3600);

            $prevMonthTime = mktime(0, 0, 0, $month, 1, $year, 0);
            $prevMonth = Db::queryRow("select year, month from {$dbPrefix}kills where unix_timestamp < :time order by unix_timestamp desc limit 1",
                                      array(":time" => $prevMonthTime), 3600);
            $this->context['nextMonth'] = $nextMonth;
            $this->context['prevMonth'] = $prevMonth;
        } else {
            $today = mktime(0, 0, 0, $month, $day, $year, 0);
            $prevDay = Db::queryRow("select year, month, day from {$dbPrefix}kills where unix_timestamp < :time order by unix_timestamp desc limit 1",
                                    array(":time" => $today), 3600);
            $nextDay = Db::queryRow("select year, month, day from {$dbPrefix}kills where unix_timestamp >= :time order by unix_timestamp limit 1",
                                    array(":time" => $today + 86400), 3600);
            if ($prevDay) $this->context['prevDay'] = $prevDay;
            if ($nextDay) $this->context['nextDay'] = $nextDay;
        }


        $limit = 5;
        $this->context['top_pilots'] = topDogs($this->context, "pilot", $this->isVictim, $limit);
        $this->context['top_ships'] = topDogs($this->context, "ship", $this->isVictim, $limit);
    }

    function controllerMidPane()
    {
        global $subDomainEveID;

        $loadKills = !$this->isVictim;
        $loadLosses = !in_array("kills", $this->p) && ($subDomainEveID != 0 || (in_array("with", $this->p) || in_array("against", $this->p) || in_array("losses", $this->p)));
        $limit = $loadKills && $loadLosses ? 15 : 30;

        if ($loadKills) $this->context['display_kills'] = getKills($this->context, false, null, $limit);
        if ($loadLosses) $this->context['display_losses'] = getKills($this->context, true, null, $limit);
        if (!($loadKills && $loadLosses)) {
            if (isset($this->context['display_kills']) && sizeof($this->context['display_kills']) > 2) calculateFirstAndPrevious($this->context['display_kills'], $this->context);
            else if (isset($this->context['display_losses']) && sizeof($this->context['display_losses']) > 2) calculateFirstAndPrevious($this->context['display_losses'], $this->context);
        }
        $this->context['stats'] = getStatistics($this->context, $this->context['subDomainPageType'], $subDomainEveID);
    }


    function viewLeftPane($xml)
    {
        parent::viewLeftPane($xml);

        echo "<span class='smallCorner menuSpan'><span class='title'>Filters</span><span>";
        $filters = $this->context['SearchParameters'];
        if (sizeof($filters)) {
            asort($filters);
            echo implode("<br/>", $filters);
        } else if ($this->context['pageType'] == "killmail") {
            echo "Killmail";
        }
        echo "</span></span>";

        echo "<span class='leftRightSpacer'/>";
        if (isset($this->context['top_value_kills'])) displayTopDogs("Big Isk", "pilot", "killID", $this->context["total_price"]);
        if (isset($this->context["top_allis"])) displayTopDogs("Top<br/>Alliances", "alli", "allianceID", $this->context["top_allis"]);
        if (isset($this->context["top_corps"])) displayTopDogs("Top<br/>Corporations", "corp", "corporationID", $this->context["top_corps"]);

    }

    function viewRightPane($xml)
    {
        echo "<span class='smallCorner menuSpan'><span class='title'>Navigation</span><span>";

        if (isset($this->context['nextMonth'])) displayTimeUrl($this->context['nextMonth']);
        if (isset($this->context['prevMonth'])) displayTimeUrl($this->context['prevMonth']);
        if (isset($this->context['prevDay'])) displayTimeUrl($this->context['prevDay']);
        if (isset($this->context['nextDay'])) displayTimeUrl($this->context['nextDay']);
        echo "</span></span>";

        echo "<span class='leftRightSpacer'/>";
        if (isset($this->context["top_pilots"])) displayTopDogs("Top<br/>Pilots", "pilot", "characterID", $this->context["top_pilots"]);
        if (isset($this->context["top_ships"])) displayTopDogs("Top<br/>Ships", "ship", "shipTypeID", $this->context["top_ships"]);
    }

    function viewMidPane($xml)
    {
        /*if (isset($this->context['stats'])) {
            echo "<span>";
            print_r($this->context['stats']);
            echo "</span>";
        }*/
        $afterKillID = isset($this->context['display_after']) ? $this->context['display_after'] : null;
        $beforeKillID = isset($this->context['display_before']) ? $this->context['display_before'] : null;
        if (isset($this->context['display_kills'])) {
            showKills($this->context['display_kills'], "Kills", "", $afterKillID, $beforeKillID);
        }
        if (isset($this->context['display_losses'])) {
            showKills($this->context['display_losses'], "Losses", "", $afterKillID, $beforeKillID);
        }

    }
}
