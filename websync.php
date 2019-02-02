#! /usr/bin/env php
<?php

/**
 * Web 项目文件同步
 */

$opt = getopt('h::v::t::', ['src::', 'dst::', 'remote::',
    'force::', 'test::', 'init::', 'help::',
]);

if (isset($opt['h']) || isset($opt['help'])) {
    echo <<<DOC
USAGE
    websync [OPTION...]
OPTION
    -t
    --test
        测试模式，只打印而不执行同步命令
    --src=
        本地源路径
    --dst=
        远程目的路径
    --remote
        查看所有远程服务器配置
    --remote=
        指定远程服务器，多个服务器就用多个 --remote= 参数指定
        忽略配置项 project 里的 remote 配置项
    --force 
        项目没有 .git 时可以强制同步
    --init
        初始化配置文件
    -v
        输出 rsync 命令
    -h
    --help
        帮助
DOC;
    echo PHP_EOL;
    exit;
}

// 配置文件路径
$pathConf = getenv('HOME') . '/.websync.php';

// 初始化配置
if (isset($opt['init'])) {
    if (file_exists($pathConf)) {
        echo "{$pathConf} 已经存在" . PHP_EOL;
        exit;
    }

    system(sprintf('cp %s %s', __DIR__ . '/.websync.example.php', $pathConf));

    echo '配置文件初始化完成，请继续编辑配置:' . PHP_EOL;
    echo 'vim ~/.websync.php' . PHP_EOL;
    exit;
}

// 检查配置文件
if (!file_exists($pathConf)) {
    echo '请先初始化并编辑配置文件:' . PHP_EOL;
    echo 'websync --init' . PHP_EOL;
    exit;
}
$conf = include($pathConf);

// 显示远程配置
if (isset($opt['remote']) && $opt['remote'] === false) {
    if (empty($conf['remote'])) {
        echo '远程配置不存在' . PHP_EOL;
        exit;
    }

    print_r($conf['remote']);
    exit;
}

$pwd = getcwd();

// 检查项目配置 projects
$projectName = basename($pwd);
if (!isset($conf['projects'][$projectName])) {
    echo "不存在项目配置 {$projectName}" . PHP_EOL;
    echo "vim ~/.websync.php 配置 projects.{$projectName}" . PHP_EOL;
    exit;
}
$projectConf = $conf['projects'][$projectName];

// 检查有无为项目指定 remote
$remoteName = !empty($opt['remote']) ? (is_array($opt['remote']) ? $opt['remote'] : [$opt['remote']]) : $projectConf['remote'];
if (empty($remoteName)) {
    echo '没有指定 remote' . PHP_EOL;
    echo '使用 --remote=REMOTE 指定' . PHP_EOL;
    echo "或 vim ~/.websync.php 配置 projects.{$projectName}.remote" . PHP_EOL;
    exit;
}
$remoteName = array_unique($remoteName);

// 检查 remote 是否存在
$remoteConf = $conf['remote'];
foreach ($remoteName as $curRemoteName) {
    if (!isset($remoteConf[$curRemoteName])) {
        echo "远程配置 {$remoteName} 不存在" . PHP_EOL;
        echo 'vim ~/.websync.php 配置 remote' . PHP_EOL;
        exit;
    }
}

// 强制同步非 git 项目
$force = isset($opt['force']) ?: false;
if (!file_exists('.git') && !$force) {
    echo '项目不支持 git，强制同步可使用参数 --force' . PHP_EOL;
    exit;
}

// 用命令查询 .gitignore 里的忽略列表
$ignores = [];
if (file_exists('.gitignore')) {
    exec('git clean -ndX', $ignoresRaw);
    foreach ($ignoresRaw as $v) {
        $ignores[] = trim(str_replace('Would remove ', '', $v));
    }
}

// 配置文件里的全局忽略列表
if (!empty($conf['global']['ignore'])) {
    $ignores = array_merge($ignores, $conf['global']['ignore']);
}

// 配置文件里的项目忽略列表
if (!empty($projectConf['ignore'])) {
    $ignores = array_merge($ignores, $projectConf['ignore']);
}

// 构造用于忽略文件的参数
$excludes = [];
foreach ($ignores as $ignore) {
    $ignore = trim($ignore);
    $excludes[] = "--exclude={$ignore}";
}
$excludes = implode(' ', $excludes);

foreach ($remoteName as $curRemoteName) {
    // 当前使用的远程配置
    $curRemoteConf = $remoteConf[$curRemoteName];

    $src = isset($opt['src']) ? $opt['src'] : $pwd . '/';
    $dst = isset($opt['dst']) ? $opt['dst'] : $curRemoteConf['dst'] . '/' . $projectName;

    // 重置文件权限
    $chown = '';
    if (!empty($curRemoteConf['chown'])) {
        $chown = "--chown={$curRemoteConf['chown']}";
    }

    $cmd = "rsync -avz --delete --progress {$chown} {$excludes} {$src} {$curRemoteConf['hostname']}:{$dst}";

    if (isset($opt['t']) || isset($opt['test']) || isset($opt['v'])) {
        echo $cmd . PHP_EOL;
    }

    // 非测试模式才执行命令
    if (!isset($opt['t']) && !isset($opt['test'])) {
        system($cmd);
    }
}
