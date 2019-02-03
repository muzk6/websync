<?php

return [
    // 全局配置
    'global' => [
        // 不排除的列表
        'include' => [],
        // 排除列表
        'exclude' => [
            '.git',
        ],
    ],
    // 远程服务器
    'remote' => [
        // 自定义服务器名称
        'host1' => [
            'hostname' => 'root@host',
            // 远程目的根目录
            'dst' => '/path/to',
            // 对所有同步的文件进行 chown, 不设置此项或值为空就不修改文件权限
            'chown' => 'root:root',
        ],
    ],
    // 项目配置
    'projects' => [
        // 自定义项目名
        'project1' => [
            // 同步到指定的远程服务器
            'remote' => ['host1'],
            // 项目别名，用于远程项目的名字，不设置或为空时以当前项目名为准
            'alias' => '',
            // 不排除的列表
            'include' => [],
            // 排除列表
            'exclude' => [],
        ],
    ]
];
