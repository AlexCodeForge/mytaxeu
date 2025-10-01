<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckUserSuspension
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If no user is authenticated, continue
        if (!$user) {
            return $next($request);
        }

        Log::info('🚨 SUSPENSION CHECK:', [
            'user_id' => $user->id,
            'email' => $user->email,
            'is_suspended' => $user->is_suspended,
            'suspended_at' => $user->suspended_at,
            'route' => $request->path(),
        ]);

        // Check if user is suspended
        if ($user->is_suspended) {
            Log::warning('🚨 SUSPENDED USER BLOCKED:', [
                'user_id' => $user->id,
                'email' => $user->email,
                'attempted_route' => $request->path(),
                'suspension_reason' => $user->suspension_reason,
            ]);

            // Log out the user immediately
            Auth::logout();

            // Invalidate the session
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Redirect to login with suspension message
            return redirect()->route('login')->with('error', 'Tu cuenta ha sido suspendida. ' . ($user->suspension_reason ? 'Motivo: ' . $user->suspension_reason : 'Por favor, contacta con soporte para más información.'));
        }

        return $next($request);
    }
}

