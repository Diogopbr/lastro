<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AccessController extends Controller
{
    public function login(Request $request): Response
    {
        abort_if(config('lastro.demo_enabled'), 409, 'Use uma demonstração privada para entrar.');
        $input = $request->validate(['email' => 'required|string|email|max:254', 'password' => 'required|string|max:256']);
        $user = User::where('email', $input['email'])->first();
        $workspace = $user === null ? null : DB::table('workspaces')->where('id', $user->workspace_id)->where('kind', 'normal')->whereNull('revoked_at')->first();
        if ($workspace === null || ! Hash::check($input['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'E-mail ou senha inválidos.']);
        }
        Auth::login($user);
        $request->session()->regenerate();

        return $request->expectsJson() ? response()->json(['redirect' => route('conferences.index')]) : redirect()->route('conferences.index', [], 303);
    }

    public function logout(Request $request): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->expectsJson() ? response()->json(['redirect' => route('login')]) : redirect()->route('login', [], 303);
    }
}
