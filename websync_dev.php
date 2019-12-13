#! /usr/bin/env php
<?php

/**
 * 开发版入口
 * rm /usr/local/bin/websync &&ln -s `pwd`/websync_dev.php /usr/local/bin/websync
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . '/websync.php';
