<?php

class Info
{

    /**
     * Retrieve the system id of a solar system.
     *
     * @static
     * @param  $systemName
     * @return int The solarSystemID
     */
    public static function getSystemID($systemName)
    {
        return Db::queryField("select solarSystemID from mapSolarSystems where upperSolarSystemName = :name", "solarSystemID",
                              array(":name" => strtoupper($systemName)), 86400);
    }

    /**
     * @static
     * @param  $systemID
     * @return array Returns an array containing the solarSystemName and security of a solarSystemID
     */
    public static function getSystemInfo($systemID)
    {
        return Db::queryRow("select solarSystemName, security from mapSolarSystems where solarSystemID = :systemID", array(":systemID" => $systemID), 86400);
    }

    /**
     * @static
     * @param  $systemID
     * @return string The system name of a solarSystemID
     */
    public static function getSystemName($systemID)
    {
        $systemInfo = Info::getSystemInfo($systemID);
        return $systemInfo['solarSystemName'];
    }

    /**
     * @static
     * @param  int $systemID
     * @return double The system secruity of a solarSystemID
     */
    public static function getSystemSecurity($systemID)
    {
        $systemInfo = Info::getSystemInfo($systemID);
        return $systemInfo['security'];
    }

    /**
     * @static
     * @param  $typeID
     * @return string The item name.
     */
    public static function getItemName($typeID)
    {
        return Db::queryField("select typeName from invTypes where typeID = :typeID", "typeName", array(":typeID" => $typeID), 86400);
    }

    /**
     * @param  $itemName
     * @return int The typeID of an item.
     */
    public static function getItemID($itemName)
    {
        return Db::queryField("select typeID from invTypes where upper(typeName) = :typeName", "typeID", array(":typeName" => strtoupper($itemName)), 86400);
    }

    /**
     * Retrieves the effectID of an item.  This is useful for determining if an item is fitted into a low,
     * medium, high, rig, or t3 slot.
     *
     * @param  $typeID
     * @return int The effectID of an item.
     */
    public static function getEffectID($typeID)
    {
        return Db::queryField("select effectID from dgmTypeEffects where typeID = :typeID and effectID in (11, 12, 13, 2663, 3772)", "effectID", array(":typeID" => $typeID), 86400);
    }

    /**
     * Attempt to find the name of a corporation in the eveNames table.  If not found the
     * and $fetchIfNotFound is true, it will then attempt to pull the name via an API lookup.
     *
     * @static
     * @param  $id
     * @param bool $fetchIfNotFound
     * @return string The name of the corp if found, null otherwise.
     */
    public static function getCorpName($id, $fetchIfNotFound = false)
    {
        $name = Db::queryField("select itemName from eveNames where itemID = :id", "itemName", array(":id" => $id), $fetchIfNotFound
                                                                                         ? 0 : 86400);
        if ($name != null || $fetchIfNotFound == false) return $name;

        $pheal = new Pheal();
        $pheal->scope = "corp";
        $corpInfo = $pheal->CorporationSheet(array("corporationID" => $id));
        $name = $corpInfo->corporationName;
        addName($id, $name, 1, 2, 2);
        return $name;
    }

    /**
     * Retrieve the itemID associated to a name in the eveNames table.
     *
     * @static
     * @param  $name
     * @return int The itemID of the eveName if found, null otherwise.
     */
    public static function getEveId($name)
    {
        $name = Db::queryField("select itemID from eveNames where upperItemName = :name", "itemID", array(":name" => strtoupper($name)), 86400);
        return $name;
    }

    public static function getEveIdFromTicker($ticker)
    {
        $eveID = Db::queryField("select itemID from eveNames where ticker = :name", "itemID", array(":name" => strtoupper($ticker)), 86400);
        if ($eveID == null) return Info::getEveID($ticker);
        return $eveID;
    }

    /**
     * Retrieve a name from the eveNames table.
     *
     * @static
     * @param  $id
     * @return string The name in the table if found, null otherwise.
     */
    public static function getEveName($id)
    {
        $name = Db::queryField("select itemName from eveNames where itemID = :id", "itemName", array(":id" => $id), 86400);
        return $name;
    }

    /**
     * Get the name of the group
     *
     * @static
     * @param int $groupID
     * @return string
     */
    public static function getGroupName($groupID)
    {
        $name = Db::queryField("select groupName from invGroups where groupID = :id", "groupName", array(":id" => $groupID), 86400);
        return $name;
    }
}