<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('metode_pembayaran', function (Blueprint $t) {
            $t->id('id_metode');
            $t->string('jenis', 30);
            $t->string('nama', 100);
            $t->string('nomor', 100);
            $t->string('atas_nama', 100);
            $t->string('qr_url')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metode_pembayaran');
    }
};
