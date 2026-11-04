<?php
$preview = recruitment_is_ui_preview();
$samples = [
    [ 'id' => 'DAW-2026-001245', 'title' => 'Sales Executive', 'dealer' => 'DAW Airmadidi · Minahasa Utara', 'date' => '5 Agustus 2026', 'stage' => 'Tes Psikologi', 'status' => 'Sedang Berlangsung', 'state' => 'Aktif' ],
    [ 'id' => 'DAW-2025-000874', 'title' => 'Service Advisor', 'dealer' => 'DAW Manado · Manado', 'date' => '12 November 2025', 'stage' => 'Keputusan Akhir', 'status' => 'Tidak Lolos', 'state' => 'Tidak Lolos' ],
];
?>
<div class="daw-recruitment daw-account">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-account__main">
        <header class="daw-account__heading"><div><span class="daw-recruitment__eyebrow">AKUN PELAMAR</span><h1>Detail &amp; Riwayat Lamaran</h1><p>Seluruh lamaran akan terhubung ke akun Anda dan memiliki riwayat terpisah.</p></div></header>
        <?php require recruitment_get_plugin_path( 'public/components/applicant-account-nav.php' ); ?>
        <?php if ( $preview ) : ?>
            <div class="daw-account__preview-note">Contoh tampilan. Data berikut bukan data lamaran akun Anda.</div>
            <div class="daw-account__filters"><div class="daw-account__tabs"><button class="is-active" type="button" data-application-filter="all" aria-pressed="true">Semua Lamaran</button><button type="button" data-application-filter="active" aria-pressed="false">Lamaran Aktif</button><button type="button" data-application-filter="finished" aria-pressed="false">Proses Selesai</button><button type="button" data-application-filter="accepted" aria-pressed="false">Diterima</button><button type="button" data-application-filter="rejected" aria-pressed="false">Tidak Lolos</button></div><input type="search" data-application-search placeholder="Cari posisi atau kode lamaran..."></div>
            <div class="daw-account__stats"><div><span>Total lamaran</span><strong>2</strong></div><div><span>Aktif</span><strong>1</strong></div><div><span>Proses selesai</span><strong>1</strong></div><div><span>Diterima</span><strong>0</strong></div></div>
            <?php foreach ( $samples as $index => $application ) : ?>
                <article class="daw-account__application-row" data-application-state="<?= 0 === $index ? 'active' : 'rejected' ?>" data-application-search="<?= esc_attr( strtolower( $application['id'] . ' ' . $application['title'] . ' ' . $application['dealer'] ) ) ?>"><div><h2><?= esc_html( $application['title'] ) ?> <span class="daw-account__status <?= 0 === $index ? 'is-active' : 'is-closed' ?>"><?= esc_html( $application['state'] ) ?></span></h2><p><?= esc_html( $application['dealer'] ) ?></p><small><?= esc_html( $application['id'] ) ?></small></div><div><span>Tanggal melamar</span><strong><?= esc_html( $application['date'] ) ?></strong></div><div><span>Tahap terakhir</span><strong><?= esc_html( $application['stage'] ) ?></strong></div><div><span>Status terkini</span><strong><?= esc_html( $application['status'] ) ?></strong></div><div class="daw-account__row-actions"><a href="<?= esc_url( recruitment_get_public_url( 'application-detail', [ 'daw_ui_preview' => '1', 'application_id' => (string) $index ] ) ) ?>">Lihat Detail</a><?php if ( 0 === $index ) : ?><a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'application-process', [ 'daw_ui_preview' => '1', 'application_id' => (string) $index ] ) ) ?>">Lihat Proses</a><?php endif; ?></div></article>
            <?php endforeach; ?>
        <?php else : ?>
            <section class="daw-account__empty"><span aria-hidden="true">▤</span><h2>Riwayat lamaran belum tersedia di akun</h2><p>Pengiriman lamaran masih menggunakan penyimpanan rekrutmen saat ini. Riwayat akan ditampilkan di sini setelah integrasi akun dengan data aplikasi selesai.</p><a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">Lihat Lowongan</a></section>
        <?php endif; ?>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
