<?php

/**
 * 打包
 */

$phar = new Phar(__DIR__ . '/websync.phar');
$phar->buildFromDirectory(__DIR__ . '/src', '#\.php$#i');
$phar->setStub("#!/usr/bin/env php\n" . Phar::createDefaultStub('index.php'));
$phar->compressFiles(Phar::GZ);
