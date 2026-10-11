<?php

namespace App\Http\Controllers\Access;

use App\Domain\Access\DemoWorkspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class DemoController extends Controller
{
    public function start(Request $request, DemoWorkspace $demo): Response
    {
        abort_unless(config('lastro.demo_enabled'), 404);
        $token = $request->cookie('lastro_visitor');
        if (! is_string($token) || ! preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            $token = bin2hex(random_bytes(32));
        }
        $user = $demo->start($token);
        Auth::login($user);
        $request->session()->regenerate();
        $response = $request->expectsJson() ? response()->json(['redirect' => route('conferences.index')], 201) : redirect()->route('conferences.index', [], 303);

        return $response->withCookie(new Cookie('lastro_visitor', $token, time() + 365 * 86400, '/', null, (bool) config('session.secure'), true, false, Cookie::SAMESITE_LAX));
    }

    public function reset(Request $request, DemoWorkspace $demo): Response
    {
        abort_unless(config('lastro.demo_enabled'), 404);
        $token = $request->cookie('lastro_visitor');
        abort_unless(is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token), 404);
        $user = $demo->reset($request->attributes->get('workspace'), $token, $request->ip() ?? 'unknown');
        Auth::login($user);
        $request->session()->regenerate();

        return $request->expectsJson() ? response()->json(['redirect' => route('conferences.index')]) : redirect()->route('conferences.index', [], 303);
    }
}
