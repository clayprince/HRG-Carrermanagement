<?php
$user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
$display_name = $user && ! empty( $user->display_name ) ? $user->display_name : 'Admin HR';
$user_email = $user && ! empty( $user->user_email ) ? $user->user_email : 'hr@daw.co.id';
$logout_form = recruitment_logout_form( 'Keluar', home_url( '/' ), 'daw-user-interview__logout' );
$show_notes = 'notes' === sanitize_key( $_GET['view'] ?? '' );
$list_url = recruitment_get_admin_url( 'user-interview' );
$notes_url = add_query_arg( 'view', 'notes', $list_url );
?>
<div class="daw-user-interview<?= $show_notes ? ' daw-user-interview--notes' : '' ?>">
    <?php if ( $show_notes ) : ?>
        <aside class="daw-user-interview__sidebar">
            <a class="daw-user-interview__brand" href="<?= esc_url( $list_url ) ?>"><span>DAW</span><strong>Admin</strong></a>
            <nav aria-label="Navigasi admin">
                <span class="daw-user-interview__nav-item">Dashboard</span>
                <span class="daw-user-interview__nav-heading">Recruitment</span>
                <span class="daw-user-interview__nav-item">Semua Kandidat Aktif <b>12</b></span>
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
                <span class="daw-user-interview__nav-item">Form Lamaran</span>
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
            <div class="daw-user-interview__detail-grid">
                <section class="daw-user-interview__card daw-user-interview__candidate-card" aria-labelledby="candidate-card-title">
                    <h1 id="candidate-card-title">Kandidat</h1>
                    <div class="daw-user-interview__candidate-profile">
                        <span class="daw-user-interview__candidate-avatar" aria-hidden="true">BS</span>
                        <span><strong>Budi Santoso</strong><small>DAW-2026-601245</small></span>
                    </div>
                    <dl class="daw-user-interview__candidate-facts">
                        <div><dt>Posisi</dt><dd>Sales Executive</dd></div>
                        <div><dt>Dealer</dt><dd>DAW Airmadidi</dd></div>
                        <div><dt>Tgl Wawancara</dt><dd>3 September 2026</dd></div>
                        <div><dt>Waktu</dt><dd>09:00 WITA</dd></div>
                    </dl>
                    <details class="daw-user-interview__profile-details">
                        <summary>Lihat Profil Lengkap</summary>
                        <p>Budi Santoso · Sales Executive · DAW Airmadidi</p>
                    </details>
                </section>

                <section class="daw-user-interview__card daw-user-interview__notes-card" aria-labelledby="notes-title">
                    <h1 id="notes-title">Catatan Wawancara User</h1>
                    <form data-user-interview-form>
                        <div class="daw-user-interview__field-row">
                            <label class="daw-user-interview__field">Interviewer<input name="interviewer" type="text" value="Kepala Cabang"></label>
                            <label class="daw-user-interview__field">Tanggal Wawancara<input name="interview_date" type="date" value="2026-09-03"></label>
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

                    <section class="daw-user-interview__history" aria-labelledby="history-title">
                        <h2 id="history-title">Riwayat Catatan Sebelumnya</h2>
                        <blockquote>“Kandidat menunjukkan antusiasme yang tinggi dan pengalaman relevan di bidang sales.”<small>2 Sep 2026 · Screening awal</small></blockquote>
                    </section>
                </section>
            </div>
        <?php else : ?>
            <div class="daw-interview-screen__heading">
                <div><h1>Wawancara User</h1><p>Kelola wawancara user (hiring manager) untuk kandidat yang lolos wawancara HR.</p></div>
            </div>
            <section class="daw-interview-screen__stats" aria-label="Ringkasan wawancara user">
                <article class="is-waiting"><strong>1</strong><span>Menunggu Wawancara</span></article>
                <article class="is-scheduled"><strong>0</strong><span>Sudah Diwawancarai</span></article>
                <article class="is-passed"><strong>0</strong><span>Lolos</span></article>
                <article class="is-failed"><strong>0</strong><span>Tidak Lolos</span></article>
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
                            <tr>
                                <td>
                                    <span class="daw-user-interview__candidate">Doni Pratama</span>
                                    <small class="daw-user-interview__code">DAW-2026-001215</small>
                                </td>
                                <td>Sales Executive</td><td>AHASS Ternate</td><td>Maluku Utara</td>
                                <td><span class="daw-interview-screen__result is-passed">Lolos</span><small class="daw-interview-screen__result-note">Sangat Baik</small></td>
                                <td>—</td><td><span class="daw-interview-screen__status is-waiting">Menunggu</span></td>
                                <td><a class="daw-interview-screen__action" href="<?= esc_url( $notes_url ) ?>">Catat Wawancara</a></td>
                            </tr>
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
