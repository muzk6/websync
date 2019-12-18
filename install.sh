#! /bin/bash

chmod +x websync.phar
rm /usr/local/bin/websync
ln -s `pwd`/websync.phar /usr/local/bin/websync
