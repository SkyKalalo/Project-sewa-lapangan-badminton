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
            ->select('b.*', 'c.id_pelanggan', 'c.nama', 'c.no_hp', 'c.is_member', 'd.id_lapangan', 'd.tanggal_main', 'd.jam_mulai', 'd.jam_selesai', 'd.durasi')->orderByDesc('b.id_booking')->get();
        $courts = DB::table('detail_booking')->whereIn('id_booking', $rows->pluck('id_booking'))->get(['id_booking', 'id_lapangan'])->groupBy('id_booking');
        return $rows->map(function ($row) use ($courts) { $row->id_lapangans = ($courts[$row->id_booking] ?? collect())->pluck('id_lapangan')->unique()->values(); return $row; });
    }
    public function report(Request $request) { $d=$request->validate(['mulai'=>'required|date','selesai'=>'required|date|after_or_equal:mulai']); $range=[$d['mulai'],$d['selesai'].' 23:59:59']; return ['pendapatan'=>(float) DB::table('pembayaran')->where('status_verifikasi','Terverifikasi')->whereBetween('tanggal_pembayaran',$range)->sum('nominal'),'jumlah_booking'=>DB::table('booking')->whereBetween('tanggal_booking',$range)->count(),'pendapatan_harian'=>DB::table('pembayaran')->where('status_verifikasi','Terverifikasi')->whereBetween('tanggal_pembayaran',$range)->selectRaw('DATE(tanggal_pembayaran) as tanggal, SUM(nominal) as pendapatan')->groupByRaw('DATE(tanggal_pembayaran)')->orderBy('tanggal')->get()]; }
    public function monitoring() { return DB::table('booking')->select('status_booking', DB::raw('count(*) as jumlah'))->groupBy('status_booking')->get(); }
    public function quietHours(Request $request) {
        $d=$request->validate(['mulai'=>'required|date','selesai'=>'required|date|after_or_equal:mulai']);
        $rows = DB::table('detail_booking as d')->join('booking as b','b.id_booking','=','d.id_booking')->whereBetween('d.tanggal_main',[$d['mulai'],$d['selesai']])->where('b.status_booking','<>','Dibatalkan')->get(['d.id_lapangan','d.jam_mulai','d.jam_selesai']);
        $byHour = [];
        foreach ($rows as $row) {
            $start = (int) substr((string) $row->jam_mulai, 0, 2);
            $end = (int) substr((string) $row->jam_selesai, 0, 2);
            for ($hour = $start; $hour < $end; $hour++) $byHour[$hour] = ($byHour[$hour] ?? 0) + 1;
        }
        $hours = [];
        for ($hour = 7; $hour < 23; $hour++) {
            $count = $byHour[$hour] ?? 0;
            $hours[] = ['jam_mulai' => sprintf('%02d:00', $hour), 'jam_selesai' => sprintf('%02d:00', $hour + 1), 'slot_terpesan' => $count, 'kapasitas' => 4, 'tingkat_terisi' => round(($count / 4) * 100, 1)];
        }
        usort($hours, fn ($a, $b) => $a['slot_terpesan'] <=> $b['slot_terpesan'] ?: strcmp($a['jam_mulai'], $b['jam_mulai']));
        $courts = DB::table('lapangan as l')->leftJoin('detail_booking as d', function($join) use($d) { $join->on('d.id_lapangan','=','l.id_lapangan')->whereBetween('d.tanggal_main',[$d['mulai'],$d['selesai']]); })->leftJoin('booking as b','b.id_booking','=','d.id_booking')->select('l.nomor_lapangan')->selectRaw("count(case when b.status_booking <> 'Dibatalkan' then d.id_detail end) as slot_terpesan")->groupBy('l.nomor_lapangan')->orderBy('l.nomor_lapangan')->get();
        $recommendations = array_slice($hours, 0, 3);
        usort($hours, fn ($a, $b) => strcmp($a['jam_mulai'], $b['jam_mulai']));
        return ['lapangan' => $courts, 'jam_operasional' => $hours, 'jam_sepi' => $hours, 'rekomendasi' => $recommendations];
    }
    public function activities() { return DB::table('log_aktivitas')->leftJoin('pengguna','pengguna.id_pengguna','=','log_aktivitas.id_pengguna')->orderByDesc('waktu_aktivitas')->select('log_aktivitas.*','pengguna.nama')->get(); }
}
