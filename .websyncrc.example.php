<?php

return [
    // 配置文件的版本号
    'version' => '2.3',
    // rsync --include=, 不排除的列表
    'include' => [],
    // rsync --exclude=, 排除列表
    'exclude' => [
        '.DS_Store',
        '.git/',
        '.idea/',
    ],
    // rsync 参数 Options
    'options' => '-avz --delete --progress',
    // 远程服务器
    'remotes' => [
        // 自定义服务器名称，优化级比全局高，不存在时使用全局配置
        'hostname1' => [
            // SSH
            '@' => 'websync@host',
            // rsync -e, 例如指定 ssh 端口
            'command' => 'ssh -p 22',
            // 远程根目录
            'path' => '/path/to',
            // rsync --chown=, 对所有同步的文件进行 chown, 不设置此项或值为空就不修改文件权限
            'chown' => 'websync:websync',
        ],
    ],
    // 远程同步到本地
    'pull' => 'hostname1',
    // 本地同步到远程
    'push' => [
        'hostname1',
    ],
];
