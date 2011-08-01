<?php

class RelatedPage extends SearchPage
{
    protected $hostiles = array();
    protected $friendlies = array();

    function controllerMidPane()
    {
        global $context;
        parent::controllerMidPane();
        getQuery($context, false);

        $kills = isset($this->context['display_kills']) ? $this->context['display_kills'] : array();
        $losses = isset($this->context['display_losses']) ? $this->context['display_losses'] : array();

        $this->hashPilots($kills, $this->hostiles, $this->friendlies);
        $this->hashPilots($losses, $this->friendlies, $this->hostiles);
    }

    function viewMidPane($xml)
    {
        global $subDomainEveID, $p;

        $useFriendlyTerm = $subDomainEveID != 0 || (in_array("with", $p) || in_array("against", $p));

        echo "<span class='typeHeader'>Involved Pilots</span>";
        echo "<span class='relatedOverview'>";
        $this->listArray($useFriendlyTerm ? "Hostiles" : "Victims", $this->hostiles, false);
        $this->listArray($useFriendlyTerm ? "Friendlies" : "Aggressors", $this->friendlies, true);
        echo "</span>";

        parent::viewMidPane($xml);
    }

    protected function listArray($title, &$array, $isWith)
    {
        uksort($array, "shipGroupSort");

        $shipsDestroyed = array();
        $totalShips = array();
        foreach ($array as $killInfo) {
            //$kill = $killInfo['kill'];
            $pilot = $killInfo['pilot'];
            $characterID = $pilot['characterID'];
            $shipTypeID = $pilot['shipTypeID'];
            if (!isset($shipsDestroyed["$shipTypeID"])) $shipsDestroyed["$shipTypeID"] = array();
            if (!isset($totalShips["$shipTypeID"])) $totalShips["$shipTypeID"] = array();

            if (!isset($shipsDestroyed["$shipTypeID"]["$characterID"])) $shipsDestroyed["$shipTypeID"]["$characterID"] = array();
            if (!isset($totalShips["$shipTypeID"]["$characterID"])) $totalShips["$shipTypeID"]["$characterID"] = array();

            if ($pilot['isVictim'] == "T") $shipsDestroyed["$shipTypeID"]["$characterID"][] = $killInfo;
            $totalShips["$shipTypeID"]["$characterID"][] = $killInfo;
        }

        $podCount = $this->getShipIDCount($shipsDestroyed, 670);
        $shipsDestroyedCount = $this->getShipIDCount($shipsDestroyed);
        $totalShipsCount = $this->getShipIDCount($totalShips);
        $totalShipsCount = max($totalShipsCount, $podCount);
        $pods = $podCount == 1 ? "Pod" : "Pods";
        $wrecks = $shipsDestroyedCount == 1 ? "Wreck" : "Wrecks";

        $podCount = number_format($podCount, 0);
        $shipsDestroyedCount = number_format($shipsDestroyedCount, 0);
        $totalShipsCount = number_format($totalShipsCount, 0);

        $hasDisplayedPilot = array();
        echo "<span class='relatedSection smallCorner'><span class='relatedTitle'>$title</span><br/>";
        echo "$podCount $pods / $shipsDestroyedCount $wrecks / $totalShipsCount Involved<br/><br/>";
        foreach ($array as $killInfo) {
            $pilot = $killInfo['pilot'];
            $shipTypeID = $pilot['shipTypeID'];
            $characterID = $pilot['characterID'];
            $corpID = $pilot['corporationID'];
            $shipClass = "";
            $relatedBorder = "";
            $pilotPodded = "";

            if ($shipTypeID == 670 && isset($hasDisplayedPilot["$characterID"])) continue; // Ignore pods

            $hasDisplayedPilot["$characterID"] = true;

            $shipDestroyed = $pilot['isVictim'] == 'T';
            if ($shipDestroyed) {
                $shipClass = "destroyedImage";
                $relatedBorder = "relatedDestroyed";
            }
            if (@$this->pilotPodded["$characterID"] == true) {
                $pilotPodded = "destroyedImage";
            }

            echo "<span class='related smallCorner $relatedBorder'>";
            eveImageLink($shipTypeID, "ship", Info::getItemName($shipTypeID), true, 64, $isWith, $shipClass);
            eveImageLink($characterID, "pilot", Info::getEveName($characterID), true, 64, $isWith, $pilotPodded);
            eveImageLink($corpID, "corp", Info::getEveName($corpID), true, 64, $isWith);
            echo "</span>";
        }
        echo "</span>";
    }

    protected function getShipIDCount(&$mappedArray, $inspectShipTypeID = null)
    {
        $count = 0;
        foreach ($mappedArray as $shipTypeID => $pilots) {
            if ($inspectShipTypeID == null || $inspectShipTypeID == $shipTypeID) {
                if ($shipTypeID != 670) {
                    foreach ($pilots as $pilot) {
                        $count += sizeof($pilot);
                    }
                } else if ($shipTypeID == 670 && $inspectShipTypeID == 670) {
                    foreach ($pilots as $pilot) {
                        $count += sizeof($pilot);
                    }
                }
            }
        }
        return $count;
    }

    protected function hashPilots($kills, &$hostiles, &$friendlies)
    {
        global $dbPrefix, $context;

        asort($kills);

        foreach ($kills as $killID => $kill) {
            $detail = $kill['detail'];
            $victim = $kill['victim'];
            $this->addRelatedPilot($hostiles, $kill, $victim);

            if (isset($detail['involved']) && $detail['involved'] == 1) {
                $attacker = $kill['attacker'];
                $this->addRelatedPilot($friendlies, $kill, $attacker);
            } else {
                $year = $context['searchYear'];
                $month = $context['searchMonth'];
                $month = strlen("$month") < 2 ? "0$month" : $month;
                $attackers = Db::query("select * from {$dbPrefix}participants_{$year}_{$month} where killID = :killID and isVictim = 'F'",
                                       array(":killID" => $killID), 3600);
                foreach ($attackers as $attacker) {
                    $this->addRelatedPilot($friendlies, $kill, $attacker);
                }
            }
        }
    }

    protected $pilotPodded = array();

    protected function addRelatedPilot(&$array, &$kill, &$pilot)
    {
        $characterID = $pilot['characterID'];
        $corporationID = $pilot['corporationID'];
        $shipTypeID = $pilot['shipTypeID'];

        if ($characterID == 0) return;

        if ($shipTypeID == 670) {
            $this->pilotPodded["$characterID"] = true;
        }

        $hash = "$shipTypeID|$corporationID|$characterID";
        $array[$hash] = array("kill" => $kill, "pilot" => $pilot);
    }

}

