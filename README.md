mega-downloader(peter279k)
===============

#Introduction

PHP script to download files and folders (as ZIPs) from Mega.co.nz

If you are downloading folder, make sure you have enough local space available.

#Usage
```bash
# download composer.phar
curl -sS https://getcomposer.org/installer | php
	
<<<<<<< HEAD
	# use composer.phar and  install required packages.
	php composer.pahr install
	```
=======
# use composer.phar and  install required packages.
php composer.pahr install
```

>>>>>>> c3c1e8eb81709da8bafd2ad8def6d6226973985f
#Change log
####2016/07/21
+ using Guzzle library , comodojo/zip and symfony/console(future)
+ Using composer to manage package
+ fix origin project that cannot support on Windows and Linux_X86.
+ fix some Undefined messages.
