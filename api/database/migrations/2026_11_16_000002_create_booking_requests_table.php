<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('permohonan_booking', function (Blueprint $t) {
            $t->id('id_permohonan');
            $t->foreignId('id_booking')->constrained('booking', 'id_booking')->cascadeOnDelete();
            $t->string('jenis', 20);
            $t->text('alasan')->nullable();
            $t->foreignId('id_lapangan_tujuan')->nullable()->constrained('lapangan', 'id_lapangan')->nullOnDelete();
            $t->date('tanggal_tujuan')->nullable();
            $t->time('jam_tujuan')->nullable();
            $t->string('status', 20)->default('Menunggu');
            $t->text('catatan_admin')->nullable();
            $t->foreignId('diproses_oleh')->nullable()->constrained('pengguna', 'id_pengguna')->nullOnDelete();
            $t->timestamps();
            $t->index(['id_booking', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permohonan_booking');
    }
};
