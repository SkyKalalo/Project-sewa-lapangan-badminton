<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OwnerReportController extends Controller
{
    public function report(Request $request) { $d=$request->validate(['mulai'=>'required|date','selesai'=>'required|date|after_or_equal:mulai']); return ['pendapatan'=>(float) DB::table('pembayaran')->where('status_verifikasi','Terverifikasi')->whereBetween('tanggal_pembayaran',[$d['mulai'],$d['selesai'].' 23:59:59'])->sum('nominal'),'jumlah_booking'=>DB::table('booking')->whereBetween('tanggal_booking',[$d['mulai'],$d['selesai'].' 23:59:59'])->count()]; }
    public function monitoring() { return DB::table('booking')->select('status_booking', DB::raw('count(*) as jumlah'))->groupBy('status_booking')->get(); }
    public function quietHours(Request $request) { $d=$request->validate(['mulai'=>'required|date','selesai'=>'required|date|after_or_equal:mulai']); return DB::table('lapangan as l')->leftJoin('detail_booking as d', function($join) use($d) { $join->on('d.id_lapangan','=','l.id_lapangan')->whereBetween('d.tanggal_main',[$d['mulai'],$d['selesai']]); })->select('l.nomor_lapangan',DB::raw('count(d.id_detail) as slot_terpesan'))->groupBy('l.nomor_lapangan')->get(); }
    public function activities() { return DB::table('log_aktivitas')->leftJoin('pengguna','pengguna.id_pengguna','=','log_aktivitas.id_pengguna')->orderByDesc('waktu_aktivitas')->select('log_aktivitas.*','pengguna.nama')->get(); }
}
