<?php

namespace App\Support;

use App\Models\Channel;
use App\Models\Toko;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Filter toko & channel untuk laporan berbasis resi.
 *
 * Nilai filter: id master, 'none' (resi tanpa toko/channel), atau null (semua).
 */
class TokoFilter
{
    public const NONE = 'none';

    public static function fromRequest(Request $request, string $key = 'toko_id'): int|string|null
    {
        return self::normalize($request->input($key));
    }

    public static function normalize(mixed $value): int|string|null
    {
        if ($value === null || $value === '' || $value === 'all') {
            return null;
        }
        if ($value === self::NONE) {
            return self::NONE;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    /**
     * Terapkan filter ke kolom toko_id/channel_id (Eloquent maupun Query Builder).
     */
    public static function apply($query, int|string|null $value, string $column): void
    {
        if ($value === null) {
            return;
        }

        if ($value === self::NONE) {
            $query->whereNull($column);

            return;
        }

        $query->where($column, $value);
    }

    /**
     * Batasi query ke resi milik toko/channel tertentu lewat kolom resi_id.
     */
    public static function applyViaResi($query, string $resiIdColumn, int|string|null $tokoId, int|string|null $channelId = null): void
    {
        if ($tokoId === null && $channelId === null) {
            return;
        }

        $query->whereIn($resiIdColumn, function ($sub) use ($tokoId, $channelId) {
            $sub->from('resis')->select('resis.id');
            self::apply($sub, $tokoId, 'resis.toko_id');
            self::apply($sub, $channelId, 'resis.channel_id');
        });
    }

    public static function tokoOptions(): Collection
    {
        return Toko::query()->orderBy('name')->get(['id', 'name']);
    }

    public static function channelOptions(): Collection
    {
        return Channel::query()->orderBy('name')->get(['id', 'name']);
    }

    public static function tokoLabel(int|string|null $value): string
    {
        return self::label(Toko::class, $value, 'Semua toko', 'Tanpa toko');
    }

    public static function channelLabel(int|string|null $value): string
    {
        return self::label(Channel::class, $value, 'Semua channel', 'Tanpa channel');
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private static function label(string $modelClass, int|string|null $value, string $all, string $none): string
    {
        if ($value === null) {
            return $all;
        }
        if ($value === self::NONE) {
            return $none;
        }

        return (string) ($modelClass::query()->whereKey($value)->value('name') ?? $all);
    }
}
