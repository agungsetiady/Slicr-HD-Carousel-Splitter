# ✂️ Slicr — HD Carousel Splitter

**Slicr** adalah aplikasi pemotong gambar berbasis web yang dirancang untuk memecah gambar resolusi tinggi menjadi beberapa bagian (slide) tanpa mengurangi kualitas (tanpa resampling/resize)[cite: 3, 4]. Sangat ideal untuk pembuatan konten carousel Instagram, pemotongan grid visual, maupun kebutuhan layout desain banner[cite: 2, 3].

Aplikasi ini mengusung konsep **Sekali Pakai (Stateless Processing)**: semua proses pemotongan dilakukan langsung di memori server dan dikembalikan ke browser tanpa ada satu pun file yang disimpan di disk atau database[cite: 2, 3, 4].

---

## 🚀 Fitur Utama

- **Zero Quality Loss**: Menggunakan fungsi pencuplikan pixel murni (`imagecopy`) pada library GD PHP tanpa interpolasi ulang, menjaga ketajaman resolusi asli[cite: 4].
- **Live Interactive Overlay**: Preview visual garis potong secara *real-time* di atas canvas gambar sebelum diproses[cite: 2, 3].
- **3 Mode Pemotongan**:
  - **Vertikal**: Memotong gambar menjadi baris bertumpuk[cite: 3, 4].
  - **Horizontal**: Memotong gambar menjadi kolom berdampingan (slide carousel)[cite: 3, 4].
  - **Grid**: Memotong gambar menjadi pola baris × kolom ($R \times C$)[cite: 3, 4].
- **Flexibilitas Pemotongan**: Potong berdasarkan jumlah bagian (*quantity*) atau menentukan ukuran spesifik per blok (*pixels*)[cite: 2, 3, 4].
- **Overlap Mode**: Fitur menyisipkan area irisan antarslide (*overlap*) untuk transisi sambungan antar slide carousel[cite: 2, 3, 4].
- **Client-Side ZIP Generator**: Seluruh hasil potongan dapat diunduh langsung sebagai satu file `.zip` yang dirakit penuh di sisi browser via `JSZip`.
- **Stateless & Safe**: File sementara yang diunggah langsung dihapus dari disk segera setelah gambar dimuat ke GD memory[cite: 2, 4].

---

## 🛠️ Arsitektur & Teknologi

- **Frontend**: HTML5, JavaScript (ES6+), jQuery 3.7.1, Tailwind CSS (via CDN), Boxicons, Toastify.js, JSZip[cite: 3].
- **Backend**: PHP 7.4+ / PHP 8.x dengan ekstensi **GD Image Library** (`imagecreatefromjpeg`, `imagecreatefrompng`, `imagecreatefromwebp`)[cite: 1, 4].
- **Styling**: Custom CSS Glassmorphism UI dengan Dark Mode theme[cite: 3, 5].

---

## 📋 Persyaratan Sistem (Prerequisites)

- Web Server: **Apache / Nginx**
- PHP Version: **PHP 7.4** atau lebih baru
- Ekstensi PHP Wajib:
  - `php-gd` (Mendukung format JPEG, PNG, dan WEBP)[cite: 4]
  - `fileinfo`

---

## ⚙️ Konfigurasi Server (Penting)

Untuk memproses gambar resolusi tinggi (HD/4K) tanpa *Memory Limit Error* atau *Payload Too Large*, pastikan konfigurasi PHP pada `.htaccess` atau `php.ini` disesuaikan[cite: 1]:

### Konfigurasi `.htaccess`
```apache
<IfModule mod_php7.c>
  php_value upload_max_filesize 50M
  php_value post_max_size 50M
  php_value memory_limit 512M
  php_value max_execution_time 300
  php_value max_input_time 300
</IfModule>

<IfModule mod_php.c>
  php_value upload_max_filesize 50M
  php_value post_max_size 50M
  php_value memory_limit 512M
  php_value max_execution_time 300
  php_value max_input_time 300
</IfModule>
