# Arsitektur Nusa Travel

Catatan onboarding untuk pengembang yang baru masuk ke repositori ini. Fokusnya: bagaimana permintaan mengalir, di mana aturan bisnis disimpan, dan di mana biasanya perubahan harus dilakukan.

## 1. Ringkasan

Aplikasi pemesanan tiket travel antarkota (contoh rute Mukomuko–Bengkulu–Padang). Satu pesanan menahan kursi pada satu jadwal, dibayar lewat transfer manual atau Midtrans Snap, lalu menghasilkan e-ticket dan faktur A4 ber-QR.

Dua peran saja: `super_admin` (operasional penuh) dan `pelanggan`. Tamu tanpa akun juga dapat memesan; aksesnya diverifikasi dengan nomor WhatsApp (lihat 4.2).

## 2. Tumpukan teknologi

| Lapisan | Pilihan |
| --- | --- |
| Bahasa / framework | PHP ^8.3, Laravel ^13.17 |
| Basis data | SQLite (`database/database.sqlite`); pengujian memakai SQLite `:memory:` |
| Frontend | Vite 8 + Tailwind CSS v4 (`@tailwindcss/vite`), Alpine tidak dipakai, font Plus Jakarta Sans dari Google Fonts |
| Dokumen | `barryvdh/laravel-dompdf` (PDF A4), `bacon/bacon-qr-code` (QR sebagai SVG data-URI) |
| Antrean / sesi | `QUEUE_CONNECTION=sync`, sesi berbasis file |
| Bahasa aplikasi | `APP_LOCALE=id`, `APP_FALLBACK_LOCALE=en`, terjemahan di `lang/id` + `lang/id.json` |
| Penyimpanan | disk `local` untuk bukti transfer, disk `public` untuk branding & foto armada |
| Alat bantu | PHPUnit 12, Laravel Pint, Pail (log), Boost (MCP untuk agen AI) |

Skeleton Laravel sudah bergaya ramping: tidak ada `app/Http/Kernel.php`. Middleware dan pengecualian CSRF didaftarkan pada `bootstrap/app.php` (alias `super_admin`, pengecualian CSRF untuk `payments/midtrans/webhook`, serta middleware `AuthenticateSession` pada grup `web` agar sesi lain gugur ketika kata sandi diganti).

## 3. Peta direktori

```
app/Http/Controllers/           # publik + subfolder Admin/
app/Http/Requests/              # ReserveBookingRequest, OperationalRequest
app/Http/Middleware/            # EnsureSuperAdmin
app/Models/                     # 17 model Eloquent
app/Policies/                   # BookingPolicy, InvoicePolicy
app/Services/                   # BookingService (kursi & kedaluwarsa), PaymentService (uang & faktur)
app/Console/Commands/           # CreateSuperAdmin (app:super-admin)
routes/web.php                  # seluruh rute HTTP
routes/console.php              # bookings:expire + jadwal setiap menit
resources/views/                # layouts/ (+partials/), admin/, customer/, partials/, halaman publik
tests/Feature/TravelFlowTest.php# tes alur end-to-end aplikasi
docs/                           # PLAN.md, ARCHITECTURE.md, REVIEW.md
```

Model `User` memakai atribut PHP (`#[Fillable]`, `#[Hidden]`) alih-alih properti `$fillable`; model lain masih memakai properti biasa. Pertahankan gaya di sekitarnya saat menambah model.

## 4. Alur utama

### 4.1 Pencarian dan pemilihan kursi

`HomeController::search` memfilter jadwal lewat `route.origin`, `route.destination`, tanggal, model kendaraan, dan sisa kapasitas (subquery ke `booking_seats`). `HomeController::show` memuat kursi kendaraan dan mengirim daftar kursi terisi ke `resources/views/schedule.blade.php`.

### 4.2 Pembuatan pesanan

`BookingController::store` → `BookingService::reserve` (satu transaksi):

1. `expireDue()` lebih dulu melepas kursi pesanan yang kedaluwarsa.
2. Baris `schedules` dikunci; jadwal harus `terjadwal`, belum berangkat, dan kendaraannya `aktif`.
3. Kursi harus unik antar penumpang, ada di kendaraan itu, dan `selectable`.
4. Kursi yang sudah ada di `booking_seats` untuk jadwal itu ditolak dengan pesan validasi.
5. `fare_snapshot` diambil dari `schedules.fare`, ditambah biaya `pickup_fee`/`dropoff_fee` dari tabel `settings`.
6. Pesanan berlaku **20 menit** (`expires_at`).

