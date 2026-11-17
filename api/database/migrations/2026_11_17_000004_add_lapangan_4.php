<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('lapangan')->updateOrInsert(
            ['nomor_lapangan' => 'Lapangan 4'],
            ['status' => 'tersedia']
        );
    }

    public function down(): void
    {
        DB::table('lapangan')
            ->where('nomor_lapangan', 'Lapangan 4')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('detail_booking')
                    ->whereColumn('detail_booking.id_lapangan', 'lapangan.id_lapangan');
            })
            ->delete();
    }
};
