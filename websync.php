#! /usr/bin/env php
<?php

/**
 * 基于 rsync 的本地、远程 双向同步工具
 */

const VERSION = '2.2.4'; // 程序版本
const VERSION_CONFIG = '2.3'; // 配置文件版本

if (version_compare(phpversion(), '7.1', '<')) {
    echo 'PHP版本必须 >=7.1' . PHP_EOL;
    exit;
}

$opt = getopt('h::t::g::d::', ['init::', 'test::', 'help::', 'delete::', 'global::', 'version::'], $ind);

$isHelp = isset($opt['h']) || isset($opt['help']);
$isInit = isset($opt['init']);
$isTest = isset($opt['t']) || isset($opt['test']);
$isGlobal = isset($opt['g']) || isset($opt['global']);
$isVersion = isset($opt['version']);
$isDelete = isset($opt['d']) || isset($opt['delete']);
$action = $argv[$ind] ?? 'push';
$hostname = $argv[$ind + 1] ?? null;

// 查看帮助
if ($isHelp) {
    if ($action == 'config') {
        echo file_get_contents(__DIR__ . '/.websyncrc.example.php') . PHP_EOL;
    } else {
        help();
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
        echo getenv('HOME') . '/.websyncrc.global.php' . PHP_EOL;
    } else {
        setGlobal($action, $isDelete ? ['isDelete' => 1] : []);
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
    if (strtolower(readline2("配置文件不存在，是否现在进行初始化？[Y/n] \n") ?: 'Y') == 'y') {
        initSetting($pathConf);
    }
    exit;
}

// 加载当前目录下的配置文件
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
            if (strtolower(readline2("远程服务器 {$hostname} 不存在，是否现在进行配置？[Y/n] \n") ?: 'Y') == 'y') {
                setGlobal('remote', ['hostname' => $hostname]);
            }
            exit;
        }
    }

    // 本机、远程 路径
    $localPath = './';
    $remotePath = "{$curRemoteConf['@']}:"
        . rtrim($curRemoteConf['path'], '/')
        . '/'
        . basename(getcwd())
        . '/';
    list($chown, $src, $dst) = $actionHook($localPath, $remotePath);

    // ssh命令，通常用于指定端口
    $command = '';
    if (!empty($curRemoteConf['command'])) {
        $command = "-e \"{$curRemoteConf['command']}\"";
    }

    // rsync 参数 Options
    $options = '';
    if (!empty($conf['options'])) {
        $options = $conf['options'];
    }

    // 完整 rsync 命令
    $cmd = "rsync {$options} {$chown} {$command} {$includeParam} {$excludeParam} {$src} {$dst}";

    // 测试模式
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
                    $globalConf = getGlobalConfig();

                    // 重置文件归属
                    $chown = '--chown=websync:websync';
                    if (!empty($globalConf['remotes'][$hostname]['chown'])) {
                        $chown = "--chown={$globalConf['remotes'][$hostname]['chown']}";
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
        if (strtolower(readline2("参数错误，是否查看帮助？[Y/n] \n") ?: 'Y') == 'y') {
            help();
        }
        exit;
        break;
}

/**
 * 帮助
 */
