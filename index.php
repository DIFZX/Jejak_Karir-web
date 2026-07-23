<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/includes/functions.php';

$loggedIn = is_logged_in();
$primaryUrl = $loggedIn ? 'dashboard.php' : 'register.php';
$primaryLabel = $loggedIn ? 'Buka Dashboard' : 'Mulai Sekarang';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="JejakKarier membantu pencari kerja mencatat, memantau, dan mengevaluasi seluruh proses lamaran dalam satu tempat.">
    <title>JejakKarier — Kelola Perjalanan Kariermu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/landing.css">
</head>
<body>
<header class="landing-header" data-header>
    <nav class="landing-nav">
        <a class="landing-brand" href="#beranda" aria-label="JejakKarier">
            <span class="landing-brand-mark">
                <svg viewBox="0 0 24 24"><path d="M5 7.5A2.5 2.5 0 0 1 7.5 5h9A2.5 2.5 0 0 1 19 7.5v10a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 5 17.5v-10Z"/><path d="M9 5V3.8A1.8 1.8 0 0 1 10.8 2h2.4A1.8 1.8 0 0 1 15 3.8V5M5 10h14M10 13h4"/></svg>
            </span>
            <span>Jejak<span>Karier</span></span>
        </a>

        <button class="mobile-menu-button" type="button" data-mobile-menu aria-label="Buka navigasi" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

        <div class="landing-menu" data-menu>
            <a href="#fitur">Fitur</a>
            <a href="#panduan">Panduan</a>
            <a href="#tentang">Tentang</a>
            <a href="#faq">FAQ</a>
            <a href="#kontak">Kontak</a>
        </div>

        <div class="landing-nav-actions">
            <?php if (!$loggedIn): ?><a class="nav-login" href="login.php">Masuk</a><?php endif; ?>
            <a class="landing-button small" href="<?= e($primaryUrl) ?>"><?= e($primaryLabel) ?><svg viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></svg></a>
        </div>
    </nav>
</header>

