<?php
$user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
$display_name = $user && ! empty( $user->display_name ) ? $user->display_name : 'Admin HR';
$user_email = $user && ! empty( $user->user_email ) ? $user->user_email : 'hr@daw.co.id';
$logout_url = function_exists( 'wp_logout_url' ) ? wp_logout_url( home_url( '/' ) ) : '?page=login';
$list_url = recruitment_get_admin_url( 'vacancies' );
$create_url = add_query_arg( 'view', 'create', $list_url );
$view = sanitize_key( $_GET['view'] ?? 'list' );
$is_create_view = 'create' === $view;
$vacancies = [
    [ 'title' => 'Sales Executive', 'dealer' => 'Dealer Airmadidi', 'region' => 'Sulawesi Utara', 'type' => 'Full-time', 'deadline' => '30 Sep 2026', 'posted' => '15 Agu 2026', 'status' => 'published' ],
    [ 'title' => 'Teknisi', 'dealer' => 'AHASS Manado Selatan', 'region' => 'Sulawesi Utara', 'type' => 'Full-time', 'deadline' => '15 Sep 2026', 'posted' => '10 Agu 2026', 'status' => 'published' ],
    [ 'title' => 'Admin Finance', 'dealer' => 'Dealer Gorontalo', 'region' => 'Gorontalo', 'type' => 'Full-time', 'deadline' => '10 Sep 2026', 'posted' => '05 Agu 2026', 'status' => 'closed' ],
    [ 'title' => 'Service Advisor', 'dealer' => 'AHASS Ternate', 'region' => 'Maluku Utara', 'type' => 'Full-time', 'deadline' => '31 Agu 2026', 'posted' => '20 Jul 2026', 'status' => 'archived' ],
    [ 'title' => 'Kepala Mekanik', 'dealer' => 'Dealer Bitung', 'region' => 'Sulawesi Utara', 'type' => 'Full-time', 'deadline' => '30 Okt 2026', 'posted' => '28 Agu 2026', 'status' => 'draft' ],
];
$status_labels = [ 'published' => 'Published', 'draft' => 'Draft', 'closed' => 'Closed', 'archived' => 'Archived' ];
?>
<div class="daw-vacancies">
    <aside class="daw-vacancies__sidebar">
        <a class="daw-vacancies__brand" href="<?= esc_url( recruitment_get_admin_url( 'vacancies' ) ) ?>"><span>DAW</span><strong>Admin</strong></a>
        <nav aria-label="Navigasi admin">
            <span class="daw-vacancies__nav-item">Dashboard</span>
            <span class="daw-vacancies__nav-heading">Recruitment</span>
            <span class="daw-vacancies__nav-item">Semua Kandidat Aktif <b>12</b></span>
            <span class="daw-vacancies__nav-item">Seleksi Administrasi</span>
            <span class="daw-vacancies__nav-item">Psikotes</span>
            <span class="daw-vacancies__nav-item">Wawancara HR</span>
            <span class="daw-vacancies__nav-item">Wawancara User</span>
            <span class="daw-vacancies__nav-item">Final Decision</span>
            <span class="daw-vacancies__nav-item">Database Pelamar</span>
            <details class="daw-vacancies__nav-group" open>
                <summary class="daw-vacancies__nav-parent">Lowongan</summary>
                <div class="daw-vacancies__nav-children">
                    <a class="daw-vacancies__nav-item daw-vacancies__nav-subitem <?= ! $is_create_view ? 'is-active' : '' ?>" href="<?= esc_url( $list_url ) ?>" <?= ! $is_create_view ? 'aria-current="page"' : '' ?>>Semua Lowongan</a>
                    <a class="daw-vacancies__nav-item daw-vacancies__nav-subitem <?= $is_create_view ? 'is-active' : '' ?>" href="<?= esc_url( $create_url ) ?>" <?= $is_create_view ? 'aria-current="page"' : '' ?>>Tambah Lowongan</a>
                    <a class="daw-vacancies__nav-item daw-vacancies__nav-subitem" href="<?= esc_url( recruitment_get_admin_url( 'applications' ) ) ?>">Pengajuan Lowongan</a>
                </div>
            </details>
            <span class="daw-vacancies__nav-item">Form Lamaran</span>
            <span class="daw-vacancies__nav-item">Psikotes</span>
            <span class="daw-vacancies__nav-item">Jadwal Wawancara</span>
            <span class="daw-vacancies__nav-item">Email Recruitment</span>
            <span class="daw-vacancies__nav-item">Akun User Dept</span>
        </nav>
        <span class="daw-vacancies__collapse">‹‹ Ciutkan</span>
    </aside>

    <div class="daw-vacancies__workspace">
        <header class="daw-vacancies__header">
            <nav aria-label="Breadcrumb"><a href="<?= esc_url( $list_url ) ?>">Lowongan</a><i aria-hidden="true">/</i><strong><?= $is_create_view ? 'Tambah' : 'Semua Lowongan' ?></strong></nav>
            <div class="daw-vacancies__account">
                <span class="daw-vacancies__avatar" aria-hidden="true">HR</span>
                <span><strong><?= esc_html( $display_name ) ?></strong><small><?= esc_html( $user_email ) ?></small></span>
                <a href="<?= esc_url( $logout_url ) ?>">Keluar</a>
            </div>
        </header>

        <main class="daw-vacancies__main <?= $is_create_view ? 'daw-vacancies__main--create' : '' ?>">
            <?php if ( $is_create_view ) : ?>
                <a class="daw-vacancies__back" href="<?= esc_url( $list_url ) ?>">&#8592; Semua Lowongan</a>
                <div class="daw-vacancies__page-heading"><h1>Tambah Lowongan</h1><p>Buat lowongan pekerjaan baru untuk dipublikasikan di portal karier DAW.</p></div>
                <p class="daw-vacancies__feedback" role="status" aria-live="polite" data-vacancy-feedback hidden></p>
                <form class="daw-vacancies__create-form" data-vacancy-create-form>
                    <label>Nama Posisi <span>*</span><input name="title" required></label>
                    <label>Deskripsi Pekerjaan<textarea name="description" placeholder="Deskripsi singkat tentang posisi dan tanggung jawab utama..."></textarea></label>
                    <label>Tanggung Jawab<textarea name="responsibilities" placeholder="• Tanggung jawab 1&#10;• Tanggung jawab 2"></textarea></label>
                    <label>Persyaratan<textarea name="requirements" placeholder="• Min. D3 bidang terkait&#10;• Pengalaman 1 tahun"></textarea></label>
                    <div class="daw-vacancies__form-row"><label>Jenis Pekerjaan<select name="type"><option>Full-time</option><option>Part-time</option><option>Kontrak</option><option>Magang</option></select></label><label>Wilayah<select name="region"><option>Sulawesi Utara</option><option>Gorontalo</option><option>Maluku Utara</option></select></label></div>
                    <div class="daw-vacancies__form-row"><label>Dealer <span>*</span><input name="dealer" placeholder="Nama Dealer / AHASS" required></label><label>Lokasi<input name="location" placeholder="Kota/Kabupaten"></label></div>
                    <div class="daw-vacancies__form-row"><label>Deadline Lamaran <span>*</span><input name="deadline" type="date" value="2026-10-06" required></label><fieldset><legend>Status</legend><label><input type="radio" name="status" value="draft" checked> Draft</label><label><input type="radio" name="status" value="published"> Published</label></fieldset></div>
                    <div class="daw-vacancies__form-actions"><a href="<?= esc_url( $list_url ) ?>">Batal</a><button type="button" data-vacancy-save-draft>Simpan Draft</button><button type="submit">Publish Lowongan</button></div>
                </form>
            <?php else : ?>
                <div class="daw-vacancies__list-heading"><div><h1>Semua Lowongan</h1><p>Kelola lowongan pekerjaan yang dipublikasikan di portal karier DAW.</p></div><a class="daw-vacancies__create" href="<?= esc_url( $create_url ) ?>"><span aria-hidden="true">+</span> Tambah Lowongan</a></div>
                <nav class="daw-vacancies__tabs" aria-label="Filter status lowongan" data-vacancy-tabs>
                    <?php foreach ( [ 'all' => 'Semua', 'draft' => 'Draft', 'published' => 'Published', 'closed' => 'Closed', 'archived' => 'Archived' ] as $key => $label ) : ?>
                        <button type="button" class="<?= 'all' === $key ? 'is-active' : '' ?>" data-vacancy-tab="<?= esc_attr( $key ) ?>" aria-pressed="<?= 'all' === $key ? 'true' : 'false' ?>"><?= esc_html( $label ) ?><span><?= esc_html( (string) ( 'all' === $key ? count( $vacancies ) : count( array_filter( $vacancies, static fn( $item ) => $item['status'] === $key ) ) ) ) ?></span></button>
                    <?php endforeach; ?>
                </nav>
                <p class="daw-vacancies__feedback" role="status" aria-live="polite" data-vacancy-feedback hidden></p>
                <div class="daw-vacancies__table-wrap"><table class="daw-vacancies__table"><caption class="screen-reader-text">Daftar lowongan</caption>
                    <thead><tr><th scope="col">Posisi</th><th scope="col">Dealer</th><th scope="col">Wilayah</th><th scope="col">Jenis</th><th scope="col">Dibuat</th><th scope="col">Deadline</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead>
                    <tbody data-vacancy-list><?php foreach ( $vacancies as $index => $vacancy ) : ?>
                        <tr data-vacancy-row data-vacancy-id="<?= esc_attr( (string) ( $index + 1 ) ) ?>" data-status="<?= esc_attr( $vacancy['status'] ) ?>">
                            <td><strong data-field="title"><?= esc_html( $vacancy['title'] ) ?></strong></td><td data-field="dealer"><?= esc_html( $vacancy['dealer'] ) ?></td><td data-field="region"><?= esc_html( $vacancy['region'] ) ?></td><td data-field="type"><?= esc_html( $vacancy['type'] ) ?></td><td><?= esc_html( $vacancy['posted'] ) ?></td><td data-field="deadline"><?= esc_html( $vacancy['deadline'] ) ?></td>
                            <td><span class="daw-vacancies__badge daw-vacancies__badge--<?= esc_attr( $vacancy['status'] ) ?>" data-vacancy-status><?= esc_html( $status_labels[ $vacancy['status'] ] ) ?></span></td><td class="daw-vacancies__actions"><a href="<?= esc_url( $create_url ) ?>">Edit</a><?php if ( 'published' === $vacancy['status'] ) : ?><button type="button" data-vacancy-toggle>Tutup</button><?php elseif ( 'draft' === $vacancy['status'] ) : ?><button type="button" data-vacancy-toggle>Publish</button><?php elseif ( 'closed' === $vacancy['status'] ) : ?><button type="button" data-vacancy-archive>Arsipkan</button><?php endif; ?></td>
                        </tr><?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </main>
    </div>

</div>
<?php if ( defined( 'RECRUITMENT_SANDBOX' ) ) : ?>
    <?php require recruitment_get_plugin_path( 'public/components/screen-explorer.php' ); ?>
<?php endif; ?>
