<?php
$user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
$display_name = $user && ! empty( $user->display_name ) ? $user->display_name : 'Admin HR';
$user_email = $user && ! empty( $user->user_email ) ? $user->user_email : 'hr@daw.co.id';
$logout_form = recruitment_logout_form( 'Keluar', home_url( '/' ) );
$list_url = recruitment_get_admin_url( 'vacancies' );
$create_url = add_query_arg( 'view', 'create', $list_url );
$view = sanitize_key( $_GET['view'] ?? 'list' );
$is_create_view = 'create' === $view;
$notice = sanitize_key( wp_unslash( $_GET['vacancy_notice'] ?? '' ) );
$notice_messages = [
    'saved' => 'Lowongan berhasil disimpan.',
    'status_updated' => 'Status lowongan berhasil diperbarui.',
    'invalid_data' => 'Lowongan belum disimpan. Periksa nama posisi, dealer, departemen, dan deadline yang harus diisi dengan tanggal hari ini atau sesudahnya.',
    'department_unavailable' => 'Daftar departemen Oracle tidak tersedia. Lowongan belum disimpan.',
    'save_failed' => 'Lowongan belum dapat disimpan. Silakan coba kembali.',
];
$edit_id = absint( $_GET['edit_vacancy'] ?? 0 );
$editing_vacancy = $edit_id ? get_post( $edit_id ) : null;
if ( ! $editing_vacancy || 'daw_vacancy' !== $editing_vacancy->post_type ) {
    $editing_vacancy = null;
    $edit_id = 0;
}
$database = new Recruitment_Database();
$departments = $database->get_departments();
$department_lookup_error = $database->has_error();
$selected_department_id = $edit_id ? (string) get_post_meta( $edit_id, '_daw_department_id', true ) : '';
$vacancy_posts = get_posts(
    [
        'post_type' => 'daw_vacancy',
        'post_status' => [ 'publish', 'draft', 'private' ],
        'numberposts' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
    ]
);
$vacancies = [];
foreach ( $vacancy_posts as $vacancy_post ) {
    $status = (string) get_post_meta( $vacancy_post->ID, '_daw_vacancy_status', true );
    if ( ! in_array( $status, [ 'published', 'draft', 'closed', 'archived' ], true ) ) {
        $status = 'publish' === $vacancy_post->post_status ? 'published' : ( 'private' === $vacancy_post->post_status ? 'archived' : 'draft' );
    }
    $deadline = (string) get_post_meta( $vacancy_post->ID, '_daw_deadline', true );
    if ( 'published' === $status && '' !== $deadline && $deadline < current_time( 'Y-m-d' ) ) {
        $status = 'closed';
    }
    $deadline_timestamp = strtotime( $deadline );
    $vacancies[] = [
        'id' => (int) $vacancy_post->ID,
        'title' => get_the_title( $vacancy_post ),
        'dealer' => (string) get_post_meta( $vacancy_post->ID, '_daw_dealer', true ),
        'region' => (string) get_post_meta( $vacancy_post->ID, '_daw_region', true ),
        'department' => (string) get_post_meta( $vacancy_post->ID, '_daw_department', true ),
        'type' => (string) get_post_meta( $vacancy_post->ID, '_daw_type', true ),
        'deadline' => $deadline_timestamp ? wp_date( 'j M Y', $deadline_timestamp ) : '—',
        'posted' => get_the_date( 'j M Y', $vacancy_post ),
        'status' => $status,
    ];
}
$status_labels = [ 'published' => 'Published', 'draft' => 'Draft', 'closed' => 'Closed', 'archived' => 'Archived' ];
?>
<div class="daw-vacancies">
    <aside class="daw-vacancies__sidebar">
        <a class="daw-vacancies__brand" href="<?= esc_url( recruitment_get_admin_url( 'vacancies' ) ) ?>"><span>DAW</span><strong>Admin</strong></a>
        <nav aria-label="Navigasi admin">
            <span class="daw-vacancies__nav-item">Dashboard</span>
            <span class="daw-vacancies__nav-heading">Recruitment</span>
            <span class="daw-vacancies__nav-item">Semua Kandidat Aktif</span>
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
            <span class="daw-vacancies__nav-item">Psikotes</span>
            <span class="daw-vacancies__nav-item">Jadwal Wawancara</span>
            <span class="daw-vacancies__nav-item">Email Recruitment</span>
            <span class="daw-vacancies__nav-item">Akun User Dept</span>
        </nav>
        <span class="daw-vacancies__collapse">‹‹ Ciutkan</span>
    </aside>

    <div class="daw-vacancies__workspace">
        <header class="daw-vacancies__header">
            <nav aria-label="Breadcrumb"><a href="<?= esc_url( $list_url ) ?>">Lowongan</a><i aria-hidden="true">/</i><strong><?= $is_create_view ? ( $editing_vacancy ? 'Edit' : 'Tambah' ) : 'Semua Lowongan' ?></strong></nav>
            <div class="daw-vacancies__account">
                <span class="daw-vacancies__avatar" aria-hidden="true">HR</span>
                <span><strong><?= esc_html( $display_name ) ?></strong><small><?= esc_html( $user_email ) ?></small></span>
                <?= $logout_form ?>
            </div>
        </header>

        <main class="daw-vacancies__main <?= $is_create_view ? 'daw-vacancies__main--create' : '' ?>">
            <?php if ( $is_create_view ) : ?>
                <a class="daw-vacancies__back" href="<?= esc_url( $list_url ) ?>">&#8592; Semua Lowongan</a>
                <div class="daw-vacancies__page-heading"><h1><?= $editing_vacancy ? 'Edit Lowongan' : 'Tambah Lowongan' ?></h1><p>Isi detail lowongan. Lowongan berstatus Published akan tampil di portal karier DAW.</p></div>
                <?php if ( isset( $notice_messages[ $notice ] ) ) : ?><p class="daw-vacancies__feedback daw-vacancies__feedback--error" role="alert"><?= esc_html( $notice_messages[ $notice ] ) ?></p><?php endif; ?>
                <?php if ( $department_lookup_error ) : ?><p class="daw-vacancies__feedback daw-vacancies__feedback--error" role="alert"><?= esc_html( $notice_messages['department_unavailable'] ) ?></p><?php endif; ?>
                <form class="daw-vacancies__create-form" data-vacancy-create-form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
                    <input type="hidden" name="action" value="daw_save_vacancy">
                    <input type="hidden" name="vacancy_id" value="<?= esc_attr( (string) $edit_id ) ?>">
                    <?php wp_nonce_field( 'daw_save_vacancy' ); ?>
                    <label>Nama Posisi <span>*</span><input name="title" value="<?= esc_attr( $editing_vacancy ? $editing_vacancy->post_title : '' ) ?>" maxlength="255" required></label>
                    <label>Deskripsi Pekerjaan<textarea name="description" placeholder="Deskripsi singkat tentang posisi dan tanggung jawab utama..."><?= esc_textarea( $editing_vacancy ? $editing_vacancy->post_content : '' ) ?></textarea></label>
                    <label>Tanggung Jawab<textarea name="responsibilities" placeholder="Tanggung jawab utama"><?= esc_textarea( $edit_id ? get_post_meta( $edit_id, '_daw_responsibilities', true ) : '' ) ?></textarea></label>
                    <label>Persyaratan<textarea name="requirements" placeholder="Pendidikan, pengalaman, atau persyaratan lain"><?= esc_textarea( $edit_id ? get_post_meta( $edit_id, '_daw_requirements', true ) : '' ) ?></textarea></label>
                    <div class="daw-vacancies__form-row"><label>Jenis Pekerjaan<select name="type"><?php foreach ( [ 'Full-time', 'Part-time', 'Kontrak', 'Magang' ] as $type ) : ?><option value="<?= esc_attr( $type ) ?>" <?= selected( $edit_id ? get_post_meta( $edit_id, '_daw_type', true ) : 'Full-time', $type, false ) ?>><?= esc_html( $type ) ?></option><?php endforeach; ?></select></label><label>Wilayah<select name="region"><?php foreach ( [ 'Sulawesi Utara', 'Gorontalo', 'Maluku Utara' ] as $region ) : ?><option value="<?= esc_attr( $region ) ?>" <?= selected( $edit_id ? get_post_meta( $edit_id, '_daw_region', true ) : 'Sulawesi Utara', $region, false ) ?>><?= esc_html( $region ) ?></option><?php endforeach; ?></select></label></div>
                    <label>Departemen <span>*</span><select name="department_id" required <?= $departments && ! $department_lookup_error ? '' : 'disabled' ?>><option value="">Pilih departemen</option><?php foreach ( $departments as $department ) : ?><option value="<?= esc_attr( $department['id'] ) ?>" <?= selected( $selected_department_id, (string) $department['id'], false ) ?>><?= esc_html( $department['name'] ) ?></option><?php endforeach; ?></select></label>
                    <div class="daw-vacancies__form-row"><label>Dealer <span>*</span><input name="dealer" value="<?= esc_attr( $edit_id ? get_post_meta( $edit_id, '_daw_dealer', true ) : '' ) ?>" placeholder="Nama Dealer / AHASS" maxlength="255" required></label><label>Lokasi<input name="location" value="<?= esc_attr( $edit_id ? get_post_meta( $edit_id, '_daw_location', true ) : '' ) ?>" placeholder="Kota/Kabupaten" maxlength="255"></label></div>
                    <div class="daw-vacancies__form-row"><label>Deadline Lamaran <span>*</span><input name="deadline" type="date" value="<?= esc_attr( $edit_id ? get_post_meta( $edit_id, '_daw_deadline', true ) : '' ) ?>" min="<?= esc_attr( current_time( 'Y-m-d' ) ) ?>" required></label></div>
                    <div class="daw-vacancies__form-actions"><a href="<?= esc_url( $list_url ) ?>">Batal</a><button type="submit" name="status" value="draft">Simpan Draft</button><button type="submit" name="status" value="published">Publish Lowongan</button></div>
                </form>
            <?php else : ?>
                <div class="daw-vacancies__list-heading"><div><h1>Semua Lowongan</h1><p>Kelola lowongan pekerjaan yang dipublikasikan di portal karier DAW.</p></div><a class="daw-vacancies__create" href="<?= esc_url( $create_url ) ?>"><span aria-hidden="true">+</span> Tambah Lowongan</a></div>
                <?php if ( isset( $notice_messages[ $notice ] ) ) : ?><p class="daw-vacancies__feedback" role="status"><?= esc_html( $notice_messages[ $notice ] ) ?></p><?php endif; ?>
                <nav class="daw-vacancies__tabs" aria-label="Filter status lowongan" data-vacancy-tabs>
                    <?php foreach ( [ 'all' => 'Semua', 'draft' => 'Draft', 'published' => 'Published', 'closed' => 'Closed', 'archived' => 'Archived' ] as $key => $label ) : ?>
                        <button type="button" class="<?= 'all' === $key ? 'is-active' : '' ?>" data-vacancy-tab="<?= esc_attr( $key ) ?>" aria-pressed="<?= 'all' === $key ? 'true' : 'false' ?>"><?= esc_html( $label ) ?><span><?= esc_html( (string) ( 'all' === $key ? count( $vacancies ) : count( array_filter( $vacancies, static fn( $item ) => $item['status'] === $key ) ) ) ) ?></span></button>
                    <?php endforeach; ?>
                </nav>
                <p class="daw-vacancies__feedback" role="status" aria-live="polite" data-vacancy-feedback hidden></p>
                <div class="daw-vacancies__table-wrap"><table class="daw-vacancies__table"><caption class="screen-reader-text">Daftar lowongan</caption>
                    <thead><tr><th scope="col">Posisi</th><th scope="col">Dealer</th><th scope="col">Wilayah</th><th scope="col">Departemen</th><th scope="col">Jenis</th><th scope="col">Dibuat</th><th scope="col">Deadline</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead>
                    <tbody data-vacancy-list><?php if ( ! $vacancies ) : ?><tr><td colspan="9">Belum ada data lowongan.</td></tr><?php endif; ?><?php foreach ( $vacancies as $vacancy ) : ?>
                        <tr data-vacancy-row data-vacancy-id="<?= esc_attr( (string) $vacancy['id'] ) ?>" data-status="<?= esc_attr( $vacancy['status'] ) ?>">
                            <td><strong data-field="title"><?= esc_html( $vacancy['title'] ) ?></strong></td><td data-field="dealer"><?= esc_html( $vacancy['dealer'] ) ?></td><td data-field="region"><?= esc_html( $vacancy['region'] ) ?></td><td data-field="department"><?= esc_html( $vacancy['department'] ?: 'Belum diatur' ) ?></td><td data-field="type"><?= esc_html( $vacancy['type'] ) ?></td><td><?= esc_html( $vacancy['posted'] ) ?></td><td data-field="deadline"><?= esc_html( $vacancy['deadline'] ) ?></td>
                            <td><span class="daw-vacancies__badge daw-vacancies__badge--<?= esc_attr( $vacancy['status'] ) ?>" data-vacancy-status><?= esc_html( $status_labels[ $vacancy['status'] ] ) ?></span></td><td class="daw-vacancies__actions">
                                <a href="<?= esc_url( add_query_arg( [ 'view' => 'create', 'edit_vacancy' => $vacancy['id'] ], $list_url ) ) ?>">Edit</a>
                                <?php if ( 'archived' !== $vacancy['status'] ) : ?>
                                    <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
                                        <input type="hidden" name="action" value="daw_set_vacancy_status"><input type="hidden" name="vacancy_id" value="<?= esc_attr( (string) $vacancy['id'] ) ?>">
                                        <?php wp_nonce_field( 'daw_set_vacancy_status_' . $vacancy['id'] ); ?>
                                        <?php if ( 'published' === $vacancy['status'] ) : ?><button type="submit" name="status" value="closed">Tutup</button><?php elseif ( 'draft' === $vacancy['status'] || 'closed' === $vacancy['status'] ) : ?><button type="submit" name="status" value="published">Publish</button><?php endif; ?>
                                        <button type="submit" name="status" value="archived">Arsipkan</button>
                                    </form>
                                <?php endif; ?>
                            </td>
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
