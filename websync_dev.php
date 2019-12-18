#! /usr/bin/env php
<?php

/**
 * 开发版入口
 * bash install_dev.sh
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
$IS_DEV = true;

include __DIR__ . '/websync.php';
