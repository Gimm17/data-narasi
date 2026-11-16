<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Batas ukuran file upload (MB)
    |--------------------------------------------------------------------------
    |
    | SATU-SATUNYA sumber angka ini. Sebelumnya nilainya tersebar dan saling
    | bertentangan: validasi Form Request menolak >10 MB, sedangkan pipeline
    | menerima sampai 15 MB, jadi file 12 MB ditolak di pintu masuk padahal
    | pipeline sanggup memprosesnya (dan sebaliknya, pesan errornya menyebut
    | angka yang berbeda dari yang sebenarnya berlaku).
    |
    | Nilai ini juga harus lebih kecil dari upload_max_filesize + post_max_size
    | di php.ini. Lihat deploy/cpanel/README.md bagian "Batas PHP".
    |
    */

    'max_upload_mb' => (int) env('MAX_UPLOAD_SIZE_MB', 10),

];
