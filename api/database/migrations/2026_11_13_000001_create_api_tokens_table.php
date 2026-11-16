<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::create('api_tokens', function (Blueprint $t) { $t->id(); $t->foreignId('id_pengguna')->constrained('pengguna','id_pengguna')->cascadeOnDelete(); $t->string('token',64)->unique(); $t->timestamp('expires_at')->nullable(); $t->timestamps(); }); }
    public function down(): void { Schema::dropIfExists('api_tokens'); }
};
