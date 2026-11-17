<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentMethodController extends Controller
{
    public function index()
    {
        return DB::table('metode_pembayaran')->where('jenis', 'Transfer Bank')->orderBy('id_metode')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate(['jenis' => 'required|in:Transfer Bank', 'nama' => 'required|string|max:100', 'nomor' => 'required|string|max:100', 'atas_nama' => 'required|string|max:100', 'qr_url' => 'nullable|string']);
        $data['qr_url'] = null;
        $id = DB::table('metode_pembayaran')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()], 'id_metode');
        return response()->json(DB::table('metode_pembayaran')->where('id_metode', $id)->first(), 201);
    }

    public function destroy(int $id)
    {
        abort_unless(DB::table('metode_pembayaran')->where('id_metode', $id)->exists(), 404);
        DB::table('metode_pembayaran')->where('id_metode', $id)->delete();
        return ['deleted' => true];
    }
}
