#! /usr/bin/env php
<?php

/**
 * Web 项目文件同步
 * 所有 project 以 remote 为对象，project 里的配置项不能在 remote 里有，也不能在命令行指定
 */

$opt = getopt('h::v::t::', ['remote::',
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
    --remote
        查看所有远程服务器配置
    --remote=
        指定远程服务器，多个服务器就用多个 --remote= 参数指定
        可以不需要在配置文件里的配置 projects
        优先级比配置文件指定的高
    --init
        初始化配置文件
    -h
    --help
        帮助
DOC;
    echo PHP_EOL;

    echo 'CONFIG' . PHP_EOL;
    echo file_get_contents(__DIR__ . '/.websync.example.php') . PHP_EOL;
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
if (empty($opt['remote']) && !isset($conf['projects'][$projectName])) {
    echo "不存在项目配置 {$projectName}" . PHP_EOL;
    echo "vim ~/.websync.php 配置 projects.{$projectName}" . PHP_EOL;
    exit;
}
$projectConf = $conf['projects'][$projectName] ?? [];

// 检查有无为项目指定 remote
$remote = !empty($opt['remote'])
    ? (is_array($opt['remote']) ? $opt['remote'] : [$opt['remote']])
    : ($projectConf['remote'] ?? '');
if (empty($remote)) {
    echo '没有指定 remote' . PHP_EOL;
    echo '使用 --remote=REMOTE 指定' . PHP_EOL;
    echo "或 vim ~/.websync.php 配置 projects.{$projectName}.remote" . PHP_EOL;
    exit;
}
$remote = array_unique($remote);

// 检查 remote 是否存在
$remoteConf = $conf['remote'];
foreach ($remote as $curRemoteName) {
    if (!isset($remoteConf[$curRemoteName])) {
        echo "远程配置 {$curRemoteName} 不存在" . PHP_EOL;
        echo 'vim ~/.websync.php 配置 remote' . PHP_EOL;
        exit;
    }
}

// 配置文件里的 全局-非排除 列表
$include = [];
if (!empty($conf['global']['include'])) {
    $include = array_merge($include, $conf['global']['include']);
}

// 配置文件里的 项目-非排除 列表
if (!empty($projectConf['include'])) {
    $include = array_merge($include, $projectConf['include']);
}

// 构造用于非排除文件的参数
$includeParam = [];
foreach ($include as $v) {
    $v = trim($v);
    $includeParam[] = "--include={$v}";
}
$includeParam = implode(' ', $includeParam);

// 配置文件里的 全局-排除 列表
$ignores = [];
if (!empty($conf['global']['exclude'])) {
    $ignores = array_merge($ignores, $conf['global']['exclude']);
}

// 配置文件里的 项目-排除 列表
if (!empty($projectConf['exclude'])) {
    $ignores = array_merge($ignores, $projectConf['exclude']);
}

// 构造用于排除文件的参数
$excludeParam = [];
foreach ($ignores as $ignore) {
    $ignore = trim($ignore);
    $excludeParam[] = "--exclude={$ignore}";
}
$excludeParam = implode(' ', $excludeParam);

foreach ($remote as $curRemoteName) {
    // 当前使用的远程配置
    $curRemoteConf = $remoteConf[$curRemoteName];

    $src = $pwd . '/';

    $curProjectName = $projectName;
    if (!empty($projectConf['alias'])) {
        if (is_string($projectConf['alias'])) {
            $curProjectName = $projectConf['alias'];
        } elseif (isset($projectConf['alias'][$curRemoteName])) {
            $curProjectName = $projectConf['alias'][$curRemoteName];
        }
    }
    $dst = $curRemoteConf['dst'] . '/' . $curProjectName;

    // 重置文件权限
    $chown = '';
    if (!empty($curRemoteConf['chown'])) {
        $chown = "--chown={$curRemoteConf['chown']}";
    }

    $cmd = "rsync -avz --delete --progress {$chown} {$includeParam} {$excludeParam} {$src} {$curRemoteConf['hostname']}:{$dst}";

    $isTest = isset($opt['t']) || isset($opt['test']);
    if ($isTest) {
        echo $cmd . PHP_EOL;
        system(preg_replace('/^rsync/', 'rsync -n', $cmd));
    } else {
        system($cmd);
    }
}