Pesanan tamu (`user_id` null) diverifikasi dengan **nomor WhatsApp** yang diisi saat memesan. Sesi tamu diberi akses **24 jam** (`Booking::grantGuestAccess`, `hasGuestAccess`); pemulihan dari perangkat lain memakai `/cek-booking` dengan kode pesanan + nomor WhatsApp. Perbandingan nomor memakai `Booking::normalizePhone` sehingga `0812…`, `62812…`, dan `+62 812-…` dianggap sama. Tidak ada kunci rahasia yang ditampilkan atau disimpan.

### 4.3 Pembayaran

| Jalur | Kode | Catatan |
| --- | --- | --- |
| Transfer manual | `PaymentService::manual` | Mengunggah bukti (`payments.proof`), disetujui admin di `/admin/pembayaran` |
| Midtrans Snap | `PaymentService::midtrans` | Membuat transaksi ke `/snap/v1/transactions`, menyimpan `redirect_url` |
| Webhook | `PaymentService::webhook` | Signature SHA-512 wajib, idempoten lewat `payment_events.event_key` |

`PaymentService::approve` adalah satu-satunya jalan pesanan menjadi lunas. Di dalam satu transaksi: memvalidasi status pesanan dan nominal, menandai pembayaran `lunas`, mengubah pesanan menjadi `dikonfirmasi`/`lunas`, lalu membuat faktur berurutan per hari (`invoice_counters`) beserta `invoice_items` dan `snapshot` harga.

### 4.4 Dokumen dan verifikasi

`DocumentController` menyajikan e-ticket dan faktur (HTML serta PDF) dengan QR berisi URL verifikasi. Akses dijaga `BookingPolicy`/`InvoicePolicy` dan wajib `payment_status = lunas`. Halaman `/verifikasi/tiket/{code}` dan `/verifikasi/faktur/{number}` bersifat publik tetapi sengaja hanya menampilkan status validitas, tanpa data penumpang.

### 4.5 Operasional admin

`Admin/OperationalController` adalah CRUD generik untuk `vehicles`, `drivers`, `routes`, `schedules`, dan `bank-accounts` dengan tipe sebagai segmen URL (`/admin/data/{type}`). Aturan khusus jadwal: durasi diambil dari rute dan benturan kendaraan/sopir dicek dengan waktu persiapan **60 menit**. Kapasitas kendaraan hanya boleh diubah bila tidak ada jadwal mendatang (selain yang dibatalkan) dan tidak ada kursi yang pernah dipakai di `booking_passengers`; bila boleh, seluruh kursi dibuat ulang. `saveSeats` memvalidasi bahwa seluruh ID kursi milik kendaraan tersebut, jumlah kursi penumpang sama dengan kapasitas, dan nomor/posisi unik, lalu menulis ulang posisi dalam dua fase (penomoran sementara lebih dahulu) agar constraint unik tidak bertabrakan.

`Admin/ManagementController` menangani persetujuan pembayaran, status pesanan, check-in penumpang, pengaturan situs, daftar pelanggan, dan faktur. `Admin/ReportController` menangani laporan, ekspor CSV, dan manifest jadwal. CSV memuat metode dan status pembayaran agar bisa dipakai untuk rekonsiliasi; filter tanggal divalidasi sebagai tanggal dan urutannya diperiksa manual (formulir ini memakai GET, sehingga galat validasi tidak boleh memantulkan redirect ke URL yang sama terus-menerus). Setiap tindakan penting dicatat ke `audit_logs`.

**Shell admin terpisah.** Semua halaman `/admin/*` memakai dokumen HTML sendiri (`resources/views/layouts/admin.blade.php`) dengan sidebar gelap, topbar ringkas, dan navigasi mobile berbasis `<details>`; header/footer situs publik tidak dimuat. Navigasi dikelompokkan (Operasional, Data master, Situs) lewat `resources/views/layouts/partials/admin-nav.blade.php`. Super Admin yang membuka halaman profil (`/profil`) tetap berada di shell admin (`admin/profile.blade.php`), dan halaman detail pesanan (`/pesanan/{booking}`) memilih shell admin saat dilihat Super Admin. Shell menampilkan `session('status')` dan `$errors` sendiri karena `layouts.app` tidak lagi ikut dirender.

Aturan status pesanan di panel admin (dijaga di server, bukan hanya di formulir):

| Status sekarang | Transisi yang tersedia untuk admin |
| --- | --- |
| `menunggu_pembayaran`, belum lunas | `dibatalkan` (kursi dilepas) |
| `menunggu_pembayaran`, lunas | `dikonfirmasi`, `selesai` |
| `dikonfirmasi`, lunas | `selesai` |
| `selesai`, `dibatalkan`, `kedaluwarsa` | tidak ada (hanya informasi) |

