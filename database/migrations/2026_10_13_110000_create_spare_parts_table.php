<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master Suku Cadang (spare parts).
 *
 * Sumber awal: references/suku_cadang.csv. Kolom tabel ini mengikuti kolom
 * CSV apa adanya (setelah header dua barisnya disatukan) supaya data lama bisa
 * diimpor tanpa kehilangan informasi. Suku cadang bersifat global — tidak
 * terikat ke satu unit Mesin/Utility; kolom Code.asset hanya kode barang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spare_parts', function (Blueprint $table) {
            $table->id();

            // Code.asset — kode unik suku cadang (mis. SM381).
            $table->string('code', 50)->unique();

            // CATEGORY DEVICE — kategori alat (mis. Sensor, Bearing).
            $table->string('category', 100)->nullable();

            // Suku Cadang — nama barang.
            $table->string('name');

            $table->string('brand', 100)->nullable();   // MEREK
            $table->string('type', 150)->nullable();    // TYPE
            $table->text('description')->nullable();    // Desc

            // Kolom jejak dari CSV; disimpan sebagai teks karena isinya bebas.
            $table->string('last_modified', 100)->nullable();       // Last - Modified
            $table->string('last_modified_note', 255)->nullable();  // Ket. Last - Modified

            $table->string('unit', 30)->nullable();     // UNIT (mis. pcs)

            // QTY pada CSV = jumlah per unit yang dipakai (bukan stok fisik).
            $table->unsignedInteger('qty')->default(0);

            // Stok fisik. `stock` = STOK AKHIR (stok terkini).
            $table->integer('stock')->default(0);           // STOK AKHIR
            $table->unsignedInteger('stock_out')->default(0);      // BARANG KELUAR
            $table->string('stock_out_log', 255)->nullable();      // Log.Barang Keluar
            $table->unsignedInteger('stock_in')->default(0);       // BARANG MASUK
            $table->integer('stock_start')->default(0);            // STOK AWAL
            $table->unsignedInteger('min_stock')->default(0);      // MIN

            $table->string('supplier', 255)->nullable();   // SUPLIER
            $table->string('needed_for', 255)->nullable(); // Kebutuhan Untuk
            $table->text('note')->nullable();              // Penjelasan

            $table->string('location_code', 50)->nullable(); // Code.loc
            $table->string('location', 255)->nullable();     // LOC

            $table->unsignedInteger('sort_no')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('category');
            $table->index('name');
            $table->index('location_code');
            $table->index(['category', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spare_parts');
    }
};
