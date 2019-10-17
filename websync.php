#! /usr/bin/env php
<?php

/**
 * 基于 rsync 的本地、远程 双向同步工具
 */

const VERSION = '2.2.2'; // 程序版本
const VERSION_CONFIG = '2.2'; // 配置文件版本

if (version_compare(phpversion(), '7.1', '<')) {
    echo 'PHP版本必须 >=7.1' . PHP_EOL;
    exit;
}

$opt = getopt('h::t::g::', ['init::', 'test::', 'help::', 'global::', 'version::'], $ind);

$isHelp = isset($opt['h']) || isset($opt['help']);
$isInit = isset($opt['init']);
$isTest = isset($opt['t']) || isset($opt['test']);
$isGlobal = isset($opt['g']) || isset($opt['global']);
$isVersion = isset($opt['version']);
$action = $argv[$ind] ?? 'push';
$hostname = $argv[$ind + 1] ?? null;

// 查看帮助
if ($isHelp) {
    if ($action == 'config') {
        echo file_get_contents(__DIR__ . '/.websyncrc.example.php') . PHP_EOL;
    } else {
        echo PHP_EOL;
        echo <<<DOC
基于 rsync 的本地、远程 双向同步工具

USAGE:
    websync push [hostname1[,hostname2]]
    本地->远程，默认动作，没有指定 hostname 时读取配置
    
    websync pull [hostname1]
    远程->本地，没有指定 hostname 时读取配置
    
    websync --init
    初始化配置文件
    
    websync -t
    测试模式，只打印而不执行同步命令
    
    websync -g config
    查看全局配置文件的路径
    
    websync -g remote
    设置全局配置里的远程服务器
    
    websync -h config
    查看配置说明
    
    websync -h
    查看帮助
    
    websync --version
    查看 websync 版本
   
OPTIONS:
    --init          初始化配置文件
    -t, --test      测试模式
    -g, --global    全局配置
    -h, --help      帮助
    --version       版本
DOC;
        echo PHP_EOL;
        echo PHP_EOL;
    }

    exit;
}

// 查看程序版本
if ($isVersion) {
    echo 'websync version ' . VERSION . PHP_EOL;
    echo '.websyncrc.php version minimum support ' . VERSION_CONFIG . PHP_EOL;
    exit;
}

// 远程配置
if ($isGlobal) {
    if ($action == 'config') {
        echo getenv('HOME') . '/.websyncrc.php' . PHP_EOL;
    } else {
        setGlobal($action);
    }
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
        $globalConf = getGlobalConfig();

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
        echo '参数错误，请参考 websync -h' . PHP_EOL;
        exit;
        break;
}

/**
 * 初始化配置
 * @param string $pathConf
 */
function initSetting(string $pathConf): void
{
    if (file_exists($pathConf)) {
        echo "{$pathConf} 已经存在" . PHP_EOL;
        exit;
    }

    $globalConf = getGlobalConfig();
    $hostname = inputHostname($globalConf);

    // 全局配置: 读取 全局配置-远程服务器 如果没有全局配置则自动新建
    if (!isset($globalConf['remotes'][$hostname])) {
        setGlobal('remote', ['hostname' => $hostname]);
    }

    // 本项目的配置
    $sampleConf = include(__DIR__ . '/.websyncrc.example.php');
    $sampleConf['remotes'] = [];
    if ($hostname) {
        $sampleConf['pull'] = $hostname;
        $sampleConf['push'] = [$hostname];
    }

    echo var_export($sampleConf, true) . PHP_EOL;
    if (strtolower(readline("请确认本项目的配置 [Y/n] \n") ?: 'Y') != 'y') {
        exit;
    }

    file_put_contents($pathConf, "<?php\n\nreturn " . var_export($sampleConf, true) . ';' . PHP_EOL);
    echo "{$pathConf} 初始化完成，可随时修改配置文件" . PHP_EOL;
}

/**
 * 远程配置
 * @param string $action
 * @param array $opt
 */
