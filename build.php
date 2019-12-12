<?php

$outfile = 'websync.phar'; // 成品文件名
$extList = ['php']; // 需要打包的文件后缀
$phar = new Phar(__DIR__ . '/' . $outfile, FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, $outfile);

$phar->startBuffering();

// 将后缀名相关的文件打包
foreach ($extList as $ext) {
    $phar->buildFromDirectory(__DIR__, '/\.' . $ext . '$/');
}
$phar->delete('build.php'); // 排除 build.php 本身
$phar->setStub("#! /usr/bin/env php\n\n" . $phar->createDefaultStub('websync.php')); // 程序入口

$phar->stopBuffering();
echo "Finished {$outfile}\n";
