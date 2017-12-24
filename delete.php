<?php

$dir = '/data/application/logs/japi/default/*.log';

$files = glob($dir);
$time = time();

foreach($files as $f){
	$last_modify = filemtime($f);
	$delete_time = 24*3600*2;
	
// 	echo (($time - $delete_time).'--'. $last_modify.'---'.date('Y-m-d H', $last_modify).'--'.date('Y-m-d H', ($time - $delete_time)));
// 	echo '<br />';
// 	file_put_contents('qq.txt', time());
	if(($time - $delete_time) > $last_modify) unlink($f);
}
