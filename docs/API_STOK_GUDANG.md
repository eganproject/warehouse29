# API Stok Gudang

Implementasi ini mengikuti kontrak `GET /api/v1/stocks` untuk gudang `WSEHA29`.

## Konfigurasi produksi

Simpan nilai berikut di konfigurasi lingkungan produksi, bukan di source code:

```env
STOCK_API_ENABLED=true
STOCK_API_WAREHOUSE_CODE=WSEHA29
STOCK_API_TOKEN=<token-rahasia-panjang-dan-acak>
STOCK_API_RATE_LIMIT_PER_MINUTE=60
```

Token dikirim melalui header `Authorization: Bearer <token>`. Tambahkan IP server pusat di menu **Master Data → Akses API** sebelum API dipakai.

## Deploy pertama kali

```bash
php artisan migrate
php artisan db:seed --class=MenuSeeder
php artisan stock-api:backfill
php artisan optimize:clear
```

`stock-api:backfill` wajib dijalankan sekali agar seluruh item lama ikut tersedia saat full sync. Sesudah itu, perubahan item dan stok reguler dicatat otomatis. Barang yang dihapus tetap dikirim sebagai `status: deleted`.

## Endpoint

- `GET /api/v1/health`
- `GET /api/v1/stocks?updated_since=<ISO-8601>&updated_until=<ISO-8601>&page=1&per_page=100`

`per_page` maksimum 500. Data selalu diurutkan `updated_at ASC, sku ASC`.

Untuk sinkronisasi lebih dari satu halaman, gunakan nilai `meta.server_time` dari halaman pertama sebagai `updated_until` pada seluruh halaman berikutnya. Ini menjaga snapshot tetap stabil bila data berubah di tengah proses penarikan.

`qty` hanya mencakup stok reguler/layak jual. Stok rusak tidak termasuk. Stok bundle dihitung secara virtual dari komponen.

## Catatan operasional

- Rate limit default: 60 request/menit per kombinasi token dan IP.
- Respons `429` menyertakan header `Retry-After`.
- Gunakan HTTPS pada reverse proxy/web server produksi.
- Bila aplikasi berada di belakang reverse proxy, pastikan proxy tepercaya dikonfigurasi agar IP klien yang dibaca aplikasi adalah IP server pusat, bukan IP proxy.