<main>
    <section class="landing-hero" id="beranda">
        <div class="hero-grid-pattern"></div>
        <div class="hero-glow glow-one"></div>
        <div class="hero-glow glow-two"></div>
        <div class="landing-shell hero-layout">
            <div class="hero-copy">
                <div class="hero-badge reveal">
                    <span><i></i></span>
                    JOB APPLICATION TRACKER
                </div>
                <h1 class="reveal delay-one">Lebih rapi melamar kerja.<br><em>Lebih dekat ke karier impian.</em></h1>
                <p class="reveal delay-two">Catat setiap lamaran, pantau tahapan rekrutmen, atur jadwal interview, dan pahami progres kariermu melalui satu dashboard yang sederhana.</p>
                <div class="hero-actions reveal delay-three">
                    <a class="landing-button large" href="<?= e($primaryUrl) ?>">
                        <?= e($primaryLabel) ?>
                        <svg viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></svg>
                    </a>
                    <a class="landing-button secondary large" href="#panduan">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4Z"/></svg>
                        Lihat Cara Kerja
                    </a>
                </div>
                <div class="hero-trust reveal delay-three">
                    <div class="trust-avatars"><span>D</span><span>A</span><span>R</span><span>+</span></div>
                    <div><strong>Siap menemani pencari kerja</strong><small>Data aman, terorganisir, dan mudah diekspor</small></div>
                </div>
            </div>

            <div class="hero-product reveal delay-two" aria-label="Pratinjau dashboard JejakKarier">
                <div class="product-orbit orbit-one"></div>
                <div class="product-orbit orbit-two"></div>
                <div class="floating-card floating-interview">
                    <span class="float-icon purple"><svg viewBox="0 0 24 24"><path d="M8 3v3M16 3v3M4 9h16M5 5h14v15H4V6a1 1 0 0 1 1-1Z"/><path d="m9 15 2 2 4-4"/></svg></span>
                    <div><small>Jadwal berikutnya</small><strong>Interview · 10.00</strong></div>
                </div>
                <div class="floating-card floating-success">
                    <span class="float-icon green"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg></span>
                    <div><small>Status diperbarui</small><strong>Lolos HR Screening</strong></div>
                </div>
                <div class="product-window">
                    <div class="product-topbar">
                        <div class="mini-brand"><span></span><b>JejakKarier</b></div>
                        <div class="mini-user"><i></i><i></i><span>D</span></div>
                    </div>
                    <div class="product-body">
                        <div class="product-greeting"><div><small>SELAMAT DATANG KEMBALI</small><strong>Pantau progres kariermu.</strong></div><button>+ Catat Lamaran</button></div>
                        <div class="preview-stats">
                            <div><span class="preview-icon coral"><svg viewBox="0 0 24 24"><path d="M5 7h14v13H5zM9 7V4h6v3"/></svg></span><p>Total Lamaran<strong>24</strong></p></div>
                            <div><span class="preview-icon blue"><svg viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 9 9M12 7v5l3 2"/></svg></span><p>Diproses<strong>8</strong></p></div>
                            <div><span class="preview-icon green"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg></span><p>Interview<strong>3</strong></p></div>
                        </div>
                        <div class="preview-content">
                            <div class="preview-chart">
                                <div><strong>Aktivitas Lamaran</strong><small>7 hari terakhir</small></div>
                                <div class="preview-bars"><i style="height:30%"></i><i style="height:48%"></i><i style="height:38%"></i><i style="height:66%"></i><i style="height:52%"></i><i style="height:85%"></i><i style="height:72%"></i></div>
                            </div>
                            <div class="preview-history">
                                <div><strong>Lamaran Terbaru</strong><small>Hari ini</small></div>
                                <article><span>T</span><p><b>Teknologi Nusantara</b><small>UI/UX Designer</small></p><i>Interview</i></article>
                                <article><span>S</span><p><b>Solusi Digital</b><small>Product Designer</small></p><i>Diproses</i></article>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <a class="scroll-hint" href="#fitur"><span>Jelajahi selengkapnya</span><i><svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></i></a>
    </section>

    <section class="feature-section section-space" id="fitur">
        <div class="landing-shell">
            <div class="section-intro reveal">
                <span class="section-label">FITUR LENGKAP</span>
                <h2>Semua yang kamu butuhkan<br>untuk mengelola proses lamaran.</h2>
                <p>Tidak ada lagi catatan tercecer atau jadwal yang terlewat. Semua tersusun dalam satu alur yang mudah dipahami.</p>
            </div>
            <div class="feature-grid">
                <article class="feature-card reveal">
                    <div class="feature-icon coral"><svg viewBox="0 0 24 24"><path d="M5 7h14v13H5zM9 7V4h6v3M5 11h14M9 15h6"/></svg></div>
                    <span class="feature-number">01</span>
                    <h3>Riwayat Lamaran Terorganisir</h3>
                    <p>Simpan perusahaan, posisi, kanal, catatan, dan waktu melamar secara otomatis seperti riwayat transaksi.</p>
                    <div class="feature-mini-list"><span><i></i> Hari ini</span><span><i></i> Kemarin</span><span><i></i> Berdasarkan tanggal</span></div>
                </article>
                <article class="feature-card reveal delay-one">
                    <div class="feature-icon blue"><svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg></div>
                    <span class="feature-number">02</span>
                    <h3>Dashboard Analitik</h3>
                    <p>Lihat aktivitas harian dan bulanan, kanal paling efektif, serta ringkasan progres secara visual.</p>
                </article>
                <article class="feature-card reveal delay-two">
                    <div class="feature-icon purple"><svg viewBox="0 0 24 24"><path d="M8 3v3M16 3v3M4 9h16M5 5h14v15H4V6a1 1 0 0 1 1-1Z"/><path d="M12 13v3M12 19h.01"/></svg></div>
                    <span class="feature-number">03</span>
                    <h3>Jadwal & Pengingat</h3>
                    <p>Atur follow-up, interview, dan deadline agar setiap kesempatan penting tetap terpantau.</p>
                </article>
                <article class="feature-card reveal">
                    <div class="feature-icon green"><svg viewBox="0 0 24 24"><path d="M4 12h4l2-4 4 8 2-4h4"/><path d="M5 5h14v14H5z"/></svg></div>
                    <span class="feature-number">04</span>
                    <h3>Tahapan Rekrutmen</h3>
                    <p>Pantau perjalanan dari lamaran terkirim, screening, tes, interview, offering, hingga diterima.</p>
                </article>
                <article class="feature-card reveal delay-one">
                    <div class="feature-icon amber"><svg viewBox="0 0 24 24"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14"/></svg></div>
                    <span class="feature-number">05</span>
                    <h3>Ekspor Laporan</h3>
                    <p>Unduh seluruh data atau tanggal tertentu dalam format PDF dan CSV kapan saja dibutuhkan.</p>
                </article>
                <article class="feature-card reveal delay-two">
                    <div class="feature-icon navy"><svg viewBox="0 0 24 24"><path d="M6 10V7a6 6 0 0 1 12 0v3M4 10h16v11H4zM12 14v3"/></svg></div>
                    <span class="feature-number">06</span>
                    <h3>Data Pribadi Terpisah</h3>
                    <p>Setiap akun hanya dapat melihat dan mengelola data miliknya sendiri melalui sesi yang aman.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="guide-section section-space" id="panduan">
        <div class="guide-decoration"></div>
        <div class="landing-shell">
            <div class="section-intro centered reveal">
                <span class="section-label light">CARA KERJA</span>
                <h2>Tiga langkah sederhana untuk<br>perjalanan karier yang lebih terarah.</h2>
                <p>Mulai dalam hitungan menit. Tidak perlu instalasi atau pengaturan yang rumit.</p>
            </div>
            <div class="guide-steps">
                <article class="guide-step reveal">
                    <div class="step-visual"><span>01</span><div><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/></svg></div></div>
                    <h3>Buat akunmu</h3>
                    <p>Daftar menggunakan username dan password. Data setiap akun tersimpan secara terpisah.</p>
                </article>
                <span class="step-line reveal delay-one"><i></i></span>
                <article class="guide-step reveal delay-one">
                    <div class="step-visual"><span>02</span><div><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></div></div>
                    <h3>Catat lamaran</h3>
                    <p>Masukkan perusahaan, posisi, kanal, prioritas, dan jadwal bila diperlukan.</p>
                </article>
                <span class="step-line reveal delay-two"><i></i></span>
                <article class="guide-step reveal delay-two">
                    <div class="step-visual"><span>03</span><div><svg viewBox="0 0 24 24"><path d="M4 19V9M10 19V5M16 19v-7M2 19h20"/></svg></div></div>
                    <h3>Pantau progres</h3>
                    <p>Perbarui tahapan, lihat analitik, dan ekspor laporan untuk evaluasi kariermu.</p>
                </article>
            </div>
            <div class="guide-cta reveal">
                <p>Siap menata perjalanan kariermu?</p>
                <a class="landing-button light-button" href="<?= e($primaryUrl) ?>"><?= e($primaryLabel) ?><svg viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></svg></a>
            </div>
        </div>
    </section>

    <section class="about-section section-space" id="tentang">
        <div class="landing-shell about-layout">
            <div class="about-visual reveal">
                <div class="about-card main-card">
                    <span class="about-icon"><svg viewBox="0 0 24 24"><path d="M5 7h14v13H5zM9 7V4h6v3M5 11h14"/></svg></span>
                    <div><small>PERJALANAN KARIER</small><strong>Satu langkah hari ini,<br>peluang baru esok hari.</strong></div>
                    <div class="journey-line"><i class="done"></i><i class="done"></i><i class="active"></i><i></i><i></i></div>
                </div>
                <div class="about-card stat-card-one"><strong>100%</strong><span>Data milikmu sendiri</span></div>
                <div class="about-card stat-card-two"><span>✦</span><strong>Lebih fokus</strong><small>Tanpa catatan tercecer</small></div>
                <div class="about-shape"></div>
            </div>
            <div class="about-copy reveal delay-one">
                <span class="section-label">TENTANG JEJAKKARIER</span>
                <h2>Dibuat untuk membantu setiap pencari kerja tetap terarah.</h2>
                <p>Proses mencari kerja sering melibatkan banyak perusahaan, jadwal, dan tahapan berbeda. JejakKarier hadir untuk menyederhanakan semuanya menjadi informasi yang rapi dan mudah ditindaklanjuti.</p>
                <p>Kami percaya bahwa pencatatan yang baik membantu kamu mengambil keputusan lebih tepat, melakukan follow-up pada waktunya, dan memahami strategi lamaran yang paling efektif.</p>
                <div class="about-values">
                    <div><span><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg></span><p><strong>Sederhana</strong><small>Mudah digunakan tanpa proses rumit.</small></p></div>
                    <div><span><svg viewBox="0 0 24 24"><path d="M6 10V7a6 6 0 0 1 12 0v3M4 10h16v11H4z"/></svg></span><p><strong>Pribadi</strong><small>Data setiap akun tersimpan terpisah.</small></p></div>
                    <div><span><svg viewBox="0 0 24 24"><path d="M12 3v18M3 12h18"/></svg></span><p><strong>Berkembang</strong><small>Dirancang untuk terus mengikuti kebutuhanmu.</small></p></div>
                </div>
            </div>
        </div>
    </section>

    <section class="faq-section section-space" id="faq">
        <div class="landing-shell faq-layout">
            <div class="faq-copy reveal">
                <span class="section-label">PERTANYAAN UMUM</span>
                <h2>Ada yang ingin kamu ketahui?</h2>
                <p>Temukan jawaban singkat mengenai penggunaan dan keamanan JejakKarier.</p>
                <div class="faq-contact-mini">
                    <span><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4zM4 7l8 6 8-6"/></svg></span>
                    <div><small>Masih punya pertanyaan?</small><a href="mailto:alfaruqdifa1211@gmai.com">Hubungi kami</a></div>
                </div>
            </div>
            <div class="faq-list reveal delay-one">
                <details open>
                    <summary>Apakah JejakKarier dapat digunakan gratis?<span></span></summary>
                    <p>Ya. Kamu dapat membuat akun dan menggunakan fitur pencatatan, pencarian, analitik, jadwal, serta ekspor yang tersedia.</p>
                </details>
                <details>
                    <summary>Apakah data setiap pengguna terpisah?<span></span></summary>
                    <p>Ya. Setiap lamaran terhubung dengan akun pemiliknya. Pengguna lain tidak dapat melihat, mengubah, atau menghapus data tersebut.</p>
                </details>
                <details>
                    <summary>Bagaimana cara mencatat jadwal interview?<span></span></summary>
                    <p>Saat menambah atau mengedit lamaran, aktifkan pilihan “Jadwal & Pengingat”, kemudian isi waktu interview atau deadline yang diperlukan.</p>
                </details>
                <details>
                    <summary>Bisakah data lamaran diunduh?<span></span></summary>
                    <p>Bisa. Buka menu hamburger, pilih “Ekspor Data”, lalu tentukan semua data atau tanggal tertentu dalam format PDF maupun CSV.</p>
                </details>
                <details>
                    <summary>Apakah JejakKarier bisa digunakan melalui ponsel?<span></span></summary>
                    <p>Bisa. Seluruh halaman dirancang responsif agar tetap nyaman digunakan melalui desktop, tablet, dan ponsel.</p>
                </details>
                <details>
                    <summary>Apa yang harus dilakukan jika lupa password?<span></span></summary>
                    <p>Silakan hubungi pengelola melalui alamat email pada bagian kontak untuk mendapatkan bantuan pemulihan akun.</p>
                </details>
            </div>
        </div>
    </section>

    <section class="contact-section" id="kontak">
        <div class="landing-shell">
            <div class="contact-card reveal">
                <div class="contact-glow"></div>
                <div class="contact-copy">
                    <span class="section-label light">HUBUNGI KAMI</span>
                    <h2>Punya pertanyaan atau masukan?</h2>
                    <p>Kami terbuka untuk pertanyaan, laporan kendala, dan ide yang dapat membuat JejakKarier menjadi lebih baik.</p>
                </div>
                <div class="contact-action">
                    <span class="contact-mail-icon"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4zM4 7l8 6 8-6"/></svg></span>
                    <div><small>EMAIL</small><a href="mailto:alfaruqdifa1211@gmai.com">alfaruqdifa1211@gmai.com</a></div>
                    <a class="mail-button" href="mailto:alfaruqdifa1211@gmai.com" aria-label="Kirim email"><svg viewBox="0 0 24 24"><path d="m5 12 14-7-5 14-2-6-7-1Z"/></svg></a>
                </div>
            </div>
        </div>
    </section>

    <section class="final-cta">
        <div class="final-pattern"></div>
        <div class="landing-shell reveal">
            <span class="section-label">MULAI HARI INI</span>
            <h2>Setiap lamaran adalah langkah.<br><em>Pastikan tidak ada yang terlewat.</em></h2>
            <p>Bangun kebiasaan pencatatan yang rapi dan fokus pada peluang terbaikmu.</p>
            <a class="landing-button large" href="<?= e($primaryUrl) ?>"><?= e($primaryLabel) ?><svg viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></svg></a>
        </div>
    </section>
