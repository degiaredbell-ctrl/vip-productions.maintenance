<?php

namespace Tests\Unit;

use App\Services\BackupService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Pemecah skrip SQL pada BackupService menentukan benar/tidaknya restore file
 * .sql. Diuji terpisah dari database supaya bisa jalan di lingkungan mana pun.
 */
class BackupSqlSplitterTest extends TestCase
{
    private function split(string $sql): array
    {
        $method = new ReflectionMethod(BackupService::class, 'splitStatements');
        $method->setAccessible(true);

        return $method->invoke(new BackupService(), $sql);
    }

    public function test_memecah_pernyataan_sederhana(): void
    {
        $this->assertSame(
            ['SELECT 1', 'SELECT 2'],
            $this->split('SELECT 1; SELECT 2;')
        );
    }

    public function test_titik_koma_di_dalam_string_tidak_memotong(): void
    {
        $statements = $this->split("INSERT INTO t VALUES ('a;b'); SELECT 1;");

        $this->assertCount(2, $statements);
        $this->assertSame("INSERT INTO t VALUES ('a;b')", $statements[0]);
    }

    public function test_kutip_yang_di_escape_tidak_menutup_string(): void
    {
        $statements = $this->split("INSERT INTO t VALUES ('a\\';b'); SELECT 1;");

        $this->assertCount(2, $statements);
    }

    public function test_tanda_kutip_backtick_dihormati(): void
    {
        $statements = $this->split('INSERT INTO `we;ird` VALUES (1); SELECT 1;');

        $this->assertCount(2, $statements);
        $this->assertStringContainsString('`we;ird`', $statements[0]);
    }

    public function test_komentar_baris_dan_blok_diabaikan_saat_memotong(): void
    {
        $this->assertCount(1, $this->split("-- komentar; dengan titik koma\nSELECT 1;"));
        $this->assertCount(1, $this->split('/* blok; komentar */ SELECT 1;'));
    }

    public function test_komentar_kondisional_mysql_dipertahankan(): void
    {
        $statements = $this->split("/*!40101 SET NAMES utf8mb4 */;\nSELECT 1;");

        $this->assertCount(2, $statements);
        $this->assertStringContainsString('/*!40101', $statements[0]);
    }

    public function test_dump_bawaan_aplikasi_terurai_benar(): void
    {
        $sql = "SET NAMES utf8mb4;\n"
            ."SET FOREIGN_KEY_CHECKS=0;\n\n"
            ."DROP TABLE IF EXISTS `users`;\n"
            ."CREATE TABLE `users` (`id` bigint NOT NULL, `name` varchar(255));\n\n"
            ."INSERT INTO `users` (`id`, `name`) VALUES (1, 'O''Brien');\n"
            ."INSERT INTO `users` (`id`, `name`) VALUES (2, 'Baris\\nBaru');\n"
            ."SET FOREIGN_KEY_CHECKS=1;\n";

        $statements = $this->split($sql);

        // SET, SET, DROP, CREATE, 2x INSERT, SET = 7
        $this->assertCount(7, $statements);
        $this->assertSame('DROP TABLE IF EXISTS `users`', $statements[2]);
        $this->assertStringContainsString("'O''Brien'", $statements[4]);
    }
}
