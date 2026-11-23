# Telaah kode dan kesiapan operasional

Hasil penelusuran menyeluruh atas aplikasi Nusa Travel, termasuk perbaikan yang sudah diterapkan. Rujukan perilaku ada di [ARCHITECTURE.md](ARCHITECTURE.md), batas modul di [PLAN.md](PLAN.md).

Kondisi akhir telaah: `php artisan test` → **25 tes, 252 assertion, semua lulus**, dan `vendor/bin/pint --test` → **bersih untuk seluruh repositori**. Sebelum telaah: 13 tes, 132 assertion, 8 berkas gagal Pint.

## A. Yang sudah diperbaiki

| # | Temuan | Dampak sebelumnya | Berkas |
| --- | --- | --- | --- |
| 1 | Migrasi `2026_11_23_000003_allow_guest_bookings` masih **Pending** di database dev | Pesanan tamu pasti gagal: `bookings.user_id` masih `NOT NULL` dan kolom `guest_key_hash` belum ada. Tes tidak menangkapnya karena memakai SQLite `:memory:` yang selalu dimigrasi ulang | `database/migrations/2026_11_23_000003_allow_guest_bookings.php` (dijalankan) |
| 2 | Direktori `storage/framework/views` tidak di-ignore | 65 berkas Blade hasil kompilasi muncul sebagai perubahan dan bisa ikut ter-commit | `storage/framework/views/.gitignore` |
| 3 | Idempotensi webhook memakai pola cek-lalu-insert | Dua notifikasi kembar yang datang bersamaan sama-sama lolos pengecekan, lalu insert kedua melanggar unique `event_key` menjadi error 500 | `app/Services/PaymentService.php` |
| 4 | Notifikasi Midtrans untuk `order_id` yang tidak dikenal dijawab 404 | Midtrans mengulang notifikasi tanpa akhir dan memenuhi log | `app/Services/PaymentService.php` |
| 5 | Pembayaran kanal kedua dapat menimpa pesanan yang sudah lunas | Pembayaran transfer disetujui, lalu settlement Midtrans datang: pembayaran kedua ikut ditandai `lunas` dan status pesanan tertimpa (mis. `selesai` kembali `dikonfirmasi`) | `app/Services/PaymentService.php` |
| 6 | Total laporan hanya menjumlahkan baris pada halaman aktif | Angka total tidak mencerminkan pendapatan satu periode; admin harus menjumlahkan manual antar halaman | `app/Http/Controllers/Admin/ReportController.php`, `resources/views/admin/reports.blade.php` |
| 7 | Tidak ada cara aman membuat super admin selain lewat seeder berbasis `.env` | Akun admin hanya bisa dibuat bila dua variabel lingkungan terisi | `app/Console/Commands/CreateSuperAdmin.php` |
| 8 | `README.md` ditutup blok UTF-16LE (`# nusa_travel`) | Git menganggap seluruh README sebagai berkas biner, sehingga riwayat dan diff-nya tidak dapat dibaca | `README.md` |
| 9 | Langkah instalasi README hanya memakai perintah shell POSIX | Pengguna Windows tidak dapat mengikuti panduan apa adanya | `README.md` |
| 10 | Tidak ada cara mengganti kata sandi sendiri; akun admin hanya bisa diubah lewat terminal | Admin/pelanggan tidak dapat mengganti kata sandi dari aplikasi | `app/Http/Controllers/ProfileController.php`, `resources/views/customer/profile.blade.php`, `bootstrap/app.php` |
| 11 | Formulir status pada detail pesanan selalu menawarkan *dikonfirmasi/selesai* meski pesanan belum lunas | Admin menekan **Simpan** lalu mendapat halaman error 422 tanpa penjelasan; pesanan tidak berubah | `resources/views/customer/booking.blade.php`, `app/Http/Controllers/Admin/ManagementController.php` |
| 12 | Breadcrumb detail pesanan selalu "‹ Pesanan Saya" | Super admin yang membuka pesanan pelanggan diarahkan ke dasbor pelanggan miliknya sendiri | `resources/views/customer/booking.blade.php` |
| 13 | `abort(422)` dipakai untuk aturan bisnis (setujui/tolak pembayaran, check-in, ubah status) | Semua pelanggaran aturan berakhir di halaman "422 Unprocessable Content", bukan pesan yang bisa dibaca | `app/Http/Controllers/Admin/ManagementController.php`, `app/Http/Controllers/Admin/OperationalController.php` |
| 14 | `APP_LOCALE=id` tetapi direktori `lang/id` belum ada, `APP_FALLBACK_LOCALE` juga `id` | Seluruh pesan validasi, galat login, dan label paginasi tampil sebagai kunci mentah (`validation.required`, `auth.failed`, `pagination.next`) | `lang/id/validation.php`, `lang/id/auth.php`, `lang/id/passwords.php`, `lang/id/pagination.php`, `lang/id.json`, `.env`, `.env.example` |
| 15 | Check-in menerima `passenger_ids` milik pesanan lain dan mengabaikannya tanpa pesan | Admin mengira check-in berhasil padahal tidak ada penumpang yang diperbarui | `app/Http/Controllers/Admin/ManagementController.php` |
| 16 | Perubahan kapasitas kendaraan diblokir oleh jadwal lampau, dan sebaliknya bisa menghapus kursi yang sudah pernah dipakai | Koreksi data mustahil, atau kegagalan foreign key (500) saat kursi historis dihapus | `app/Http/Controllers/Admin/OperationalController.php` |
| 17 | `saveSeats` memakai `abort(422)` untuk kursi yang bukan milik kendaraan | Salah ketik/ID tak valid menghasilkan halaman error, bukan pesan formulir | `app/Http/Controllers/Admin/OperationalController.php` |
| 18 | Daftar pesanan admin tidak memicu pembersihan pesanan kedaluwarsa; grafik dashboard hanya menampilkan bulan yang ada transaksi dan bisa gagal bila `paid_at` kosong | Status "menunggu pembayaran" menumpuk untuk pesanan yang sudah lewat batas, grafik menyesatkan, dan satu baris data lama bisa menyebabkan error 500 | `app/Http/Controllers/Admin/ManagementController.php`, `app/Http/Controllers/Admin/DashboardController.php` |
| 19 | Daftar pelanggan/faktur tidak bisa dicari; navigasi admin tanpa penanda halaman aktif maupun jumlah pembayaran menunggu | Admin harus menggulir halaman demi halaman dan mudah salah halaman | `app/Http/Controllers/Admin/ManagementController.php`, `resources/views/admin/customers.blade.php`, `resources/views/admin/invoices.blade.php`, `resources/views/layouts/admin.blade.php` |
| 20 | Filter tanggal laporan memvalidasi `after_or_equal:from` pada formulir **GET**, dan CSV tidak memuat metode/status pembayaran | Browser yang mengirim `Referer` yang sama dapat terjebak redirect berulang; CSV tidak cukup untuk rekonsiliasi | `app/Http/Controllers/Admin/ReportController.php` |
| 21 | Akses pesanan tamu memakai kunci acak 64 heksadesimal yang ditampilkan sekali | Kunci yang hilang = pesanan tak dapat dibuka lagi; pengguna juga harus menyimpan string panjang yang mudah salah ketik | `app/Http/Controllers/BookingController.php`, `app/Models/Booking.php`, `app/Services/BookingService.php`, `resources/views/check.blade.php`, migrasi `2026_11_23_000004_drop_guest_key_hash_from_bookings` |
| 22 | Shell admin memakai `layouts.app` sehingga header/footer situs publik ikut dirender di `/admin/*` | Admin melihat navigasi publik (Beranda, Pesan Travel, footer) di dalam panel; dua konteks yang berbeda tercampur dalam satu dokumen | `resources/views/layouts/admin.blade.php`, `resources/views/layouts/partials/admin-nav.blade.php`, `resources/views/admin/profile.blade.php`, `resources/views/partials/profile-forms.blade.php` |
| 23 | Grafik pendapatan dashboard memaksa lebar halaman melebihi viewport pada ponsel | Halaman bergeser ke samping dan label bulan terpotong; diagram kini bisa digulir di dalam kartunya sendiri | `resources/views/admin/dashboard.blade.php` |
| 24 | Landing page memakai susunan seksi yang datar dan tidak memakai jadwal terdekat sebagai bukti sosial | Halaman terasa seperti template; pengunjung tidak melihat keberangkatan maupun kartu harga tanpa menggulir jauh | `resources/views/home.blade.php`, `resources/views/layouts/app.blade.php` |

