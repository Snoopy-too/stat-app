#!/bin/bash
set -e

echo "1. Backing up existing configs..."
sudo cp /etc/nginx/sites-available/joshnishikawa.ddns.net.conf /etc/nginx/sites-available/joshnishikawa.ddns.net.conf.bak
sudo cp /etc/nginx/sites-available/englishjones.com.conf /etc/nginx/sites-available/englishjones.com.conf.bak
sudo cp /etc/nginx/sites-available/dev.conf /etc/nginx/sites-available/dev.conf.bak

echo "2. Writing clean HTTP configs..."

sudo tee /etc/nginx/sites-available/joshnishikawa.ddns.net.conf > /dev/null << 'EOF'
server {
    listen 80;
    server_name joshnishikawa.ddns.net;
    root /var/www/joshnishikawa.ddns.net/;

    location / {
        proxy_pass http://opti:5000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }

    location /public {
        alias /var/www/joshnishikawa.ddns.net/public/;
        autoindex on;
    }

    try_files $uri $uri/ =404;
    client_max_body_size 64M;
}
EOF

sudo tee /etc/nginx/sites-available/englishjones.com.conf > /dev/null << 'EOF'
server {
    listen 80;
    server_name www.englishjones.com share.englishjones.com englishjones.com;

    location / {
        proxy_pass http://opti:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }

    location /public {
        alias /var/www/share/public/;
        autoindex on;
    }

    try_files $uri $uri/ =404;
    client_max_body_size 64M;
}
EOF

sudo tee /etc/nginx/sites-available/dev.conf > /dev/null << 'EOF'
server {
    listen 80;
    server_name dev.englishjones.com;

    index index.html index.htm index.nginx-debian.html;

    location / {
        proxy_pass http://opti:3003;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }

    location /public {
        alias /var/www/dev.share/public/;
        autoindex on;
    }

    try_files $uri $uri/ =404;
    client_max_body_size 64M;
}
EOF

echo "3. Testing Nginx configuration..."
sudo nginx -t

echo "4. Restarting Nginx service..."
sudo systemctl restart nginx

echo "Done! Nginx is active and running."
