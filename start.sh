#!/bin/bash
php -d upload_max_filesize=32M -d post_max_size=32M -d memory_limit=256M -S 127.0.0.1:4001 router.php &> /dev/null &
echo "Server started on port 4001"