Rincian perbaikan 3–5:

- Dedupe kini memakai `insertOrIgnore` pada `payment_events`; bila tidak ada baris tersisip, notifikasi dianggap duplikat. Unique index `event_key` menjadi penjaga tunggal, bukan pengecekan aplikasi.
- `approve()` mengembalikan faktur yang sudah ada bila pembayaran itu sendiri sudah `lunas` (idempoten), dan **menolak** pembayaran berbeda saat pesanan sudah lunas dengan pesan "Selesaikan pengembalian dana secara manual".
- Di jalur webhook, settlement yang masuk lewat kanal kedua tidak mengubah pesanan sama sekali; pembayaran ditandai `refund` dan dicatat ke log agar direkonsiliasi manual.

Rincian perbaikan 11–13 (aturan status pesanan):

- Formulir status hanya menawarkan transisi yang sah: pesanan belum lunas hanya bisa **dibatalkan**; pesanan lunas `menunggu_pembayaran` bisa **dikonfirmasi** lalu **selesai**; pesanan `dibatalkan`, `kedaluwarsa`, atau `selesai` tidak lagi menampilkan formulir dan menjelaskan alasannya.
- Server memvalidasi ulang transisi yang sama (bukan hanya formulir), sehingga permintaan manual pun ditolak dengan pesan yang terbaca, bukan 422.
- Pembatalan pesanan lunas tetap ditolak dengan arahan refund; check-in juga ikut memverifikasi bahwa penumpang benar milik pesanan tersebut.

