<?php
$user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
$display_name = $user && ! empty( $user->display_name ) ? $user->display_name : 'Admin HR';
$user_email = $user && ! empty( $user->user_email ) ? $user->user_email : 'hr@daw.co.id';
$logout_form = recruitment_logout_form( 'Keluar', home_url( '/' ) );
$list_url = recruitment_get_admin_url( 'vacancies' );
$create_url = add_query_arg( 'view', 'create', $list_url );
$view = sanitize_key( $_GET['view'] ?? 'list' );
$is_create_view = 'create' === $view;
$vacancy_form_steps = [
    [
        'title' => 'Informasi Permintaan',
        'description' => 'Lengkapi posisi, kebutuhan tim, dan lokasi kerja.',
        'fields' => [
            [ 'key' => 'department', 'label' => 'Departemen', 'type' => 'text', 'required' => true, 'maxlength' => 100 ],
            [ 'key' => 'position', 'label' => 'Posisi yang Dibutuhkan', 'type' => 'text', 'required' => true, 'maxlength' => 100 ],
            [ 'key' => 'job_level', 'label' => 'Level Jabatan', 'type' => 'select', 'options' => [ 'Staff', 'Supervisor', 'Manager', 'Head' ] ],
            [ 'key' => 'employment_type', 'label' => 'Kategori KJ', 'type' => 'select', 'required' => true, 'options' => [ 'Pegawai Tetap', 'Kontrak', 'Magang' ] ],
            [ 'key' => 'hiring_user', 'label' => 'Hiring User', 'type' => 'text', 'required' => true, 'maxlength' => 255, 'value' => $display_name ],
            [ 'key' => 'headcount', 'label' => 'Jumlah Kebutuhan', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 1000, 'value' => 1 ],
            [ 'key' => 'employee_status', 'label' => 'Status Karyawan', 'type' => 'select', 'options' => [ 'Baru', 'Pengganti', 'Penambahan Tim' ] ],
            [ 'key' => 'priority', 'label' => 'Prioritas', 'type' => 'select', 'required' => true, 'options' => [ 'Tinggi', 'Sedang', 'Rendah', 'Urgent' ] ],
            [ 'key' => 'target_date', 'label' => 'Target Pemenuhan', 'type' => 'date' ],
            [ 'key' => 'location', 'label' => 'Lokasi Penempatan', 'type' => 'text', 'required' => true, 'maxlength' => 100, 'placeholder' => 'Contoh: Manado' ],
            [ 'key' => 'reason', 'label' => 'Alasan Kebutuhan', 'type' => 'textarea', 'maxlength' => 5000 ],
        ],
    ],
    [
        'title' => 'Tujuan Posisi',
        'description' => 'Jelaskan peran dan tanggung jawab posisi.',
        'fields' => [
            [ 'key' => 'role_summary', 'label' => 'Ringkasan Peran', 'type' => 'textarea', 'required' => true, 'maxlength' => 5000 ],
            [ 'key' => 'kpi', 'label' => 'KPI / Target Utama', 'type' => 'textarea', 'maxlength' => 5000 ],
            [ 'key' => 'strategic_contribution', 'label' => 'Kontribusi Strategis', 'type' => 'textarea', 'maxlength' => 5000 ],
            [ 'key' => 'main_challenges', 'label' => 'Tantangan Utama', 'type' => 'textarea', 'maxlength' => 5000 ],
        ],
    ],
    [
        'title' => 'Profil Kandidat',
        'description' => 'Tentukan persyaratan kandidat yang dibutuhkan.',
        'fields' => [
            [ 'key' => 'minimum_education', 'label' => 'Pendidikan Minimum', 'type' => 'select', 'required' => true, 'options' => [ 'SMA/SMK', 'D3', 'S1', 'S2' ] ],
            [ 'key' => 'major', 'label' => 'Jurusan', 'type' => 'text', 'maxlength' => 255 ],
            [ 'key' => 'minimum_experience', 'label' => 'Pengalaman Minimum', 'type' => 'text', 'maxlength' => 500 ],
            [ 'key' => 'required_skills', 'label' => 'Keahlian Wajib', 'type' => 'textarea', 'required' => true, 'maxlength' => 5000 ],
            [ 'key' => 'age_range', 'label' => 'Rentang Usia', 'type' => 'text', 'maxlength' => 50 ],
            [ 'key' => 'certifications', 'label' => 'Sertifikasi', 'type' => 'textarea', 'maxlength' => 500 ],
            [ 'key' => 'industry_experience', 'label' => 'Pengalaman Industri', 'type' => 'text', 'maxlength' => 500 ],
            [ 'key' => 'preferred_skills', 'label' => 'Keahlian Tambahan', 'type' => 'textarea', 'maxlength' => 5000 ],
        ],
    ],
    [
        'title' => 'Validasi Requirement',
        'description' => 'Catat validasi budget dan kebutuhan posisi.',
        'fields' => [
            [ 'key' => 'salary_range', 'label' => 'Budget Gaji (Range)', 'type' => 'text', 'required' => true, 'maxlength' => 100 ],
            [ 'key' => 'recruitment_budget', 'label' => 'Anggaran Rekrutmen', 'type' => 'select', 'required' => true, 'options' => [ 'Tersedia', 'Menunggu Persetujuan', 'Belum Tersedia' ] ],
            [ 'key' => 'headcount_confirmation', 'label' => 'Konfirmasi Headcount', 'type' => 'select', 'required' => true, 'options' => [ 'Sesuai Rencana', 'Perlu Persetujuan' ] ],
            [ 'key' => 'priority_competencies', 'label' => 'Kompetensi Utama yang Diprioritaskan', 'type' => 'textarea', 'maxlength' => 5000 ],
            [ 'key' => 'validation_notes', 'label' => 'Catatan Validasi', 'type' => 'textarea', 'maxlength' => 5000 ],
        ],
    ],
    [
        'title' => 'Strategi Rekrutmen',
        'description' => 'Tentukan channel dan tahapan seleksi.',
        'fields' => [
            [ 'key' => 'sourcing_channels', 'label' => 'Channel Sourcing yang Disarankan', 'type' => 'checkboxes', 'options' => [ 'Job Portal (Jobstreet/LinkedIn)', 'Internal Referral', 'Walk-in / Karir.com', 'Social Media', 'Campus Recruitment' ] ],
            [ 'key' => 'sourcing_timeline', 'label' => 'Timeline Sourcing', 'type' => 'text', 'required' => true, 'maxlength' => 100 ],
            [ 'key' => 'selection_stages', 'label' => 'Tahap Seleksi', 'type' => 'select', 'required' => true, 'options' => [ 'Standar (Administrasi → Psikotes → Wawancara HR → Wawancara User)', 'Administrasi → Wawancara HR → Wawancara User', 'Administrasi → Tes Teknis → Wawancara User' ] ],
            [ 'key' => 'special_notes', 'label' => 'Catatan Khusus', 'type' => 'textarea', 'maxlength' => 5000 ],
        ],
    ],
    [
        'title' => 'Kesepakatan dan Publikasi',
        'description' => 'Lengkapi informasi publikasi. Pengajuan HR disimpan sebagai draft untuk diterbitkan terpisah.',
        'fields' => [
            [ 'key' => 'dealer', 'label' => 'Dealer / AHASS', 'type' => 'text', 'required' => true, 'maxlength' => 100 ],
            [ 'key' => 'deadline', 'label' => 'Deadline Lamaran', 'type' => 'date', 'required' => true ],
            [ 'key' => 'approval', 'label' => 'Saya menyatakan informasi lowongan ini telah diperiksa.', 'type' => 'checkbox', 'required' => true ],
            [ 'key' => 'signature', 'label' => 'Nama HR yang mengajukan', 'type' => 'text', 'required' => true, 'maxlength' => 255, 'value' => $display_name ],
        ],
    ],
];
$database = new Recruitment_Database();
$vacancy_records = $is_create_view ? [] : $database->get_vacancies( false );
$vacancy_load_error = ! $is_create_view && $database->has_error();
$vacancies = array_map(
    static function ( array $vacancy ): array {
        $deadline_timestamp = strtotime( (string) ( $vacancy['deadline'] ?? '' ) );
        $posted_timestamp = strtotime( (string) ( $vacancy['posted_at'] ?? '' ) );

        return [
            'id' => (string) ( $vacancy['id'] ?? '' ),
            'title' => (string) ( $vacancy['title'] ?? '' ),
            'dealer' => (string) ( $vacancy['dealer'] ?? '' ),
            'region' => (string) ( $vacancy['location'] ?? '' ),
            'type' => (string) ( $vacancy['type'] ?? '' ),
            'deadline' => false !== $deadline_timestamp ? date_i18n( 'd M Y', $deadline_timestamp ) : 'Belum ditentukan',
            'posted' => false !== $posted_timestamp ? date_i18n( 'd M Y', $posted_timestamp ) : '—',
            'status' => 1 === (int) ( $vacancy['status'] ?? 0 ) ? 'published' : 'draft',
        ];
    },
    $vacancy_records
);
$status_labels = [ 'published' => 'Published', 'draft' => 'Draft' ];
$notice_messages = [
    'created' => [ 'success', 'Lowongan berhasil disimpan di Oracle.' ],
    'status_updated' => [ 'success', 'Status lowongan berhasil diperbarui.' ],
    'request_link_failed' => [ 'warning', 'Lowongan berhasil diterbitkan, tetapi status pengajuan tidak berhasil diperbarui. Periksa halaman pengajuan sebelum mencoba membuatnya lagi.' ],
    'invalid' => [ 'error', 'Data lowongan tidak valid atau tidak dapat diproses.' ],
    'unavailable' => [ 'error', 'Data Oracle tidak dapat dimuat atau disimpan. Periksa koneksi dan skema Oracle.' ],
    'not_found' => [ 'error', 'Lowongan tidak ditemukan di Oracle.' ],
];
$notice = $notice_messages[ sanitize_key( $_GET['notice'] ?? '' ) ] ?? null;
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
                <?= $logout_form ?>
            </div>
        </header>

        <main class="daw-vacancies__main <?= $is_create_view ? 'daw-vacancies__main--create' : '' ?>">
            <?php if ( $notice ) : ?>
                <div class="notice notice-<?= esc_attr( $notice[0] ) ?>" role="status"><p><?= esc_html( $notice[1] ) ?></p></div>
            <?php endif; ?>
            <?php if ( $is_create_view ) : ?>
                <a class="daw-vacancies__back" href="<?= esc_url( $list_url ) ?>">&#8592; Semua Lowongan</a>
                <div class="daw-ud-request-heading"><div><h2>Form Lowongan HR</h2><span>Lowongan disimpan sebagai Draft dan dapat diterbitkan dari daftar lowongan.</span></div></div>
                <div class="daw-ud-request-wizard">
                    <nav class="daw-ud-request-steps" aria-label="Tahapan form lowongan">
                        <?php foreach ( $vacancy_form_steps as $step_index => $step ) : ?>
                            <div data-vacancy-step-label class="<?= 0 === $step_index ? 'is-active' : '' ?>"><small>Seksi <?= esc_html( (string) ( $step_index + 1 ) ) ?></small><strong><?= esc_html( $step['title'] ) ?></strong></div>
                        <?php endforeach; ?>
                    </nav>
                    <form class="daw-ud-request-form" data-vacancy-create-form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" novalidate>
                    <input type="hidden" name="action" value="daw_create_vacancy">
                    <?php wp_nonce_field( 'daw_create_vacancy' ); ?>
                    <?php foreach ( $vacancy_form_steps as $step_index => $step ) : ?>
                        <section class="daw-ud-request-card" data-vacancy-step <?= 0 === $step_index ? '' : 'hidden' ?>>
                            <header><h3><?= esc_html( $step['title'] ) ?></h3><p><?= esc_html( $step['description'] ) ?></p></header>
                            <div class="daw-ud-request-fields">
                                <?php foreach ( $step['fields'] as $field ) : ?>
                                    <?php
                                    $key = (string) $field['key'];
                                    $field_name = 'vacancy[' . $key . ']';
                                    $required = ! empty( $field['required'] );
                                    $required_marker = $required ? ' <b>*</b>' : '';
                                    ?>
                                    <?php if ( 'checkboxes' === $field['type'] ) : ?>
                                        <fieldset class="daw-ud-request-checkboxes"><legend><?= esc_html( $field['label'] ) ?></legend><?php foreach ( $field['options'] as $option ) : ?><label><input type="checkbox" name="<?= esc_attr( $field_name ) ?>[]" value="<?= esc_attr( $option ) ?>"><span><?= esc_html( $option ) ?></span></label><?php endforeach; ?></fieldset>
                                    <?php elseif ( 'checkbox' === $field['type'] ) : ?>
                                        <label class="daw-ud-request-approval"><input type="checkbox" name="<?= esc_attr( $field_name ) ?>" value="1" <?= $required ? 'required' : '' ?>><span><?= esc_html( $field['label'] ) ?><?= $required_marker ?></span></label>
                                    <?php else : ?>
                                        <label class="daw-ud-request-field"><span><?= esc_html( $field['label'] ) ?><?= $required_marker ?></span>
                                            <?php if ( 'select' === $field['type'] ) : ?>
                                                <select name="<?= esc_attr( $field_name ) ?>" <?= $required ? 'required' : '' ?>><option value="">Pilih...</option><?php foreach ( $field['options'] as $option ) : ?><option value="<?= esc_attr( $option ) ?>"><?= esc_html( $option ) ?></option><?php endforeach; ?></select>
                                            <?php elseif ( 'textarea' === $field['type'] ) : ?>
                                                <textarea name="<?= esc_attr( $field_name ) ?>" rows="3" placeholder="<?= esc_attr( $field['placeholder'] ?? '' ) ?>" maxlength="<?= esc_attr( (string) ( $field['maxlength'] ?? 5000 ) ) ?>" <?= $required ? 'required' : '' ?>></textarea>
                                            <?php else : ?>
                                                <input type="<?= esc_attr( $field['type'] ) ?>" name="<?= esc_attr( $field_name ) ?>" value="<?= esc_attr( (string) ( $field['value'] ?? '' ) ) ?>" placeholder="<?= esc_attr( $field['placeholder'] ?? '' ) ?>" <?= isset( $field['maxlength'] ) ? 'maxlength="' . esc_attr( (string) $field['maxlength'] ) . '"' : '' ?> <?= isset( $field['min'] ) ? 'min="' . esc_attr( (string) $field['min'] ) . '"' : '' ?> <?= isset( $field['max'] ) ? 'max="' . esc_attr( (string) $field['max'] ) . '"' : '' ?> <?= $required ? 'required' : '' ?>>
                                            <?php endif; ?>
                                        </label>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <footer class="daw-ud-request-actions">
                                <?php if ( 0 < $step_index ) : ?><button class="daw-ud-request-previous" type="button" data-vacancy-step-back>&larr; Sebelumnya</button><?php else : ?><span></span><?php endif; ?>
                                <div>
                                    <?php if ( count( $vacancy_form_steps ) - 1 === $step_index ) : ?>
                                        <button class="button" type="submit" name="submit_status" value="draft" data-vacancy-submit>Simpan Draft</button>
                                    <?php else : ?>
                                        <button class="daw-ud-primary-button" type="button" data-vacancy-step-next>Selanjutnya <span aria-hidden="true">&rarr;</span></button>
                                    <?php endif; ?>
                                </div>
                            </footer>
                        </section>
                    <?php endforeach; ?>
                    </form>
                </div>
            <?php else : ?>
                <div class="daw-vacancies__list-heading"><div><h1>Semua Lowongan</h1><p>Kelola lowongan pekerjaan yang dipublikasikan di portal karier DAW.</p></div><a class="daw-vacancies__create" href="<?= esc_url( $create_url ) ?>"><span aria-hidden="true">+</span> Tambah Lowongan</a></div>
                <nav class="daw-vacancies__tabs" aria-label="Filter status lowongan" data-vacancy-tabs>
                    <?php foreach ( [ 'all' => 'Semua', 'draft' => 'Draft', 'published' => 'Published' ] as $key => $label ) : ?>
                        <button type="button" class="<?= 'all' === $key ? 'is-active' : '' ?>" data-vacancy-tab="<?= esc_attr( $key ) ?>" aria-pressed="<?= 'all' === $key ? 'true' : 'false' ?>"><?= esc_html( $label ) ?><span><?= esc_html( (string) ( 'all' === $key ? count( $vacancies ) : count( array_filter( $vacancies, static fn( $item ) => $item['status'] === $key ) ) ) ) ?></span></button>
                    <?php endforeach; ?>
                </nav>
                <?php if ( $vacancy_load_error ) : ?>
                    <div class="notice notice-error"><p>Data lowongan Oracle tidak dapat dimuat. Periksa koneksi dan skema Oracle.</p></div>
                <?php endif; ?>
                <div class="daw-vacancies__table-wrap"><table class="daw-vacancies__table"><caption class="screen-reader-text">Daftar lowongan</caption>
                    <thead><tr><th scope="col">Posisi</th><th scope="col">Dealer</th><th scope="col">Wilayah</th><th scope="col">Jenis</th><th scope="col">Dibuat</th><th scope="col">Deadline</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead>
                    <tbody data-vacancy-list><?php if ( empty( $vacancies ) && ! $vacancy_load_error ) : ?><tr><td class="daw-vacancies__empty" colspan="8">Belum ada lowongan.</td></tr><?php endif; ?><?php foreach ( $vacancies as $vacancy ) : ?>
                        <tr data-vacancy-row data-vacancy-id="<?= esc_attr( (string) $vacancy['id'] ) ?>" data-status="<?= esc_attr( $vacancy['status'] ) ?>">
                            <td><strong data-field="title"><?= esc_html( $vacancy['title'] ) ?></strong></td><td data-field="dealer"><?= esc_html( $vacancy['dealer'] ) ?></td><td data-field="region"><?= esc_html( $vacancy['region'] ) ?></td><td data-field="type"><?= esc_html( $vacancy['type'] ) ?></td><td><?= esc_html( $vacancy['posted'] ) ?></td><td data-field="deadline"><?= esc_html( $vacancy['deadline'] ) ?></td>
                            <td><span class="daw-vacancies__badge daw-vacancies__badge--<?= esc_attr( $vacancy['status'] ) ?>" data-vacancy-status><?= esc_html( $status_labels[ $vacancy['status'] ] ) ?></span></td><td class="daw-vacancies__actions">
                                <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
                                    <input type="hidden" name="action" value="daw_set_vacancy_status">
                                    <input type="hidden" name="vacancy_id" value="<?= esc_attr( $vacancy['id'] ) ?>">
                                    <input type="hidden" name="status" value="<?= 'published' === $vacancy['status'] ? 'draft' : 'published' ?>">
                                    <?php wp_nonce_field( 'daw_set_vacancy_status_' . $vacancy['id'] ); ?>
                                    <button type="submit"><?= 'published' === $vacancy['status'] ? 'Jadikan Draft' : 'Publikasikan' ?></button>
                                </form>
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
