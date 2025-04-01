<?php

namespace App\Http\Controllers\APIController;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

class AuthController extends Controller
{
    public function signup()
    {
        return view('auth.signup');
    }
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|unique:App\Models\User|email',
            'password' => 'required|min:6'
        ]);
        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->role = 'reader';
        $user->save();
        $user->remember_token = $user->createToken('myapptoken')->plainTextToken;
        $user->save();
        
        return response()->json($user);
    }
    public function login()
    {
        // return view('auth.signin');
    }
    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $token = $request->user()->createToken('myapptoken')->plainTextToken;;

            return response()->json($token);
        }

        // return response()->json(0);
    }
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response('Logout');
    }
}
