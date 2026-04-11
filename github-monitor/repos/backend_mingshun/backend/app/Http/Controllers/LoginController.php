<?php


namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Helper;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function loginPage()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        } else {
            return view('login');
        }
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required'],
            'password' => ['required'],
        ], [
            'username.required' => '用户名不能为空',
            'password.required' => '密码不能为空',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            $now = Carbon::now();
            $user = Auth::user();

            if($user->status != 1){
                Auth::logout();

                $request->session()->invalidate();

                $request->session()->regenerateToken();

                return back()->withErrors([
                    'username' => '用户已被冻结，无法登录',
                ])->onlyInput('username');
            }

            $user->last_login = $now;

            $ip = Helper::getIp();
            $user->last_ip = $ip;

            $user->save();
            return redirect()->route('dashboard');
        }

        return back()->withErrors([
            'username' => '用户名或密码不正确',
        ])->onlyInput('username');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