</main>

<footer class="landing-footer">
    <div class="landing-shell footer-main">
        <div>
            <a class="landing-brand footer-brand" href="#beranda">
                <span class="landing-brand-mark"><svg viewBox="0 0 24 24"><path d="M5 7.5A2.5 2.5 0 0 1 7.5 5h9A2.5 2.5 0 0 1 19 7.5v10a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 5 17.5v-10Z"/><path d="M9 5V4h6v1M5 10h14"/></svg></span>
                <span>Jejak<span>Karier</span></span>
            </a>
            <p>Catat langkahmu. Pantau progresmu.<br>Raih karier impianmu.</p>
        </div>
        <div class="footer-links"><strong>Produk</strong><a href="#fitur">Fitur</a><a href="#panduan">Panduan</a><a href="<?= e($primaryUrl) ?>">Dashboard</a></div>
        <div class="footer-links"><strong>Informasi</strong><a href="#tentang">Tentang</a><a href="#faq">FAQ</a><a href="#kontak">Kontak</a></div>
        <div class="footer-links"><strong>Akun</strong><?php if (!$loggedIn): ?><a href="login.php">Masuk</a><a href="register.php">Daftar</a><?php else: ?><a href="account.php">Kelola Akun</a><a href="logout.php">Keluar</a><?php endif; ?></div>
    </div>
    <div class="landing-shell footer-bottom"><span>© <?= date('Y') ?> JejakKarier. Seluruh hak dilindungi.</span><span>Dibuat untuk perjalanan karier yang lebih baik.</span></div>
</footer>

<script src="assets/landing.js"></script>
</body>
</html>
