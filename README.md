# API Payment Gateway (Bahasa Indonesia)

## Gambaran Umum
Sistem API Payment Gateway berbasis Laravel yang terintegrasi dengan layanan payment gateway Midtrans (opsional, dapat diatur di .env).

## Kebutuhan Sistem
- PHP versi 8.x dengan dukungan PostgreSQL
- Database PostgreSQL
- Composer
- Port 9191 harus tersedia

## Konfigurasi Lingkungan

### Konfigurasi Dasar
```env
APP_URL=http://localhost:9191
APP_ENV=local
APP_DEBUG=true
```

### Konfigurasi Database
Sistem menggunakan PostgreSQL sebagai database utama:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=api_payment_gateway_laravel
DB_USERNAME=postgres
```

### Session & Cache
- Manajemen session: Berbasis database
- Penyimpanan cache: Berbasis database
- Waktu session: 120 menit

### Dokumentasi API (Swagger)
Dokumentasi API menggunakan L5-Swagger dengan konfigurasi:
- URL Dasar: http://127.0.0.1:9191/api/documentation
- Autentikasi: Bearer token
- Auto-generate dokumentasi aktif

### Integrasi Payment Gateway
Sistem terintegrasi dengan payment gateway Midtrans:
- Mode Sandbox: Aktif
- API Endpoint: https://app.sandbox.midtrans.com/snap/v2/vtweb/

## Cara Memulai

1. Clone repository
2. Salin `.env.example` ke `.env`
3. Sesuaikan pengaturan database
4. Jalankan `composer install`
5. Generate application key: `php artisan key:generate`
6. Jalankan migrasi: `php artisan migrate`
7. Jalankan server: `php artisan serve --port=9191`

## Autentikasi API
1. Untuk Login Admin:
   - Menggunakan JWT (JSON Web Token)
   - Endpoint: `/api/admin/login`
   - Kredensial default tersedia melalui database seeder

2. Untuk Deposit dan Penarikan:
   - Menggunakan bearer token base64 (TOKEN_NAME di file .env)
   - Format: `Authorization: Bearer <base64_token>`

## Integrasi Pembayaran
Sistem ini terintegrasi dengan payment gateway Midtrans. Untuk menggunakan fitur pembayaran:
1. Daftar akun Midtrans
2. Konfigurasi kredensial Midtrans di `.env`:
   - MIDTRANS_USE=true --> jika ingin menggunakan midtrans
   - MIDTRANS_SERVER_KEY
   - MIDTRANS_CLIENT_KEY

## Cara Penggunaan
1. Silahkan setup sistem dan jalankan di port 9191 (sesuai cara setup di atas)
2. Silahkan akses dokumen swgere dengan url http://127.0.0.1:9191/api/documentation

## Deposit
1. Sebelum melakukan deposit, silahkan buat token, di sweger di endpoint Authentication
2. Silahkan masukan access_token ke header. contoh : Authorization: Bearer bnVyIG11aGFtbWFkIGFiZHVsIGZhbGFxXzE3NDMxMTMxMTc=
3. Silahkan akses endpoint deposit
4. untuk melakukan pembayaran deposit, user harus mencetak order id di endpoint http://127.0.0.1:9191/api/deposit/generate-order-id
5. Maka secara otomatis order id akan terbentuk dari responnya
6. Silahkan masukan order id (contoh: INV-20250327103349-4979) ke body post deposit
7. Silahkan masukan amount dan masukan waktu (timestamp), silahkan klik Execute
8. Silahkan cek di endpoint GET deposit untuk cek saldo

## Menggunakan Midtrans
1. Jika ingin menggunakan Midtrans maka di file .env ubah value MIDTRANS_USE menjadi true dan masukan MIDTRANS_SERVER_KEY dan MIDTRANS_CLIENT_KEY, dengan memasukan value MIDTRANS_IS_PRODUCTION true jika production atau false jika di sambox
2. Silahkan atur url callback atau notifikasi midtrasn ke endpoint http://127.0.0.1:9191/api/deposit/callback (jika menggunakan url local maka perlu Menggunakan Layanan Tunneling)
3. Jika ingin mengakses secara manual, bisa akses endpoint /api/deposit/transaction-status/{order_id}
4. silahkan masukan order id
5. maka akan muncul respon status payment, silahkan Copy respon data dan masukan ke endpoint /api/deposit/callback, setelah itu klik Execute
6. Jika dari midtrasn sudah sukses maka otomatis di deposit sudah bertambah saldonya dan di history transaksi sudah terupdate

## Withdrawal (Penarikan)
1. Sebelum melakukan penarikan, silahkan buat token, di sweger di endpoint Authentication
2. Silahkan masukan access_token ke header. contoh : Authorization: Bearer bnVyIG11aGFtbWFkIGFiZHVsIGZhbGFxXzE3NDMxMTMxMTc=
3. Silahkan akses endpoint Withdrawal /api/withdrawal
4. masukan amount yang akan di setorkan
5. klik execute

## masuk ke dashboard admin
1. Silahkan login dengan endpoint /api/admin/login (menggunakan email dan password yang sudah di daftarkan oleh seeder)
2. Data admin dimasukan menggunakan seeder (karena hanya butuh satu akun saja di scope ini)
3. masukan token dan akses endpoint /api/admin/dashboard/transactions untuk melihat data dan riwayat transaksi deposit maupun penarikan 

## Fitur Utama
1. Deposit:
   - Pembuatan order ID
   - Integrasi dengan Midtrans (opsional)
   - Pengecekan saldo
   - Riwayat transaksi

2. Withdrawal (Penarikan):
   - Penarikan saldo
   - Validasi saldo
   - Riwayat transaksi

3. Dashboard Admin:
   - Monitoring transaksi
   - Riwayat deposit dan penarikan

## Penggunaan Midtrans
Konfigurasi di .env:
```env
MIDTRANS_USE=true           # Aktifkan/nonaktifkan Midtrans
MIDTRANS_IS_PRODUCTION=false # true untuk production, false untuk sandbox
MIDTRANS_SERVER_KEY=        # Server key dari Midtrans
MIDTRANS_CLIENT_KEY=        # Client key dari Midtrans
```

Endpoint callback Midtrans:
- URL: `http://127.0.0.1:9191/api/deposit/callback`
- Memerlukan layanan tunneling jika menggunakan localhost

## Dokumentasi API
- URL Swagger: http://127.0.0.1:9191/api/documentation
- Autentikasi menggunakan Bearer token
- Dokumentasi lengkap endpoint tersedia di Swagger UI

## Catatan Penting
- Pastikan port 9191 tidak digunakan oleh aplikasi lain
- Backup database secara berkala
- Gunakan mode sandbox Midtrans untuk testing
- Simpan kredensial Midtrans dengan aman
