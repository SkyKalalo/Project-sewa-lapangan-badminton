<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OwnerReportController extends Controller
{
    public function bookings()
    {
        $perCourt = DB::table('detail_booking')->selectRaw('id_booking,id_lapangan,MIN(tanggal_main) as tanggal_main,MIN(jam_mulai) as jam_mulai,MAX(jam_selesai) as jam_selesai,SUM(durasi) as durasi')->groupBy('id_booking', 'id_lapangan');
        $details = DB::query()->fromSub($perCourt, 'pc')->selectRaw('id_booking,MIN(id_lapangan) as id_lapangan,MIN(tanggal_main) as tanggal_main,MIN(jam_mulai) as jam_mulai,MAX(jam_selesai) as jam_selesai,MAX(durasi) as durasi')->groupBy('id_booking');
        $rows = DB::table('booking as b')->join('pelanggan as c', 'c.id_pelanggan', '=', 'b.id_pelanggan')->leftJoinSub($details, 'd', 'd.id_booking', '=', 'b.id_booking')
            ->select('b.*', 'c.nama', 'c.no_hp', 'd.id_lapangan', 'd.tanggal_main', 'd.jam_mulai', 'd.jam_selesai', 'd.durasi')->orderByDesc('b.id_booking')->get();
        $courts = DB::table('detail_booking')->whereIn('id_booking', $rows->pluck('id_booking'))->get(['id_booking', 'id_lapangan'])->groupBy('id_booking');
        return $rows->map(function ($row) use ($courts) { $row->id_lapangans = ($courts[$row->id_booking] ?? collect())->pluck('id_lapangan')->unique()->values(); return $row; });
    }
    public function report(Request $request) { $d=$request->validate(['mulai'=>'required|date','selesai'=>'required|date|after_or_equal:mulai']); $range=[$d['mulai'],$d['selesai'].' 23:59:59']; return ['pendapatan'=>(float) DB::table('pembayaran')->where('status_verifikasi','Terverifikasi')->whereBetween('tanggal_pembayaran',$range)->sum('nominal'),'jumlah_booking'=>DB::table('booking')->whereBetween('tanggal_booking',$range)->count(),'pendapatan_harian'=>DB::table('pembayaran')->where('status_verifikasi','Terverifikasi')->whereBetween('tanggal_pembayaran',$range)->selectRaw('DATE(tanggal_pembayaran) as tanggal, SUM(nominal) as pendapatan')->groupByRaw('DATE(tanggal_pembayaran)')->orderBy('tanggal')->get()]; }
    public function monitoring() { return DB::table('booking')->select('status_booking', DB::raw('count(*) as jumlah'))->groupBy('status_booking')->get(); }
    public function quietHours(Request $request) { $d=$request->validate(['mulai'=>'required|date','selesai'=>'required|date|after_or_equal:mulai']); return DB::table('lapangan as l')->leftJoin('detail_booking as d', function($join) use($d) { $join->on('d.id_lapangan','=','l.id_lapangan')->whereBetween('d.tanggal_main',[$d['mulai'],$d['selesai']]); })->select('l.nomor_lapangan',DB::raw('count(d.id_detail) as slot_terpesan'))->groupBy('l.nomor_lapangan')->get(); }
    public function activities() { return DB::table('log_aktivitas')->leftJoin('pengguna','pengguna.id_pengguna','=','log_aktivitas.id_pengguna')->orderByDesc('waktu_aktivitas')->select('log_aktivitas.*','pengguna.nama')->get(); }
}
