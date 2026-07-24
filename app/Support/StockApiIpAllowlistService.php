<?php

namespace App\Support;

use App\Models\ApiIpAllowlist;

class StockApiIpAllowlistService
{
    public static function allows(string $requestIp): bool
    {
        return ApiIpAllowlist::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('ip_address')
            ->contains(fn ($allowedIp) => self::matches($requestIp, (string) $allowedIp));
    }

    public static function matches(string $requestIp, string $allowedIp): bool
    {
        [$network, $prefix] = array_pad(explode('/', $allowedIp, 2), 2, null);
        $request = inet_pton($requestIp);
        $networkBinary = inet_pton($network);
        if ($request === false || $networkBinary === false || strlen($request) !== strlen($networkBinary)) {
            return false;
        }

        $prefixLength = $prefix === null ? strlen($networkBinary) * 8 : (int) $prefix;
        $maxBits = strlen($networkBinary) * 8;
        if ($prefixLength < 0 || $prefixLength > $maxBits) {
            return false;
        }

        $wholeBytes = intdiv($prefixLength, 8);
        $remainingBits = $prefixLength % 8;
        if ($wholeBytes > 0 && substr($request, 0, $wholeBytes) !== substr($networkBinary, 0, $wholeBytes)) {
            return false;
        }
        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
        return (ord($request[$wholeBytes]) & $mask) === (ord($networkBinary[$wholeBytes]) & $mask);
    }
}
