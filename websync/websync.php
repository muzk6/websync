#! /usr/bin/env php

<?php

/**
 * 项目文件同步
 */

$opt = getopt('', ['src::', 'dst::', 'force::']);

$force = isset($opt['force']) ?: false;
if (!file_exists('.gitignore') && !$force) {
    echo '没有 .gitignore 文件，请确定是否在项目根目录' . PHP_EOL;
    exit;
}

$conf = require(__DIR__ . '/config.php');
$src = $opt['src'] ?? getcwd() . '/';
$dst = $opt['dst'] ?? $conf['dst_root'] . '/' . basename($src);

$ignores = file('.gitignore');
$excludes = [];
$ignores || $ignores = [];
$ignores[] = '.git';

foreach ($ignores as $ignore) {
    $ignore = trim($ignore);
    $excludes[] = "--exclude={$ignore}";
}
$excludes = implode(' ', $excludes);

$cmd = "rsync -avz --delete --progress {$excludes} {$src} {$conf['remote']}:{$dst}";
echo $cmd . PHP_EOL;
system($cmd);
