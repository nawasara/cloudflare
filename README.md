# nawasara/cloudflare

Cloudflare management dashboard untuk Nawasara.

## Fitur

- **Zone Management** — List domain, detail, SSL mode, security level
- **DNS Records** — CRUD DNS records per zone (A, AAAA, CNAME, MX, TXT, NS, SRV)
- **Firewall Rules** — CRUD WAF custom rules per zone
- **Analytics** — Overview traffic (requests, bandwidth, threats, visitors)
- **Cache Purge** — Purge all cache atau per-URL
- **Under Attack Mode** — Toggle security level termasuk Under Attack Mode

---

## Setup Cloudflare API Token

### 1. Login ke Cloudflare Dashboard

Buka [dash.cloudflare.com](https://dash.cloudflare.com) dan login dengan akun yang mengelola domain.

### 2. Buka halaman API Tokens

Klik **profile icon** (kanan atas) > **My Profile** > tab **API Tokens**.

### 3. Buat Custom Token

Klik **Create Token** > pilih **Create Custom Token** (paling bawah, "Get started").

### 4. Konfigurasi Permission

Isi nama token, misalnya: `Nawasara Dashboard`

Tambahkan permissions berikut sesuai fitur yang digunakan:

| Permission | Access | Digunakan untuk |
|---|---|---|
| **Zone** > **Zone** | Read | List zones, detail zone |
| **Zone** > **Zone Settings** | Edit | SSL mode, security level, Under Attack Mode |
| **Zone** > **DNS** | Edit | CRUD DNS records |
| **Zone** > **Firewall Services** | Edit | CRUD firewall rules |
| **Zone** > **Analytics** | Read | Dashboard analytics |
| **Zone** > **Cache Purge** | Purge | Purge cache all/per-URL |

> **Catatan:** Permission "Edit" sudah mencakup "Read", jadi tidak perlu menambahkan Read terpisah untuk DNS, Firewall, dan Zone Settings.

**Screenshot referensi konfigurasi:**

```
Token name:     Nawasara Dashboard
Permissions:
  [Zone]  [Zone]              [Read]
  [Zone]  [Zone Settings]     [Edit]
  [Zone]  [DNS]               [Edit]
  [Zone]  [Firewall Services] [Edit]
  [Zone]  [Analytics]         [Read]
  [Zone]  [Cache Purge]       [Purge]

Zone Resources:
  [Include]  [All zones]
  (atau pilih specific zones jika ingin membatasi)
```

### 5. Zone Resources

Pilih scope zone yang diizinkan:

- **All zones** — token bisa akses semua domain di akun
- **Specific zone** — batasi ke domain tertentu saja (lebih aman jika hanya manage beberapa domain)

### 6. (Opsional) IP Address Filtering

Jika server Nawasara punya IP statis, tambahkan filter:

- **Client IP Address Filtering** > **Is in** > masukkan IP server

Ini mencegah token digunakan dari IP lain jika bocor.

### 7. TTL (Opsional)

Set **Start Date** dan **End Date** jika ingin token berlaku sementara. Kosongkan untuk token permanen.

### 8. Create Token

Klik **Continue to summary** > review > **Create Token**.

**Salin token yang muncul** — token hanya ditampilkan sekali dan tidak bisa dilihat lagi.

### 9. Catat Account ID

Account ID bisa dilihat di:

1. Buka halaman **Overview** dari zone manapun
2. Lihat sidebar kanan > bagian **API** > **Account ID**

Atau dari URL: `dash.cloudflare.com/{ACCOUNT_ID}/...`

---

## Simpan Credential ke Vault

1. Buka Nawasara > **Vault** (`/nawasara-vault`)
2. Pilih group **Cloudflare**
3. Isi:
   - **API Token**: paste token dari langkah 8
   - **Account ID**: paste Account ID dari langkah 9
4. Simpan

Setelah tersimpan, package `nawasara/cloudflare` akan otomatis membaca credential dari Vault.

---

## Verifikasi

Setelah setup, buka **Cloudflare > Zones** di sidebar Nawasara. Jika konfigurasi benar, daftar domain akan muncul.

Jika gagal, periksa:
- Token sudah benar (tidak ada spasi/karakter tambahan)
- Account ID sesuai
- Permission token mencakup semua yang dibutuhkan
- Zone resources mencakup domain yang ingin dikelola

---

## Permissions Nawasara

Package ini mendaftarkan permissions berikut via `PermissionSeeder`:

| Permission | Deskripsi |
|---|---|
| `cloudflare.zone.view` | Lihat daftar zone/domain |
| `cloudflare.dns.view` | Lihat DNS records |
| `cloudflare.dns.create` | Buat DNS record baru |
| `cloudflare.dns.edit` | Edit DNS record |
| `cloudflare.dns.delete` | Hapus DNS record |
| `cloudflare.waf.view` | Lihat firewall rules |
| `cloudflare.waf.create` | Buat firewall rule |
| `cloudflare.waf.edit` | Edit firewall rule |
| `cloudflare.waf.delete` | Hapus firewall rule |
| `cloudflare.ssl.view` | Lihat status SSL |
| `cloudflare.ssl.manage` | Ubah SSL mode |
| `cloudflare.analytics.view` | Lihat analytics |
| `cloudflare.cache.purge` | Purge cache |
| `cloudflare.ddos.view` | Lihat security level |
| `cloudflare.ddos.manage` | Ubah security level / Under Attack Mode |

Jalankan seeder untuk mendaftarkan permissions:

```bash
php artisan db:seed --class="Nawasara\Cloudflare\Database\Seeders\PermissionSeeder"
```
