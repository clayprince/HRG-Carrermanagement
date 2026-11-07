<?php
$user = wp_get_current_user();
$display_name = $user && ! empty( $user->display_name ) ? $user->display_name : 'Admin HR';
$user_email = $user && ! empty( $user->user_email ) ? $user->user_email : 'hr@daw.co.id';
$logout_form = recruitment_logout_form( 'Keluar', home_url( '/' ), 'daw-user-interview__logout' );

$database = new Recruitment_Database();
$applications = $database->get_applications();

$candidates = [];
$stats = [
    'waiting'     => 0,
    'interviewed' => 0,
    'passed'      => 0,
    'failed'      => 0,
];

foreach ( $applications as $app ) {
    $raw_status = strtoupper( (string) ( $app['status'] ?? 'SUBMITTED' ) );
    $is_passed = in_array( $raw_status, [ 'INTERVIEW_USER', 'DITERIMA', 'ACCEPTED' ], true );
    $is_failed = in_array( $raw_status, [ 'DITOLAK', 'REJECTED' ], true );
    $is_waiting = ! $is_passed && ! $is_failed;

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
        'score'          => '—',
        'interview_date' => '—',
        'status'         => $is_passed ? 'Lolos' : ( $is_failed ? 'Tidak Lolos' : 'Menunggu' ),
        'status_class'   => $is_passed ? 'is-passed' : ( $is_failed ? 'is-failed' : 'is-waiting' ),
    ];
}
?>
<div class="daw-user-interview daw-interview-screen">
    <header class="daw-user-interview__header">
        <nav class="daw-user-interview__breadcrumb" aria-label="Breadcrumb">
            <span class="daw-user-interview__breadcrumb-root">Recruitment</span>
            <span aria-hidden="true">/</span>
            <strong>Wawancara HR</strong>
        </nav>
        <div class="daw-user-interview__account">
            <span class="daw-user-interview__avatar" aria-hidden="true">HR</span>
            <span class="daw-user-interview__account-name"><strong><?= esc_html( $display_name ) ?></strong><small><?= esc_html( $user_email ) ?></small></span>
            <?= $logout_form ?>
        </div>
    </header>

    <main class="daw-user-interview__main">
        <div class="daw-interview-screen__heading">
            <div><h1>Wawancara HR</h1><p>Kelola dan catat hasil wawancara HR untuk kandidat yang lolos psikotes.</p></div>
        </div>
        <section class="daw-interview-screen__stats" aria-label="Ringkasan wawancara HR">
            <article class="is-waiting"><strong><?= esc_html( (string) $stats['waiting'] ) ?></strong><span>Menunggu Wawancara</span></article>
            <article class="is-scheduled"><strong><?= esc_html( (string) $stats['interviewed'] ) ?></strong><span>Sudah Diwawancarai</span></article>
            <article class="is-passed"><strong><?= esc_html( (string) $stats['passed'] ) ?></strong><span>Lolos</span></article>
            <article class="is-failed"><strong><?= esc_html( (string) $stats['failed'] ) ?></strong><span>Tidak Lolos</span></article>
        </section>
        <section class="daw-user-interview__panel daw-interview-screen__panel" aria-labelledby="hr-interview-candidates-title">
            <div class="daw-interview-screen__panel-heading"><h2 id="hr-interview-candidates-title">Daftar Kandidat</h2><label for="hr-interview-status">Filter status</label><select id="hr-interview-status"><option>Semua Status</option><option>Menunggu</option><option>Selesai</option></select></div>
            <div class="daw-user-interview__table-wrap">
                <table class="daw-interview-screen__table">
                    <caption class="screen-reader-text">Daftar kandidat wawancara HR</caption>
                    <thead><tr><th scope="col">Kandidat</th><th scope="col">Posisi</th><th scope="col">Dealer</th><th scope="col">Wilayah</th><th scope="col">Nilai Psikotes</th><th scope="col">Tanggal Wawancara</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead>
                    <tbody>
                        <?php if ( ! empty( $candidates ) ) : ?>
                            <?php foreach ( $candidates as $candidate ) : ?>
                                <tr>
                                    <td><span class="daw-user-interview__candidate"><?= esc_html( $candidate['name'] ) ?></span><small class="daw-user-interview__code"><?= esc_html( $candidate['code'] ) ?></small></td>
                                    <td><?= esc_html( $candidate['position'] ) ?></td>
                                    <td><?= esc_html( $candidate['dealer'] ) ?></td>
                                    <td><?= esc_html( $candidate['region'] ) ?></td>
                                    <td><strong class="daw-interview-screen__score"><?= esc_html( $candidate['score'] ) ?></strong></td>
                                    <td><?= esc_html( $candidate['interview_date'] ) ?></td>
                                    <td><span class="daw-interview-screen__status <?= esc_attr( $candidate['status_class'] ) ?>"><?= esc_html( $candidate['status'] ) ?></span></td>
                                    <td><a class="daw-interview-screen__action" href="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-applicants', 'candidate_id' => $candidate['id'], 'application_id' => $candidate['application_id'] ], admin_url( 'admin.php' ) ) ) ?>">Lihat Detail</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="8" style="text-align:center; padding:32px 16px; color:#6b7280;">Belum ada kandidat pada tahap Wawancara HR.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>