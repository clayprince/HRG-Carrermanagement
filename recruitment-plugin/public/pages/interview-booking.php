<div class="daw-recruitment">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-account__main daw-account__main--narrow">
        <section class="daw-account__empty">
            <span aria-hidden="true">◷</span>
            <h1>Booking wawancara belum tersedia</h1>
            <p>Fitur booking akan dibuka kembali setelah jadwal terhubung langsung ke akun dan data aplikasi. Anda tidak perlu menggunakan kode tracking.</p>
            <a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'application-history' ) ) ?>">Kembali ke Akun</a>
        </section>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
