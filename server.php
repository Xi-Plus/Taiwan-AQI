<?php
require_once(__DIR__.'/config/config.php');
require_once(__DIR__.'/function/SQL-function/sql.php');

$method = $_SERVER['REQUEST_METHOD'];
if ($method == 'GET' && $_GET['hub_mode'] == 'subscribe' &&  $_GET['hub_verify_token'] == $cfg['verify_token']) {
	echo $_GET['hub_challenge'];
} else if ($method == 'POST') {
	$inputJSON = file_get_contents('php://input');
	$input = json_decode($inputJSON, true);
	$query = new query;
	$query->table = 'area';
	$query->order = 'no';
	$result = $query->SELECT();
	foreach ($result as $value) {
		$area_list[$value['area']]['name'] = $value['name'];
	}
	$query = new query;
	$query->table = 'city';
	$result = $query->SELECT();
	foreach ($result as $value) {
		$area_list[$value['area']]['city'][] = $value['city'];
		$city_list[$value['city']] = $value['name'];
	}
	foreach ($input['entry'] as $entry) {
		foreach ($entry['messaging'] as $messaging) {
			$page_id = $messaging['recipient']['id'];
			if ($page_id != $cfg['page_id']) {
				continue;
			}
			$user_id = $messaging['sender']['id'];
			if (isset($messaging['message']['quick_reply']) || isset($messaging['postback'])) {
				$payload = $messaging['message']['quick_reply']['payload'] ?? $messaging['postback']['payload'];
				if ($payload == 'new') {
					$messageData=array(
						"recipient"=>array("id"=>$user_id),
						"message"=>array(
							"text"=>"選擇一個區域：\n註：陽明、汐止屬於基北",
							"quick_replies"=>array()
						)
					);
					foreach ($area_list as $key => $value) {
						$messageData['message']['quick_replies'][]=array("content_type"=>"text", "title"=>$value['name'], "payload"=>"new_area_".$key);
					}
				} else if ($payload == 'view') {
					$query = new query;
					$query->table = 'follow';
					$query->where = array(
						array('uid', $user_id)
					);
					$result = $query->SELECT();
					$list = "";
					foreach ($result as $key => $value) {
						$list .= "\n".($key+1)." ".$city_list[$value['city']];
					}
					$messageData=array(
						"recipient"=>array("id"=>$user_id),
						"message"=>array("text"=>(count($result)>0?"目前已接收以下測站的通知：".$list:"你尚未接收任何通知"))
					);
				} else if (substr($payload, 0, 8) == "del_all") {
					$query = new query;
					$query->table = 'follow';
					$query->where = array(
						array('uid', $user_id)
					);
					$result = $query->DELETE();
					$messageData=array(
						"recipient"=>array("id"=>$user_id),
						"message"=>array("text"=>"已取消接收所有測站的通知")
					);
				} else if (substr($payload, 0, 8) == "del_none") {
					$messageData=array(
						"recipient"=>array("id"=>$user_id),
						"message"=>array("text"=>"沒有進行任何動作")
					);
				} else if (substr($payload, 0, 8) == "del_city") {
					$city_code = substr($payload, 9);
					$query = new query;
					$query->table = 'follow';
					$query->where = array(
						array('uid', $user_id),
						array('city', $city_code)
					);
					$result = $query->DELETE();
					$messageData=array(
						"recipient"=>array("id"=>$user_id),
						"message"=>array("text"=>"已取消接收".$city_list[$city_code]."測站的通知")
					);
				} else if (substr($payload, 0, 3) == "del") {
					$page = substr($payload, 4);
					$messageData=array(
						"recipient"=>array("id"=>$user_id),
						"message"=>array(
							"text"=>"選擇你要取消通知的測站",
							"quick_replies"=>array()
						)
					);
					$query = new query;
					$query->table = 'follow';
					$query->where = array(
						array('uid', $user_id)
					);
					$result = $query->SELECT();
					foreach ($result as $key => $value) {
						if ($key < $page*7) continue;
						if ($key >= ($page+1)*7) break;
						$messageData['message']['quick_replies'][]=array("content_type"=>"text", "title"=>$city_list[$value['city']], "payload"=>"del_city_".$value['city']);
					}
					if (count($result) > ($page+1)*7) {
						$messageData['message']['quick_replies'][]=array("content_type"=>"text", "title"=>"下一頁", "payload"=>"del_".($page+1));
					}
					$messageData['message']['quick_replies'][]=array("content_type"=>"text", "title"=>"全部取消", "payload"=>"del_all");
					$messageData['message']['quick_replies'][]=array("content_type"=>"text", "title"=>"不取消", "payload"=>"del_none");
				} else if (substr($payload, 0, 8) == "new_area") {
					$area_code = substr($payload, 9);
					$messageData=array(
						"recipient"=>array("id"=>$user_id),
						"message"=>array(
							"text"=>"選擇一個測站：",
							"quick_replies"=>array()
						)
					);
					foreach ($area_list[$area_code]['city'] as $city_code) {
						$messageData['message']['quick_replies'][]=array("content_type"=>"text", "title"=>$city_list[$city_code], "payload"=>"new_city_".$city_code);
					}
				} else if (substr($payload, 0, 8) == "new_city") {
					$city_code = substr($payload, 9);
					$query = new query;
					$query->table = 'follow';
					$query->value = array(
						array('uid', $user_id),
						array('city', $city_code),
						array('hash', md5($user_id.$city_code))
					);
					$result = $query->INSERT();
					$messageData=array(
						"recipient"=>array("id"=>$user_id),
						"message"=>array("text"=>($result>0?"已開始接收測站 ".$city_list[$city_code]." 的通知":"測站 ".$city_list[$city_code]." 已經接收過了"))
					);
				}
			} else if (isset($messaging['message'])) {
				$messageData=array(
					"recipient"=>array("id"=>$user_id),
					"message"=>array("text"=>"get:".$messaging['message']['text'])
				);
			} else {
				$messageData=array(
					"recipient"=>array("id"=>$user_id),
					"message"=>array("text"=>"Something went wrong!")
				);
			}
			$commend = 'curl -X POST -H "Content-Type: application/json" -d \''.json_encode($messageData,JSON_HEX_APOS|JSON_HEX_QUOT).'\' "https://graph.facebook.com/v2.7/me/messages?access_token='.$cfg['page_token'].'"';
			system($commend);
		}
	}
}
