<?php

$outfile = 'websync.phar';
$extList = ['php'];
$outPath = __DIR__ . '/' . $outfile;

file_exists($outPath) && unlink($outPath);
$phar = new Phar($outPath, FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, $outfile);

$phar->startBuffering();

foreach ($extList as $ext) {
    $phar->buildFromDirectory(__DIR__, '/\.' . $ext . '$/');
}
$phar->delete('build.php');
$phar->delete('websync.php');
file_exists(realpath('.websyncrc.php')) && $phar->delete('.websyncrc.php');
$phar->setStub("#! /usr/bin/env php\n" . $phar->createDefaultStub('index.php')); // 程序入口

$phar->stopBuffering();
echo "Finished {$outfile}\n";