Rincian perbaikan 21–23 (akses tamu dan shell admin):

- Kunci akses 64 karakter dihapus seluruhnya: `BookingController::store` tidak lagi membuat/menyimpan hash, kolom `guest_key_hash` dibuang lewat migrasi, dan `/cek-booking` meminta kode booking + nomor WhatsApp. Nomor dibandingkan memakai `Booking::normalizePhone` (hapus non-digit, `62…` → `0…`, `8…` → `08…`) dengan `hash_equals`, lalu sesi tamu diberi akses 24 jam. Nomor yang salah/kode yang salah menghasilkan pesan yang sama, dan nomor tidak di-flash balik ke formulir.
- Panel admin kini dokumen HTML mandiri: sidebar gelap dengan grup menu (Operasional, Data master, Situs), topbar ringkas, navigasi mobile berbasis `<details>`, serta tampilan `session('status')` dan `$errors` sendiri. Header/footer publik tidak dimuat. Halaman profil dirender di `admin/profile.blade.php` untuk Super Admin, dan detail pesanan memilih shell admin bila dibuka Super Admin.
- Grafik pendapatan dibungkus penggulung horizontal (`overflow-x-auto` + `min-w-max`) sehingga 12 label bulan tidak lagi melebarkan seluruh halaman pada layar 390 px.

Rincian perbaikan 24 (landing page):

- Hero baru: foto armada dengan lapisan gradasi, judul besar, kartu **keberangkatan berikutnya** (rute, jam, armada, tarif, tombol pilih kursi) yang hanya muncul bila ada jadwal, serta tiga angka ringkas (rute aktif, armada, jadwal siap).
- Formulir pencarian dibuat menempel di bawah hero (`-mt-20`) dengan grid responsif 6 kolom; label lebih pendek (Dari/Ke/Tanggal/Penumpang/Armada) dan nilai `old()` dipertahankan.
- Rute favorit menjadi kartu dengan durasi; jadwal terdekat menjadi satu daftar kartu berbaris (bukan grid dua kolom) dengan blok tanggal; seksi cara pemesanan berlatar gelap; CTA Cek Booking menjadi kartu gradien; FAQ memuat lima pertanyaan termasuk metode pembayaran dan antar jemput.
- Shell publik (`layouts/app.blade.php`) ditulis ulang sebagai dokumen yang mudah dibaca (header sticky ringkas, menu mobile `<details>`, footer tiga kolom dengan tautan informasi), memuat font Plus Jakarta Sans lewat Google Fonts, dan menampilkan tautan **Masuk** di ponsel.

Tes regresi yang ditambahkan (`tests/Feature/TravelFlowTest.php`): total laporan lintas halaman, webhook order tak dikenal, settlement kanal kedua, penolakan persetujuan pembayaran kedua, pembuatan dan pengaturan ulang akun super admin, transisi status admin (termasuk penolakan tanpa 422), pembatalan pesanan belum lunas oleh admin, validasi check-in lintas pesanan, persetujuan tanpa bukti transfer, pembersihan pesanan kedaluwarsa dari daftar admin, pencarian pelanggan, filter tanggal laporan yang tidak berulang, isi CSV (metode & status), pesan validasi berbahasa Indonesia, pemulihan pesanan tamu dengan nomor WhatsApp (nomor salah, kode salah, dan format `+62`), serta pemisahan shell admin (tidak memuat navigasi/footer publik).

## B. Temuan yang masih terbuka

### Tinggi

