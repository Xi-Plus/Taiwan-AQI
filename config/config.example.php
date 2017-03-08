<?php
$C["AQI_over"] = 51;

$C['FBpageid'] = 'page_id';
$C['FBpagetoken'] = 'page_token';
$C['FBWHtoken'] = 'Webhooks_token';
$C['FBAPI'] = 'https://graph.facebook.com/v2.8/';

$C["DBhost"] = 'localhost';
$C['DBname'] = 'dbname';
$C['DBuser'] = 'user';
$C['DBpass'] = 'pass';
$C['DBTBprefix'] = 'taiwan_aqi_';

$C['fetch'] = 'http://taqm.epa.gov.tw/taqm/tw/Aqi/Yun-Chia-Nan.aspx?type=all';

$C["allowsapi"] = array("cli");

$G["db"] = new PDO ('mysql:host='.$C["DBhost"].';dbname='.$C["DBname"].';charset=utf8', $C["DBuser"], $C["DBpass"]);

$M["nottext"] = "僅接受文字訊息";
$M["notcommand"] = "無法辨識的訊息\n".
	"本粉專由機器人自動運作\n".
	"啟用訊息通知輸入 /add\n".
	"顯示所有命令輸入 /help";
$M["/help"] = "可用命令\n".
	"/add 顯示所有區域\n".
	"/add [區域] 顯示此區域測站\n".
	"/add [測站] 啟用此測站通知，當AQI超過".$C["AQI_over"]."會傳送訊息\n".
	"/add [測站] [門檻值] 啟用此測站通知，當AQI超過門檻值會傳送訊息\n".
	"/del [測站] 停用此測站通知\n".
	"/list 列出已接收通知的測站\n".
	"/show 列出已接收通知測站的資料\n".
	"/help 顯示所有命令";
$M["/add_level_notnum"] = "第2個參數錯誤\n".
	"必須是一個整數";
$M["/add_too_many_arg"] = "參數個數錯誤\n".
	"必須提供1個或2個參數，第1個為測站，第2個為AQI指數";
$M["/add_arealist"] = "輸入 /add [區域] 顯示此區域所有的測站";
$M["/add_citylist"] = "輸入 /add [測站] 接收此測站通知\n".
	"輸入 /add [測站] [門檻值] 可自訂通知門檻值";
$M["/add_notfound"] = "找不到此區域或測站";
$M["/list"] = "已接收以下測站";
$M["/list_zero"] = "沒有接收任何測站\n".
	"輸入 /add [測站] 開始接收測站通知";
$M["/list_add_update"] = "輸入 /add  [測站] [門檻值] 可重新設定門檻值";
$M["/del"] = "輸入 /del [測站] 取消接收此測站通知";
$M["wrongcommand"] = "無法辨識命令\n".
	"輸入 /help 取得可用命令";

date_default_timezone_set("Asia/Taipei");
