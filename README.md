# websync: 基于 rsync 的本地、远程 双向同步工具

## 依赖

- rsync version >= 3.0
- PHP version >= 5.4.0

## 安装

### 使用预编译包安装

- https://github.com/muzk6/websync/releases 下载最新版安装包
- `chmod +x websync.phar` 
- `mv websync.phar /usr/local/bin/websync` 

### 使用源码安装

- `git clone --depth=1 https://github.com/muzk6/websync.git` 下载源码
- `./install.sh` 安装
- `websync -h` 查看帮助

## 帮助

```
USAGE:
    websync [push] [<hostname>...]
    websync pull [<hostname>]
    websync clone <hostname>:<remote_path> [<local_path>]
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
       
    clone
        从远程克隆指定目录的所有文件
        与 pull 的区别是：clone 不依赖 .websyncrc.php 局部配置
        
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
```
