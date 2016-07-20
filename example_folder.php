<?php
	//ini_set('display_errors',true);
	set_time_limit(0);
	require 'src/mega.class.php';
	
	//$megafile = new MEGA('https://mega.nz/#F!5V9U2B5D!vCsRLR2ns8iK3XbewidRpg');
	$megafile = new MEGA('https://mega.nz/#F!6ghFGDyD!uVPZiTXJTuDh_c5-cAevrw');

	$megafile -> download_zip(); // to download using streams. Make sure you have enough space for folder in /tmp
?>