<?php
require(__DIR__.'/config/config.php');
if (!in_array(PHP_SAPI, $C["allowsapi"])) {
	exit("No permission");
}

require(__DIR__.'/function/log.php');
require(__DIR__.'/function/curl.php');
require(__DIR__.'/function/sendmessage.php');

$start = microtime(true);
$time = date("Y-m-d H:i:s", time()-$C['RemoveOld']);
echo "remove before ".$time."\n";

$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}city` WHERE `time` < :time");
$sth->bindValue(":time", $time);
$res = $sth->execute();
$citylist = $sth->fetchAll(PDO::FETCH_ASSOC);

foreach ($citylist as $city) {
	echo $city["name"]."\n";
	$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}follow` WHERE `city` = :city");
	$sth->bindValue(":city", $city["name"]);
	$res = $sth->execute();
	$followlist = $sth->fetchAll(PDO::FETCH_ASSOC);

	$msg = "測站 ".$city["name"]." 因為過久無資料，已從我們的資料庫移除，同時也自動取消您對於這筆測站的通知接收";

	foreach ($followlist as $follow) {
		SendMessage($follow["tmid"], $msg);
	}

	$sth = $G["db"]->prepare("DELETE FROM `{$C['DBTBprefix']}city` WHERE `name` = :city");
	$sth->bindValue(":city", $city["name"]);
	$res = $sth->execute();

	$sth = $G["db"]->prepare("DELETE FROM `{$C['DBTBprefix']}follow` WHERE `city` = :city");
	$sth->bindValue(":city", $city["name"]);
	$res = $sth->execute();
}

WriteLog("[fetch][info] runtime=".round((microtime(true)-$start), 6));
