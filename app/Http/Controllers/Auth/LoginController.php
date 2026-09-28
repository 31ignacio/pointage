<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view("auth.login", [
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            "user_id" => ["required", "integer", "exists:users,id"],
            "password" => ["required"],
        ]);

        $user = User::find($credentials['user_id']);
        if (! $user || ! Auth::attempt(['email' => $user->email, 'password' => $credentials['password']], $request->boolean("remember"))) {
            AuditLog::log("login_failed", "Utilisateur : {$credentials["user_id"]}");

            return back()->withErrors([
                "user_id" => "Nom ou mot de passe incorrect.",
            ])->onlyInput("user_id");
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if ($user->employee && $user->employee->status !== "active") {
            Auth::logout();

            return back()->withErrors([
                "user_id" => "Votre compte est désactivé. Contactez un administrateur.",
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
