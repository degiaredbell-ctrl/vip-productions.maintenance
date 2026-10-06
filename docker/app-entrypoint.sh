#!/bin/sh
set -e

# `/var/www/html/public` adalah named volume, jadi isinya menutupi seluruh
# folder public di dalam image. Volume hanya diisi dari image saat pertama kali
# dibuat — sesudah itu `npm run build` pada image baru TIDAK pernah masuk ke
# volume, dan nginx (yang mount volume itu) terus menyajikan aset lama.
#
# Karena itu hasil build disimpan di /var/www/html/public-image (di luar volume)
# dan disalin ke sini setiap container start. Sinkronisasi inilah yang membuat
# `docker compose up -d --build` sudah cukup untuk memuat perubahan frontend.
#
# Hanya `build/` yang disentuh. `img/`, symlink `storage`, dan berkas lain di
# dalam volume milik instance ini dan harus tetap utuh.

if [ -d /var/www/html/public-image/build ]; then
    echo "entrypoint: menyinkronkan public/build dari image ke volume"

    mkdir -p /var/www/html/public/build

    # -a supaya struktur assets/ dan nama berkas berhash ikut terbawa.
    cp -a /var/www/html/public-image/build/. /var/www/html/public/build/

    # Aset build harus bisa dibaca nginx (user nginx di container terpisah).
    chmod -R a+rX /var/www/html/public/build
    chown -R www-data:www-data /var/www/html/public/build

    # Vite menulis manifest.json dan assets berhash; berkas lama dari build
    # sebelumnya tidak lagi dirujuk siapa pun, tapi membiarkannya menumpuk
    # di volume tiap rebuild.
    find /var/www/html/public/build -type d -name assets -exec \
        find {} -type f -mtime +7 -delete \;
fi

# Argumen pertama adalah command default image (php-fpm).
exec "$@"