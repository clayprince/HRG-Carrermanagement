<?php
$back_url = recruitment_get_public_url( 'application-process' );
$preparation_url = recruitment_get_public_url( 'psychotest-preparation' );
?>
<div class="daw-recruitment daw-recruitment--psychotest-home">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-recruitment__psychotest-main">
        <div class="daw-recruitment__psychotest-shell">
            <a class="daw-recruitment__back-link" href="<?= esc_url( $back_url ) ?>">&larr; Kembali ke Proses Lamaran</a>

            <header class="daw-recruitment__psychotest-header">
                <div class="daw-recruitment__psychotest-header-copy">
                    <span class="daw-recruitment__eyebrow daw-recruitment__eyebrow--red">TES PSIKOLOGI ONLINE</span>
                    <h1>Tes Psikologi</h1>
                    <p>Anda telah lolos seleksi administrasi. Silakan selesaikan rangkaian tes psikologi berikut sesuai instruksi.</p>
                </div>
                <div class="daw-recruitment__psychotest-counter" aria-label="Progress tes psikologi">
                    <span class="daw-recruitment__muted-small">0 / 5</span>
                    <strong>Tes Selesai</strong>
                </div>
            </header>

            <section class="daw-recruitment__psychotest-card daw-recruitment__psychotest-summary">
                <div class="daw-recruitment__psychotest-summary-row">
                    <span class="daw-recruitment__progress-label">Progress</span>
                    <span class="daw-recruitment__count-badge">0%</span>
                </div>
                <div class="daw-recruitment__progress-bar" aria-label="Progress tes psikologi">
                    <span style="width: 0%"></span>
                </div>
                <div class="daw-recruitment__psychotest-summary-meta">
                    <span>Belum ada tes yang diselesaikan</span>
                    <span>0%</span>
                </div>
            </section>

            <section class="daw-recruitment__psychotest-card daw-recruitment__psychotest-alert">
                <div class="daw-recruitment__info-box">
                    <div class="daw-recruitment__info-box-icon" aria-hidden="true">i</div>
                    <div>
                        <strong>Sebelum memulai</strong>
                        <p>Pastikan Anda berada di tempat tenang, koneksi internet stabil, dan perangkat dalam kondisi baik. Tes harus dikerjakan sekaligus tanpa jeda antar tes.</p>
                    </div>
                </div>
            </section>

            <section class="daw-recruitment__psychotest-list" aria-label="Daftar tes psikologi">
                <article class="daw-recruitment__psychotest-item is-active">
                    <div class="daw-recruitment__psychotest-index">01</div>
                    <div class="daw-recruitment__psychotest-body">
                        <div class="daw-recruitment__psychotest-row">
                            <h2>IQ</h2>
                            <span class="daw-recruitment__tag daw-recruitment__tag--available">Tersedia</span>
                        </div>
                        <p class="daw-recruitment__psychotest-description">Tes Kemampuan Intelektual</p>
                        <small>20 menit · 10 soal</small>
                    </div>
                    <button type="button" class="daw-recruitment__button daw-recruitment__button--small" data-psychotest-start="iq">Mulai Tes</button>
                </article>

                <article class="daw-recruitment__psychotest-item is-locked">
                    <div class="daw-recruitment__psychotest-index">02</div>
                    <div class="daw-recruitment__psychotest-body">
                        <div class="daw-recruitment__psychotest-row">
                            <h2>PAULI</h2>
                            <span class="daw-recruitment__tag daw-recruitment__tag--locked">Menunggu Giliran</span>
                        </div>
                        <p class="daw-recruitment__psychotest-description">Tes Ketelitian dan Konsentrasi Kerja</p>
                        <small>10 menit · 40 segmen</small>
                    </div>
                </article>

                <article class="daw-recruitment__psychotest-item is-locked">
                    <div class="daw-recruitment__psychotest-index">03</div>
                    <div class="daw-recruitment__psychotest-body">
                        <div class="daw-recruitment__psychotest-row">
                            <h2>KEPRIBADIAN</h2>
                            <span class="daw-recruitment__tag daw-recruitment__tag--locked">Menunggu Giliran</span>
                        </div>
                        <p class="daw-recruitment__psychotest-description">Asesmen Karakteristik Individu</p>
                        <small>15 menit · 10 pernyataan</small>
                    </div>
                </article>

                <article class="daw-recruitment__psychotest-item is-locked">
                    <div class="daw-recruitment__psychotest-index">04</div>
                    <div class="daw-recruitment__psychotest-body">
                        <div class="daw-recruitment__psychotest-row">
                            <h2>DISC</h2>
                            <span class="daw-recruitment__tag daw-recruitment__tag--locked">Menunggu Giliran</span>
                        </div>
                        <p class="daw-recruitment__psychotest-description">Asesmen Perilaku</p>
                        <small>10 menit · 5 skenario</small>
                    </div>
                </article>

                <article class="daw-recruitment__psychotest-item is-locked">
                    <div class="daw-recruitment__psychotest-index">05</div>
                    <div class="daw-recruitment__psychotest-body">
                        <div class="daw-recruitment__psychotest-row">
                            <h2>PAPI KOSTICK</h2>
                            <span class="daw-recruitment__tag daw-recruitment__tag--locked">Menunggu Giliran</span>
                        </div>
                        <p class="daw-recruitment__psychotest-description">Asesmen Preferensi dalam Lingkungan Kerja</p>
                        <small>10 menit · 8 pasangan</small>
                    </div>
                </article>
            </section>

            <div class="daw-recruitment__psychotest-footer">
                <div class="daw-recruitment__psychotest-divider" aria-hidden="true"></div>
                <a class="daw-recruitment__button daw-recruitment__button--full" href="<?= esc_url( $preparation_url ) ?>">Mulai Persiapan Assessment</a>
                <p>Anda akan diarahkan ke halaman persiapan, pemeriksaan perangkat, dan kamera sebelum tes dimulai.</p>
            </div>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
