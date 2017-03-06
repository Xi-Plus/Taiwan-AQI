<?php
function PSIlevel($psi){
	if ($psi <= 50) return "良好";
	if ($psi <= 100) return "普通";
	if ($psi <= 150) return "對敏感族群
不健康";
	if ($psi <= 200) return "對所有族群
不健康";
	if ($psi <= 300) return "非常不健康";
	return "危害";
}
