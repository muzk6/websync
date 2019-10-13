#! /usr/bin/env php
<?php

/**
 * Web 项目文件同步
 * 所有 project 以 remote 为对象，project 里的配置项不能在 remote 里有，也不能在命令行指定
 */

$opt = getopt('h::v::t::', ['remote::',
    'force::', 'test::', 'init::', 'help::',
], $ind);

$isHelp = isset($opt['h']) || isset($opt['help']);
$isInit = isset($opt['init']);
$isTest = isset($opt['t']) || isset($opt['test']);
$action = $argv[$ind] ?? 'push';
$hostname = $argv[$ind + 1] ?? null;

if ($isHelp) {
    echo <<<DOC
USAGE
    websync [OPTION...] [push/pull]
ARGV
    push 本地推上远程(默认)
    pull 远程拉入本地
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
$pathConf = './.websyncrc.php';

// 初始化配置
if ($isInit) {
    if (file_exists($pathConf)) {
        echo "{$pathConf} 已经存在" . PHP_EOL;
        exit;
    }

    system(sprintf('cp %s %s', __DIR__ . '/.websyncrc.example.php', $pathConf));

    echo '配置文件初始化完成，请继续编辑配置:' . PHP_EOL;
    echo 'vim .websyncrc.php' . PHP_EOL;
    exit;
}

// 检查配置文件
if (!file_exists($pathConf)) {
    echo '请先初始化并编辑配置文件:' . PHP_EOL;
    echo 'websync --init' . PHP_EOL;
    exit;
}
$conf = include($pathConf);

// 构造用于非排除文件的参数
$includeParam = [];
foreach ($conf['include'] as $v) {
    $v = trim($v);
    $includeParam[] = "--include={$v}";
}
$includeParam = implode(' ', $includeParam);

// 构造用于排除文件的参数
$excludeParam = [];
foreach ($conf['exclude'] as $ignore) {
    $ignore = trim($ignore);
    $excludeParam[] = "--exclude={$ignore}";
}
$excludeParam = implode(' ', $excludeParam);

// 执行 rsync 命令
$execRsync = function ($hostname, callable $actionHook) use ($conf, $isTest, $includeParam, $excludeParam) {
    // 检查 remote 是否存在
    $curRemoteConf = &$conf['remotes'][$hostname];
    if (!isset($curRemoteConf)) {
        echo "远程配置 {$hostname} 不存在" . PHP_EOL;
        echo 'vim .websyncrc.php 配置 remotes' . PHP_EOL;
        exit;
    }

    $localPath = './';
    $remotePath = "{$curRemoteConf['@']}:{$curRemoteConf['path']}";
    list($chown, $src, $dst) = $actionHook($localPath, $remotePath);

    $command = '';
    if (!empty($curRemoteConf['command'])) {
        $command = "-e \"{$curRemoteConf['command']}\"";
    }

    $cmd = "rsync -avz --delete --progress {$chown} {$command} {$includeParam} {$excludeParam} {$src} {$dst}";

    if ($isTest) {
        $cmd = preg_replace('/^rsync/', 'rsync -n', $cmd);
        echo $cmd . PHP_EOL;
    }

    system($cmd);
};

// 执行 action
switch ($action) {
    case 'push':
        if ($hostname) {
            $hostnameList = explode(',', $hostname);
        } else {
            $hostnameList = $conf['push'];
        }

        foreach ($hostnameList as $hostname) {
            $hostname = trim($hostname);
            $execRsync($hostname, function ($localPath, $remotePath) use ($hostname) {
                // 重置文件归属
                $chown = '--chown=websync:websync';
                if (!empty($conf['remotes'][$hostname]['chown'])) {
                    $chown = "--chown={$conf['remotes'][$hostname]['chown']}";
                }

                return [$chown, $localPath, $remotePath];
            });
        }
        break;
    case 'pull':
        $execRsync($hostname ? trim($hostname) : $conf['pull'], function ($localPath, $remotePath) {
            return ['', $remotePath, $localPath];
        });
        break;
    default:
        echo '不存在的 action, websync -h 查看帮助' . PHP_EOL;
        exit;
        break;
}
