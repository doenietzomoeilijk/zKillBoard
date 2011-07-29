<?php

function clearExpiredPrices() {
    global $dbPrefix;
    Db::execute("delete from {$dbPrefix}prices where expires < unix_timestamp()");
    Log::irc("Expired prices test complete");
}