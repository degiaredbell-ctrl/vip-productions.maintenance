<?php

namespace App\Services;

use App\Models\Backup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Backup & Restore database (MySQL) tanpa biner eksternal.
 *
 * Pendekatan awal memakai `mysqldump`/`mysql`, tetapi client MariaDB di image
 * Alpine tidak bisa menangani autentikasi `caching_sha2_password` milik MySQL 8.
 * Karena itu dump dan restore dilakukan murni lewat PDO — koneksi yang sama
 * dengan yang dipakai aplikasi, jadi otentikasi, charset, dan opsi koneksi
 * selalu konsisten.
 *
 * Hasil dump adalah file .sql dengan satu pernyataan per baris (nilai string
 * sudah di-escape termasuk newline oleh PDO::quote), sehingga file bisa
 * dipulihkan kembali dengan pemecah pernyataan sederhana di bawah.
 */
class BackupService
{
    public function disk(): string
    {
        return (string) config('backup.disk', 'backups');
    }

    /**
     * @return array{0:string,1:array<string,mixed>} [namaKoneksi, konfigurasi]
     */
    private function connection(): array
    {
        $name = (string) config('database.default');

        return [$name, (array) config("database.connections.{$name}", [])];
    }

    /**
     * Membuat file backup baru dan mencatatnya di tabel `backups`.
     */
    public function create(string $type = 'manual', ?int $userId = null): Backup
    {
        [$name, $conn] = $this->connection();
        $this->ensureSupported($conn, $name);

        // Nama berkas memakai zona waktu tampilan (default Asia/Jakarta) supaya
        // jam pada nama file sama dengan kolom "Waktu" di Daftar Backup.
        $displayTz = (string) config('backup.timezone', 'Asia/Jakarta');

        $filename = 'backup-'.now()->timezone($displayTz)->format('Ymd-His')."-{$name}.sql";
        $disk = Storage::disk($this->disk());
        $disk->makeDirectory('/');
        $absolute = $disk->path($filename);

        $handle = fopen($absolute, 'wb');

        if ($handle === false) {
            return Backup::create([
                'filename' => $filename,
                'size' => 0,
                'type' => $type,
                'status' => 'failed',
                'error' => 'Tidak dapat menulis file backup ke penyimpanan.',
                'created_by' => $userId,
            ]);
        }

        try {
            $this->writeDump($handle);
            fclose($handle);
        } catch (Throwable $e) {
            fclose($handle);
            @unlink($absolute);

            return Backup::create([
                'filename' => $filename,
                'size' => 0,
                'type' => $type,
                'status' => 'failed',
                'error' => Str::limit($e->getMessage(), 1000, ''),
                'created_by' => $userId,
            ]);
        }

        return Backup::create([
            'filename' => $filename,
            'size' => filesize($absolute) ?: 0,
            'type' => $type,
            'status' => 'completed',
            'created_by' => $userId,
        ]);
    }

    /**
     * Memulihkan database dari file backup yang tersimpan.
     */
    public function restore(Backup $backup): void
    {
        $disk = Storage::disk($this->disk());

        if (! $disk->exists($backup->filename)) {
            throw new RuntimeException('File backup tidak ditemukan di penyimpanan.');
        }

        $this->restorePath($disk->path($backup->filename));
    }

    /**
     * Memulihkan database dari file .sql (tersimpan maupun hasil unggahan).
     */
    public function restorePath(string $absolutePath): void
    {
        [$name, $conn] = $this->connection();
        $this->ensureSupported($conn, $name);

        $sql = @file_get_contents($absolutePath);

        if ($sql === false || $sql === '') {
            throw new RuntimeException('File backup tidak dapat dibaca atau kosong.');
        }

        $pdo = DB::connection()->getPdo();

        foreach ($this->splitStatements($sql) as $statement) {
            try {
                $pdo->exec($statement);
            } catch (Throwable $e) {
                throw new RuntimeException(
                    'Gagal menjalankan pernyataan restore: '.Str::limit($statement, 160, '…').' — '.$e->getMessage()
                );
            }
        }
    }

    /**
     * Menghapus file backup beserta barisnya.
     */
    public function delete(Backup $backup): void
    {
        $disk = Storage::disk($this->disk());

        if ($disk->exists($backup->filename)) {
            $disk->delete($backup->filename);
        }

        $backup->delete();
    }

    /**
     * Menyisakan `keep` backup terbaru, sisanya (beserta filenya) dihapus.
     *
     * @return int jumlah backup yang dihapus
     */
    public function prune(?int $keep = null): int
    {
        $keep ??= (int) config('backup.keep', 30);

        if ($keep < 1) {
            return 0;
        }

        $stale = Backup::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->skip($keep)
            ->take(PHP_INT_MAX)
            ->get();

        foreach ($stale as $backup) {
            $this->delete($backup);
        }

        return $stale->count();
    }

