<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function store(Request $request, BookingService $bookings) { $data=$request->validate(['nama_pelanggan'=>'required|string|max:100','no_hp'=>'required|string|max:20','id_lapangan'=>'required|integer','tanggal_main'=>'required|date','jam_mulai'=>'required|date_format:H:i','durasi'=>'required|integer|min:1']); return response()->json($bookings->create($data),201); }
    public function show(string $kode, Request $request) { $booking=\DB::table('booking')->join('pelanggan','pelanggan.id_pelanggan','=','booking.id_pelanggan')->where('kode_booking',$kode)->where('no_hp',$request->query('no_hp'))->select('booking.*','pelanggan.nama','pelanggan.no_hp')->first(); abort_unless($booking,404); return response()->json($booking); }
}
