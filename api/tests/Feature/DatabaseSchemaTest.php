<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;
    public function test_gor_smash_domain_tables_exist(): void
    {
        foreach (['pengguna', 'pelanggan', 'lapangan', 'tarif', 'booking', 'detail_booking', 'pembayaran', 'log_aktivitas'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing {$table} table");
        }
    }
}
