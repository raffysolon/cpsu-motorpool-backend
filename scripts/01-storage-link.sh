#!/usr/bin/env bash
set -e

storage_public_path=/var/www/html/storage/app/public
pdf_path="$storage_public_path/pdfs"

mkdir -p "$pdf_path"
chown -R nginx:nginx "$storage_public_path"
chmod -R ug+rwX "$storage_public_path"
php artisan storage:link --force
