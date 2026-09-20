#!/usr/bin/env bash
mkdir -p /var/www/html/storage/app/public/pdfs
chown -R nginx:nginx /var/www/html/storage/app/public || true
chmod -R ug+rwX /var/www/html/storage/app/public || true
ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage