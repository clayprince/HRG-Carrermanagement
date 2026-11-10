<?php
$user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
$display_name = $user && ! empty( $user->display_name ) ? $user->display_name : 'Admin HR';
$user_email = $user && ! empty( $user->user_email ) ? $user->user_email : 'hr@daw.co.id';
$logout_form = recruitment_logout_form( 'Keluar', home_url( '/' ), 'daw-user-interview__logout' );
$show_notes = 'notes' === sanitize_key( $_GET['view'] ?? '' );
$list_url = recruitment_get_admin_url( 'user-interview' );
$notes_url = add_query_arg( 'view', 'notes', $list_url );

$database = new Recruitment_Database();
$applications = $database->get_applications();
$active_candidate_count = $database->get_active_applicant_count();

$candidates = [];
$stats = [
    'waiting'     => 0,
    'interviewed' => 0,
    'passed'      => 0,
    'failed'      => 0,
];

foreach ( $applications as $app ) {
    $raw_status = strtoupper( (string) ( $app['status'] ?? 'SUBMITTED' ) );
    $is_passed = in_array( $raw_status, [ 'DITERIMA', 'ACCEPTED' ], true );
    $is_failed = in_array( $raw_status, [ 'DITOLAK', 'REJECTED' ], true );
    $is_waiting = in_array( $raw_status, [ 'INTERVIEW_HR', 'INTERVIEW_USER' ], true );

    if ( ! in_array( $raw_status, [ 'INTERVIEW_HR', 'INTERVIEW_USER', 'DITERIMA', 'DITOLAK' ], true ) ) {
        continue;
    }

    if ( $is_waiting ) {
        $stats['waiting']++;
    } elseif ( $is_passed ) {
        $stats['passed']++;
        $stats['interviewed']++;
    } elseif ( $is_failed ) {
        $stats['failed']++;
        $stats['interviewed']++;
    }

    $created_at = (string) ( $app['created_at'] ?? '' );
    $year = $created_at ? date( 'Y', strtotime( $created_at ) ) : date( 'Y' );
    $cand_id = (int) ( $app['applicant_id'] ?? 0 );

    $candidates[] = [
        'id'             => $cand_id,
        'application_id' => (int) $app['id'],
        'name'           => (string) ( $app['applicant_name'] ?: 'Pelamar #' . $cand_id ),
        'code'           => 'DAW-' . $year . '-' . str_pad( (string) $cand_id, 4, '0', STR_PAD_LEFT ),
        'position'       => (string) ( $app['title'] ?: 'Posisi belum ditentukan' ),
        'dealer'         => (string) ( $app['dealer'] ?: '-' ),
        'region'         => (string) ( $app['location'] ?: $app['province'] ?: '-' ),
        'hr_result'      => 'Lolos',
        'interview_date' => '—',
        'status'         => $is_passed ? 'Lolos' : ( $is_failed ? 'Tidak Lolos' : 'Menunggu' ),
        'status_class'   => $is_passed ? 'is-passed' : ( $is_failed ? 'is-failed' : 'is-waiting' ),
    ];
}

