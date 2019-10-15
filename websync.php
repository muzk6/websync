#! /usr/bin/env php
<?php

/**
 * 基于 rsync 的本地、远程 双向同步工具
 */

if (version_compare(phpversion(), '7.1', '<')) {
    echo 'PHP版本必须 >=7.1' . PHP_EOL;
    exit;
}

$opt = getopt('h::t::', ['init::', 'test::', 'help::'], $ind);

$isHelp = isset($opt['h']) || isset($opt['help']);
$isInit = isset($opt['init']);
$isTest = isset($opt['t']) || isset($opt['test']);
$action = $argv[$ind] ?? 'push';
$hostname = $argv[$ind + 1] ?? null;

if ($isHelp) {
    echo <<<DOC
基于 rsync 的本地、远程 双向同步工具
USAGE
    websync [OPTION...] [push/pull] [hostname1]
ARGV
    push [hostname1[,hostname2]] 本地推上远程(默认动作)，没有指定 hostname 时读取配置
    pull [hostname1] 远程拉入本地，没有指定 hostname 时读取配置
OPTION
    --init
        初始化配置文件
    -t
    --test
        测试模式，只打印而不执行同步命令
    -h
    --help
        帮助
DOC;
    echo PHP_EOL;
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

    $sampleConf = file_get_contents(__DIR__ . '/.websyncrc.example.php');

    $readHostname = readline("远程服务器名: [hostname1]\n");
    $sampleConf = str_replace('hostname1', $readHostname ?: 'hostname1', $sampleConf);
    $sampleConf = str_replace("websync@host", readline("远程SSH: [websync@{$readHostname}]\n") ?: "websync@{$readHostname}", $sampleConf);
    $sampleConf = str_replace('22', readline("远程SSH 端口: [22]\n") ?: 22, $sampleConf);
    $sampleConf = str_replace('/path/to/', readline("远程目标目录: [/path/to/]\n") ?: '/path/to/', $sampleConf);
    $sampleConf = str_replace('websync:websync', readline("远程文件所属: [websync:websync]\n") ?: 'websync:websync', $sampleConf);

    echo $sampleConf . PHP_EOL;
    if (strtolower(readline("请确认 [Y/n]\n") ?: 'Y') != 'y') {
        exit;
    }

    file_put_contents($pathConf, $sampleConf);
    echo "{$pathConf} 配置文件初始化完成，可随时修改" . PHP_EOL;
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

// 执行动作
switch ($action) {
    case 'push':
        if ($hostname) {
            $hostnameList = explode(',', $hostname);
        } else {
            $hostnameList = $conf['push'];
        }

        foreach ($hostnameList as $hostname) {
            $hostname = trim($hostname);
            $execRsync($hostname,
                function ($localPath, $remotePath) use ($hostname) {
                    // 重置文件归属
                    $chown = '--chown=websync:websync';
                    if (!empty($conf['remotes'][$hostname]['chown'])) {
                        $chown = "--chown={$conf['remotes'][$hostname]['chown']}";
                    }

                    return [$chown, $localPath, $remotePath];
                }
            );
        }
        break;
    case 'pull':
        $execRsync($hostname ? trim($hostname) : $conf['pull'],
            function ($localPath, $remotePath) {
                return ['', $remotePath, $localPath];
            }
        );
        break;
    default:
        echo '不存在的动作，请使用 websync -h 查看帮助' . PHP_EOL;
        exit;
        break;
}
