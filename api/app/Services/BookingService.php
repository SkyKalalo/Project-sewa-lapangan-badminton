<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function create(array $data, ?int $adminId = null): array
    {
        return DB::transaction(function () use ($data, $adminId) {
            $data['nama_pelanggan'] = trim((string) $data['nama_pelanggan']);
            $data['no_hp'] = preg_replace('/\D+/', '', (string) $data['no_hp']);
            if (mb_strlen($data['nama_pelanggan']) < 2) {
                throw ValidationException::withMessages(['nama_pelanggan' => 'Nama pelanggan wajib diisi minimal 2 karakter.']);
            }
            if (strlen($data['no_hp']) < 9) {
                throw ValidationException::withMessages(['no_hp' => 'Nomor telepon minimal 9 digit.']);
            }
            $start = Carbon::parse("{$data['tanggal_main']} {$data['jam_mulai']}", 'Asia/Makassar');
            // Match normalized phone numbers so formatting differences do not
            // bypass the customer/member booking window.
            $customer = DB::table('pelanggan')->get(['id_pelanggan', 'nama', 'no_hp', 'is_member'])
                ->first(fn ($row) => preg_replace('/\D+/', '', (string) $row->no_hp) === $data['no_hp']);
            $isMember = filter_var($customer?->is_member, FILTER_VALIDATE_BOOLEAN);
            $maxDays = $isMember ? 14 : 3;
            $maxDate = now('Asia/Makassar')->startOfDay()->addDays($maxDays);
            if ($start->copy()->startOfDay()->greaterThan($maxDate)) {
                throw ValidationException::withMessages(['tanggal_main' => $isMember ? 'Member hanya dapat booking maksimal 14 hari ke depan.' : 'Pelanggan biasa hanya dapat booking maksimal 3 hari ke depan.']);
            }
            $duration = (int) $data['durasi'];
            $end = $start->copy()->addHours($duration);
            $courtIds = array_values(array_unique(array_map('intval', $data['id_lapangans'] ?? [$data['id_lapangan'] ?? 0])));

            if ($start->minute !== 0 || $start->second !== 0 || $start->hour < 7 || $duration < 1 || $duration > 4 || $end->toDateString() !== $start->toDateString() || $end->format('H:i:s') > '23:00:00') {
                throw ValidationException::withMessages(['jadwal' => 'GOR buka pukul 07.00–23.00. Durasi maksimal 4 jam dan booking harus selesai paling lambat pukul 23.00.']);
            }
            if ($start->lessThanOrEqualTo(now('Asia/Makassar'))) throw ValidationException::withMessages(['jadwal' => 'Waktu mulai booking harus berada di masa depan.']);
            if (count($courtIds) < 1 || count($courtIds) > 4) {
                throw ValidationException::withMessages(['id_lapangans' => 'Pilih 1 sampai 4 lapangan.']);
            }

            sort($courtIds);
            $courts = DB::table('lapangan')->whereIn('id_lapangan', $courtIds)->orderBy('id_lapangan')->lockForUpdate()->get();
            if ($courts->count() !== count($courtIds) || $courts->contains(fn ($court) => $court->status !== 'tersedia')) {
                throw ValidationException::withMessages(['id_lapangans' => 'Satu atau lebih lapangan tidak tersedia.']);
            }

            foreach ($courtIds as $courtId) {
                $overlap = DB::table('detail_booking as d')->join('booking as b', 'b.id_booking', '=', 'd.id_booking')
                    ->where('d.id_lapangan', $courtId)->where('d.tanggal_main', $data['tanggal_main'])
                    ->whereIn('b.status_booking', ['Menunggu DP', 'DP Terverifikasi', 'Lunas'])
                    ->where('d.jam_mulai', '<', $end->format('H:i:s'))->where('d.jam_selesai', '>', $start->format('H:i:s'))->exists();
                if ($overlap) throw ValidationException::withMessages(['jadwal' => "Slot Lapangan {$courtId} sudah terisi."]);
            }

            $customerId = $customer?->id_pelanggan ?? DB::table('pelanggan')->insertGetId(
                ['nama' => $data['nama_pelanggan'], 'no_hp' => $data['no_hp'], 'created_at' => now(), 'updated_at' => now()],
                'id_pelanggan'
            );
            $rates = $this->rates($start, $end);
            $total = array_sum(array_column($rates, 'harga')) * count($courtIds);
            do {
                $code = 'BK-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
            } while (DB::table('booking')->where('kode_booking', $code)->exists());

            $id = DB::table('booking')->insertGetId([
                'kode_booking' => $code, 'status_booking' => 'Menunggu DP', 'total_biaya' => $total,
                'id_pelanggan' => $customerId, 'id_admin' => $adminId, 'batas_waktu_dp' => now()->addHours(2),
                'tanggal_booking' => now(), 'waktu_dibuat' => now(),
            ], 'id_booking');

            foreach ($courtIds as $courtId) {
                foreach ($rates as $rate) DB::table('detail_booking')->insert([
                    'id_booking' => $id, 'id_lapangan' => $courtId, 'id_tarif' => $rate['id'],
                    'tanggal_main' => $start->toDateString(), 'jam_mulai' => $rate['mulai'], 'jam_selesai' => $rate['selesai'],
                    'durasi' => 1, 'tarif_saat_booking' => $rate['harga'],
                ]);
            }
            DB::table('log_aktivitas')->insert(['id_pengguna' => $adminId, 'id_booking' => $id, 'aktivitas' => 'Buat booking', 'detail_aktivitas' => 'Booking dibuat', 'waktu_aktivitas' => now()]);

            return [
                'id_booking' => $id, 'kode_booking' => $code, 'id_lapangans' => $courtIds,
                'lapangan_names' => $courts->pluck('nomor_lapangan')->values(), 'id_lapangan' => $courtIds[0],
                'tanggal_main' => $data['tanggal_main'], 'jam_mulai' => $data['jam_mulai'], 'durasi' => $duration,
                'nama_pelanggan' => $data['nama_pelanggan'], 'no_hp' => $data['no_hp'],
                'status_booking' => 'Menunggu DP', 'total_biaya' => $total, 'nominal_dp' => $total / 2,
                'batas_waktu_dp' => now()->addHours(2),
            ];
        });
    }

    private function rates(Carbon $start, Carbon $end): array
    {
        $rates = [];
        for ($time = $start->copy(); $time < $end; $time->addHour()) {
            $weekend = $time->isWeekend();
            $tarif = DB::table('tarif')->where('jam_mulai', '<=', $time->format('H:i:s'))->where('jam_selesai', '>', $time->format('H:i:s'))
                ->where(fn ($query) => $query->where('hari_berlaku', 'Setiap Hari')->orWhere('hari_berlaku', $weekend ? 'Sabtu-Minggu' : 'Senin-Jumat'))->first();
            if (! $tarif) throw ValidationException::withMessages(['tarif' => 'Tarif tidak ditemukan untuk slot ini.']);
            $rates[] = ['id' => $tarif->id_tarif, 'harga' => (float) $tarif->tarif_per_jam, 'mulai' => $time->format('H:i:s'), 'selesai' => $time->copy()->addHour()->format('H:i:s')];
        }
        return $rates;
    }
}
