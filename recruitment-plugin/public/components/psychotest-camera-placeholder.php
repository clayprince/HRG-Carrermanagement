<aside
    class="daw-exam-camera"
    aria-label="Area kamera"
    data-psychotest-tab-watch
    data-ajax-url="<?= esc_url( admin_url( 'admin-ajax.php' ) ) ?>"
    data-nonce="<?= esc_attr( wp_create_nonce( 'recruitment_proctoring_event' ) ) ?>"
>
    <div class="daw-exam-camera__preview" aria-hidden="true">
        <svg viewBox="0 0 24 24" focusable="false">
            <path d="M4 7.5h10a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2Zm12 3 6-3v9l-6-3" />
        </svg>
    </div>
    <strong>Area kamera</strong>
    <p data-tab-watch-status>Kamera belum terhubung. Integrasi disiapkan oleh PIC; perpindahan tab tetap dicatat.</p>
</aside>
