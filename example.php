<?php
require_once('mega.class.php');

$megafile = new MEGA('https://mega.co.nz/#!yl5EFARS!c6T1en1P8N9GuQzsMy5iCh2U9NEmuqTvSd4KkW42UX4');

$megafile->download();
// OR
// $megafile->stream_download(); // to download using streams
?>