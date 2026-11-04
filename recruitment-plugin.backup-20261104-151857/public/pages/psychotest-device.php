<?php
$back_url = recruitment_get_public_url( 'psychotest-preparation' );
$next_url = recruitment_get_public_url( 'psych-test', [ 'state' => 'review' ] );
?>
<div class="daw-recruitment daw-recruitment--psychotest-device">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-recruitment__psychotest-main">
        <div class="daw-recruitment__psychotest-shell daw-recruitment__psychotest-shell--narrow">
            <a class="daw-recruitment__back-link" href="<?= esc_url( $back_url ) ?>">&larr; Kembali</a>

            <div class="daw-recruitment__psychotest-stepper" aria-label="Tahap pemeriksaan perangkat">
                <div class="daw-recruitment__psychotest-step">
                    <span>1</span>
                    <small>Persiapan</small>
                </div>
                <div class="daw-recruitment__psychotest-step">
                    <span>2</span>
                    <small>Perangkat</small>
                </div>
                <div class="daw-recruitment__psychotest-step is-active">
                    <span>3</span>
                    <small>Kamera</small>
                </div>
                <div class="daw-recruitment__psychotest-step">
                    <span>4</span>
                    <small>Persetujuan</small>
                </div>
            </div>

            <section class="daw-recruitment__psychotest-card daw-recruitment__device-check-card" data-psychotest-device-check>
                <div class="daw-recruitment__device-layout">
                    <div class="daw-recruitment__device-preview">
                        <div class="daw-recruitment__device-camera is-empty" data-device-camera-shell>
                            <video class="daw-recruitment__device-video" data-device-video autoplay muted playsinline></video>
                            <div class="daw-recruitment__device-empty" data-device-empty>
                                <span aria-hidden="true">◉</span>
                                <strong>Belum ada video</strong>
                                <small>Tekan tombol di bawah untuk mengizinkan akses kamera.</small>
                            </div>
                        </div>
                        <button type="button" class="daw-recruitment__button daw-recruitment__button--full" data-device-permission>
                            Izinkan Kamera
                        </button>
                        <div class="daw-recruitment__device-unsupported" data-device-browser-banner hidden>Browser Tidak Didukung</div>
                    </div>

                    <div class="daw-recruitment__device-status-panel">
                        <div class="daw-recruitment__device-status-item is-warning" data-device-status data-device-camera-status>
                            <span class="daw-recruitment__device-status-icon" data-device-status-icon aria-hidden="true">•</span>
                            <div>
                                <strong data-device-status-title>Kamera belum aktif</strong>
                                <small data-device-status-copy>Klik “Izinkan Kamera” untuk memulai pengecekan.</small>
                            </div>
                        </div>
                        <div class="daw-recruitment__device-status-item is-warning" data-device-status data-device-microphone-status>
                            <span class="daw-recruitment__device-status-icon" data-device-status-icon aria-hidden="true">•</span>
                            <div>
                                <strong data-device-status-title>Mikrofon belum aktif</strong>
                                <small data-device-status-copy>Izinkan akses mikrofon saat kamera dibuka.</small>
                            </div>
                        </div>
                        <div class="daw-recruitment__device-status-item is-warning" data-device-status data-device-audio-status>
                            <span class="daw-recruitment__device-status-icon" data-device-status-icon aria-hidden="true">•</span>
                            <div>
                                <strong data-device-status-title>Audio belum diperiksa</strong>
                                <small data-device-status-copy>Volume dan input audio akan dicek saat izin diberikan.</small>
                            </div>
                        </div>
                        <div class="daw-recruitment__device-status-item is-warning" data-device-status data-device-browser-status>
                            <span class="daw-recruitment__device-status-icon" data-device-status-icon aria-hidden="true">•</span>
                            <div>
                                <strong data-device-status-title>Browser sedang mengecek</strong>
                                <small data-device-status-copy>Memeriksa dukungan browser untuk kamera dan mikrofon.</small>
                            </div>
                        </div>
                        <div class="daw-recruitment__device-status-item is-warning" data-device-status data-device-connection-status>
                            <span class="daw-recruitment__device-status-icon" data-device-status-icon aria-hidden="true">•</span>
                            <div>
                                <strong data-device-status-title>Jaringan sedang diperiksa</strong>
                                <small data-device-status-copy>Memantau kestabilan koneksi internet Anda.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="daw-recruitment__psychotest-footer daw-recruitment__psychotest-footer--split">
                <button type="button" class="daw-recruitment__button daw-recruitment__button--ghost" data-device-retry>
                    Cek Ulang
                </button>
                <button type="button" class="daw-recruitment__button daw-recruitment__button--full" data-device-continue data-next-url="<?= esc_url( $next_url ) ?>" disabled aria-disabled="true">
                    Lanjut ke Persetujuan
                </button>
            </div>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
