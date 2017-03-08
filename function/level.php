<?php
function AQIlevel($AQI){
	if ($AQI <= 50) return "良好";
	if ($AQI <= 100) return "普通";
	if ($AQI <= 150) return "對敏感族群
不健康";
	if ($AQI <= 200) return "對所有族群
不健康";
	if ($AQI <= 300) return "非常不健康";
	return "危害";
}
