<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pelanggan', function (Blueprint $table) {
            $table->boolean('is_member')->default(false)->after('no_hp');
        });
    }

    public function down(): void
    {
        Schema::table('pelanggan', fn (Blueprint $table) => $table->dropColumn('is_member'));
    }
};
