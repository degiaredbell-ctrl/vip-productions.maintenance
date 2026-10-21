<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proxy tepercaya
    |--------------------------------------------------------------------------
    |
    | Alamat proxy/load balancer di depan aplikasi yang boleh mengirim header
    | X-Forwarded-For / X-Real-IP. Hanya dari alamat ini header tersebut
    | dipercaya; dari alamat lain header diabaikan supaya IP di Activity Logs
    | tidak bisa dipalsukan. Pisahkan dengan koma. Kosong = tidak ada proxy.
    |
    */
    'trusted_proxies' => env('TRUSTED_PROXIES', ''),

    /*
    |--------------------------------------------------------------------------
    | Label alamat IP
    |--------------------------------------------------------------------------
    |
    | Keterangan untuk alamat/jaringan yang dikenal, ditampilkan di samping IP
    | pada Activity Logs. Format: "ip_atau_cidr=Label;ip_atau_cidr=Label".
    | Contoh: "192.168.99.247=Gateway VPN (NAT);192.168.20.0/24=LAN Kantor".
    |
    */
    'ip_labels' => env('AUDIT_IP_LABELS', ''),

];
