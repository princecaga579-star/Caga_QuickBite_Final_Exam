<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
class AuthController extends Controller {
 public function login(Request $request){$data=$request->validate(['email'=>'required|email','password'=>'required|string']); $user=\App\Models\User::where('email',$data['email'])->first(); if(!$user||!Hash::check($data['password'],$user->password)||!$user->is_admin)return response()->json(['message'=>'Invalid admin credentials.'],422); $token=$user->createToken('quickbite-admin')->plainTextToken; return response()->json(['token'=>$token,'user'=>['id'=>$user->id,'name'=>$user->name,'email'=>$user->email]]);}
 public function me(Request $request){$u=$request->user(); abort_unless($u->is_admin,403); return ['id'=>$u->id,'name'=>$u->name,'email'=>$u->email];}
 public function logout(Request $request){$request->user()->currentAccessToken()?->delete(); return response()->json(['message'=>'Logged out']);}
}
