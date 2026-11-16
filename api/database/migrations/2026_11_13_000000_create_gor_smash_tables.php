<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pengguna', function (Blueprint $t) { $t->id('id_pengguna'); $t->string('nama',100); $t->string('username',50)->unique(); $t->string('email',100)->unique(); $t->string('password_hash'); $t->string('role',20); $t->timestamps(); });
        Schema::create('pelanggan', function (Blueprint $t) { $t->id('id_pelanggan'); $t->string('nama',100); $t->string('no_hp',20)->unique(); $t->timestamps(); });
        Schema::create('lapangan', function (Blueprint $t) { $t->id('id_lapangan'); $t->string('nomor_lapangan',30)->unique(); $t->string('status',30)->default('tersedia'); });
        Schema::create('tarif', function (Blueprint $t) { $t->id('id_tarif'); $t->string('jenis_periode',100)->unique(); $t->decimal('tarif_per_jam',12,2); $t->string('hari_berlaku',50); $t->time('jam_mulai'); $t->time('jam_selesai'); });
        Schema::create('booking', function (Blueprint $t) { $t->id('id_booking'); $t->string('kode_booking',25)->unique(); $t->timestamp('tanggal_booking')->useCurrent(); $t->timestamp('waktu_dibuat')->useCurrent(); $t->string('status_booking',30)->default('Menunggu DP'); $t->decimal('total_biaya',12,2); $t->foreignId('id_pelanggan')->constrained('pelanggan','id_pelanggan'); $t->foreignId('id_admin')->nullable()->constrained('pengguna','id_pengguna')->nullOnDelete(); $t->timestamp('batas_waktu_dp'); $t->unsignedSmallInteger('jumlah_pindah_jadwal')->default(0); $t->boolean('dp_hangus')->default(false); });
        Schema::create('detail_booking', function (Blueprint $t) { $t->id('id_detail'); $t->foreignId('id_booking')->constrained('booking','id_booking')->cascadeOnDelete(); $t->foreignId('id_lapangan')->constrained('lapangan','id_lapangan'); $t->foreignId('id_tarif')->constrained('tarif','id_tarif'); $t->date('tanggal_main'); $t->time('jam_mulai'); $t->time('jam_selesai'); $t->unsignedInteger('durasi'); $t->decimal('tarif_saat_booking',12,2); $t->index(['id_lapangan','tanggal_main','jam_mulai','jam_selesai']); });
        Schema::create('pembayaran', function (Blueprint $t) { $t->id('id_pembayaran'); $t->foreignId('id_booking')->constrained('booking','id_booking'); $t->string('jenis_pembayaran',20); $t->decimal('nominal',12,2); $t->string('status_verifikasi',30)->default('Menunggu Verifikasi'); $t->string('metode_pembayaran',30); $t->timestamp('tanggal_pembayaran')->useCurrent(); $t->string('bukti_transfer_url')->nullable(); $t->foreignId('diverifikasi_oleh')->nullable()->constrained('pengguna','id_pengguna')->nullOnDelete(); });
        Schema::create('log_aktivitas', function (Blueprint $t) { $t->id('id_log'); $t->foreignId('id_pengguna')->nullable()->constrained('pengguna','id_pengguna')->nullOnDelete(); $t->foreignId('id_booking')->nullable()->constrained('booking','id_booking')->nullOnDelete(); $t->string('aktivitas',255); $t->text('detail_aktivitas')->nullable(); $t->timestamp('waktu_aktivitas')->useCurrent(); });
    }
    public function down(): void { foreach (['log_aktivitas','pembayaran','detail_booking','booking','tarif','lapangan','pelanggan','pengguna'] as $t) Schema::dropIfExists($t); }
};
