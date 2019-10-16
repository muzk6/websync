#! /usr/bin/env php
<?php

/**
 * 基于 rsync 的本地、远程 双向同步工具
 */

const VERSION = '2.1';

if (version_compare(phpversion(), '7.1', '<')) {
    echo 'PHP版本必须 >=7.1' . PHP_EOL;
    exit;
}

$opt = getopt('h::t::g::', ['init::', 'test::', 'help::', 'global::'], $ind);

$isHelp = isset($opt['h']) || isset($opt['help']);
$isInit = isset($opt['init']);
$isTest = isset($opt['t']) || isset($opt['test']);
$isGlobal = isset($opt['g']) || isset($opt['global']);
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
    -g
    --global
        remote 新增/编辑 全局远程服务器
    -h
    --help
        帮助
DOC;
    echo PHP_EOL;
    exit;
}

// 远程配置
if ($isGlobal) {
    setGlobal($action);
    exit;
}

// 配置文件路径
$pathConf = './.websyncrc.php';

// 初始化配置
if ($isInit) {
    initSetting($pathConf);
    exit;
}

// 检查配置文件
if (!file_exists($pathConf)) {
    echo '请先初始化并编辑配置文件:' . PHP_EOL;
    echo 'websync --init' . PHP_EOL;
    exit;
}
$conf = include($pathConf);

// 检查版本号
if (!checkVersion($conf)) {
    exit;
}

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
    if (!isset($curRemoteConf)) { // 先检查当前目录的配置
        $pathGlobal = getenv('HOME') . '/.websyncrc.php';
        $globalConf = is_file($pathGlobal) ? include($pathGlobal) : [];

        $curRemoteConf = &$globalConf['remotes'][$hostname];
        if (!isset($curRemoteConf)) { // 再检查全局配置
            echo "远程服务器 {$hostname} 不存在" . PHP_EOL;
            echo 'vim .websyncrc.php 配置 remotes' . PHP_EOL;
            exit;
        }
    }

    $localPath = './';
    $remotePath = "{$curRemoteConf['@']}:"
        . rtrim($curRemoteConf['path'], '/')
        . '/'
        . basename(getcwd())
        . '/';
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
                function ($localPath, $remotePath) use ($hostname, $conf) {
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

/**
 * 初始化配置
 * @param string $pathConf
 */
function initSetting(string $pathConf)
{
    if (file_exists($pathConf)) {
        echo "{$pathConf} 已经存在" . PHP_EOL;
        exit;
    }

    $sampleConf = file_get_contents(__DIR__ . '/.websyncrc.example.php');

    $readHostname = readline("远程服务器名: [hostname1] \n");
    $sampleConf = str_replace('hostname1', $readHostname ?: 'hostname1', $sampleConf);

    // 读取 全局配置-远程服务器 如果没有全局配置则自动新建
    $pathGlobal = getenv('HOME') . '/.websyncrc.php';
    if (is_file($pathGlobal)) {
        $globalConf = include($pathGlobal);
        if (!isset($globalConf['remotes'][$readHostname])) {
            setGlobal('remote', ['hostname' => $readHostname]);
        }
    } else {
        setGlobal('remote', ['hostname' => $readHostname]);
    }

    // 重新加载全局配置文件
    $globalConf = include($pathGlobal);
    $globalHost = $globalConf['remotes'][$readHostname];

    $sampleConf = str_replace("websync@host", $globalHost['@'], $sampleConf);
    $sampleConf = str_replace('ssh -p 22', $globalHost['command'], $sampleConf);
    $sampleConf = str_replace('/path/to/', $globalHost['path'], $sampleConf);
    $sampleConf = str_replace('websync:websync', $globalHost['chown'], $sampleConf);

    echo $sampleConf . PHP_EOL;
    if (strtolower(readline("请确认 [Y/n] \n") ?: 'Y') != 'y') {
        exit;
    }

    file_put_contents($pathConf, $sampleConf);
    echo "{$pathConf} 配置文件初始化完成，可随时修改" . PHP_EOL;
}

/**
 * 远程配置
 * @param string $action
 * @param array $opt
 */
function setGlobal(string $action, $opt = [])
{
    $pathGlobal = getenv('HOME') . '/.websyncrc.php';
    $globalConf = is_file($pathGlobal) ? include($pathGlobal) : [];

    switch ($action) {
        case 'remote':
            $readHostname = $opt['hostname'] ?? readline("远程服务器名: [hostname1] \n");
            if (isset($globalConf['remotes'][$readHostname])) {
                if (strtolower(readline("{$readHostname} 配置已存在，是不继续修改？[Y/n] \n") ?: 'Y') != 'y') {
                    exit;
                }
                $globalHost = $globalConf['remotes'][$readHostname];
            } else {
                $globalHost = [];
            }

            $ssh = $globalHost['@'] ?? "websync@{$readHostname}";
            $globalHost['@'] = readline("远程SSH: [{$ssh}] \n") ?: $ssh;

            $command = $globalHost['command'] ?? 'ssh -p 22';
            $globalHost['command'] = readline("远程命令: [{$command}] \n") ?: $command;

            $path = $globalHost['path'] ?? '/path/to/';
            $globalHost['path'] = readline("远程根目录: [{$path}] \n") ?: $path;

            $chown = $globalHost['chown'] ?? 'websync:websync';
            $globalHost['chown'] = readline("远程chown: [{$chown}] \n") ?: $chown;

            $globalConf['remotes'][$readHostname] = $globalHost;
            echo var_export($globalConf['remotes'], true) . PHP_EOL;
            if (strtolower(readline("请确认 [Y/n] \n") ?: 'Y') != 'y') {
                exit;
            }

            file_put_contents($pathGlobal, "<?php\n    return " . var_export($globalConf, true) . ';' . PHP_EOL);
            echo "{$pathGlobal} 全局配置设置完成，可随时修改" . PHP_EOL;

            break;
        default:
            echo '全局配置仅支持: remote' . PHP_EOL;
            break;
    }
}

/**
 * 检查版本号
 * @param array $conf
 * @return bool
 */
function checkVersion(array $conf)
{
    if (empty($conf['version']) || !version_compare(VERSION, $conf['version'][0], $conf['version'][1])) {
        echo '配置文件版本过低，请先备份好再重新生成配置文件' . PHP_EOL;
        return false;
    }

    return true;
}
