mega-downloader
===============

## Introduction

PHP script to download files and folders (as ZIPs) from Mega.co.nz.

Does not work with PHP 7.3, as there is no mcrypt extension there.

PRs are welcomed if someone decided to rewrite this without mcrypt support.

If you are downloading folder, make sure you have enough local space available.

## Usage

```bash
# clone the repository
git clone https://github.com/ZonD80/mega-downloader.git 
cd mega-downloader/
```

```bash
# install mcrypt and zip extensions for php
sudo apt install php php-dev libmcrypt-dev libzip-dev php-pear
sudo pecl channel-update pecl.php.net
sudo pecl install mcrypt
sudo pecl install zip
```

```bash
# add these to php.ini
extension=mcrypt.so
extension=zip.so
```

```bash
# download composer.phar
curl -sS https://getcomposer.org/installer | php
```

```bash
# use composer.phar and  install required packages.
php composer.phar install 
```

## Sample code

You can refer example_file.php and example_folder.php

## Change log
Can be found on [commits page](https://github.com/ZonD80/mega-downloader/commits/master)