# 项目文件同步
> 自动根据`.gitignore`忽略列表进行`rsync --exclude`

#### 安装

- `cp config.example.php config.php`
- `ln -s /path/to/websync/websync.php /usr/local/bin/websync` *注意权限实体文件有无执行权限*

#### 用法

- `cd /path/to/project`
- `websync`

#### 配置

- `remote` 远程服务器例如 `name@host`
- `dst_root` 远程目的根路径，指定 `--dst` 参数时忽略此配置
- `chown` 即 `--chown` 重置文件权限，否则按源文件权限来
- `ignore` 主动忽略列表