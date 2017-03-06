<?php
$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}area` ORDER BY `no` ASC");
$res = $sth->execute();
$row = $sth->fetchAll(PDO::FETCH_ASSOC);
$D["area"] = array();
$D["arealist"] = array();
$D["citylist"] = array();
foreach ($row as $value) {
	$D["area"][$value['area']] = $value;
	$D["area"][$value['area']]["city"] = array();
	$D["arealist"][] = $value['name'];
	$D["citylist"][$value['name']] = array();
}

$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}city`");
$res = $sth->execute();
$row = $sth->fetchAll(PDO::FETCH_ASSOC);
$D["city"] = array();
foreach ($row as $value) {
	$D["area"][$value['area']]['city'][] = $value['name'];
	$D["city"][$value['name']] = $value;
	$D["citylist"][$D["area"][$value['area']]['name']][] = $value['name'];
}