Pesanan lunas tidak dapat dibatalkan dari panel (harus lewat proses refund), check-in hanya untuk pesanan lunas dan hanya untuk penumpang milik pesanan itu, dan persetujuan pembayaran transfer mensyaratkan bukti transfer serta nominal yang cocok. Salah satu pun dari syarat itu dilanggar → pesan validasi yang tampil di halaman, bukan respons 422.

Daftar pemesanan dan pembayaran admin memanggil `BookingService::expireDue()` saat dibuka, sehingga status pesanan yang lewat batas bayar tetap akurat walau scheduler tidak berjalan. Daftar pelanggan dan faktur mendukung pencarian (nama/email/WhatsApp untuk pelanggan; nomor faktur, kode booking, atau pelanggan untuk faktur).

### 4.6 Ulasan

`ReviewController::store` hanya mengizinkan pemilik pesanan berstatus `selesai`, menyimpan ulasan dengan `approved = false`, dan admin menampilkannya dari `/admin/ulasan`. Hanya ulasan `approved` yang muncul di landing page.

### 4.7 Landing page

`resources/views/home.blade.php` adalah halaman publik utama dengan alur naratif: hero (foto + kartu keberangkatan berikutnya dari jadwal terdekat), formulir pencarian yang menempel di hero, rute favorit, armada, daftar jadwal terdekat, cara pemesanan, ulasan (bila ada), CTA Cek Booking, FAQ, dan kontak. Data yang dipakai tetap berasal dari `HomeController::index`; kartu keberangkatan berikutnya hanya tampil bila ada jadwal. `layouts/app.blade.php` adalah shell situs publik (header sticky, menu mobile `<details>`, footer) dan tidak dipakai shell admin.

## 5. Model data

- `users` 1—n `bookings` (opsional, karena tamu `user_id` null), `reviews`.
- `vehicles` 1—n `vehicle_seats`, 1—n `schedules`; `routes`, `drivers` → `schedules`.
- `schedules` 1—n `bookings`; `bookings` 1—n `booking_passengers`, `booking_seats`, `payments`, 1—1 `invoices`.
- `booking_seats` menyimpan kursi yang ditahan/terjual dengan unique `(schedule_id, vehicle_seat_id)` dan `(booking_id, vehicle_seat_id)`. Baris dihapus saat batal/kedaluwarsa; riwayat penumpang tetap ada di `booking_passengers`.
- `payments` 1—n `payment_events` (unique `event_key`); `invoices` 1—n `invoice_items`, `invoice_counters.day` unik sebagai alokasi nomor harian.
- `settings` (key-value), `bank_accounts`, `audit_logs`, `reviews`.

## 6. Aturan bisnis kunci

1. Masa tahan kursi **20 menit**; pelepasan kursi dijalankan `bookings:expire` setiap menit dan juga dipicu pada pencarian/detail jadwal.
2. Jadwal tidak boleh berangkat di masa lalu saat dibuat; durasi kedatangan = durasi rute; benturan kendaraan/sopir menghormati **60 menit** persiapan.
3. Satu kursi hanya untuk satu penumpang aktif per jadwal (dijamin index unik, bukan hanya pengecekan aplikasi).
4. Pesanan lunas tidak dapat dibatalkan manual — perubahan hanya lewat jalur refund.
5. Nomor faktur berformat `INV-YYYYMMDD-0001`, urut per hari, dan hanya boleh satu faktur per pesanan.
6. Faktur menyimpan `snapshot` harga, sehingga perubahan tarif berikutnya tidak mengubah dokumen lama.
7. Kursi `SOPIR` (`selectable = false`) tidak dihitung sebagai kapasitas penumpang.
8. Mengganti kata sandi wajib menyertakan kata sandi saat ini, minimal 8 karakter, dan otomatis mengakhiri sesi login lain milik akun tersebut.
9. Perubahan status pesanan dari panel admin mengikuti tabel transisi di §4.5; pelanggaran aturan selalu dijawab dengan pesan validasi (bukan `abort(422)`), sehingga pengguna tetap berada di halaman yang sama.
10. Semua pesan ke pengguna berbahasa Indonesia. Pesan validasi, autentikasi, dan paginasi berasal dari `lang/id`; `APP_LOCALE=id` dipasangkan dengan `APP_FALLBACK_LOCALE=en` supaya kunci yang belum diterjemahkan jatuh ke bahasa Inggris, bukan tampil sebagai kunci mentah.

## 7. Kontrol akses

