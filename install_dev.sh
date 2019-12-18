#! /bin/bash

chmod +x websync_dev.php
rm /usr/local/bin/websync
ln -s `pwd`/websync_dev.php /usr/local/bin/websync
