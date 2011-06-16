<?php

require_once "init.php";
require_once "util/pheal/config.php";

//echo getItemPrice(3898, true);
//die();

$noPrices = Db::query("select killID, typeID from zz_items where price = 0 order by 1, 2", array(), 0);
foreach($noPrices as $noPrice) {
	$typeID = $noPrice['typeID'];
	$killID = $noPrice['killID'];

	$price = getItemPrice($typeID, true);
	Db::execute("update zz_items set price = $price where typeID = $typeID and killID = $killID");
	$sum = resumKill($killID);
	echo "$killID $typeID $sum\n";
}

function resumKill($killID) {
	$shipPrice = Db::queryField("select sum(shipPrice) sum from zz_participants where killID = $killID", "sum");
	$items = Db::queryField("select sum((qtyDestroyed * price) + (qtyDropped * price)) sum from zz_items where killid = $killID", "sum");
	$sum = $shipPrice + $items;
	Db::execute("update zz_kills set total_price = $sum where killID = $killID");
	return $sum;
}
