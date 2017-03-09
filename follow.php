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
			if (isset($messaging['message']['attachments']) && $messaging['message']['attachments'][0]['type'] == "location") {
				$lat = $messaging['message']['attachments'][0]['payload']['coordinates']['lat'];
				$long = $messaging['message']['attachments'][0]['payload']['coordinates']['long'];
				$msg = "您的座標是".round(abs($long), 3)."°".($long>=0?"E":"W")." ".round(abs($lat), 3)."°".($lat>=0?"N":"S")."\n";
				$closest = array("length"=>1e100, "lat"=>0, "long"=>0, "city"=>"");
				require(__DIR__.'/function/gpsdist.php');
				foreach ($D["city"] as $name => $city) {
					$D["city"][$name]["dist"] = gpsdist($lat, $long, $city["lat"], $city["long"]);
					$D["city"][$name]["angle"] = (anglei($lat, $long, $city["lat"], $city["long"])+anglef($lat, $long, $city["lat"], $city["long"]))/2;
				}
				function cmp($a, $b) {
					return ($a["dist"] < $b["dist"]) ? -1 : 1;
				}
				$msg .= "離您最近的測站有\n";
				uasort($D["city"], 'cmp');
				WriteLog(json_encode($D["city"]));
				$city = reset($D["city"]);
				for ($i=0; $i < $C["GPSlist"]; $i++) {
					$msg .= $city["name"]." ";
					if ($city["angle"]>157.5) {
						$msg .= "⬇";
					} else if ($city["angle"]>112.5) {
						$msg .= "↘";
					} else if ($city["angle"]>67.5) {
						$msg .= "➡";
					} else if ($city["angle"]>22.5) {
						$msg .= "↗";
					} else if ($city["angle"]>-22.5) {
						$msg .= "⬆";
					} else if ($city["angle"]>-67.5) {
						$msg .= "↖";
					} else if ($city["angle"]>-112.5) {
						$msg .= "⬅";
					} else if ($city["angle"]>-157.5) {
						$msg .= "↙";
					} else {
						$msg .= "⬇";
					}
					if ($city["angle"]>135) {
						$msg .= "南偏東".round(180-$city["angle"], 0)."° ";
					} else if ($city["angle"]>90) {
						$msg .= "東偏南".round($city["angle"]-90, 0)."° ";
					} else if ($city["angle"]>45) {
						$msg .= "東偏北".round(90-$city["angle"], 0)."° ";
					} else if ($city["angle"]>0) {
						$msg .= "北偏東".round($city["angle"], 0)."° ";
					} else if ($city["angle"]>-45) {
						$msg .= "北偏西".round(-$city["angle"], 0)."° ";
					} else if ($city["angle"]>-90) {
						$msg .= "西偏北".round($city["angle"]+90, 0)."° ";
					} else if ($city["angle"]>-135) {
						$msg .= "西偏南".round(-90-$city["angle"], 0)."° ";
					} else {
						$msg .= "南偏西".round($city["angle"]+180, 0)."° ";
					}
					if ($city["dist"] < 1000) {
						$msg .= round($city["dist"], 0)."m\n";
					} else if ($city["dist"] < 10000) {
						$msg .= round($city["dist"]/1000, 1)."km\n";
					} else {
						$msg .= round($city["dist"]/1000, 0)."km\n";
					}
					$msg .= "    ".round($city["long"], 3)."°E ".round($city["lat"], 3)."°N\n";
					$city = next($D["city"]);
				}
				$city = reset($D["city"]);
				$msg .= "\n輸入 /add ".$city["name"]." 以接收此測站通知";
				SendMessage($tmid, $msg);
				continue;
			}
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
					$city = $cmd[1];
					$level = $C["AQIover"];
					if (isset($cmd[2])) {
						if (!ctype_digit($cmd[2])) {
							SendMessage($tmid, $M["/add_level_notnum"]);
							break;
						}
						$level = (int)$cmd[2];
					}
					$diff = $C["AQIdiff"];
					if (isset($cmd[3])) {
						if (!ctype_digit($cmd[3])) {
							SendMessage($tmid, $M["/add_diff_notnum"]);
							break;
						}
						$diff = (int)$cmd[3];
					}
					if (isset($cmd[4])) {
						SendMessage($tmid, $M["/add_too_many_arg"]);
						break;
					}
					if (in_array($city, $D["arealist"])) {
						SendMessage($tmid, $M["/add_citylist"]."\n\n可用的測站有：".implode("、", $D["citylist"][$city])."\n\n範例: /add ".$D["citylist"][$city][0]);
					} else if (isset($D["city"][$city])) {
						$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}follow` WHERE `tmid` = :tmid AND `city` = :city LIMIT 1");
						$sth->bindValue(":tmid", $tmid);
						$sth->bindValue(":city", $city);
						$res = $sth->execute();
						$row = $sth->fetch(PDO::FETCH_ASSOC);
						if ($row === false) {
							$sth = $G["db"]->prepare("INSERT INTO `{$C['DBTBprefix']}follow` (`tmid`, `city`, `level`, `diff`, `lastAQI`) VALUES (:tmid, :city, :level, :diff, :lastAQI)");
							$sth->bindValue(":tmid", $tmid);
							$sth->bindValue(":city", $city);
							$sth->bindValue(":level", $level);
							$sth->bindValue(":diff", $diff);
							$sth->bindValue(":lastAQI", $D["city"][$city]["AQI"]);
							$res = $sth->execute();
							SendMessage($tmid, "已開始接收".$city."測站的通知，當AQI達到".$level."且變化達到".$diff."時會通知\n目前的AQI是 ".$D["city"][$city]["AQI"]);
						} else {
							$sth = $G["db"]->prepare("UPDATE `{$C['DBTBprefix']}follow` SET `level` = :level WHERE `tmid` = :tmid AND `city` = :city");
							$sth->bindValue(":tmid", $tmid);
							$sth->bindValue(":city", $city);
							$sth->bindValue(":level", $level);
							$res = $sth->execute();
							SendMessage($tmid, "已經".$city."測站的通知的門檻值改成".$level);
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
					$city = $cmd[1];
					if (!isset($D["city"][$city])) {
						SendMessage($tmid, "找不到".$city."測站");
					} else if (in_array($city, $follow)) {
						$sth = $G["db"]->prepare("DELETE FROM `{$C['DBTBprefix']}follow` WHERE `tmid` = :tmid AND `city` = :city");
						$sth->bindValue(":tmid", $tmid);
						$sth->bindValue(":city", $city);
						$res = $sth->execute();
						SendMessage($tmid, "已停止接收".$city."測站的通知");
					} else {
						SendMessage($tmid, "並沒有接收".$city."測站的通知");
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
					require(__DIR__.'/function/level.php');
					require(__DIR__.'/function/makemessage.php');
					if (!makemessage(true, $tmid)) {
						SendMessage($tmid, $M["/list_zero"]);
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
