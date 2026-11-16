<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function create(array $data, ?int $adminId = null): array
    {
        return DB::transaction(function () use ($data, $adminId) {
            $start = Carbon::parse("{$data['tanggal_main']} {$data['jam_mulai']}");
            $end = $start->copy()->addHours((int) $data['durasi']);
            $court = DB::table('lapangan')->where('id_lapangan', $data['id_lapangan'])->lockForUpdate()->first();
            if (! $court || $court->status !== 'tersedia') throw ValidationException::withMessages(['id_lapangan' => 'Lapangan tidak tersedia.']);
            $overlap = DB::table('detail_booking as d')->join('booking as b','b.id_booking','=','d.id_booking')->where('d.id_lapangan',$data['id_lapangan'])->where('d.tanggal_main',$data['tanggal_main'])->whereIn('b.status_booking',['Menunggu DP','DP Terverifikasi','Lunas'])->where('d.jam_mulai','<',$end->format('H:i:s'))->where('d.jam_selesai','>',$start->format('H:i:s'))->exists();
            if ($overlap) throw ValidationException::withMessages(['jadwal' => 'Slot waktu sudah terisi.']);
            $customer = DB::table('pelanggan')->where('no_hp',$data['no_hp'])->first();
            $customerId = $customer?->id_pelanggan ?? DB::table('pelanggan')->insertGetId(['nama'=>$data['nama_pelanggan'],'no_hp'=>$data['no_hp'],'created_at'=>now(),'updated_at'=>now()]);
            $rates = $this->rates($start, $end);
            $total = array_sum(array_column($rates,'harga'));
            $id = DB::table('booking')->insertGetId(['kode_booking'=>'BK-'.now()->format('Ymd').'-'.str_pad((string)(DB::table('booking')->count()+1),3,'0',STR_PAD_LEFT),'status_booking'=>'Menunggu DP','total_biaya'=>$total,'id_pelanggan'=>$customerId,'id_admin'=>$adminId,'batas_waktu_dp'=>now()->addHours(2),'tanggal_booking'=>now(),'waktu_dibuat'=>now()]);
            foreach ($rates as $rate) DB::table('detail_booking')->insert(['id_booking'=>$id,'id_lapangan'=>$data['id_lapangan'],'id_tarif'=>$rate['id'],'tanggal_main'=>$start->toDateString(),'jam_mulai'=>$rate['mulai'],'jam_selesai'=>$rate['selesai'],'durasi'=>1,'tarif_saat_booking'=>$rate['harga']]);
            DB::table('log_aktivitas')->insert(['id_pengguna'=>$adminId,'id_booking'=>$id,'aktivitas'=>'Buat booking','detail_aktivitas'=>'Booking dibuat','waktu_aktivitas'=>now()]);
            return ['id_booking'=>$id,'total_biaya'=>$total,'nominal_dp'=>$total / 2,'batas_waktu_dp'=>now()->addHours(2)];
        });
    }
    private function rates(Carbon $start, Carbon $end): array { $rates=[]; for ($time=$start->copy(); $time<$end; $time->addHour()) { $weekend=$time->isWeekend(); $tarif=DB::table('tarif')->where('jam_mulai','<=',$time->format('H:i:s'))->where('jam_selesai','>',$time->format('H:i:s'))->where(function($q) use($weekend){ $q->where('hari_berlaku','Setiap Hari')->orWhere('hari_berlaku',$weekend?'Sabtu-Minggu':'Senin-Jumat'); })->first(); if(! $tarif) throw ValidationException::withMessages(['tarif'=>'Tarif tidak ditemukan untuk slot ini.']); $rates[]=['id'=>$tarif->id_tarif,'harga'=>(float)$tarif->tarif_per_jam,'mulai'=>$time->format('H:i:s'),'selesai'=>$time->copy()->addHour()->format('H:i:s')]; } return $rates; }
}