function help()
{
    echo PHP_EOL;
    echo /** @lang text */
    <<<DOC
基于 rsync 的本地、远程 双向同步工具

USAGE:
    websync [push] [<hostname>...]
    websync pull [<hostname>]
    websync -t
    websync --init
    websync -g ( config | ([-d] remote) )
    websync -h [config]
    websync --version
   
OPTIONS:
    push
        本地->远程 同步，默认动作，支持多个，没有指定 hostname 时读取配置
        
    pull
        远程->本地 同步，没有指定 hostname 时读取配置
       
    -t, --test
        测试模式，只打印而不执行同步命令

    --init
        初始化配置文件

    -g, --global
        全局配置
        `config` 查看全局配置文件的路径
        `remote` 设置全局配置里的远程服务器
        
    -d, --delete
        删除配置
        `-g -d remote` 删除全局的远程服务器配置
        
    -h, --help
        查看帮助
        `config` 查看配置说明
        
    --version
        查看版本
DOC;
    echo PHP_EOL . PHP_EOL;
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
    if (strtolower(readline2("请确认本项目的配置 [Y/n] \n") ?: 'Y') != 'y') {
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

            if (isset($opt['isDelete'])) {
                $hostname = inputHostname($globalConf, $isSelect, false, "请选择要删除的远程服务器配置项:\n");
                if (strtolower(readline2("请确认是否要删除 {$hostname} [y/N] \n") ?: 'N') != 'y') {
                    exit;
                }

                unset($globalConf['remotes'][$hostname]);
            } else {
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
                        && strtolower(readline2("{$hostname} 配置已存在，是否继续修改？[Y/n] \n") ?: 'Y') != 'y') {
                        exit;
                    }
                    $globalHost = $globalConf['remotes'][$hostname];
                } else {
                    $globalHost = [];
                }

                if ($globalHost) {
                    echo "当前 {$hostname} 配置如下:" . PHP_EOL;
                    echo var_export($globalHost, true) . PHP_EOL . PHP_EOL;
                }

                // 修改配置
                echo '请按提示输入新值，直接回车则跳过' . PHP_EOL . PHP_EOL;

                $ssh = $globalHost['@'] ?? "websync@{$hostname}";
                $globalHost['@'] = readline2("远程SSH: [{$ssh}] \n") ?: $ssh;

                $command = $globalHost['command'] ?? 'ssh -p 22';
                $globalHost['command'] = readline2("远程命令: [{$command}] \n") ?: $command;

                $path = $globalHost['path'] ?? '/path/to';
                $globalHost['path'] = readline2("远程根目录: [{$path}] \n") ?: $path;

                $chown = $globalHost['chown'] ?? 'websync:websync';
                $globalHost['chown'] = readline2("远程chown: [{$chown}] \n") ?: $chown;

                // 询问是否符合设置的配置
                $globalConf['remotes'][$hostname] = $globalHost;
                echo var_export($globalConf['remotes'], true) . PHP_EOL;
                if (strtolower(readline2("请确认全局远程服务器配置 [Y/n] \n") ?: 'Y') != 'y') {
                    exit;
                }
            }

            // 写入配置
            $pathGlobal = getenv('HOME') . '/.websyncrc.global.php';
            file_put_contents($pathGlobal, "<?php\n\nreturn " . var_export($globalConf, true) . ';' . PHP_EOL);
            echo "{$pathGlobal} 设置完成，可随时修改配置文件" . PHP_EOL;

            break;
        default:
            if (strtolower(readline2("参数错误，是否查看帮助？[Y/n] \n") ?: 'Y') == 'y') {
                help();
            }
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
 * 读取全局配置
 * @return array
 */
function getGlobalConfig(): array
{
    $pathGlobal = getenv('HOME') . '/.websyncrc.global.php';
    $globalConf = is_file($pathGlobal) ? include($pathGlobal) : [];

    return $globalConf;
}

/**
 * 用户 选择/输入 远程服务器
 * @param array $globalConf
 * @param bool $isSelect 是否以选择方式得到服务器名
 * @param bool $allowCustom 是否允许自定义输入
 * @param string $prompt 选择提示
 * @return string
 */
function inputHostname(array $globalConf, bool &$isSelect = false, bool $allowCustom = true, string $prompt = "请选择远程服务器:\n"): string
{
    $globalRemotes = isset($globalConf['remotes']) ? array_keys($globalConf['remotes']) : []; // 所有远程服务器名

    if (count($globalRemotes)) {
        // 选择/输入 远程服务器名
        $remoteHostname = [];
        foreach ($globalRemotes as $k => $v) {
            $remoteHostname[] = ($k + 1) . ". {$v}";
        }

        if ($allowCustom) {
            $remoteHostname[] = (count($remoteHostname) + 1) . '. 其它';
        }

        // 选择
        $hostnameIndex = readline2($prompt . implode("\n", $remoteHostname) . PHP_EOL) - 1;
        while (!isset($remoteHostname[$hostnameIndex])) {
            $hostnameIndex = readline2("不在范围内，请重新选择:\n" . implode("\n", $remoteHostname) . PHP_EOL) - 1;
        }

        // 选择了 最后一项-其它，自行输入
        if ($allowCustom && $hostnameIndex == (count($remoteHostname) - 1)) {
            $hostname = readline2("远程服务器名: [hostname1] \n");
        } else {
            // 选择
            $isSelect = true;
            $hostname = $globalRemotes[$hostnameIndex];
        }
    } else {
        if ($allowCustom) {
            // 自行输入
            $hostname = readline2("远程服务器名: [hostname1] \n");
        } else {
            echo '没有服务器可选择' . PHP_EOL;
            exit;
        }
    }

    return $hostname;
}

/**
 * 兼容 readline("\n") 换行不生效的环境
 * @param string $prompt
 * @return string
 */
function readline2(string $prompt): string
{
    echo $prompt;
    return readline();
}
