<?php
require(__DIR__.'/config/config.php');
if (!in_array(PHP_SAPI, $C["allowsapi"])) {
	exit("No permission");
}

require(__DIR__.'/function/curl.php');
require(__DIR__.'/function/log.php');
require(__DIR__.'/function/sendmessage.php');
require(__DIR__.'/function/getlist.php');

$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}input` ORDER BY `time` ASC");
$res = $sth->execute();
$row = $sth->fetchAll(PDO::FETCH_ASSOC);
foreach ($row as $data) {
	$sth = $G["db"]->prepare("DELETE FROM `{$C['DBTBprefix']}input` WHERE `hash` = :hash");
	$sth->bindValue(":hash", $data["hash"]);
	$res = $sth->execute();
}
function GetTmid() {
	global $C, $G;
	$res = cURL($C['FBAPI']."me/conversations?fields=participants,updated_time&access_token=".$C['FBpagetoken']);
	$updated_time = file_get_contents("data/updated_time.txt");
	$newesttime = $updated_time;
	while (true) {
		if ($res === false) {
			WriteLog("[follow][error][getuid]");
			break;
		}
		$res = json_decode($res, true);
		if (count($res["data"]) == 0) {
			break;
		}
		foreach ($res["data"] as $data) {
			if ($data["updated_time"] <= $updated_time) {
				break 2;
			}
			if ($data["updated_time"] > $newesttime) {
				$newesttime = $data["updated_time"];
			}
			foreach ($data["participants"]["data"] as $participants) {
				if ($participants["id"] != $C['FBpageid']) {
					$sth = $G["db"]->prepare("INSERT INTO `{$C['DBTBprefix']}user` (`uid`, `tmid`, `name`) VALUES (:uid, :tmid, :name)");
					$sth->bindValue(":uid", $participants["id"]);
					$sth->bindValue(":tmid", $data["id"]);
					$sth->bindValue(":name", $participants["name"]);
					$res = $sth->execute();
					break;
				}
			}
		}
		$res = cURL($res["paging"]["next"]);
	}
	file_put_contents("data/updated_time.txt", $newesttime);
}
foreach ($row as $data) {
	$input = json_decode($data["input"], true);
	foreach ($input['entry'] as $entry) {
		foreach ($entry['messaging'] as $messaging) {
			$mmid = "m_".$messaging['message']['mid'];
			$res = cURL($C['FBAPI'].$mmid."?fields=from&access_token=".$C['FBpagetoken']);
			$res = json_decode($res, true);
			$uid = $res["from"]["id"];

			$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}user` WHERE `uid` = :uid");
			$sth->bindValue(":uid", $uid);
			$sth->execute();
			$row = $sth->fetch(PDO::FETCH_ASSOC);
			if ($row === false) {
				GetTmid();
				$sth->execute();
				$row = $sth->fetch(PDO::FETCH_ASSOC);
				if ($row === false) {
					WriteLog("[follow][error][uid404] uid=".$uid);
					continue;
				} else {
					WriteLog("[follow][info][newuser] uid=".$uid);
				}
			}
			$tmid = $row["tmid"];
			if (!isset($messaging['message']['text'])) {
				SendMessage($tmid, $M["nottext"]);
				continue;
			}
			$msg = $messaging['message']['text'];
			if ($msg[0] !== "/") {
				SendMessage($tmid, $M["notcommand"]);
				continue;
			}
			$msg = str_replace("\n", " ", $msg);
			$msg = preg_replace("/\s+/", " ", $msg);
			$cmd = explode(" ", $msg);
			switch ($cmd[0]) {
				case '/add':
					if (!isset($cmd[1])) {
						$msg = $M["/add_arealist"]."\n\n".
							"可用的區域有：".implode("、", $D["arealist"])."\n\n".
							"範例： /add ".$D["arealist"][0];
						SendMessage($tmid, $msg);
						break;
					}
					$level = $C["AQI_over"];
					if (isset($cmd[2])) {
						if (!ctype_digit($cmd[2])) {
							SendMessage($tmid, $M["/add_level_notnum"]);
							break;
						}
						$level = (int)$cmd[2];
					}
					if (isset($cmd[3])) {
						SendMessage($tmid, $M["/add_too_many_arg"]);
						break;
					}
					if (in_array($cmd[1], $D["arealist"])) {
						SendMessage($tmid, $M["/add_citylist"]."\n\n可用的測站有：".implode("、", $D["citylist"][$cmd[1]])."\n\n範例: /add ".$D["citylist"][$cmd[1]][0]);
					} else if (isset($D["city"][$cmd[1]])) {
						$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}follow` WHERE `tmid` = :tmid AND `city` = :city LIMIT 1");
						$sth->bindValue(":tmid", $tmid);
						$sth->bindValue(":city", $cmd[1]);
						$res = $sth->execute();
						$row = $sth->fetch(PDO::FETCH_ASSOC);
						if ($row === false) {
							$sth = $G["db"]->prepare("INSERT INTO `{$C['DBTBprefix']}follow` (`tmid`, `city`, `level`) VALUES (:tmid, :city, :level)");
							$sth->bindValue(":tmid", $tmid);
							$sth->bindValue(":city", $cmd[1]);
							$sth->bindValue(":level", $level);
							$res = $sth->execute();
							WriteLog(json_encode($res));
							SendMessage($tmid, "已開始接收".$cmd[1]."測站的通知，當AQI超過".$level."時會通知");
						} else {
							$sth = $G["db"]->prepare("UPDATE `{$C['DBTBprefix']}follow` SET `level` = :level WHERE `tmid` = :tmid AND `city` = :city");
							$sth->bindValue(":tmid", $tmid);
							$sth->bindValue(":city", $cmd[1]);
							$sth->bindValue(":level", $level);
							$res = $sth->execute();
							SendMessage($tmid, "已經".$cmd[1]."測站的通知的門檻值改成".$level);
						}
					} else {
						$msg = $M["/add_notfound"]."\n".
							$M["/add_arealist"]."\n\n".
							"可用的區域有：".implode("、", $D["arealist"])."\n\n".
							"範例： /add ".$D["arealist"][0];
						SendMessage($tmid, $msg);
					}
					break;
				
				case '/del':
					$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}follow` WHERE `tmid` = :tmid");
					$sth->bindValue(":tmid", $tmid);
					$res = $sth->execute();
					$row = $sth->fetchAll(PDO::FETCH_ASSOC);
					$follow = array();
					foreach ($row as $temp) {
						$follow []= $temp["city"];
					}
					if (!isset($cmd[1])) {
						$msg = $M["/del"]."\n\n";
						if (count($follow) == 0) {
							$msg .= $M["/list_zero"];
						} else {
							$msg .= "接收的測站有：".implode("、", $follow)."\n\n".
								"範例： /del ".$follow[0];
						}
						SendMessage($tmid, $msg);
						break;
					}
					if (!isset($D["city"][$cmd[1]])) {
						SendMessage($tmid, "找不到".$cmd[1]."測站");
					} else if (in_array($cmd[1], $follow)) {
						$sth = $G["db"]->prepare("DELETE FROM `{$C['DBTBprefix']}follow` WHERE `tmid` = :tmid AND `city` = :city");
						$sth->bindValue(":tmid", $tmid);
						$sth->bindValue(":city", $cmd[1]);
						$res = $sth->execute();
						SendMessage($tmid, "已停止接收".$cmd[1]."測站的通知");
					} else {
						SendMessage($tmid, "並沒有接收".$cmd[1]."測站的通知");
					}
					break;

				case '/list':
					$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}follow` WHERE `tmid` = :tmid");
					$sth->bindValue(":tmid", $tmid);
					$res = $sth->execute();
					$row = $sth->fetchAll(PDO::FETCH_ASSOC);

					if (count($row) == 0) {
						SendMessage($tmid, $M["/list_zero"]);
					} else {
						$msg = $M["/list"]."\n";
						foreach ($row as $follow) {
							$msg .= $follow["city"]." ".$follow["level"]."\n";
						}
						$msg .= "\n".$M["/list_add_update"]."\n".
							"範例： /add ".$row[0]["city"]." ".($row[0]["level"]+10)."\n".
							$M["/del"]."\n".
							"範例： /del ".$row[0]["city"];
						SendMessage($tmid, $msg);
					}
					break;
				
				case '/show':
					$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}follow` WHERE `tmid` = :tmid");
					$sth->bindValue(":tmid", $tmid);
					$res = $sth->execute();
					$row = $sth->fetchAll(PDO::FETCH_ASSOC);
					if (count($row) == 0) {
						SendMessage($tmid, $M["/list_zero"]);
					} else {
						require(__DIR__.'/function/level.php');
						$msg = "";
						foreach ($row as $follow) {
							$msg .= $follow["city"]." AQI ".$D["city"][$follow["city"]]["AQI"]." ".AQIlevel($D["city"][$follow["city"]]["AQI"])."\n";
						}
						SendMessage($tmid, $msg);
					}
					break;
				
				case '/help':
					SendMessage($tmid, $M["/help"]);
					break;
				
				default:
					SendMessage($tmid, $M["wrongcommand"]);
					break;
			}
		}
	}
}
