<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view("auth.login");
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            "email" => ["required", "email"],
            "password" => ["required"],
        ]);

        if (! Auth::attempt($credentials, $request->boolean("remember"))) {
            AuditLog::log("login_failed", "Email : {$credentials["email"]}");

            return back()->withErrors([
                "email" => "Identifiants incorrects.",
            ])->onlyInput("email");
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if ($user->employee && $user->employee->status !== "active") {
            Auth::logout();

            return back()->withErrors([
                "email" => "Votre compte est désactivé. Contactez un administrateur.",
            ]);
        }

        AuditLog::log("login_success");

        return redirect()->intended($user->isAdmin() ? route("dashboard") : route("attendance.scan"));
    }

    public function logout(Request $request)
    {
        AuditLog::log("logout");

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route("login");
    }
}
