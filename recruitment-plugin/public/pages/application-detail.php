<?php
$preview = recruitment_is_ui_preview();
$stages = [
    [ 'label' => 'Lamaran Dikirim', 'date' => '5 Agustus 2026', 'detail' => 'Lamaran berhasil diterima oleh sistem.', 'status' => 'Selesai', 'state' => 'done' ],
    [ 'label' => 'Seleksi Administrasi', 'date' => '10 Agustus 2026', 'detail' => 'Dokumen administrasi telah diverifikasi.', 'status' => 'Lolos', 'state' => 'done' ],
    [ 'label' => 'Tes Psikologi', 'date' => '15 Agustus 2026', 'detail' => 'Tes psikologi online tersedia sampai 28 Agustus 2026.', 'status' => 'Sedang Berlangsung', 'state' => 'current' ],
    [ 'label' => 'Wawancara HR', 'date' => '—', 'detail' => 'Menunggu hasil tahap sebelumnya.', 'status' => 'Menunggu', 'state' => 'pending' ],
    [ 'label' => 'Wawancara User', 'date' => '—', 'detail' => 'Menunggu hasil tahap sebelumnya.', 'status' => 'Menunggu', 'state' => 'pending' ],
    [ 'label' => 'Keputusan Akhir', 'date' => '—', 'detail' => 'Menunggu hasil tahap sebelumnya.', 'status' => 'Menunggu', 'state' => 'pending' ],
];
?>
<div class="daw-recruitment daw-account">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-account__main daw-account__main--narrow">
        <a class="daw-account__back" href="<?= esc_url( recruitment_get_public_url( 'application-history', $preview ? [ 'daw_ui_preview' => '1' ] : [] ) ) ?>">&larr; Kembali ke Riwayat Lamaran</a>
        <?php if ( ! $preview ) : ?>
            <section class="daw-account__empty"><h1>Detail lamaran belum terhubung</h1><p>Detail aplikasi akun akan tersedia setelah integrasi dengan data rekrutmen selesai.</p><a href="<?= esc_url( recruitment_get_public_url( 'application-history' ) ) ?>">Kembali ke Riwayat Lamaran</a></section>
        <?php else : ?>
            <div class="daw-account__preview-note">Contoh tampilan. Data berikut bukan data lamaran akun Anda.</div>
            <section class="daw-account__card daw-account__application-summary"><small>DAW-2026-001245</small><span class="daw-account__status is-active">Aktif</span><h1>Sales Executive</h1><p>DAW Airmadidi · Minahasa Utara</p><div><span>Tanggal dikirim<strong>5 Agustus 2026</strong></span><span>Tahap terakhir<strong>Tes Psikologi</strong></span><span>Status akhir<strong>Belum tersedia</strong></span></div></section>
            <section class="daw-account__card"><h2>Snapshot Data Saat Melamar</h2><div class="daw-account__fields"><span>Nama lengkap<strong>Aiko Rahayu</strong></span><span>Email<strong>aiko.rahayu@email.com</strong></span><span>Nomor telepon<strong>0812 3456 7890</strong></span><span>Pendidikan<strong>S1 Manajemen</strong></span><span>Institusi<strong>Universitas Sam Ratulangi</strong></span><span>Pengalaman terakhir<strong>Sales Associate · PT Maju Bersama</strong></span></div></section>
            <section class="daw-account__card"><h2>Dokumen yang Dikirim</h2><div class="daw-account__document-list"><span>CV_Aiko_Rahayu.pdf <b>Terkirim</b></span><span>Ijazah_S1.pdf <b>Terkirim</b></span><span>Foto_Profil.jpg <b>Terkirim</b></span></div></section>
            <section class="daw-account__card"><h2>Riwayat Status Seleksi</h2><div class="daw-account__timeline"><?php foreach ( $stages as $stage ) : ?><article class="is-<?= esc_attr( $stage['state'] ) ?>"><span class="daw-account__timeline-marker"><?= 'done' === $stage['state'] ? '&#10003;' : ( 'current' === $stage['state'] ? '●' : '○' ) ?></span><div><strong><?= esc_html( $stage['label'] ) ?></strong><small><?= esc_html( $stage['date'] ) ?></small><p><?= esc_html( $stage['detail'] ) ?></p></div><b><?= esc_html( $stage['status'] ) ?></b></article><?php endforeach; ?></div></section>
            <a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'application-process', [ 'daw_ui_preview' => '1' ] ) ) ?>">Lihat Proses Lamaran</a>
        <?php endif; ?>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
