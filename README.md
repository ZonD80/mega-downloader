mega-downloader
===============

#Introduction

PHP script to download files and folders (as ZIPs) from Mega.co.nz

If you are downloading folder, make sure you have enough local space available.

# Change log

## 2016/07/21

+ this branch don't need to use composer.
+ fix origin project that cannot support on Windows and Linux_X86.
+ fix some Undefined messages.
+ remove zipstream.php and use zip_progress.php
+ the default download path is the root path of the project.
