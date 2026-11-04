<?php
$user = wp_get_current_user();
$display_name = $user && ! empty( $user->display_name ) ? $user->display_name : 'Admin HR';
$user_email = $user && ! empty( $user->user_email ) ? $user->user_email : 'hr@daw.co.id';
$logout_url = wp_logout_url( home_url( '/' ) );
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
            <a class="daw-user-interview__logout" href="<?= esc_url( $logout_url ) ?>">Keluar</a>
        </div>
    </header>

    <main class="daw-user-interview__main">
        <div class="daw-interview-screen__heading">
            <div><h1>Wawancara HR</h1><p>Kelola dan catat hasil wawancara HR untuk kandidat yang lolos psikotes.</p></div>
        </div>
        <section class="daw-interview-screen__stats" aria-label="Ringkasan wawancara HR">
            <article class="is-waiting"><strong>1</strong><span>Menunggu Wawancara</span></article>
            <article class="is-scheduled"><strong>0</strong><span>Sudah Diwawancarai</span></article>
            <article class="is-passed"><strong>0</strong><span>Lolos</span></article>
            <article class="is-failed"><strong>0</strong><span>Tidak Lolos</span></article>
        </section>
        <section class="daw-user-interview__panel daw-interview-screen__panel" aria-labelledby="hr-interview-candidates-title">
            <div class="daw-interview-screen__panel-heading"><h2 id="hr-interview-candidates-title">Daftar Kandidat</h2><label for="hr-interview-status">Filter status</label><select id="hr-interview-status"><option>Semua Status</option><option>Menunggu</option><option>Selesai</option></select></div>
            <div class="daw-user-interview__table-wrap">
                <table class="daw-interview-screen__table">
                    <caption class="screen-reader-text">Daftar kandidat wawancara HR</caption>
                    <thead><tr><th scope="col">Kandidat</th><th scope="col">Posisi</th><th scope="col">Dealer</th><th scope="col">Wilayah</th><th scope="col">Nilai Psikotes</th><th scope="col">Tanggal Wawancara</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead>
                    <tbody><tr>
                        <td><span class="daw-user-interview__candidate">Rini Kusuma</span><small class="daw-user-interview__code">DAW-2026-001228</small></td>
                        <td>Admin Finance</td><td>Dealer Gorontalo</td><td>Gorontalo</td><td><strong class="daw-interview-screen__score">85</strong></td><td>—</td>
                        <td><span class="daw-interview-screen__status is-waiting">Menunggu</span></td><td><a class="daw-interview-screen__action" href="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-hr-interview', 'candidate_id' => 1 ], admin_url( 'admin.php' ) ) ) ?>">Catat Wawancara</a></td>
                    </tr></tbody>
                </table>
            </div>
        </section>
    </main>
</div>