function setGlobal(string $action, $opt = []): void
{
    switch ($action) {
        case 'remote':
            $globalConf = getGlobalConfig();
            $isSelect = false; // 是否以选择方式得到服务器名

            if (isset($opt['hostname'])) {
                // 已经指定远程服务器名
                $hostname = $opt['hostname'];
            } else {
                // 未指定时由用户 选择/输入
                $hostname = inputHostname($globalConf, $isSelect);
            }

            // 检查配置是否存在并询问修改与否
            if (isset($globalConf['remotes'][$hostname])) {
                if (!$isSelect
                    && strtolower(readline("{$hostname} 配置已存在，是否继续修改？[Y/n] \n") ?: 'Y') != 'y') {
                    exit;
                }
                $globalHost = $globalConf['remotes'][$hostname];
            } else {
                $globalHost = [];
            }

            // 修改配置
            $ssh = $globalHost['@'] ?? "websync@{$hostname}";
            $globalHost['@'] = readline("远程SSH: [{$ssh}] \n") ?: $ssh;

            $command = $globalHost['command'] ?? 'ssh -p 22';
            $globalHost['command'] = readline("远程命令: [{$command}] \n") ?: $command;

            $path = $globalHost['path'] ?? '/path/to';
            $globalHost['path'] = readline("远程根目录: [{$path}] \n") ?: $path;

            $chown = $globalHost['chown'] ?? 'websync:websync';
            $globalHost['chown'] = readline("远程chown: [{$chown}] \n") ?: $chown;

            // 询问是否符合设置的配置
            $globalConf['remotes'][$hostname] = $globalHost;
            echo var_export($globalConf['remotes'], true) . PHP_EOL;
            if (strtolower(readline("请确认全局远程服务器配置 [Y/n] \n") ?: 'Y') != 'y') {
                exit;
            }

            // 写入配置
            $pathGlobal = getenv('HOME') . '/.websyncrc.php';
            file_put_contents($pathGlobal, "<?php\n\nreturn " . var_export($globalConf, true) . ';' . PHP_EOL);
            echo "{$pathGlobal} 设置完成，可随时修改配置文件" . PHP_EOL;

            break;
        default:
            echo '参数错误，请参考 websync -h' . PHP_EOL;
            break;
    }
}

/**
 * 检查版本号
 * @param array $conf
 * @return bool
 */
function checkVersion(array $conf): bool
{
    if (empty($conf['version'])
        || !is_string($conf['version'])
        || !version_compare($conf['version'], VERSION_CONFIG, '>=')) {
        echo '配置文件版本过低，请先备份好再重新生成配置文件' . PHP_EOL;
        return false;
    }

    return true;
}

/**
 * 取全局配置
 * @return array
 */
function getGlobalConfig(): array
{
    $pathGlobal = getenv('HOME') . '/.websyncrc.php';
    $globalConf = is_file($pathGlobal) ? include($pathGlobal) : [];

    return $globalConf;
}

/**
 * 用户 选择/输入 远程服务器
 * @param array $globalConf
 * @param bool $isSelect 是否以选择方式得到服务器名
 * @return string
 */
function inputHostname(array $globalConf, bool &$isSelect = false): string
{
    $globalRemotes = array_keys($globalConf['remotes']) ?? []; // 所有远程服务器名

    if (count($globalRemotes)) {
        // 选择/输入 远程服务器名
        $remoteHostname = [];
        foreach ($globalRemotes as $k => $v) {
            $remoteHostname[] = ($k + 1) . ". {$v}";
        }
        $remoteHostname[] = (count($remoteHostname) + 1) . '. 其它';

        // 选择
        $hostnameIndex = readline("请选择远程服务器:\n" . implode("\n", $remoteHostname) . PHP_EOL) - 1;
        while (!isset($remoteHostname[$hostnameIndex])) {
            $hostnameIndex = readline("不在范围内，请重新选择:\n" . implode("\n", $remoteHostname) . PHP_EOL) - 1;
        }

        // 选择了 最后一项-其它，自行输入
        if ($hostnameIndex == (count($remoteHostname) - 1)) {
            $hostname = readline("远程服务器名: [hostname1] \n");
        } else {
            // 选择
            $isSelect = true;
            $hostname = $globalRemotes[$hostnameIndex];
        }
    } else {
        // 自行输入
        $hostname = readline("远程服务器名: [hostname1] \n");
    }

    return $hostname;
}
