<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class ItemBulkUpdateFields
{
    /**
     * Field yang aman diperbarui lewat template update item.
     * SKU sengaja tidak menjadi pilihan karena hanya berfungsi sebagai identifier.
     */
    public const DEFINITIONS = [
        'name' => [
            'label' => 'Nama Item',
            'description' => 'Nama item yang tampil di seluruh sistem.',
            'headings' => ['name'],
        ],
        'uom' => [
            'label' => 'Satuan (UOM)',
            'description' => 'Kode satuan yang sudah terdaftar, contoh: pcs.',
            'headings' => ['uom'],
        ],
        'category' => [
            'label' => 'Kategori',
            'description' => 'Dua kolom kategori terdaftar; kosong berarti Tanpa Kategori.',
            'headings' => ['parent_category', 'category'],
        ],
        'address' => [
            'label' => 'Alamat Penyimpanan',
            'description' => 'Lokasi atau alamat penyimpanan item.',
            'headings' => ['address'],
        ],
        'description' => [
            'label' => 'Deskripsi',
            'description' => 'Keterangan tambahan item.',
            'headings' => ['description'],
        ],
        'safety_stock' => [
            'label' => 'Stok Pengaman',
            'description' => 'Angka minimum stok, minimal 0.',
            'headings' => ['safety_stock'],
        ],
        'is_active' => [
            'label' => 'Status Aktif',
            'description' => 'Isi Aktif atau Nonaktif.',
            'headings' => ['is_active'],
        ],
        'bundle' => [
            'label' => 'Tipe & Komponen Bundle',
            'description' => 'Isi tipe item dan komponen dalam format SKU:QTY.',
            'headings' => ['is_bundle', 'bundle_components'],
        ],
    ];

    public static function definitions(): array
    {
        return self::DEFINITIONS;
    }

    public static function allowed(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /**
     * @return array<int, string>
     */
    public static function normalize(array $fields): array
    {
        $requested = array_values(array_unique(array_map(
            static fn ($field) => trim((string) $field),
            $fields
        )));

        $invalid = array_values(array_diff($requested, self::allowed()));
        if ($requested === [] || $invalid !== []) {
            throw ValidationException::withMessages([
                'fields' => $requested === []
                    ? 'Pilih minimal satu field yang akan diperbarui.'
                    : 'Pilihan field tidak valid: '.implode(', ', $invalid).'.',
            ]);
        }

        // Gunakan urutan definisi agar susunan kolom template selalu konsisten.
        return array_values(array_filter(
            self::allowed(),
            static fn (string $field) => in_array($field, $requested, true)
        ));
    }

    /**
     * @return array<int, string>
     */
    public static function headings(array $fields): array
    {
        $headings = ['sku'];
        foreach (self::normalize($fields) as $field) {
            $headings = array_merge($headings, self::DEFINITIONS[$field]['headings']);
        }

        return $headings;
    }
}
