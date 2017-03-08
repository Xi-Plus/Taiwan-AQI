<?php
require(__DIR__.'/config/config.php');
if (!in_array(PHP_SAPI, $C["allowsapi"])) {
	exit("No permission");
}

require(__DIR__.'/function/log.php');
require(__DIR__.'/function/getlist.php');

$start = microtime(true);
$time = date("Y-m-d H:i:s");

$html = file_get_contents($C['fetch']);
preg_match_all("/\/Aqi\/(.+?)\/(.+?)\.aspx\">\s*(.+?)\s*<small>\((.+?)\)<\/small>/", $html, $m1);
preg_match_all("/labPSI\">(.*)<\/span>/", $html, $m2);
preg_match_all("/labO3\">(.*)<\/span>/", $html, $m3);
preg_match_all("/labPM25\">(.*)<\/span>/", $html, $m4);
preg_match_all("/labPM10\">(.*)<\/span>/", $html, $m5);
preg_match_all("/labCO\">(.*)<\/span>/", $html, $m6);
preg_match_all("/labSO2\">(.*)<\/span>/", $html, $m7);
preg_match_all("/labNO2\">(.*)<\/span>/", $html, $m8);
for ($i=0; $i < count($m1[0]); $i++) {
	if (!isset($D["area_list"][$m1[1][$i]])) {
		$sth = $G["db"]->prepare("INSERT INTO `{$C['DBTBprefix']}area` (`area`) VALUES (:area)");
		$sth->bindValue(":area", $m1[1][$i]);
		$res = $sth->execute();
		$D["area_list"][$m1[1][$i]] = array();
	}
	if (!isset($D["city_list"][$m1[3][$i]])) {
		$lat = "";
		$long = "";
		$html = file_get_contents("http://taqm.epa.gov.tw/taqm/tw/Site/".$m1[2][$i].".aspx");
		if (preg_match("/北緯：(\d+?)度(\d+?)分(.+?)秒/", $html, $m)) {
			$lat = round($m[1]+($m[2]/60)+($m[3]/3600), 6);
		}
		if (preg_match("/東經：(\d+?)度(\d+?)分(.+?)秒/", $html, $m)) {
			$long = round($m[1]+($m[2]/60)+($m[3]/3600), 6);
		}
		$sth = $G["db"]->prepare("INSERT INTO `{$C['DBTBprefix']}city` (`area`, `enname`, `name`, `lat`, `long`) VALUES (:area, :enname, :name, :lat, :long)");
		$sth->bindValue(":area", $m1[1][$i]);
		$sth->bindValue(":enname", $m1[2][$i]);
		$sth->bindValue(":name", $m1[3][$i]);
		$sth->bindValue(":lat", $lat);
		$sth->bindValue(":long", $long);
		$res = $sth->execute();
		$D["city_list"][$m1[3][$i]] = array();
	}
	if ($m2[1][$i] != "") {
		$sth = $G["db"]->prepare("UPDATE `{$C['DBTBprefix']}city` SET `AQI` = :AQI, `O3` = :O3, `PM25` = :PM25, `PM10` = :PM10, `CO` = :CO, `SO2` = :SO2, `NO2` = :NO2, `time` = :time WHERE `name` = :name");
		$sth->bindValue(":AQI", $m2[1][$i]);
		$sth->bindValue(":O3", $m3[1][$i]);
		$sth->bindValue(":PM25", $m4[1][$i]);
		$sth->bindValue(":PM10", $m5[1][$i]);
		$sth->bindValue(":CO", $m6[1][$i]);
		$sth->bindValue(":SO2", $m7[1][$i]);
		$sth->bindValue(":NO2", $m8[1][$i]);
		$sth->bindValue(":time", $time);
		$sth->bindValue(":name", $m1[3][$i]);
		$res = $sth->execute();
	}
}
exec("php ".__DIR__."/fbmessage.php > /dev/null 2>&1 &");
WriteLog("[fetch][info] runtime=".round((microtime(true)-$start), 6));
