# Panduan Integrasi API Outbound Manual Gudang WSEHA29

Versi 17 November 2026

## Ringkasan

API ini dipakai sistem partner untuk **membuat transaksi barang keluar (outbound) manual** di WMS gudang WSEHA29, lalu memantau statusnya. Semua request dan respons memakai JSON. Waktu memakai zona WIB (UTC+7).

| Endpoint | Isi |
| --- | --- |
| `POST /api/v1/outbound-manuals` | Buat outbound manual baru (status `pending`) |
| `GET /api/v1/outbound-manuals/{code_atau_ref_no}` | Lihat detail dan status satu outbound manual |
| `GET /api/v1/health` | Cek koneksi dan token |

Alur bisnisnya:

1. Partner mengirim `POST /api/v1/outbound-manuals` berisi nomor referensi (`ref_no`) dan daftar SKU + qty.
2. WMS membuat transaksi Outbound Manual dengan status **`pending`**. Stok **belum** terpotong.
3. Admin gudang memeriksa dan menyetujui (approve) transaksi di menu Outbound → Manual. Saat disetujui, stok terpotong dan status berubah menjadi **`approved`**.
4. Partner dapat memantau status lewat `GET /api/v1/outbound-manuals/{code}`.

Transaksi dari API ditandai dengan awalan `[API]` pada catatan (`note`) sehingga mudah dikenali di WMS.

## Akses

Akses sama dengan API Stok (token dan pendaftaran IP yang sama). Partner butuh tiga hal dari tim Warehouse29:

| Yang dibutuhkan | Keterangan |
| --- | --- |
| Base URL | `https://<domain-warehouse29>` (diberikan oleh tim Warehouse29) |
| Token API | Kunci rahasia. Simpan di server partner, jangan ditaruh di aplikasi front-end atau repository |
| Pendaftaran IP | Kirim IP publik server partner (satu IP atau rentang CIDR). Request dari IP lain akan ditolak |

Kirim header ini di setiap request:

```
Authorization: Bearer <TOKEN>
Accept: application/json
Content-Type: application/json
```

Uji koneksi dengan `GET /api/v1/health` (lihat Panduan Integrasi API Stok).

## Buat outbound manual – `POST /api/v1/outbound-manuals`

### Body request

| Field | Tipe | Wajib | Keterangan |
| --- | --- | --- | --- |
| `ref_no` | teks, maks. 100 | Ya | Nomor referensi unik dari sistem partner (mis. nomor PO/SO/permintaan). Dipakai sebagai kunci anti-duplikat |
| `transacted_at` | ISO-8601 dengan zona waktu, mis. `2026-11-17T14:00:00+07:00` | Tidak | Waktu barang keluar. Default: waktu server saat request diterima. Tidak boleh lebih dari 1 hari ke depan |
| `note` | teks, maks. 1000 | Tidak | Catatan transaksi. Disimpan dengan awalan `[API]` |
| `items` | array, 1–200 baris | Ya | Daftar barang yang keluar |
| `items[].sku` | teks | Ya | SKU barang di WMS (huruf besar/kecil tidak dibedakan). Harus ada dan aktif |
| `items[].qty` | bilangan bulat 1–1.000.000 | Ya | Jumlah barang keluar (dalam UOM barang) |
| `items[].stock_source` | `regular` atau `damaged` | Tidak | Sumber stok: `regular` = stok layak jual (default), `damaged` = stok rusak |
| `items[].note` | teks, maks. 500 | Tidak | Catatan per baris |

Aturan tambahan:

- Satu SKU dengan `stock_source` yang sama hanya boleh muncul sekali. Gabungkan qty-nya dalam satu baris.
- SKU bundle boleh dipakai untuk `stock_source: regular` (saat approve, stok komponennya yang dipotong). SKU bundle tidak boleh dipakai untuk `stock_source: damaged`.
- Ketersediaan stok **tidak** dicek saat transaksi dibuat; pengecekan dan pemotongan stok terjadi saat admin gudang menyetujui transaksi.

### Contoh

```bash
curl -X POST https://<domain>/api/v1/outbound-manuals \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "ref_no": "SO-2026-11-0001",
    "transacted_at": "2026-11-17T14:00:00+07:00",
    "note": "Kirim ke cabang Bandung",
    "items": [
      { "sku": "SKU-001", "qty": 5, "note": "dus 1" },
      { "sku": "SKU-002", "qty": 2, "stock_source": "damaged" }
    ]
  }'
```

Respons sukses `201 Created`:

```json
{
  "success": true,
  "message": "Outbound manual berhasil dibuat dan menunggu persetujuan admin gudang.",
  "data": {
    "code": "OUT-MNL-20261117140012-AB12",
    "ref_no": "SO-2026-11-0001",
    "type": "manual",
    "status": "pending",
    "transacted_at": "2026-11-17T14:00:00+07:00",
    "approved_at": null,
    "note": "[API] Kirim ke cabang Bandung",
    "total_qty": 7,
    "items": [
      { "sku": "SKU-001", "name": "Barang A", "qty": 5, "stock_source": "regular", "note": "dus 1" },
      { "sku": "SKU-002", "name": "Barang B", "qty": 2, "stock_source": "damaged", "note": null }
    ],
    "created_at": "2026-11-17T14:00:12+07:00"
  }
}
```

Simpan `data.code` (kode transaksi WMS) di sistem partner untuk pengecekan status.

