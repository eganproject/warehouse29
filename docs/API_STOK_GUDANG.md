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
- `GET /api/v1/stocks?as_of=YYYY-MM-DD&page=1&per_page=100`
- `GET /api/v1/stock-movements?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD&toko_id=<id>&page=1&per_page=100` (lihat [Pergerakan stok](#pergerakan-stok-get-apiv1stock-movements))

`per_page` maksimum 500. Data selalu diurutkan `updated_at ASC, sku ASC`.

Untuk sinkronisasi lebih dari satu halaman, gunakan nilai `meta.server_time` dari halaman pertama sebagai `updated_until` pada seluruh halaman berikutnya. Ini menjaga snapshot tetap stabil bila data berubah di tengah proses penarikan.

`qty` hanya mencakup stok reguler/layak jual. Stok rusak tidak termasuk. Stok bundle dihitung secara virtual dari komponen.

### Stok akhir per tanggal (`as_of`)

`as_of` bersifat opsional dan memakai tanggal WIB. Contoh:

```http
GET /api/v1/stocks?as_of=2026-06-30&page=1&per_page=100
Authorization: Bearer <API_TOKEN>
Accept: application/json
```

Server mengembalikan posisi stok akhir pada penutupan tanggal tersebut (30 Juni 2026 pukul 23:59:59 WIB). Amplop respons dan bentuk setiap baris tetap sama: `success`, `meta`, dan `data` dengan `sku`, `name`, `category`, `uom`, `qty`, `min_qty`, `status`, serta `updated_at`.

Jika `as_of` kosong, endpoint tetap mengembalikan posisi terkini dan mendukung filter `updated_since`/`updated_until` seperti sebelumnya. `as_of` tidak boleh digabung dengan kedua filter tersebut karena keduanya adalah filter perubahan data, bukan filter posisi stok historis.

## Pergerakan stok (`GET /api/v1/stock-movements`)

Mengembalikan data yang sama dengan menu **Laporan → Stok Per Tanggal → tab Pergerakan Stok** (termasuk filter toko). Endpoint memakai query laporan yang sama (`App\Support\StockPeriodReport`), sehingga angkanya selalu identik dengan halaman dan export Excel. Autentikasi, allowlist IP, dan rate limit sama dengan endpoint stok lainnya.

### Parameter (query string, semuanya opsional)

| Parameter | Nilai | Default | Keterangan |
|---|---|---|---|
| `date_from` | `YYYY-MM-DD` | tanggal 1 bulan dari `date_to` | Awal periode (WIB). |
| `date_to` | `YYYY-MM-DD` | hari ini | Akhir periode (WIB, inklusif). Rentang maksimal 366 hari. |
| `stock_type` | `regular`, `damaged` | `regular` | Stok layak jual atau stok rusak. |
| `category_id` | integer ≥ 0 | semua | `0` = Tanpa Kategori. |
| `sku` | teks | semua | Pencarian SKU persis (sama seperti kotak pencarian di halaman). |
| `status` | `positive`, `zero`, `negative`, `low` | semua | Status stok akhir; `low` = di bawah safety stock. |
| `movement` | `fast`, `medium`, `slow`, `non` | semua | Klasifikasi pergerakan. |
| `toko_id` | id toko, `none`, `all` | semua | Qty masuk/keluar, frekuensi, dan tanggal keluar hanya dari resi toko tsb. `none` = resi tanpa toko. Stok awal/akhir tetap stok gudang (stok tidak dipisah per toko). |
| `include` | `trend`, `per_toko` (pisahkan dengan koma) | – | Data tambahan: tren harian dan/atau rekap barang keluar per toko. |
| `page` | integer ≥ 1 | `1` | Halaman. |
| `per_page` | 1–500 | `100` | Jumlah baris per halaman. |

Urutan data sama dengan halaman: `qty_out DESC, outgoing_days DESC, sku ASC`.

### Contoh

```http
GET /api/v1/stock-movements?date_from=2026-09-01&date_to=2026-09-30&toko_id=3&movement=fast&include=trend,per_toko&per_page=100
Authorization: Bearer <API_TOKEN>
Accept: application/json
```

```json
{
  "success": true,
  "meta": {
    "warehouse_code": "WSEHA29",
    "server_time": "2026-10-21T15:00:00+07:00",
    "filters": {
      "date_from": "2026-09-01", "date_to": "2026-09-30", "days": 30,
      "stock_type": "regular", "category_id": null, "sku": null,
      "status": null, "movement": "fast", "toko_id": 3, "toko": "Toko Alpha"
    },
    "page": 1, "per_page": 100, "total": 42, "total_pages": 1
  },
  "summary": {
    "total_sku": 42, "opening": 1200, "qty_in": 300, "qty_out": 950, "closing": 550,
    "movement_count": { "fast": 42, "medium": 0, "slow": 0, "non": 0 }
  },
  "data": [
    {
      "sku": "SKU-001", "name": "Barang A", "category": "ATK", "uom": "pcs", "address": "A-01",
      "safety_stock": 10, "opening": 100, "qty_in": 20, "qty_out": 50, "closing": 70,
      "outgoing_days": 12, "outgoing_frequency": 18, "average_out_per_day": 1.6667,
      "days_cover": 42, "contribution_percent": 5.2632, "cumulative_percent": 5.2632,
      "movement": "fast", "movement_label": "Fast Moving",
      "last_out_at": "2026-09-30T14:10:00+07:00"
    }
  ],
  "daily_trend": [ { "date": "2026-09-01", "qty_in": 10, "qty_out": 35 } ],
  "per_toko": [
    { "toko_id": 3, "toko": "Toko Alpha", "source": "toko", "qty_out": 950,
      "frequency": 400, "sku_count": 42, "resi_count": 310, "active_days": 30 }
  ]
}
```

Catatan:

- `summary` dihitung dari seluruh baris yang lolos filter (bukan hanya halaman yang diminta).
- `daily_trend` dan `per_toko` hanya muncul bila diminta lewat `include`.
- Pada `per_toko`, `source` bernilai `toko`, `resi_without_toko` (resi tanpa toko, `toko_id: null`), atau `non_resi` (mutasi manual/lainnya, `toko_id: null`).
- `days_cover` bernilai `null` bila tidak ada barang keluar pada periode tsb.
- Parameter tidak valid menghasilkan `400` dengan `error.code = INVALID_PARAMETER`.

## Catatan operasional

- Rate limit default: 60 request/menit per kombinasi token dan IP.
- Respons `429` menyertakan header `Retry-After`.
- Gunakan HTTPS pada reverse proxy/web server produksi.
- Bila aplikasi berada di belakang reverse proxy, pastikan proxy tepercaya dikonfigurasi agar IP klien yang dibaca aplikasi adalah IP server pusat, bukan IP proxy.
