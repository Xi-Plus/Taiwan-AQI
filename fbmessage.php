<?php
require(__DIR__.'/config/config.php');
if (!in_array(PHP_SAPI, $C["allowsapi"])) {
	exit("No permission");
}

require(__DIR__.'/function/log.php');
require(__DIR__.'/function/curl.php');
require(__DIR__.'/function/sendmessage.php');
require(__DIR__.'/function/level.php');
require(__DIR__.'/function/getlist.php');

$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}follow`");
$sth->execute();
$follows = $sth->fetchAll(PDO::FETCH_ASSOC);
$msgs = array();
foreach ($follows as $follow) {
	if (!isset($msgs[$follow["tmid"]])) {
		$msgs[$follow["tmid"]] = "";
	}
	if ($D["city"][$follow["city"]]["AQI"] > $follow["level"]) {
		$msgs[$follow["tmid"]] .= $follow["city"]." AQI ".$D["city"][$follow["city"]]["AQI"]." ".AQIlevel($D["city"][$follow["city"]]["AQI"])."\n";
	}
}
foreach ($msgs as $tmid => $msg) {
	if ($msg != "") {
		SendMessage($tmid, $msg);
	}
}
