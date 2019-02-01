#! /usr/bin/env php

<?php

/**
 * 项目文件同步
 */

$opt = getopt('', ['src::', 'dst::', 'force::', 'help::'], $ind);

$help = isset($opt['help']) ?: false;
if ($help) {
    echo <<<DOC
USAGE
    websync [OPTION...] [SCOPE]
OPTION
    [--src=本地源路径] [--dst=远程目的路径] [--force 项目没有 .git 时强制同步]
SCOPE
    域，对应配置文件里的 scope
    如果不指定[SCOPE]，默认会从项目目录上一层的 .websyncscope 里取
DOC;
    echo PHP_EOL;
    exit;
}

$force = isset($opt['force']) ?: false;
if (!file_exists('.git') && !$force) {
    echo '项目不支持 git，强制同步可使用参数 --force' . PHP_EOL;
    exit;
}

$conf = require(__DIR__ . '/config.php');
$pwd = getcwd();

if (isset($argv[$ind])) {
    $scope = $argv[$ind];
} elseif (file_exists($websyncscope = realpath($pwd . '/../.websyncscope'))) {
    $scope = trim(file_get_contents($websyncscope));
} else {
    echo '请指定[SCOPE]，或者在上一层目录里新建 .websyncscope 以 [SCOPE] 为内容' . PHP_EOL;
    exit;
}

$scopeConf = &$conf[$scope];
if (!isset($scopeConf)) {
    echo "不存在域 {$scope}";
    exit;
}

$src = isset($opt['src']) ? $opt['src'] : getcwd() . '/';
$dst = isset($opt['dst']) ? $opt['dst'] : $scopeConf['dst_root'] . '/' . basename($src);

// .gitignore 里的忽略列表
$ignores = [];
if (file_exists('.gitignore')) {
    exec('git clean -ndX', $ignoresRaw);
    foreach ($ignoresRaw as $v) {
        $ignores[] = trim(str_replace('Would remove ', '', $v));
    }
}

// 配置文件里的忽略列表
foreach ($scopeConf['ignore'] as $v) {
    $ignores[] = $v;
}

// 忽略文件的参数
$excludes = [];
foreach ($ignores as $ignore) {
    $ignore = trim($ignore);
    if (strpos($ignore, '#') !== false) {
        continue;
    }

    $excludes[] = "--exclude={$ignore}";
}
$excludes = implode(' ', $excludes);

// 重置文件权限
$chown = '';
if (isset($scopeConf['chown'])) {
    $chown = "--chown={$scopeConf['chown']}";
}

$cmd = "rsync -avz --delete --progress {$chown} {$excludes} {$src} {$scopeConf['remote']}:{$dst}";
echo $cmd . PHP_EOL;
system($cmd);