    /**
     * Menulis seluruh isi database ke stream dalam format SQL.
     *
     * @param  resource  $handle
     */
    private function writeDump($handle): void
    {
        $database = DB::connection()->getDatabaseName();
        $pdo = DB::connection()->getPdo();
        $displayTz = (string) config('backup.timezone', 'Asia/Jakarta');

        fwrite($handle, "-- Backup database `{$database}`\n");
        fwrite($handle, '-- Dibuat: '.now()->timezone($displayTz)->toDateTimeString()."\n");
        fwrite($handle, "-- Dibuat oleh: Sistem PM Preventive\n\n");
        fwrite($handle, "SET NAMES utf8mb4;\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($this->baseTables() as $table) {
            $this->dumpTable($pdo, $handle, $table);
        }

        fwrite($handle, "\nSET FOREIGN_KEY_CHECKS=1;\n");
    }

    /**
     * @return array<int,string>
     */
    private function baseTables(): array
    {
        $tables = [];

        foreach (DB::select('SHOW FULL TABLES') as $row) {
            $values = array_values((array) $row);

            if (($values[1] ?? null) === 'BASE TABLE' && isset($values[0])) {
                $tables[] = (string) $values[0];
            }
        }

        return $tables;
    }

    /**
     * @param  resource  $handle
     */
    private function dumpTable(PDO $pdo, $handle, string $table): void
    {
        $quoted = '`'.str_replace('`', '``', $table).'`';

        $create = DB::select("SHOW CREATE TABLE {$quoted}");
        $createSql = array_values((array) $create[0])[1] ?? null;

        if (! $createSql) {
            return;
        }

        fwrite($handle, "\n--\n-- Tabel {$quoted}\n--\n");
        fwrite($handle, "DROP TABLE IF EXISTS {$quoted};\n");
        fwrite($handle, $createSql.";\n\n");

        $statement = $pdo->query("SELECT * FROM {$quoted}");

        if ($statement === false) {
            return;
        }

        $columns = null;
        $columnList = '';

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if ($columns === null) {
                $columns = array_keys($row);
                $columnList = implode(', ', array_map(
                    fn ($c) => '`'.str_replace('`', '``', $c).'`',
                    $columns
                ));
            }

            $values = array_map(fn ($value) => $this->quote($pdo, $value), array_values($row));

            fwrite($handle, "INSERT INTO {$quoted} ({$columnList}) VALUES (".implode(', ', $values).");\n");
        }
    }

    private function quote(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        // PDO::quote memakai mysql_real_escape_string: karakter kontrol,
        // backslash, dan newline sudah di-escape sehingga tiap pernyataan
        // tetap berada pada satu baris.
        return $pdo->quote((string) $value);
    }

    /**
     * Memecah skrip SQL menjadi pernyataan-pernyataan, menghormati tanda
     * kutip serta komentar agar `;` di dalam nilai tidak ikut memotong.
     *
     * @return array<int,string>
     */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);

        $inSingle = $inDouble = $inBacktick = $inLine = $inBlock = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($inLine) {
                $buffer .= $char;
                if ($char === "\n") {
                    $inLine = false;
                }
                continue;
            }

            if ($inBlock) {
                $buffer .= $char;
                if ($char === '*' && $next === '/') {
                    $buffer .= '/';
                    $i++;
                    $inBlock = false;
                }
                continue;
            }

            if ($inSingle || $inDouble) {
                $buffer .= $char;
                if ($char === '\\' && $next !== '') {
                    $buffer .= $next;
                    $i++;
                    continue;
                }
                if ($inSingle && $char === "'") {
                    $inSingle = false;
                } elseif ($inDouble && $char === '"') {
                    $inDouble = false;
                }
                continue;
            }

            if ($inBacktick) {
                $buffer .= $char;
                if ($char === '`') {
                    $inBacktick = false;
                }
                continue;
            }

            if ($char === "'") {
                $inSingle = true;
                $buffer .= $char;
            } elseif ($char === '"') {
                $inDouble = true;
                $buffer .= $char;
            } elseif ($char === '`') {
                $inBacktick = true;
                $buffer .= $char;
            } elseif ($char === '-' && $next === '-') {
                $inLine = true;
                $buffer .= $char;
            } elseif ($char === '#') {
                $inLine = true;
                $buffer .= $char;
            } elseif ($char === '/' && $next === '*') {
                $inBlock = true;
                $buffer .= $char;
            } elseif ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }

    /**
     * @param  array<string,mixed>  $conn
     */
    private function ensureSupported(array $conn, string $name): void
    {
        if (($conn['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException(
                "Backup & Restore otomatis saat ini hanya mendukung koneksi MySQL (koneksi aktif: {$name})."
            );
        }
    }
}