| Rute | Penjaga |
| --- | --- |
| `/`, `/travel`, `/jadwal/{schedule}`, `/informasi/{type}`, `/cek-booking`, `/verifikasi/*` | publik |
| `POST /cek-booking` | publik, `throttle:6,1`; kode pesanan + nomor WhatsApp harus cocok |
| `POST /pesanan` | publik, `throttle:20,1`, respons `cache.headers:private;no_store` |
| `/pesanan/{booking}`, `/pesanan/{booking}/batal`, `/pesanan/{booking}/bayar`, `POST /pembayaran/{payment}/bukti` | `BookingPolicy` (`view`/`pay`/`cancel`): pemilik, `super_admin`, atau sesi tamu 24 jam |
| `/tiket/{booking}`, `/faktur/{invoice}` (+ `/pdf`) | policy yang sama, wajib lunas, tanpa cache |
| `POST /login`, `POST /daftar` | `guest`, `throttle:5,1` |
| `/akun`, `/profil`, `/pesanan/{booking}/ulasan`, `/akun/dokumen/{type}` | `auth` |
| `PUT /profil/kata-sandi` | `auth`, `throttle:5,1`, wajib `current_password` |
| `/admin/*` | `auth` + middleware `super_admin` |
| `POST /admin/pemesanan/{booking}/status`, `POST /admin/pemesanan/{booking}/checkin`, `POST /admin/pembayaran/{payment}/setujui` | `auth` + `super_admin`; aturan bisnis divalidasi di server (lihat §4.5) |
| `POST /payments/midtrans/webhook` | publik; validasi signature SHA-512 + nominal; CSRF dikecualikan |

## 8. Perintah dan penjadwalan

```sh
php artisan migrate              # jalankan migrasi (DB dev saat ini SQLite)
php artisan db:seed             # data contoh; butuh SUPER_ADMIN_* / DEMO_CUSTOMER_PASSWORD di .env
php artisan app:super-admin admin@nusa.local   # buat/atur ulang super admin (password acak bila tidak diisi)
php artisan bookings:expire     # dilepas otomatis setiap menit oleh scheduler
php artisan test                # tes fitur (DB :memory:)
vendor/bin/pint --test          # pemeriksaan gaya kode
npm run build                   # aset frontend ke public/build
```

Jalankan scheduler di lingkungan non-produksi dengan `php artisan schedule:work`. Bila scheduler tidak berjalan, kursi kedaluwarsa tetap dilepas saat pencarian/detail jadwal dan saat admin membuka daftar pemesanan atau pembayaran.

`npm run build` wajib dijalankan setiap kali menambah kelas Tailwind baru: Tailwind hanya menghasilkan kelas yang muncul di `resources/views` saat build, dan `public/build` tidak disimpan di git.

## 9. Titik ekstensi yang sering dibutuhkan

- **Tarif door-to-door berbasis zona**: perlu tabel tarif baru dan perubahan di `BookingService::reserve` serta `Admin/ManagementController::settings`.
- **Refund nyata & koreksi faktur**: tambahkan service baru, jangan campurkan ke `PaymentService::approve`.
- **Ekspor manifest PDF**: tiru pola `DocumentController::ticketPdf` (dompdf sudah tersedia).
- **Notifikasi email/WhatsApp**: saat ini belum ada; titik pemasangan paling masuk akal adalah setelah `approve()` dan `BookingService::reserve()` (sebaiknya lewat event/job, bukan langsung di service).
- **Klaim pesanan tamu**: verifikasi nomor WhatsApp sudah tersedia (`Booking::normalizePhone`); langkah berikutnya adalah membuat akun lalu memindahkan `bookings.user_id` setelah nomor cocok.

## 10. Konvensi

- Satu bahasa domain Indonesia untuk status (`menunggu_pembayaran`, `terjadwal`, `lunas`, `kedaluwarsa`, dst.) — jangan menerjemahkan nilai ini tanpa migrasi data.
- Logika lintas-tabel diletakkan di `app/Services`, bukan di controller.
- Validasi masukan lewat FormRequest bila dipakai di lebih dari satu tempat; validasi cepat boleh memakai `$request->validate()`.
- Semua perubahan skema wajib lewat migrasi baru, termasuk perubahan tipe kolom (`->change()`), dengan `down()` yang aman.
- Semua pesan yang dilihat pengguna berbahasa Indonesia; tambahkan kunci baru di `lang/id/*.php` atau `lang/id.json`, jangan menulis teks Inggris di controller.
- Sebelum menutup pekerjaan: `php artisan test` dan `vendor/bin/pint --test` harus hijau, serta `npm run build` dijalankan bila ada kelas Tailwind baru.
