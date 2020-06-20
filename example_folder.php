<?php
//ini_set('display_errors',true);
require_once('src/mega.class.php');

$megafile = new MEGA('https://mega.nz/#F!6ghFGDyD!uVPZiTXJTuDh_c5-cAevrw');

$megafile->download_zip(); // to download using streams. Make sure you have enough space for folder in /tmp
?>
