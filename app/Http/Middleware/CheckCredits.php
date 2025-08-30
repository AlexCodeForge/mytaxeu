<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\CreditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckCredits
{
    public function __construct(
        private CreditService $creditService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  int  $requiredCredits  Number of credits required (default: 1)
     */
    public function handle(Request $request, Closure $next, int $requiredCredits = 1): Response
    {
        $user = $request->user();

        if (!$user) {
            Log::warning('CheckCredits middleware: No authenticated user');
            return response()->json(['error' => 'Authentication required'], 401);
        }

        if (!$this->creditService->hasEnoughCredits($user, $requiredCredits)) {
            Log::info('CheckCredits middleware: Insufficient credits', [
                'user_id' => $user->id,
                'required' => $requiredCredits,
                'available' => $this->creditService->getCreditBalance($user),
                'route' => $request->route()?->getName(),
            ]);

            // Handle different request types
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Insufficient credits',
                    'required' => $requiredCredits,
                    'available' => $this->creditService->getCreditBalance($user),
                ], 402); // Payment Required
            }

            // For web requests, redirect with error message
            return redirect()->route('dashboard')->with('error',
                "No tienes suficientes créditos. Necesitas {$requiredCredits} crédito(s), pero solo tienes " .
                $this->creditService->getCreditBalance($user) . "."
            );
        }

        Log::debug('CheckCredits middleware: Credits check passed', [
            'user_id' => $user->id,
            'required' => $requiredCredits,
            'available' => $this->creditService->getCreditBalance($user),
        ]);

        return $next($request);
    }
}
