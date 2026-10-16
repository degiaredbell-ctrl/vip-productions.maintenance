<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ubah prefix sub_category dari "C." menjadi "W." untuk mesin/non-utility.
     * Utility sudah menggunakan "W.", jadi tidak diubah.
     */
    public function up(): void
    {
        DB::table('assets')
            ->where('sub_category', 'like', 'C.%')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $newSub = preg_replace('/^C\./', 'W.', (string) $row->sub_category);
                    if ($newSub !== (string) $row->sub_category) {
                        DB::table('assets')->where('id', $row->id)->update([
                            'sub_category' => $newSub,
                        ]);
                    }
                }
            });
    }

    /**
     * Kembalikan prefix "W." menjadi "C." untuk non-utility (sebagai best effort reversibel).
     */
    public function down(): void
    {
        DB::table('assets')
            ->where('sub_category', 'like', 'W.%')
            ->where('type', '!=', 'utility')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $newSub = preg_replace('/^W\./', 'C.', (string) $row->sub_category);
                    if ($newSub !== (string) $row->sub_category) {
                        DB::table('assets')->where('id', $row->id)->update([
                            'sub_category' => $newSub,
                        ]);
                    }
                }
            });
    }
};
