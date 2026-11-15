<?php
$test_key = sanitize_key( wp_unslash( $_GET['test'] ?? 'iq' ) );
$test_key = in_array( $test_key, [ 'iq', 'pauli', 'disc', 'papi' ], true ) ? $test_key : 'iq';
$proctoring = new Recruitment_Psychotest_Proctoring();
$session = $proctoring->get_current_session();
if ( $proctoring->get_current_session_error() ) {
    wp_die( esc_html__( 'Status persetujuan tidak dapat dibaca dari Oracle.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
}
if ( ! is_array( $session ) ) {
    $proctoring->save_consent();
    $session = $proctoring->get_current_session();
}
$identity_status = (string) ( $session['identity_status'] ?? '' );
$identity_processed = in_array( $identity_status, [ 'CAPTURED', 'MANUAL_REVIEW', 'MANUAL_VERIFIED' ], true );
$test_labels = [ 'iq' => 'IQ', 'pauli' => 'Pauli', 'disc' => 'DISC', 'papi' => 'PAPI Kostick' ];
$subtest_name = $test_labels[ $test_key ] ?? 'Psikotes';
$back_url = recruitment_get_public_url( 'psychotest-preparation', [ 'test' => $test_key ] );

$test_routes = [
    'iq' => 'psych-iq',
    'pauli' => 'psych-pauli',
    'disc' => 'psych-personality',
    'papi' => 'psych-personality',
];
$subtest_args = [ 'start' => '1' ];
if ( 'psych-personality' === $test_routes[ $test_key ] ) {
    $subtest_args['test'] = $test_key;
}
$next_subtest_url = recruitment_get_public_url( $test_routes[ $test_key ], $subtest_args );
recruitment_grant_psychotest_consent( $test_key );
?>
<div class="daw-recruitment daw-recruitment--psychotest-identity">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-recruitment__psychotest-main">
        <div class="daw-recruitment__psychotest-shell daw-recruitment__psychotest-shell--wide">
            <a class="daw-recruitment__back-link" href="<?= esc_url( $back_url ) ?>">&larr; Kembali</a>

            <div class="daw-recruitment__psychotest-stepper" aria-label="Tahap pemeriksaan identitas">
                <div class="daw-recruitment__psychotest-step">
                    <span>1</span>
                    <small>Persiapan</small>
                </div>
                <div class="daw-recruitment__psychotest-step">
                    <span>2</span>
                    <small>Perangkat</small>
                </div>
                <div class="daw-recruitment__psychotest-step">
                    <span>3</span>
                    <small>Kamera</small>
                </div>
                <div class="daw-recruitment__psychotest-step">
                    <span>4</span>
                    <small>Persetujuan</small>
                </div>
                <div class="daw-recruitment__psychotest-step is-active">
                    <span>5</span>
                    <small>Foto &amp; KTP</small>
                </div>
            </div>

            <section class="daw-recruitment__psychotest-card daw-identity-card" data-next-url="<?= esc_url( $next_subtest_url ) ?>">
                <header class="daw-identity-header">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
                        <div>
                            <h1>Verifikasi Identitas: Foto Bersama KTP</h1>
                            <p>
                                Untuk memastikan bahwa peserta yang mengisi ujian adalah orang yang benar sesuai dengan berkas lamaran, silakan berfoto selfie memegang KTP Anda. Setelah foto berhasil disimpan, Anda akan langsung diarahkan ke soal tes <strong><?= esc_html( $subtest_name ) ?></strong>.
                            </p>
                        </div>
                        <?php if ( $identity_processed ) : ?>
                            <a href="<?= esc_url( $next_subtest_url ) ?>" class="daw-recruitment__button daw-identity-btn-skip-top">
                                Lanjut ke Soal Tes &rarr;
                            </a>
                        <?php endif; ?>
                    </div>
                </header>

                <div class="daw-identity-grid">
                    <!-- KOLOM KIRI: CONTOH & PANDUAN BERFOTO -->
                    <aside class="daw-identity-guide-panel">
                        <div class="daw-identity-example-card">
                            <div class="daw-identity-badge-correct">
                                <span class="daw-identity-badge-icon">&#10003;</span>
                                <strong>Contoh Foto yang Benar</strong>
                            </div>

                            <div class="daw-identity-illustration">
                                <svg viewBox="0 0 320 220" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Contoh posisi berfoto memegang KTP di samping wajah">
                                    <defs>
                                        <linearGradient id="guideBg" x1="0%" y1="0%" x2="100%" y2="100%">
                                            <stop offset="0%" stop-color="#f8fafc" />
                                            <stop offset="100%" stop-color="#e2e8f0" />
                                        </linearGradient>
                                        <linearGradient id="cardGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                            <stop offset="0%" stop-color="#38bdf8" />
                                            <stop offset="100%" stop-color="#0284c7" />
                                        </linearGradient>
                                    </defs>
                                    <rect width="320" height="220" rx="12" fill="url(#guideBg)" stroke="#cbd5e1" stroke-width="1.5" />
                                    
                                    <!-- SILUET KEPALA & WAJAH -->
                                    <circle cx="115" cy="85" r="42" fill="#cbd5e1" />
                                    <path d="M 65 190 C 65 140, 165 140, 165 190 Z" fill="#94a3b8" />
                                    <!-- Fitur Wajah Sederhana -->
                                    <circle cx="102" cy="82" r="4" fill="#64748b" />
                                    <circle cx="128" cy="82" r="4" fill="#64748b" />
                                    <path d="M 108 98 Q 115 106 122 98" stroke="#64748b" stroke-width="2.5" fill="none" stroke-linecap="round" />

                                    <!-- TANGAN & KARTU KTP -->
                                    <rect x="182" y="72" width="115" height="74" rx="7" fill="#000000" opacity="0.08" />
                                    <rect x="180" y="70" width="115" height="74" rx="7" fill="url(#cardGrad)" stroke="#0369a1" stroke-width="1.5" />
                                    <rect x="188" y="76" width="55" height="6" rx="2" fill="#ffffff" opacity="0.85" />
                                    <rect x="188" y="88" width="30" height="38" rx="3" fill="#ffffff" opacity="0.9" />
                                    <circle cx="203" cy="99" r="7" fill="#94a3b8" />
                                    <path d="M 193 124 C 193 114, 213 114, 213 124 Z" fill="#64748b" />
                                    <rect x="224" y="90" width="62" height="4" rx="1.5" fill="#ffffff" opacity="0.9" />
                                    <rect x="224" y="98" width="50" height="4" rx="1.5" fill="#ffffff" opacity="0.75" />
                                    <rect x="224" y="106" width="58" height="4" rx="1.5" fill="#ffffff" opacity="0.75" />
                                    <rect x="224" y="114" width="42" height="4" rx="1.5" fill="#ffffff" opacity="0.75" />
                                    <rect x="224" y="122" width="48" height="4" rx="1.5" fill="#ffffff" opacity="0.75" />

                                    <path d="M 172 155 C 172 135, 185 130, 195 130 C 198 130, 200 133, 200 137 C 195 145, 185 155, 172 165 Z" fill="#cbd5e1" stroke="#94a3b8" stroke-width="1" />
                                    <circle cx="200" cy="136" r="5" fill="#cbd5e1" />

                                    <circle cx="295" cy="25" r="14" fill="#22c55e" />
                                    <path d="M 289 25 L 293 29 L 301 20" stroke="#ffffff" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>

                            <ul class="daw-identity-guide-list">
                                <li>
                                    <span class="daw-identity-guide-num">1</span>
                                    <div><strong>Pegang KTP di samping wajah</strong>, menghadap lurus ke arah kamera.</div>
                                </li>
                                <li>
                                    <span class="daw-identity-guide-num">2</span>
                                    <div><strong>Wajah &amp; kartu terlihat</strong>, tidak perlu scan khusus atau validasi rumit.</div>
                                </li>
                                <li>
                                    <span class="daw-identity-guide-num">3</span>
                                    <div><strong>Langsung Lanjut ke Soal</strong>, Anda bisa langsung memulai tes <strong><?= esc_html( $subtest_name ) ?></strong> setelah ini.</div>
                                </li>
                            </ul>
                        </div>
                    </aside>

                    <!-- KOLOM KANAN: AREA KAMERA & CAPTURE -->
                    <div class="daw-identity-camera-panel" data-proctoring-identity data-next-url="<?= esc_url( $next_subtest_url ) ?>">
                        <div class="daw-identity-viewport" data-proctoring-viewport>
                            <!-- Live Camera Video -->
                            <video class="daw-identity-video" data-proctoring-identity-video autoplay muted playsinline hidden></video>
                            
                            <!-- Snapshot Canvas (proses) -->
                            <canvas data-proctoring-identity-canvas hidden></canvas>
                            
                            <!-- Snapshot Preview (tampil setelah capture) -->
                            <img class="daw-identity-preview-img" data-proctoring-identity-preview hidden alt="Hasil Foto Identitas Anda" />

                            <!-- Overlay Panduan Bingkai (Wajah & KTP) -->
                            <div class="daw-identity-frame-overlay" data-proctoring-overlay hidden>
                                <div class="daw-identity-frame-face" title="Posisikan wajah Anda di sini">
                                    <span>Wajah</span>
                                </div>
                                <div class="daw-identity-frame-card" title="Posisikan KTP Anda di sini">
                                    <span>Pegang KTP Di Sini</span>
                                </div>
                            </div>

                            <!-- Placeholder Awal Saat Kamera Belum Aktif -->
                            <div class="daw-identity-placeholder" data-proctoring-placeholder>
                                <div class="daw-identity-placeholder-icon">&#128247;</div>
                                <strong>Kamera Belum Aktif</strong>
                                <small>Klik tombol "Buka Kamera" di bawah untuk memulai sesi foto identitas.</small>
                            </div>
                        </div>

                        <!-- Status Keterangan Interaktif -->
                        <div class="daw-identity-status-box" role="status" data-proctoring-identity-status>
                            Kamera belum aktif. Klik tombol "Buka Kamera" di bawah untuk mengambil foto identitas.
                        </div>

                        <!-- Panel Tombol Aksi -->
                        <div class="daw-identity-actions">
                            <!-- Tombol Tahap 1: Buka Kamera -->
                            <button type="button" class="daw-recruitment__button daw-recruitment__button--full" data-proctoring-identity-start>
                                &#128247; Buka Kamera
                            </button>

                            <!-- Tombol Tahap 2: Ambil Foto -->
                            <button type="button" class="daw-recruitment__button daw-recruitment__button--full daw-identity-btn-capture" data-proctoring-identity-capture style="display:none;">
                                &#128248; Ambil Foto Sekarang
                            </button>

                            <!-- Tombol Tahap 3: Konfirmasi / Foto Ulang (Setelah Capture) -->
                            <div class="daw-identity-confirm-group" data-proctoring-identity-confirm-group style="display:none;">
                                <button type="button" class="daw-recruitment__button daw-recruitment__button--ghost" data-proctoring-identity-retake>
                                    &#8635; Foto Ulang
                                </button>
                                <button type="button" class="daw-recruitment__button daw-recruitment__button--full daw-identity-btn-confirm" data-proctoring-identity-confirm-submit data-next-url="<?= esc_url( $next_subtest_url ) ?>">
                                    &#10003; Lanjut ke Soal Tes <?= esc_html( $subtest_name ) ?> &rarr;
                                </button>
                            </div>

                            <?php if ( $identity_processed ) : ?>
                                <a href="<?= esc_url( $next_subtest_url ) ?>" class="daw-identity-btn-skip-direct" data-proctoring-identity-skip-btn>
                                    &rarr; Lanjut ke Soal Tes <?= esc_html( $subtest_name ) ?>
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- Form Pengiriman Backend -->
                        <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" enctype="multipart/form-data" data-proctoring-identity-form style="margin-top:12px;">
                            <?php wp_nonce_field( 'daw_proctoring_identity' ); ?>
                            <input type="hidden" name="action" value="daw_proctoring_identity">
                            <input type="hidden" name="test" value="<?= esc_attr( $test_key ) ?>">
                            <input type="hidden" name="proctoring_action" value="<?= esc_attr( $identity_processed ? 'continue' : 'capture' ) ?>" data-proctoring-identity-action>
                            
                            <p role="alert" class="daw-identity-error-msg" data-proctoring-identity-error hidden></p>

                            <!-- Fallback Verifikasi Manual -->
                            <div class="daw-identity-manual-fallback">
                                <span>Mengalami kendala kamera?</span>
                                <button type="submit" class="daw-identity-manual-btn" data-proctoring-identity-manual>
                                    Minta verifikasi manual oleh HR
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <footer class="daw-identity-footer-notice">
                    <small>
                        &#128274; Foto Anda disimpan secara terenkripsi dan privat hanya untuk keperluan verifikasi pengawasan tes psikotes oleh tim HR DAW. 
                        Tidak ada data yang dibagikan ke pihak ketiga.
                    </small>
                </footer>
            </section>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