1. **Belum ada pengujian ke Midtrans sandbox asli.** Seluruh jalur Midtrans (pembuatan transaksi, `expiry`, signature, status `capture`/`fraud_status`) hanya diuji dengan payload buatan. `MIDTRANS_SERVER_KEY` di `.env` juga masih kosong.
2. **Refund tidak nyata.** Status `refund` hanya mengubah data: pembayaran, pesanan, faktur, dan kursi dilepas. Tidak ada transfer dana balik, dokumen koreksi, maupun jejak persetujuan refund.
3. **Tidak ada rekonsiliasi gateway maupun mutasi bank.** Pesanan yang menggantung (mis. pengguna menutup Snap, atau transfer masuk tanpa bukti) tidak pernah dicocokkan otomatis.
4. **Tidak ada notifikasi sama sekali.** Tidak ada email/WhatsApp untuk kode booking, pengingat 20 menit, tiket terbit, atau pembatalan. Semua komunikasi masih manual.

### Sedang

5. **Pesanan tamu belum dapat diklaim ke akun.** Verifikasi nomor WhatsApp kini tersedia (`Booking::normalizePhone`, sesi akses 24 jam), tetapi belum ada alur membuat akun lalu memindahkan `bookings.user_id` setelah nomor cocok.
6. **Tidak ada constraint basis data yang memaksa kapasitas kendaraan sama dengan jumlah kursi `selectable`.** Aplikasi sudah menjaganya (`saveSeats`, pembuatan kursi ulang saat kapasitas berubah) dan kini pemblokirannya hanya berlaku bila kursi sudah pernah dipesan atau masih ada jadwal mendatang, tetapi basis data tetap bisa tidak konsisten bila diubah langsung.
7. **Layout kursi ditulis dalam dua fase** (semua kursi dipindah ke `row=99` sementara, lalu posisi final). Fase sementara masih memakai indeks array sebagai `column`, jadi nilainya bisa melebihi `max:5` walau tidak pernah tersimpan permanen karena seluruh proses berada dalam satu transaksi. Bila jumlah kursi membesar, tambahkan kolom pembantu `sort_order` agar tidak perlu nilai sementara.
8. **Manifest hanya halaman cetak browser**, tanpa ekspor PDF resmi dan tanpa tes.
9. **Pesanan lunas tidak bisa dibatalkan admin**, tetapi dashboard tidak menyediakan alur refund. Akibatnya pesanan lunas yang batal di lapangan menggantung tanpa jalur penyelesaian.
10. **Tarif door-to-door masih nominal tetap** (`pickup_fee`/`dropoff_fee`), belum berbasis zona atau jarak.

### Rendah

11. **N+1 pada pencarian jadwal**: `HomeController::search` menghitung `available` per baris (dua query tambahan per jadwal), sementara filter jumlah penumpang sudah memakai subquery. Nilai `available` bisa dipindah ke subquery yang sama.
12. **`expireDue()` dijalankan pada jalur baca** (pencarian jadwal, detail jadwal, daftar pemesanan dan pembayaran admin). Ini membuat trafik baca melakukan penulisan; dipertahankan agar status dan kursi cepat akurat, tetapi bisa dipindah sepenuhnya ke scheduler (`bookings:expire`) bila beban baca tinggi.
13. **Kode booking `Str::random(7)`** tanpa penanganan khusus. Peluang tabrakan sangat kecil, namun bila terjadi pengguna menerima error 500, bukan pesan validasi.
14. **`/cek-booking` hanya dibatasi `throttle:6,1`** per IP. Pesan galat sudah seragam (baik), tetapi belum ada jeda bertahap atau captcha.
15. **Kredensial super admin tersimpan plaintext di `.env`**. Wajar untuk pengembangan lokal; di produksi gunakan secret manager dan rotasi berkala.
16. **Aset CSS harus dibangun ulang setelah menambah kelas Tailwind baru** (`npm run build`). Tanpa langkah itu kelas baru (mis. `max-w-64`) tidak ada di `public/build`, sehingga tata letak tampak "polos" — bukan error, tetapi membingungkan.

## C. Telaah mendalam: kuota kursi dan konsistensi pembayaran

### Kuota kursi

Proteksi berlapis, dari yang paling lunak ke paling keras:

1. **Pemeriksaan aplikasi** di `BookingService::reserve`: kursi harus milik kendaraan jadwal, `selectable`, unik antar penumpang, dan belum ada di `booking_seats`.
2. **Kunci baris jadwal** (`Schedule::lockForUpdate()`), sehingga pada MySQL/PostgreSQL semua reservasi untuk jadwal yang sama berjalan berurutan.
3. **Index unik** `booking_seats(schedule_id, vehicle_seat_id)`; `QueryException` kode `23000`/`19` diterjemahkan menjadi pesan validasi, bukan error 500.
4. **Retry transaksi 3 kali** (`DB::transaction($callback, 3)`) untuk deadlock/lock timeout.

