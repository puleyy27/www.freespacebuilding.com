<?php
// Salin file ini menjadi config.php lalu isi semua nilainya. JANGAN di-commit ke git.
return [
    // --- WhatsApp ---
    'admin_wa'       => '6285723405913',          // nomor ADMIN penerima notifikasi & pembalas ACC/TOLAK
    'fonnte_token'   => '',                       // token device dari fonnte.com
    'webhook_key'    => '',                       // kosongkan = dibuat otomatis dari 'secret'

    // --- Website ---
    'base_url'       => 'https://domainanda.com', // alamat website (HTTPS, tanpa garis miring di akhir)
    'secret'         => 'GANTI-DENGAN-STRING-ACAK-MINIMAL-32-KARAKTER',
    'admin_password' => 'ganti-password-admin',

    // --- Database (isi dari panel hosting: MySQL Databases) ---
    'db_host'        => 'localhost',
    'db_port'        => '3306',
    'db_name'        => 'namaakun_freespace',
    'db_user'        => 'namaakun_user',
    'db_pass'        => 'password-database',
];
