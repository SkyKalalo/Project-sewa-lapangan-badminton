<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminBookingController extends Controller
{
    public function cancel(string $kode, Request $request)
    {
        return DB::transaction(function () use ($kode, $request) {
            $booking = DB::table('booking')->where('kode_booking', $kode)->lockForUpdate()->first();
            abort_unless($booking && $booking->status_booking !== 'Dibatalkan', 422, 'Booking tidak dapat dibatalkan.');
            $detail = DB::table('detail_booking')->where('id_booking', $booking->id_booking)->orderBy('tanggal_main')->orderBy('jam_mulai')->first();
            abort_unless($detail, 422, 'Detail jadwal booking tidak ditemukan.');
            $hangus = now('Asia/Makassar')->diffInHours(Carbon::parse("{$detail->tanggal_main} {$detail->jam_mulai}", 'Asia/Makassar'), false) < 24;
            DB::table('booking')->where('id_booking', $booking->id_booking)->update(['status_booking' => 'Dibatalkan', 'dp_hangus' => $hangus]);
            DB::table('log_aktivitas')->insert(['id_pengguna' => $request->attributes->get('pengguna')->id_pengguna, 'id_booking' => $booking->id_booking, 'aktivitas' => 'Batalkan booking', 'detail_aktivitas' => $hangus ? 'DP hangus' : 'DP tidak hangus', 'waktu_aktivitas' => now()]);
            return ['status_booking' => 'Dibatalkan', 'dp_hangus' => $hangus];
        });
    }

    public function reschedule(string $kode, Request $request)
    {
        $data = $request->validate(['id_lapangan' => 'required|integer', 'tanggal_main' => 'required|date', 'jam_mulai' => 'required|date_format:H:i']);
        return DB::transaction(function () use ($kode, $data, $request) {
            $booking = DB::table('booking')->where('kode_booking', $kode)->lockForUpdate()->first();
            abort_unless($booking && $booking->status_booking !== 'Dibatalkan' && $booking->jumlah_pindah_jadwal < 1, 422, 'Booking tidak dapat dipindahkan.');
            $details = DB::table('detail_booking')->where('id_booking', $booking->id_booking)->orderBy('tanggal_main')->orderBy('jam_mulai')->get();
            $detail = $details->first();
            if (now('Asia/Makassar')->diffInHours(Carbon::parse("{$detail->tanggal_main} {$detail->jam_mulai}", 'Asia/Makassar'), false) <= 24) throw ValidationException::withMessages(['jadwal' => 'Pindah jadwal harus lebih dari 24 jam sebelum bermain.']);
            $start = Carbon::parse("{$data['tanggal_main']} {$data['jam_mulai']}", 'Asia/Makassar');
            $byCourt = $details->groupBy('id_lapangan');
            $duration = $byCourt->map(fn ($rows) => $rows->sum('durasi'))->max();
            $end = $start->copy()->addHours($duration);
            if ($start->minute !== 0 || $start->hour < 7 || $duration > 4 || $end->toDateString() !== $start->toDateString() || $end->format('H:i:s') > '23:00:00') {
                throw ValidationException::withMessages(['jadwal' => 'Jadwal pengganti harus berada di antara pukul 07.00–23.00 dengan durasi maksimal 4 jam.']);
            }
            $targetCourts = $byCourt->count() === 1 ? [(int) $data['id_lapangan']] : $byCourt->keys()->map(fn ($id) => (int) $id)->all();
            $courts = DB::table('lapangan')->whereIn('id_lapangan', $targetCourts)->lockForUpdate()->get();
            if ($courts->count() !== count($targetCourts) || $courts->contains(fn ($court) => $court->status !== 'tersedia')) throw ValidationException::withMessages(['id_lapangan' => 'Satu atau lebih lapangan tidak tersedia.']);
            foreach ($byCourt as $oldCourtId => $slots) {
                $targetCourtId = $byCourt->count() === 1 ? $targetCourts[0] : (int) $oldCourtId;
                $taken = DB::table('detail_booking as d')->join('booking as b', 'b.id_booking', '=', 'd.id_booking')->where('d.id_lapangan', $targetCourtId)->where('d.tanggal_main', $data['tanggal_main'])->whereNotIn('d.id_detail', $details->pluck('id_detail'))->whereIn('b.status_booking', ['Menunggu DP', 'DP Terverifikasi', 'Lunas'])->where('d.jam_mulai', '<', $end->format('H:i:s'))->where('d.jam_selesai', '>', $start->format('H:i:s'))->exists();
                if ($taken) throw ValidationException::withMessages(['jadwal' => "Slot Lapangan {$targetCourtId} sudah terisi."]);
                foreach ($slots->values() as $index => $slot) {
                    $slotStart = $start->copy()->addHours($index);
                    DB::table('detail_booking')->where('id_detail', $slot->id_detail)->update(['id_lapangan' => $targetCourtId, 'tanggal_main' => $data['tanggal_main'], 'jam_mulai' => $slotStart->format('H:i:s'), 'jam_selesai' => $slotStart->copy()->addHour()->format('H:i:s')]);
                }
            }
            DB::table('booking')->where('id_booking', $booking->id_booking)->update(['jumlah_pindah_jadwal' => 1]);
            DB::table('log_aktivitas')->insert(['id_pengguna' => $request->attributes->get('pengguna')->id_pengguna, 'id_booking' => $booking->id_booking, 'aktivitas' => 'Pindah jadwal', 'waktu_aktivitas' => now()]);
            return ['jumlah_pindah_jadwal' => 1];
        });
    }
}