$selected_candidate_id = absint( $_GET['candidate_id'] ?? 0 );
$selected_cand = null;
if ( $selected_candidate_id > 0 ) {
    foreach ( $candidates as $c ) {
        if ( $c['id'] === $selected_candidate_id ) {
            $selected_cand = $c;
            break;
        }
    }
}
if ( ! $selected_cand && ! empty( $candidates ) ) {
    $selected_cand = $candidates[0];
}
?>
<div class="daw-user-interview<?= $show_notes ? ' daw-user-interview--notes' : '' ?>">
    <?php if ( $show_notes ) : ?>
        <aside class="daw-user-interview__sidebar">
            <a class="daw-user-interview__brand" href="<?= esc_url( $list_url ) ?>"><span>DAW</span><strong>Admin</strong></a>
            <nav aria-label="Navigasi admin">
                <span class="daw-user-interview__nav-item">Dashboard</span>
                <span class="daw-user-interview__nav-heading">Recruitment</span>
                <span class="daw-user-interview__nav-item">Semua Kandidat Aktif <b><?= esc_html( number_format_i18n( $active_candidate_count ) ) ?></b></span>
                <span class="daw-user-interview__nav-item">Seleksi Administrasi</span>
                <span class="daw-user-interview__nav-item">Psikotes</span>
                <span class="daw-user-interview__nav-item">Wawancara HR</span>
                <a class="daw-user-interview__nav-item is-active" href="<?= esc_url( $list_url ) ?>">Wawancara User</a>
                <span class="daw-user-interview__nav-item">Final Decision</span>
                <span class="daw-user-interview__nav-item">Database Pelamar</span>
                <details class="daw-user-interview__nav-group" open>
                    <summary class="daw-user-interview__nav-item">Lowongan</summary>
                    <a class="daw-user-interview__nav-item daw-user-interview__nav-subitem" href="<?= esc_url( recruitment_get_admin_url( 'vacancies' ) ) ?>">Daftar Lowongan</a>
                    <a class="daw-user-interview__nav-item daw-user-interview__nav-subitem" href="<?= esc_url( recruitment_get_admin_url( 'applications' ) ) ?>">Pengajuan Lowongan</a>
                </details>
                <span class="daw-user-interview__nav-item">Jadwal Wawancara</span>
                <span class="daw-user-interview__nav-item">Email Recruitment</span>
                <span class="daw-user-interview__nav-item">Akun User Dept</span>
            </nav>
        </aside>
    <?php endif; ?>
    <header class="daw-user-interview__header">
        <nav class="daw-user-interview__breadcrumb" aria-label="Breadcrumb">
            <span class="daw-user-interview__breadcrumb-root">DAW Admin</span>
            <span aria-hidden="true">/</span>
            <strong><?= $show_notes ? 'Catatan Wawancara User' : 'Wawancara User' ?></strong>
        </nav>
        <div class="daw-user-interview__account">
            <span class="daw-user-interview__avatar" aria-hidden="true">HR</span>
            <span class="daw-user-interview__account-name">
                <strong><?= esc_html( $display_name ) ?></strong>
                <small><?= esc_html( $user_email ) ?></small>
            </span>
            <?= $logout_form ?>
        </div>
    </header>

    <main class="daw-user-interview__main">
        <?php if ( $show_notes ) : ?>
            <a class="daw-user-interview__back" href="<?= esc_url( $list_url ) ?>"><span aria-hidden="true">&#8249;</span> Kembali ke Daftar Wawancara</a>
            <?php if ( $selected_cand ) : ?>
                <div class="daw-user-interview__detail-grid">
                    <section class="daw-user-interview__card daw-user-interview__candidate-card" aria-labelledby="candidate-card-title">
                        <h1 id="candidate-card-title">Kandidat</h1>
                        <div class="daw-user-interview__candidate-profile">
                            <span class="daw-user-interview__candidate-avatar" aria-hidden="true"><?= esc_html( strtoupper( substr( $selected_cand['name'], 0, 2 ) ) ) ?></span>
                            <span><strong><?= esc_html( $selected_cand['name'] ) ?></strong><small><?= esc_html( $selected_cand['code'] ) ?></small></span>
                        </div>
                        <dl class="daw-user-interview__candidate-facts">
                            <div><dt>Posisi</dt><dd><?= esc_html( $selected_cand['position'] ) ?></dd></div>
                            <div><dt>Dealer</dt><dd><?= esc_html( $selected_cand['dealer'] ) ?></dd></div>
                            <div><dt>Wilayah</dt><dd><?= esc_html( $selected_cand['region'] ) ?></dd></div>
                            <div><dt>Hasil HR</dt><dd><?= esc_html( $selected_cand['hr_result'] ) ?></dd></div>
                        </dl>
                        <details class="daw-user-interview__profile-details">
                            <summary>Lihat Profil Lengkap</summary>
                            <p><?= esc_html( $selected_cand['name'] . ' · ' . $selected_cand['position'] . ' · ' . $selected_cand['dealer'] ) ?></p>
                            <a class="button button-small" href="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-applicants', 'candidate_id' => $selected_cand['id'], 'application_id' => $selected_cand['application_id'] ], admin_url( 'admin.php' ) ) ) ?>">Buka Database Pelamar</a>
                        </details>
                    </section>

                    <section class="daw-user-interview__card daw-user-interview__notes-card" aria-labelledby="notes-title">
                        <h1 id="notes-title">Catatan Wawancara User</h1>
                        <form data-user-interview-form>
                            <div class="daw-user-interview__field-row">
                                <label class="daw-user-interview__field">Interviewer<input name="interviewer" type="text" value="Kepala Cabang"></label>
                                <label class="daw-user-interview__field">Tanggal Wawancara<input name="interview_date" type="date" value="<?= esc_attr( date( 'Y-m-d' ) ) ?>"></label>
                            </div>
                            <label class="daw-user-interview__field daw-user-interview__field--notes">Catatan Wawancara<textarea name="notes" rows="3" placeholder="Catat observasi, poin diskusi, dan kesan umum kandidat..."></textarea></label>

                            <fieldset class="daw-user-interview__choice-field">
                                <legend>Penilaian</legend>
                                <div class="daw-user-interview__choice-grid daw-user-interview__choice-grid--rating" data-choice-group>
                                    <button type="button" aria-pressed="false">Sangat Baik</button>
                                    <button type="button" aria-pressed="false">Baik</button>
                                    <button type="button" aria-pressed="false">Cukup</button>
                                    <button type="button" aria-pressed="false">Kurang</button>
                                </div>
                            </fieldset>

                            <fieldset class="daw-user-interview__choice-field">
                                <legend>Rekomendasi</legend>
                                <div class="daw-user-interview__choice-grid daw-user-interview__choice-grid--recommendation" data-choice-group>
                                    <button type="button" aria-pressed="false">Lanjut ke Tahap Berikutnya</button>
                                    <button type="button" aria-pressed="false">Tidak Dilanjutkan</button>
                                </div>
                            </fieldset>

                            <button class="daw-user-interview__save" type="submit">Simpan Catatan</button>
                            <p class="daw-user-interview__feedback" role="status" aria-live="polite" data-interview-feedback hidden></p>
                        </form>

                        <section class="daw-user-interview__decision" aria-labelledby="decision-title">
                            <h2 id="decision-title">Keputusan Wawancara User</h2>
                            <div class="daw-user-interview__decision-grid" data-choice-group>
                                <button class="is-success" type="button" aria-pressed="false" data-final-decision>✓ &nbsp; ACC / Lolos</button>
                                <button class="is-reject" type="button" aria-pressed="false" data-final-decision>× &nbsp; Tidak Lolos</button>
                            </div>
                            <p class="daw-user-interview__feedback" role="status" aria-live="polite" data-decision-feedback hidden></p>
                        </section>
                    </section>
                </div>
            <?php else : ?>
                <div class="daw-empty-state" style="padding:40px; text-align:center; background:#fff; border-radius:8px;">Belum ada kandidat yang tersedia untuk wawancara user.</div>
            <?php endif; ?>
        <?php else : ?>
            <div class="daw-interview-screen__heading">
                <div><h1>Wawancara User</h1><p>Kelola wawancara user (hiring manager) untuk kandidat yang lolos wawancara HR.</p></div>
            </div>
            <section class="daw-interview-screen__stats" aria-label="Ringkasan wawancara user">
                <article class="is-waiting"><strong><?= esc_html( (string) $stats['waiting'] ) ?></strong><span>Menunggu Wawancara</span></article>
                <article class="is-scheduled"><strong><?= esc_html( (string) $stats['interviewed'] ) ?></strong><span>Sudah Diwawancarai</span></article>
                <article class="is-passed"><strong><?= esc_html( (string) $stats['passed'] ) ?></strong><span>Lolos</span></article>
                <article class="is-failed"><strong><?= esc_html( (string) $stats['failed'] ) ?></strong><span>Tidak Lolos</span></article>
            </section>
            <section class="daw-user-interview__panel daw-interview-screen__panel" aria-labelledby="user-interview-title">
                <div class="daw-interview-screen__panel-heading"><h2 id="user-interview-title">Daftar Kandidat</h2><label for="user-interview-status">Filter status</label><select id="user-interview-status"><option>Semua Status</option><option>Menunggu</option><option>Selesai</option></select></div>
                <div class="daw-user-interview__table-wrap">
                    <table class="daw-interview-screen__table daw-interview-screen__table--user">
                        <caption class="screen-reader-text">Daftar kandidat wawancara user</caption>
                        <thead>
                            <tr>
                                <th scope="col">Kandidat</th>
                                <th scope="col">Posisi</th>
                                <th scope="col">Dealer</th>
                                <th scope="col">Wilayah</th>
                                <th scope="col">Hasil Wawancara HR</th>
                                <th scope="col">Tgl Wawancara User</th>
                                <th scope="col">Status</th>
                                <th scope="col">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $candidates ) ) : ?>
                                <?php foreach ( $candidates as $candidate ) : ?>
                                    <tr>
                                        <td>
                                            <span class="daw-user-interview__candidate"><?= esc_html( $candidate['name'] ) ?></span>
                                            <small class="daw-user-interview__code"><?= esc_html( $candidate['code'] ) ?></small>
                                        </td>
                                        <td><?= esc_html( $candidate['position'] ) ?></td>
                                        <td><?= esc_html( $candidate['dealer'] ) ?></td>
                                        <td><?= esc_html( $candidate['region'] ) ?></td>
                                        <td><span class="daw-interview-screen__result is-passed"><?= esc_html( $candidate['hr_result'] ) ?></span></td>
                                        <td><?= esc_html( $candidate['interview_date'] ) ?></td>
                                        <td><span class="daw-interview-screen__status <?= esc_attr( $candidate['status_class'] ) ?>"><?= esc_html( $candidate['status'] ) ?></span></td>
                                        <td><a class="daw-interview-screen__action" href="<?= esc_url( add_query_arg( [ 'view' => 'notes', 'candidate_id' => $candidate['id'] ], $list_url ) ) ?>">Catat Wawancara</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr><td colspan="8" style="text-align:center; padding:32px 16px; color:#6b7280;">Belum ada kandidat pada tahap Wawancara User.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>
<?php if ( defined( 'RECRUITMENT_SANDBOX' ) ) : ?>
    <?php require recruitment_get_plugin_path( 'public/components/screen-explorer.php' ); ?>
<?php endif; ?>
