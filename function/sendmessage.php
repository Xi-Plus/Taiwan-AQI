<?php
function SendMessage($tmid, $message) {
	global $C;
	$post = array(
		"message" => $message,
		"access_token" => $C['FBpagetoken']
	);
	$res = cURL($C['FBAPI'].$tmid."/messages", $post);
	WriteLog("[smsg] tmid=".$tmid." msg=".$message);
	$res = json_decode($res, true);
	if (isset($res["error"])) {
		WriteLog("[smsg][error] res=".json_encode($res)." tmid=".$tmid." msg=".$message);
		return false;
	}
	return true;
}
