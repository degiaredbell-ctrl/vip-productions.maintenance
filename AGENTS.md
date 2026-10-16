# Agent notes

## Deploy
- Setiap perubahan langsung di-deploy ke Docker live (tanpa menunggu konfirmasi):
  `docker compose up -d --build && docker exec vip_app php artisan optimize:clear`
- Migrasi dijalankan di container: `docker exec vip_app php artisan migrate --force`
  (DB eksternal `192.168.20.9`, sama dengan `.env` host).
- Live: `http://localhost:8082` (container `vip_app` + `vip_nginx`).
