#! /usr/bin/env php

<?php

/**
 * 项目文件同步
 */

$opt = getopt('', ['src::', 'dst::', 'force::', 'help::']);

$help = isset($opt['help']) ?: false;
if ($help) {
    echo <<<DOC
    websync [--src=本地源路径] [--dst=远程目的路径] [--force 没有 .gitignore 文件时强制同步]
DOC;
    echo PHP_EOL;
    exit;
}

$force = isset($opt['force']) ?: false;
if (!file_exists('.gitignore') && !$force) {
    echo '没有 .gitignore 文件，请确定是否在项目根目录，强制同步可使用参数 --force' . PHP_EOL;
    exit;
}

$conf = require(__DIR__ . '/config.php');
$src = isset($opt['src']) ? $opt['src'] : getcwd() . '/';
$dst = isset($opt['dst']) ? $opt['dst'] : $conf['dst_root'] . '/' . basename($src);

// .gitignore 里的忽略列表
$ignores = file('.gitignore');
$ignores || $ignores = [];

// 配置文件里的忽略列表
foreach ($conf['ignore'] as $v) {
    $ignores[] = $v;
}

$excludes = [];
foreach ($ignores as $ignore) {
    $ignore = trim($ignore);
    $excludes[] = "--exclude={$ignore}";
}
$excludes = implode(' ', $excludes);

$cmd = "rsync -avz --delete --progress {$excludes} {$src} {$conf['remote']}:{$dst}";
echo $cmd . PHP_EOL;
system($cmd);
