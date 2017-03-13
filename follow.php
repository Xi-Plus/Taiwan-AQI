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
				$closest = array("length"=>1e100, "lat"=>0, "long"=>0, "city"=>"");
				require(__DIR__.'/function/gpsdist.php');
				foreach ($D["city"] as $name => $city) {
					$D["city"][$name]["dist"] = gpsdist($lat, $long, $city["lat"], $city["long"]);
					$D["city"][$name]["angle"] = (anglei($lat, $long, $city["lat"], $city["long"])+anglef($lat, $long, $city["lat"], $city["long"]))/2;
				}
				function cmp($a, $b) {
					return ($a["dist"] < $b["dist"]) ? -1 : 1;
				}
				$msg = "離您最近的測站有\n";
				uasort($D["city"], 'cmp');
				$city = reset($D["city"]);
				for ($i=0; $i < $C["GPSlist"]; $i++) {
					$msg .= $city["name"]." 目前AQI ".$city["AQI"]."\n".
						"    ";
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
					$city = next($D["city"]);
				}
				$msg .= "\n輸入 /add 接收測站通知";
				SendMessage($tmid, $msg);
				continue;
			}
			if (!isset($messaging['message']['text'])) {
				SendMessage($tmid, "僅接受文字訊息");
				continue;
			}
			$msg = $messaging['message']['text'];
			if ($msg[0] !== "/") {
				SendMessage($tmid, "無法辨識的訊息\n".
					"本粉專由機器人自動運作\n".
					"啟用訊息通知輸入 /add\n".
					"顯示所有命令輸入 /help");
				continue;
			}
			$msg = str_replace("\n", " ", $msg);
			$msg = preg_replace("/\s+/", " ", $msg);
			$cmd = explode(" ", $msg);
			switch ($cmd[0]) {
				case '/add':
					if (!isset($cmd[1])) {
						$msg = "輸入 /add [區域] 顯示此區域所有的測站"."\n\n".
							"可用的區域有：".implode("、", $D["arealist"])."\n\n".
							"範例： /add ".$D["arealist"][0];
						SendMessage($tmid, $msg);
						break;
					}
					$city = $cmd[1];
					$level = $C["AQIover"];
					if (isset($cmd[2])) {
						if (!ctype_digit($cmd[2])) {
							SendMessage($tmid, "第2個參數錯誤\n".
								"必須是一個整數");
							break;
						}
						$level = (int)$cmd[2];
					}
					$diff = $C["AQIdiff"];
					if (isset($cmd[3])) {
						if (!ctype_digit($cmd[3])) {
							SendMessage($tmid, "第3個參數錯誤\n".
								"必須是一個整數");
							break;
						}
						$diff = (int)$cmd[3];
					}
					if (isset($cmd[4])) {
						SendMessage($tmid, "參數個數錯誤\n".
							"必須提供1個或2個參數，第1個為測站，第2個為AQI指數");
						break;
					}
					if (in_array($city, $D["arealist"])) {
						SendMessage($tmid, "輸入 /add [測站] 接收此測站通知\n".
							"可用的測站有：".implode("、", $D["citylist"][$city])."\n\n".
							"範例: /add ".$D["citylist"][$city][0]);
					} else if (isset($D["city"][$city])) {
						$user = getuserlist($tmid);
						if (!isset($user[$city])) {
							$sth = $G["db"]->prepare("INSERT INTO `{$C['DBTBprefix']}follow` (`tmid`, `city`, `level`, `diff`, `lastAQI`) VALUES (:tmid, :city, :level, :diff, :lastAQI)");
							$sth->bindValue(":tmid", $tmid);
							$sth->bindValue(":city", $city);
							$sth->bindValue(":level", $level);
							$sth->bindValue(":diff", $diff);
							$sth->bindValue(":lastAQI", $D["city"][$city]["AQI"]);
							$res = $sth->execute();
							SendMessage($tmid, "已開始接收".$city."測站的通知，當AQI達到".$level."且變化達到".$diff."時會通知\n目前的AQI是 ".$D["city"][$city]["AQI"]);
						} else {
							SendMessage($tmid, $city." 測站已經接收過了\n".
								"要修改請使用 /level 和 /diff\n".
								"要刪除請使用 /del");
						}
					} else {
						$msg = "找不到此區域或測站"."\n".
							"輸入 /add [區域] 顯示此區域所有的測站"."\n\n".
							"可用的區域有：".implode("、", $D["arealist"])."\n\n".
							"範例： /add ".$D["arealist"][0];
						SendMessage($tmid, $msg);
					}
					break;

				case '/level':
					if (!isset($cmd[1])) {
						SendMessage($tmid, "參數不足\n".
							"必須給出一個或兩個參數");
						break;
					}
					if (isset($cmd[3])) {
						SendMessage($tmid, "參數過多\n".
							"必須給出一個或兩個參數");
						break;
					}
					if (isset($cmd[2])) {
						if (preg_match("/^\d+$/", $cmd[2]) == 0) {
							SendMessage($tmid, "第2個參數錯誤\n".
								"門檻值必須是一個整數");
							continue;
						}
						$city = $cmd[1];
						$level = (int)$cmd[2];
					} else {
						if (preg_match("/^\d+$/", $cmd[1]) == 0) {
							$city = $cmd[1];
							$level = $C["AQIover"];
						} else {
							$city = false;
							$level = (int)$cmd[1];
						}
					}
					if ($city !== false) {
						if (!isset($D["city"][$city])) {
							SendMessage($tmid, "找不到測站");
							continue;
						}
						$user = getuserlist($tmid);
						if (!isset($user[$city])) {
							SendMessage($tmid, "您沒有接收此測站通知");
							continue;
						}
						$sth = $G["db"]->prepare("UPDATE `{$C['DBTBprefix']}follow` SET `level` = :level WHERE `tmid` = :tmid AND `city` = :city");
						$sth->bindValue(":city", $city);
					} else {
						$sth = $G["db"]->prepare("UPDATE `{$C['DBTBprefix']}follow` SET `level` = :level WHERE `tmid` = :tmid");
					}
					$sth->bindValue(":tmid", $tmid);
					$sth->bindValue(":level", $level);
					$res = $sth->execute();
					if ($res === false) {
						SendMessage($tmid, "指令錯誤");
						continue;
					}
					if ($city === false) {
						SendMessage($tmid, "已將所有測站的通知的門檻值改成 ".$level);
					} else {
						SendMessage($tmid, "已將 ".$city." 測站的通知的門檻值改成 ".$level);
					}
					break;

				case '/diff':
					if (!isset($cmd[1])) {
						SendMessage($tmid, "參數不足\n".
							"必須給出一個或兩個參數");
						break;
					}
					if (isset($cmd[3])) {
						SendMessage($tmid, "參數過多\n".
							"必須給出一個或兩個參數");
						break;
					}
					if (isset($cmd[2])) {
						if (preg_match("/^\d+$/", $cmd[2]) == 0) {
							SendMessage($tmid, "第2個參數錯誤\n".
								"變化值必須是一個整數");
							continue;
						}
						$city = $cmd[1];
						$level = (int)$cmd[2];
					} else {
						if (preg_match("/^\d+$/", $cmd[1]) == 0) {
							$city = $cmd[1];
							$level = $C["AQIdiff"];
						} else {
							$city = false;
							$level = (int)$cmd[1];
						}
					}
					if ($city !== false) {
						if (!isset($D["city"][$city])) {
							SendMessage($tmid, "找不到測站");
							continue;
						}
						$user = getuserlist($tmid);
						if (!isset($user[$city])) {
							SendMessage($tmid, "您沒有接收此測站通知");
							continue;
						}
						$sth = $G["db"]->prepare("UPDATE `{$C['DBTBprefix']}follow` SET `diff` = :diff WHERE `tmid` = :tmid AND `city` = :city");
						$sth->bindValue(":city", $city);
					} else {
						$sth = $G["db"]->prepare("UPDATE `{$C['DBTBprefix']}follow` SET `diff` = :diff WHERE `tmid` = :tmid");
					}
					$sth->bindValue(":tmid", $tmid);
					$sth->bindValue(":diff", $level);
					$res = $sth->execute();
					if ($res === false) {
						SendMessage($tmid, "指令錯誤");
						continue;
					}
					if ($city === false) {
						SendMessage($tmid, "已將所有測站的通知的變化值改成 ".$level);
					} else {
						SendMessage($tmid, "已將 ".$city." 測站的通知的變化值改成 ".$level);
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
						$msg = "輸入 /del [測站] 取消接收此測站通知"."\n\n";
						if (count($follow) == 0) {
							$msg .= "沒有接收任何測站\n".
								"輸入 /add [測站] 開始接收測站通知";
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
						SendMessage($tmid, "已停止接收 ".$city." 測站的通知");
					} else {
						SendMessage($tmid, "並沒有接收 ".$city." 測站的通知");
					}
					break;

				case '/list':
					$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}follow` WHERE `tmid` = :tmid");
					$sth->bindValue(":tmid", $tmid);
					$res = $sth->execute();
					$row = $sth->fetchAll(PDO::FETCH_ASSOC);

					if (count($row) == 0) {
						SendMessage($tmid, "沒有接收任何測站\n".
							"輸入 /add [測站] 開始接收測站通知");
					} else {
						$msg = "已接收以下測站"."\n";
						foreach ($row as $follow) {
							$msg .= $follow["city"]." 門檻".$follow["level"]." 變化".$follow["diff"]."\n";
						}
						$msg .= "\n".
							"/level 修改門檻值\n".
							"/diff 修改變化值\n".
							"/del 停止測站通知";
						SendMessage($tmid, $msg);
					}
					break;
				
				case '/show':
					require(__DIR__.'/function/level.php');
					require(__DIR__.'/function/makemessage.php');
					if (!makemessage(true, $tmid)) {
						SendMessage($tmid, "沒有接收任何測站\n".
							"輸入 /add [測站] 開始接收測站通知");
					}
					break;
				
				case '/help':
					if (isset($cmd[2])) {
						$msg = "參數過多\n".
							"必須給出一個參數為指令的名稱";
					} else if (isset($cmd[1])) {
						switch ($cmd[1]) {
							case 'add':
								$msg = "/add 顯示所有區域\n".
									"/add [區域] 顯示此區域所有的測站\n".
									"/add [測站] 接收測站通知\n".
									"/add [測站] [門檻] 接收並自訂通知門檻值\n".
									"/add [測站] [門檻] [變化] 接收並自訂通知門檻和變化值";
								break;
							
							case 'level':
								$msg = "/level [測站] [門檻] 將此測站門檻值改為自訂值\n".
									 "/level [測站] 將此測站門檻值改為預設值{$C["AQIover"]}\n".
									 "/level [門檻] 將所有測站門檻值改為自訂值\n";
								break;
							
							case 'diff':
								$msg = "/diff [測站] [變化] 將此測站變化值改為自訂值\n".
									 "/diff [測站] 將此測站變化值改為預設值{$C["AQIdiff"]}\n".
									 "/diff [變化] 將所有測站變化值改為自訂值\n";
								break;
							
							case 'del':
								$msg = "/del [測站] 停止此測站通知";
								break;
							
							case 'list':
								$msg = "/list 列出已接收通知的測站";
								break;
							
							case 'show':
								$msg = "/show 列出已接收通知測站的資料";
								break;
							
							case 'help':
								$msg = "/help 顯示所有命令";
								break;
							
							default:
								$msg = "查無此指令";
								break;
						}
					} else {
						$msg = "可用命令\n".
						"/add 接收測站通知\n".
						"/level 修改測站門檻值\n".
						"/diff 修改測站變化值\n".
						"/del 停止測站通知\n".
						"/list 列出已接收通知的測站\n".
						"/show 列出已接收通知測站的資料\n".
						"/help 顯示所有命令\n\n".
						"/help [命令] 顯示命令的詳細用法";
					}
					SendMessage($tmid, $msg);
					break;
				
				default:
					SendMessage($tmid, "無法辨識命令\n".
						"輸入 /help 取得可用命令");
					break;
			}
		}
	}
}
