#!/bin/bash
php -S 0.0.0.0:3003 router.php &> /dev/null &
echo "Server started on port 3003"
