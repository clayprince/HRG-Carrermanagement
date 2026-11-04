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
<div class="daw-recruitment daw-account daw-recruitment--tracking">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-account__main daw-account__main--tracking">
        <header class="daw-account__heading"><div><span class="daw-recruitment__eyebrow">AKUN PELAMAR</span><h1>Tracking Lamaran</h1><p>Pantau tahapan seleksi berdasarkan lamaran yang dipilih.</p></div><label class="daw-account__select-label">Pilih lamaran<select <?= $preview ? '' : 'disabled' ?>><option>DAW-2026-001245 · Sales Executive</option></select></label></header>
        <?php if ( ! $preview ) : ?>
            <section class="daw-account__empty"><h2>Status proses belum tersedia di akun</h2><p>Informasi proses akan muncul setelah integrasi akun dengan data aplikasi selesai. Tidak diperlukan kode tracking; silakan kembali ke akun untuk pembaruan berikutnya.</p><a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'application-history' ) ) ?>">Kembali ke Riwayat</a></section>
        <?php else : ?>
            <section class="daw-account__card daw-account__process-summary"><div class="daw-account__process-identity"><span class="daw-account__tracking-label">Status lamaran</span><span class="daw-account__status is-active">Sedang Berlangsung</span><h2>Aiko Rahayu</h2><strong>Sales Executive</strong><small>DAW Airmadidi · Minahasa Utara</small></div><div class="daw-account__fields"><span>Kode Lamaran<strong>DAW-2026-001245</strong></span><span>Tanggal Daftar<strong>5 Agustus 2026</strong></span><span>Tahap Saat Ini<strong>Tes Psikologi</strong></span></div></section>
            <section class="daw-account__card"><h2>Timeline Seleksi</h2><p class="daw-account__muted">Informasi yang ditampilkan hanya informasi yang relevan untuk pelamar.</p><div class="daw-account__timeline"><?php foreach ( $stages as $stage ) : ?><article class="is-<?= esc_attr( $stage['state'] ) ?>"><span class="daw-account__timeline-marker"><?= in_array( $stage['state'], [ 'done', 'current' ], true ) ? '&#10003;' : '○' ?></span><div><strong><?= esc_html( $stage['label'] ) ?></strong><small><?= esc_html( $stage['date'] ) ?></small><p><?= esc_html( $stage['detail'] ) ?></p><?php if ( 'current' === $stage['state'] ) : ?><a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'psychotest' ) ) ?>">Mulai Tes Psikologi</a><?php endif; ?></div><b><?= esc_html( $stage['status'] ) ?></b></article><?php endforeach; ?></div></section>
            <div class="daw-account__tracking-footer"><span>Status akan diperbarui oleh tim rekrutmen sesuai proses seleksi.</span><a href="<?= esc_url( recruitment_get_public_url( 'application-detail', [ 'daw_ui_preview' => '1' ] ) ) ?>">Lihat Detail &amp; Riwayat &rarr;</a></div>
        <?php endif; ?>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
