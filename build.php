<?php

$outfile = 'websync.phar';
$extList = ['php'];

unlink(__DIR__ . '/' . $outfile);
$phar = new Phar(__DIR__ . '/' . $outfile, FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, $outfile);

$phar->startBuffering();

foreach ($extList as $ext) {
    $phar->buildFromDirectory(__DIR__, '/\.' . $ext . '$/');
}
$phar->delete('build.php');
$phar->delete('.websyncrc.php');
$phar->delete('websync_dev.php');
$phar->setStub("#! /usr/bin/env php\n" . $phar->createDefaultStub('index.php')); // 程序入口

$phar->stopBuffering();
echo "Finished {$outfile}\n";
