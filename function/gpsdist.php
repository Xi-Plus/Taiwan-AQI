<?php
function gpsdist($lat1, $long1, $lat2, $long2) {
	$lat1 = deg2rad($lat1);
	$long1 = deg2rad($long1);
	$lat2 = deg2rad($lat2);
	$long2 = deg2rad($long2);
	$R = 6371000;
	$dlat = abs($lat1 - $lat2);
	$dlong = abs($long1 - $long2);
	return 2*$R*asin(sqrt(pow(sin($dlat/2), 2)+cos($lat1)*cos($lat2)*pow(sin($dlong/2), 2)));
}
function anglei($lat1, $long1, $lat2, $long2) {
	$lat1 = deg2rad($lat1);
	$long1 = deg2rad($long1);
	$lat2 = deg2rad($lat2);
	$long2 = deg2rad($long2);
	return rad2deg(atan2((cos($lat2)*sin($long2-$long1)), (cos($lat1)*sin($lat2)-sin($lat1)*cos($lat2)*cos($long2-$long1))));
}
function anglef($lat1, $long1, $lat2, $long2) {
	$lat1 = deg2rad($lat1);
	$long1 = deg2rad($long1);
	$lat2 = deg2rad($lat2);
	$long2 = deg2rad($long2);
	return rad2deg(atan2((cos($lat1)*sin($long2-$long1)), (-sin($lat1)*cos($lat2)+cos($lat1)*sin($lat2)*cos($long2-$long1))));
}
