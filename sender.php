<?php
if(PHP_SAPI!="cli"){
	exit("No permission.");
}
require_once(__DIR__.'/config/config.php');
require_once(__DIR__.'/function/SQL-function/sql.php');

$time = date("Y-m-d H:i:s");

$html=file_get_contents("http://taqm.epa.gov.tw/taqm/tw/PsiMap.aspx");
$html=str_replace(array("\n","\t"),"",$html);
$pattern="/jTitle='(.*?)' coords/";
preg_match_all($pattern,$html,$match);
foreach ($match[1] as $temp) {
	$temp=json_decode($temp);
	$fetch[$temp->SiteKey]=$temp;
}

file_put_contents(__DIR__."/log/".date("Y_m_d_H_i_s")."_1.json", json_encode($fetch, JSON_UNESCAPED_UNICODE));

$query = new query;
$query->table = 'city';
$city_list = $query->SELECT();
foreach ($city_list as $city) {
	$query = new query;
	$query->table = 'city';
	$query->value = array();
	$data[$city['city']]['name'] = $city['name'];
	if ($fetch[$city['city']]->PSI != "-9999") {
		$query->value[] = array('PSI', $fetch[$city['city']]->PSI);
		$query->value[] = array('PSItime', $time);
		$data[$city['city']]['PSI'] = array(
			'valid'=>true,
			'value'=>$fetch[$city['city']]->PSI,
			'level'=>$fetch[$city['city']]->PSIStyle,
			'diff'=>$fetch[$city['city']]->PSI-$city['PSI']
		);
	} else {
		$data[$city['city']]['PSI'] = array('valid'=>false);
	}
	if ($fetch[$city['city']]->PM25 != "") {
		$query->value[] = array('PM25', $fetch[$city['city']]->PM25);
		$query->value[] = array('PM25time', $time);
		$data[$city['city']]['PM25'] = array(
			'valid'=>true,
			'value'=>$fetch[$city['city']]->PM25,
			'level'=>$fetch[$city['city']]->FPMI,
			'diff'=>$fetch[$city['city']]->PM25-$city['PM25']
		);
	} else {
		$data[$city['city']]['PSI'] = array('valid'=>false);
	}
	$query->where = array('city', $city['city']);
	$query->UPDATE();
}

$psilevelname=array(
	"PSI1" => "良好",
	"PSI2" => "普通",
	"PSI3" => "不良",
	"PSI4" => "非常不良",
	"PSI5" => "有害"
);
$pm25levelname=array(
	1 => "低", 2 => "低", 3 => "低",
	4 => "中", 5 => "中", 6 => "中",
	7 => "高", 8 => "高", 9 => "高",
	10 => "非常高"
);
function icon($value){
	if($value>0)return "🔺";
	else if($value<0)return "🔻";
	else return "🔴";
}

$query = new query;
$query->table = 'follow';
$result = $query->SELECT();
foreach ($result as $follow) {
	$city = $follow['city'];
	if ($data[$city]['PSI']['valid'] && $data[$city]['PSI']['value'] >= $cfg["PSI_over"]) {
		@$messages[$follow['uid']] .= $data[$city]['name']." PSI ".$data[$city]['PSI']['value'].icon($data[$city]['PSI']['diff'])." ".$psilevelname[$data[$city]['PSI']['level']]."等級\n";
	}
	if ($data[$city]['PM25']['valid'] && $data[$city]['PM25']['level'] >= $cfg["PM25_over"]) {
		@$messages[$follow['uid']] .= $data[$city]['name']." PM2.5 ".$data[$city]['PM25']['value'].icon($data[$city]['PM25']['diff'])." 第".$data[$city]['PM25']['level']."級 分類".$pm25levelname[$data[$city]['PM25']['level']]."\n";
	}
}

foreach ($messages as $uid => $message) {
	$messageData=array(
		"recipient"=>array("id"=>$uid),
		"message"=>array("text"=>$message)
	);
	$commend = 'curl -X POST -H "Content-Type: application/json" -d \''.json_encode($messageData,JSON_HEX_APOS|JSON_HEX_QUOT).'\' "https://graph.facebook.com/v2.7/me/messages?access_token='.$cfg['page_token'].'"';
	system($commend);
}
?>
