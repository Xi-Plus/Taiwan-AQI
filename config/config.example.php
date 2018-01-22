<?php
$C["AQIover"] = 101;
$C["AQIdiff"] = 10;
$C["GPSlist"] = 3;

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

$C['LogKeep'] = 86400*7;

$C["allowsapi"] = array("cli");

$G["db"] = new PDO ('mysql:host='.$C["DBhost"].';dbname='.$C["DBname"].';charset=utf8', $C["DBuser"], $C["DBpass"]);

date_default_timezone_set("Asia/Taipei");