Catatan penting saat pindah basis data: SQLite **mengabaikan** `lockForUpdate()`. Keamanan di SQLite bertumpu pada serialisasi penulisan dan unique index, bukan pada kunci baris. Artinya perilaku di MySQL/PostgreSQL akan berbeda (lebih ketat pada kunci, tetapi deadlock lebih mungkin), dan belum ada uji beban paralel lintas proses. Ini pekerjaan yang harus dilakukan sebelum produksi.

`expireDue()` bersifat idempoten: menghapus `booking_seats` dan mengubah status pesanan/pembayaran yang masih menunggu. Menjalankannya dua kali tidak merusak data, dan baris jadwal yang dikunci membuat dua proses tidak saling menimpa.

Satu celah teoretis yang tersisa: kapasitas pesanan divalidasi terhadap `vehicles.capacity`, sedangkan jumlah kursi `selectable` divalidasi terhadap `vehicle_seats`. Saat ini keduanya dijaga konsisten (`saveSeats` mewajibkan jumlah kursi penumpang = kapasitas, perubahan kapasitas membuat ulang kursi), tetapi tidak ada constraint basis data yang memaksanya.

### Uang

Ada tiga sumber kebenaran yang harus selalu sinkron: `payments.status`, `bookings.payment_status`, dan `invoices.status`. Setelah perbaikan, jalur yang diizinkan adalah:

- **Satu pesanan, satu pembayaran efektif.** Pembayaran lain ditolak saat disetujui manual, atau ditandai `refund` bila datang dari gateway.
- **Faktur tetap tunggal** karena `invoices.booking_id` unik dan `approve()` mengembalikan faktur yang sudah ada.
- **Nomor faktur** dialokasikan dalam transaksi yang sama lewat `invoice_counters` dengan `lockForUpdate()` dan `insertOrIgnore` untuk hari baru.

Yang belum tertutup: pencocokan nominal transfer dengan mutasi bank (masih keputusan manusia), pembatalan sepihak dari gateway setelah refund, dan status `expire` yang bergantung pada scheduler yang berjalan.

## D. Prioritas pekerjaan berikutnya

1. Uji end-to-end Midtrans sandbox, termasuk notifikasi HTTPS dan status `deny`/`expire`.
2. Bangun alur refund di dashboard: permintaan, persetujuan, bukti transfer balik, dan koreksi/kredit faktur.
3. Rekonsiliasi otomatis: tarik status transaksi gateway yang masih `pending` dan bandingkan dengan mutasi rekening.
4. Alur klaim pesanan tamu ke akun pelanggan (verifikasi nomor WhatsApp sudah tersedia).
5. Notifikasi email/WhatsApp untuk kode booking, pengingat kedaluwarsa, tiket, dan pembatalan.
6. Ekspor manifest PDF memakai dompdf yang sudah terpasang.
7. Uji beban reservasi paralel dan validasi seluruh jalur pada MySQL sebelum produksi.

## E. Kondisi lingkungan pengembangan saat ini

- Database: SQLite `database/database.sqlite`, seluruh migrasi **sudah** berjalan.
- Akun: 1 super admin (`admin@nusa.local`). Belum ada pelanggan terdaftar; dua pesanan yang ada dibuat sebagai tamu (dibuka dengan kode booking + nomor WhatsApp pemesan).
- Pesanan terakhir: `TRV-261123-QJROO2B` (`dikonfirmasi`, lunas) dan `TRV-261123-PHUX4HX` (`selesai`, lunas).
- Data operasional awal: 2 kendaraan, 4 rute, 2 jadwal (dari seeder).
- Midtrans belum dikonfigurasi; hanya transfer manual yang dapat dipakai.
- Akun super admin tambahan dapat dibuat kapan saja dengan `php artisan app:super-admin email@domain.test`.
- Kata sandi admin dapat diganti dari menu **Profil & Kata Sandi** di panel admin (wajib memasukkan kata sandi saat ini) atau dari terminal dengan `php artisan app:super-admin email --password="..."`.
- Antrean pengingat kedaluwarsa `bookings:expire` terjadwal tiap menit. Bila `php artisan schedule:work` tidak berjalan, daftar pemesanan/pembayaran admin tetap membersihkan pesanan kedaluwarsa saat dibuka.
- Pesan aplikasi berbahasa Indonesia melalui `lang/id` (`validation`, `auth`, `passwords`, `pagination`, `id.json`). `APP_LOCALE=id` dengan `APP_FALLBACK_LOCALE=en` agar kunci yang belum diterjemahkan jatuh ke bahasa Inggris, bukan tampil sebagai kunci mentah.
