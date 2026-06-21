<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AutoLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('auth.bypass', false)) {
            abort_unless(app()->environment(['local', 'testing']), 500);
        }

        if (config('auth.bypass', false) && ! $request->user()) {
            Auth::login(User::first());
        }

        return $next($request);
    }
}
