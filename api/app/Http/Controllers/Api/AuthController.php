<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request) { $data=$request->validate(['username'=>'required','password'=>'required']); $user=\DB::table('pengguna')->where('username',$data['username'])->first(); abort_unless($user && password_verify($data['password'],$user->password_hash),401,'Kredensial tidak valid.'); $plain=Str::random(64); \DB::table('api_tokens')->insert(['id_pengguna'=>$user->id_pengguna,'token'=>hash('sha256',$plain),'expires_at'=>now()->addDay(),'created_at'=>now(),'updated_at'=>now()]); return ['token'=>$plain,'role'=>$user->role]; }
}
