<?php

namespace App\Http\Middleware;

use App\Support\StockApiIpAllowlistService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class EnsureStockApiAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('stock_api.enabled')) {
            return $this->error('FORBIDDEN', 'API stok sedang tidak aktif.', 403);
        }

        $token = trim((string) $request->bearerToken());
        $configuredToken = trim((string) config('stock_api.token'));
        if ($token === '' || $configuredToken === '' || ! hash_equals($configuredToken, $token)) {
            return $this->error('INVALID_TOKEN', 'Token tidak valid atau sudah kedaluwarsa.', 401);
        }

        if (! StockApiIpAllowlistService::allows((string) $request->ip())) {
            return $this->error('FORBIDDEN', 'IP sumber tidak diizinkan mengakses API stok.', 403);
        }

        $limit = max(1, (int) config('stock_api.rate_limit_per_minute', 60));
        $key = 'stock-api:'.hash('sha256', $token.'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return $this->error('RATE_LIMIT_EXCEEDED', 'Terlalu banyak permintaan. Silakan coba lagi nanti.', 429)
                ->header('Retry-After', RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 60);

        return $next($request);
    }

    private function error(string $code, string $message, int $status): \Illuminate\Http\JsonResponse
    {
        return response()->json(['success' => false, 'error' => compact('code', 'message')], $status);
    }
}
