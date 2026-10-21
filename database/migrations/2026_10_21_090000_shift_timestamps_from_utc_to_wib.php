<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aplikasi sebelumnya berjalan dengan APP_TIMEZONE=UTC, jadi semua waktu yang
 * ditulis Laravel tersimpan 7 jam lebih awal dari jam Jakarta. Sekarang
 * APP_TIMEZONE=Asia/Jakarta, sehingga data lama perlu digeser +7 jam supaya
 * terbaca sebagai WIB yang benar.
 *
 * Hanya kolom DATETIME/TIMESTAMP yang digeser; kolom DATE (mis. tanggal
 * pemakaian suku cadang) adalah tanggal kalender, bukan waktu.
 *
 * Khusus MySQL/MariaDB (database produksi). SQLite di test selalu dibuat
 * kosong, jadi tidak ada data yang perlu digeser.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->shift('+');
    }

    public function down(): void
    {
        $this->shift('-');
    }

    private function shift(string $sign): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $columns = DB::select(
            'select table_name as t, column_name as c
               from information_schema.columns
              where table_schema = database()
                and data_type in ("datetime", "timestamp")
              order by table_name, column_name'
        );

        foreach (collect($columns)->groupBy('t') as $table => $cols) {
            $sets = $cols
                ->map(fn ($col) => "`{$col->c}` = `{$col->c}` {$sign} INTERVAL 7 HOUR")
                ->implode(', ');

            // NULL tetap NULL karena NULL + INTERVAL = NULL.
            DB::statement("UPDATE `{$table}` SET {$sets}");
        }
    }
};
