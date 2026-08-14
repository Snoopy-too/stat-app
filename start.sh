#!/bin/bash
php -S 127.0.0.1:4001 router.php &> /dev/null &
echo "Server started on port 4001"
