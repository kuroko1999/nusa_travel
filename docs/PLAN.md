# Rencana modul dan desain database

## Modul

1. Fondasi: Laravel 13, SQLite, autentikasi dua role, migrasi dan seeder.
2. Operasional: CRUD armada, layout kursi, sopir, rute, jadwal, dan rekening; jadwal memeriksa benturan kendaraan/sopir dengan waktu persiapan 60 menit.
3. Pelanggan: pencarian jadwal, pemilihan kursi, identitas penumpang, alamat door-to-door, dan riwayat.
4. Pembayaran: transfer manual privat dan Midtrans Snap dengan signature webhook.
5. Dokumen: e-ticket dan faktur A4 dengan QR, snapshot harga, nomor faktur berurutan.
6. Pengelolaan: dashboard, laporan CSV, manifest, check-in, dan pengaturan.
7. QA: tes fitur dan build frontend.

## Relasi inti

- `users` → `bookings` (pelanggan); role hanya `super_admin` atau `pelanggan` dalam aplikasi.
- `vehicles` → `vehicle_seats`; satu kendaraan dapat dipakai banyak `schedules`.
- `routes`, `drivers`, dan `vehicles` → `schedules`.
- `schedules` → `bookings`; `bookings` → `booking_passengers`, `booking_seats`, `payments`, satu `invoice`.
- `booking_seats` menyimpan kursi yang sedang ditahan atau telah terjual dan memiliki unique `(schedule_id, vehicle_seat_id)`. Saat booking batal/kedaluwarsa, baris ini dihapus. Riwayat penumpang tetap ada di `booking_passengers`.
- `payments` → `payment_events` menyimpan riwayat webhook. `event_key` unik untuk mencegah efek ganda.
- `invoices` → `invoice_items`; `invoices.booking_id` unik. `invoice_counters.day` mengalokasikan urutan per hari dalam transaksi database.
- `reviews` menyimpan ulasan perjalanan yang disetujui untuk ditampilkan di landing page. `settings`, `bank_accounts`, dan `audit_logs` menyimpan konfigurasi dan jejak tindakan.

## Batas yang masih perlu dikembangkan sebelum operasi penuh

- Tarif door-to-door saat ini satu nilai tetap per layanan, belum berdasarkan zona/alamat.
- Pengembalian dana manual, koreksi faktur formal, dan rekonsiliasi gateway dari dashboard belum ada.
- Konten FAQ belum tersedia di dashboard.
- Belum ada tes end-to-end terhadap akun Midtrans sandbox asli atau pengujian beban paralel lintas proses.
- Belum ada ekspor manifest PDF; halaman manifest tersedia untuk cetak browser.
