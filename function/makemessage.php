<?php
function TimediffFormat($time) {
	if ($time<60) return $time."秒";
	if ($time<60*50) return round($time/60)."分";
	if ($time<60*60*23.5) return round($time/(60*60))."小時";
	return round($time/(60*60*24))."天";
}
function MakeMessage($force=false, $tmid=null) {
	global $C, $D, $G;
	if (isset($tmid)) {
		$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}follow` WHERE `tmid` = :tmid");
		$sth->bindValue(":tmid", $tmid);
	} else {
		$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}follow`");
	}
	$sth->execute();
	$follows = $sth->fetchAll(PDO::FETCH_ASSOC);
	if (count($follows) == 0) {
		return false;
	}
	foreach ($follows as $follow) {
		if (!$force) {
			if ($D["city"][$follow["city"]]["AQI"] < $follow["level"] && $follow["lastAQI"] < $follow["level"]) {
				continue;
			}
			if (abs($D["city"][$follow["city"]]["AQI"]-$follow["lastAQI"]) < $follow["diff"] &&
					$follow["lastAQI"] >= $follow["level"] &&
					$D["city"][$follow["city"]]["AQI"] >= $follow["level"]) {
				continue;
			}
		}
		$msg = $follow["city"]." 距".TimediffFormat(time()-strtotime($follow["lastmsg"]))."前AQI";
		$diff = $D["city"][$follow["city"]]["AQI"]-$follow["lastAQI"];
		if ($diff > 0) {
			$msg .= "已上升".$diff."達到 ".$D["city"][$follow["city"]]["AQI"];
		} else if ($diff < 0) {
			$msg .= "已下降".(-$diff)."達到 ".$D["city"][$follow["city"]]["AQI"];
		} else {
			$msg .= "仍然維持 ".$D["city"][$follow["city"]]["AQI"];
		}
		$msg .= "\n指標為".AQIlevel($D["city"][$follow["city"]]["AQI"]);
		if ($D["city"][$follow["city"]]["AQI"] < $follow["level"]) {
			$msg .= "\n已低於門檻值，暫停通知直到超過門檻值";
		}
		if ($follow["lastAQI"] < $follow["level"] && $D["city"][$follow["city"]]["AQI"] >= $follow["level"]) {
			$msg .= "\n已超過門檻值，繼續通知";
		}
		$sth = $G["db"]->prepare("UPDATE `{$C['DBTBprefix']}follow` SET `lastAQI`=:lastAQI, `lastmsg`=:lastmsg WHERE `tmid` = :tmid AND `city` = :city");
		$sth->bindValue(":lastAQI", $D["city"][$follow["city"]]["AQI"]);
		$sth->bindValue(":lastmsg", date("Y-m-d H:i:s"));
		$sth->bindValue(":tmid", $follow["tmid"]);
		$sth->bindValue(":city", $follow["city"]);
		$sth->execute();
		SendMessage($follow["tmid"], $msg);
	}
	return true;
}