### Anti-duplikat dan retry

`ref_no` bersifat unik untuk outbound manual. Bila request dikirim ulang dengan `ref_no` yang sudah pernah dibuat (mis. karena timeout lalu retry), WMS **tidak** membuat transaksi baru dan membalas `409` berisi data transaksi yang sudah ada:

```json
{
  "success": false,
  "error": {
    "code": "DUPLICATE_REF_NO",
    "message": "Outbound manual dengan ref_no SO-2026-11-0001 sudah ada (OUT-MNL-20261117140012-AB12)."
  },
  "data": { "code": "OUT-MNL-20261117140012-AB12", "status": "pending", "...": "..." }
}
```

Jadi aman untuk melakukan retry dengan `ref_no` yang sama: perlakukan `409 DUPLICATE_REF_NO` sebagai "sudah tercatat" dan ambil `data.code` dari respons. Untuk mengirim transaksi yang memang berbeda, gunakan `ref_no` baru.

## Cek status – `GET /api/v1/outbound-manuals/{code_atau_ref_no}`

Parameter path bisa berupa kode transaksi WMS (`OUT-MNL-...`) atau `ref_no` partner.

```bash
curl https://<domain>/api/v1/outbound-manuals/SO-2026-11-0001 \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```

Respons `200` berisi `data` dengan bentuk yang sama seperti respons pembuatan:

```json
{
  "success": true,
  "data": {
    "code": "OUT-MNL-20261117140012-AB12",
    "ref_no": "SO-2026-11-0001",
    "type": "manual",
    "status": "approved",
    "transacted_at": "2026-11-17T14:00:00+07:00",
    "approved_at": "2026-11-17T15:30:00+07:00",
    "note": "[API] Kirim ke cabang Bandung",
    "total_qty": 7,
    "items": [ { "sku": "SKU-001", "name": "Barang A", "qty": 5, "stock_source": "regular", "note": "dus 1" } ],
    "created_at": "2026-11-17T14:00:12+07:00"
  }
}
```

| Field | Arti |
| --- | --- |
| `code` | Kode transaksi di WMS |
| `ref_no` | Nomor referensi dari partner |
| `status` | `pending` (menunggu persetujuan, stok belum terpotong) atau `approved` (sudah disetujui, stok terpotong) |
| `transacted_at` | Waktu barang keluar |
| `approved_at` | Waktu disetujui, `null` bila masih pending |
| `total_qty` | Jumlah qty semua baris |
| `items[].stock_source` | `regular` (stok layak jual) atau `damaged` (stok rusak) |

Catatan: admin gudang masih bisa mengubah atau menghapus transaksi yang berstatus `pending` di WMS. Bila transaksi dihapus, `GET` akan membalas `404`.

## Error dan batas request

Semua error berbentuk `{"success": false, "error": {"code": "...", "message": "..."}}`. Error validasi menambahkan `error.fields` yang memetakan nama field ke daftar pesan.

| HTTP | `error.code` | Penyebab | Tindakan |
| --- | --- | --- | --- |
| 401 | `INVALID_TOKEN` | Header `Authorization` kosong atau token salah | Cek token; minta token baru bila perlu |
| 403 | `FORBIDDEN` | IP server belum terdaftar, API dinonaktifkan, atau fitur outbound manual via API dimatikan | Hubungi tim Warehouse29 |
| 404 | `NOT_FOUND` | Kode / `ref_no` tidak ditemukan (GET) | Cek kode yang dipakai |
| 409 | `DUPLICATE_REF_NO` | `ref_no` sudah pernah dibuat | Anggap sudah tercatat; pakai `data.code` dari respons |
| 409 | `CONFLICT` | Request lain dengan `ref_no` sama sedang diproses bersamaan | Tunggu beberapa detik lalu ulangi |
| 422 | `VALIDATION_ERROR` | Body tidak valid: field wajib kosong, SKU tidak ditemukan / tidak aktif, SKU duplikat, qty < 1, format `transacted_at` salah | Perbaiki data sesuai `error.fields`; jangan di-retry tanpa perubahan |
| 429 | `RATE_LIMIT_EXCEEDED` | Lebih dari 60 request per menit (dihitung bersama API Stok) | Tunggu sesuai header `Retry-After` (detik), lalu ulangi |

Contoh error validasi `422`:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "SKU SKU-999 tidak ditemukan.",
    "fields": {
      "items.1.sku": ["SKU SKU-999 tidak ditemukan."],
      "items.2.qty": ["items.2.qty minimal 1."]
    }
  }
}
```

Kunci di `fields` memakai indeks baris mulai dari 0 (`items.1.sku` = baris kedua).

Praktik yang disarankan:

- Panggil API dari server partner, bukan dari browser atau aplikasi mobile, agar token tidak bocor.
- Selalu isi `ref_no` dengan nomor unik dari sistem partner, dan gunakan nomor yang sama saat retry.
- Validasi SKU di sisi partner terlebih dulu memakai `GET /api/v1/stocks` (lihat Panduan Integrasi API Stok) agar tidak kena `422`.
- Untuk error `5xx` atau koneksi putus, ulangi dengan jeda bertahap (mis. 2, 4, 8 detik) memakai `ref_no` yang sama.
- Untuk memantau persetujuan, cek status secara berkala dengan jeda wajar (mis. tiap 5–15 menit), bukan tiap detik.
