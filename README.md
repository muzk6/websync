# 项目文件同步
> 自动根据`.gitignore`忽略列表进行`rsync --exclude`

#### 安装

- `ln -s /path/to/websync/websync.php /usr/local/bin/websync` 创建软链接 *注意`websync.php`有无执行权限*
- `websync --init` 初始化配置

#### 用法

- `cd /path/to/project` 切换到项目目录
- `websync` 开始同步
- `websync --help` 查看帮助