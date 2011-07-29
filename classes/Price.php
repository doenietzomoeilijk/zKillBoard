<?php

class Price
{

    /**
     * Obtain the price of an item.
     *
     * @static
     * @param  $typeID int The typeID of the item
     * @return double The price of the item.
     */
    public static function getItemPrice($typeID)
    {
        if (in_array($typeID, array(588, 596, 601, 670, 606))) return 10000; // Pods and noobships
        if (in_array($typeID, array(25, 51, 29148))) return 1; // Corpses and Bookmarks

        $price = Price::getDatabasePrice($typeID);
        if ($price == 0) $price = Price::getMarketPrice($typeID);
        if ($price == 0) $price = Price::getFactionPrice($typeID);
        if ($price == 0) $price = Price::getItemBasePrice($typeID);
        if ($price == 0) $price = 0.01; // Give up

        return $price;
    }

    /**
     * @static
     * @param  $typeID int
     * @return double The price of the typeID, if found.  Zero if not found.
     */
    protected static function getDatabasePrice($typeID)
    {
        global $dbPrefix;

        $price = Db::queryField("select price from {$dbPrefix}prices where typeID = :typeID", "price",
                                array(":typeID" => $typeID));
        if ($price != null) return $price;
        return 0;
    }

    /**
     * @static
     * @param  $typeID int
     * @return null
     */
    protected static function getItemBasePrice($typeID)
    {
        // Market failed, faction pricing failed, do we have a basePrice in the database?
        $price = Db::queryField("select basePrice from invTypes where typeID = :typeID", "basePrice", array(":typeID" => $typeID));
        if ($price != null) return $price;
    }

    /**
     * Get the market price of the item in Jita.
     * Attempts to use the sell median price first, if that value isn't set it will use
     * the median of all values.
     *
     * @param int $typeID
     * @return double
     */
    protected static function getMarketPrice($typeID)
    {
        // Get the Jita price.
        $eveCentralApi = "http://api.eve-central.com/api/marketstat?usesystem=30000142&typeid=$typeID";
        $result = Memcached::get($eveCentralApi);
        if ($result === FALSE) {
            $result = Pheal::request_http_curl($eveCentralApi, array());
            Memcached::set($eveCentralApi, $result, 600);
        }
        $xml = new SimpleXMLElement($result);
        @$price = (double)$xml->marketstat->type->sell->median;
        if ($price == 0) @$price = (double)$xml->marketstat->type->all->median;
        if ($price != 0) {
            Price::storeItemPrice($typeID, $price);
            return $price;
        }
        return 0;
    }

    /**
     * Get the faction price, courtesy of http://prices.c0rporation.com
     *
     * @static
     * @param  $typeID
     * @return float|int
     */
    protected static function getFactionPrice($typeID)
    {
        $factionListing = Memcached::get("factionListing");
        if ($factionListing === FALSE) {
            $factionListing = Pheal::request_http_curl("http://prices.c0rporation.com/faction.xml", array());
            Memcached::set("factionListing", $factionListing, 3600 * 12); // Store for 12 hours
        }
        $factionXml = new SimpleXmlElement($factionListing);
        $xpath = $factionXml->xpath("result/rowset/row[@typeID=$typeID]");
        if (sizeof($xpath)) {
            $price = (float)$xpath[0]["median"];
            if ($price != 0) {
                Price::storeItemPrice($typeID, $price);
                return $price;
            }
        }

        return 0;
    }

    /**
     * Save the price in the database for future reference.
     * Price will expire after 7 days.
     *
     * @static
     * @param  $typeID
     * @param  $price
     * @return void
     */
    protected static function storeItemPrice($typeID, $price)
    {
        global $dbPrefix;

        // 604800 is the number of seconds in 7 days

        Db::execute("replace into {$dbPrefix}prices (typeID, price, expires) values (:typeID, :price, unix_timestamp() + 604800)",
                    array(
                         ":price" => $price,
                         ":typeID" => $typeID,
                    ));
    }

}