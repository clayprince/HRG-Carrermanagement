<?php
$user = wp_get_current_user();
if ( ! is_user_logged_in() || ! in_array( 'daw_candidate', (array) $user->roles, true ) ) {
	wp_safe_redirect( recruitment_get_public_url( 'account' ) );
	exit;
}

$candidate_id = absint( get_user_meta( $user->ID, 'recruitment_candidate_id', true ) );
$database = new Recruitment_Database();
$tracking = $candidate_id ? $database->get_candidate_tracking_data( $candidate_id ) : [
	'profile' => null,
	'applications' => [],
	'status_history' => [],
	'interview' => null,
	'decision' => null,
];
$candidate_name = (string) ( $tracking['profile']['NAMA_LENGKAP'] ?? $user->display_name );
$final_decision = trim( (string) ( $tracking['decision']['KEPUTUSAN_AKHIR'] ?? '' ) );
$interview_result = trim( (string) ( $tracking['interview']['STATUS_SELEKSI'] ?? $tracking['interview']['REKOMENDASI'] ?? '' ) );
?>
<div class="daw-recruitment daw-recruitment--tracking">
	<?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
	<main class="daw-recruitment__tracking-main">
		<div class="daw-recruitment__tracking-container">
			<section class="daw-recruitment__tracking-card daw-recruitment__tracking-summary">
				<div class="daw-recruitment__tracking-summary-top">
					<div><span class="daw-recruitment__tracking-label">Akun Kandidat</span><h1><?= esc_html( $candidate_name ) ?></h1></div>
					<a class="daw-recruitment__tracking-logout" href="<?= esc_url( wp_logout_url( recruitment_get_public_url( 'account' ) ) ) ?>">Keluar</a>
				</div>
				<?php if ( $database->has_error() ) : ?>
					<p class="daw-recruitment__alert" role="alert">Data seleksi belum dapat dimuat dari database. Silakan coba beberapa saat lagi.</p>
				<?php elseif ( ! $tracking['profile'] ) : ?>
					<p class="daw-recruitment__alert" role="alert">Profil akun tidak ditemukan pada database kandidat. Hubungi tim HR untuk bantuan.</p>
				<?php else : ?>
					<div class="daw-recruitment__tracking-summary-meta">
						<div><span>Posisi</span><strong><?= esc_html( (string) ( $tracking['profile']['POSISI_DILAMAR'] ?? 'Belum tersedia' ) ) ?></strong></div>
						<div><span>Status Keputusan Akhir</span><strong><?= esc_html( $final_decision ?: 'Menunggu keputusan HR' ) ?></strong></div>
					</div>
				<?php endif; ?>
			</section>

			<section class="daw-recruitment__tracking-card daw-recruitment__tracking-history">
				<h2>Lamaran Saya</h2>
				<?php if ( ! $tracking['applications'] ) : ?>
					<p>Belum ada data lamaran yang tercatat pada akun ini.</p>
				<?php else : ?>
					<?php foreach ( $tracking['applications'] as $application ) : ?>
						<article class="daw-recruitment__tracking-step">
							<div>
								<strong><?= esc_html( (string) ( $application['POSISI_DILAMAR'] ?? 'Lamaran' ) ) ?></strong>
								<small><?= esc_html( ! empty( $application['TANGGAL_MELAMAR'] ) ? date_i18n( 'd F Y', strtotime( (string) $application['TANGGAL_MELAMAR'] ) ) : 'Tanggal belum tersedia' ) ?></small>
							</div>
							<b><?= esc_html( (string) ( $application['STATUS_AKHIR'] ?: $application['STATUS_TERKINI'] ?: 'Dalam proses' ) ) ?></b>
						</article>
					<?php endforeach; ?>
				<?php endif; ?>
			</section>

			<section class="daw-recruitment__tracking-card daw-recruitment__tracking-history">
				<h2>Hasil Seleksi</h2>
				<div class="daw-recruitment__tracking-step">
					<div><strong>Wawancara User</strong></div>
					<b><?= esc_html( $interview_result ?: 'Belum ada hasil' ) ?></b>
				</div>
				<div class="daw-recruitment__tracking-step">
					<div><strong>Keputusan Akhir HR</strong></div>
					<b><?= esc_html( $final_decision ?: 'Menunggu keputusan' ) ?></b>
				</div>
			</section>
			<section class="daw-recruitment__tracking-card daw-recruitment__tracking-history">
				<h2>Riwayat Status</h2>
				<?php if ( empty( $tracking['status_history'] ) ) : ?>
					<p>Belum ada riwayat status seleksi.</p>
				<?php else : ?>
					<?php foreach ( $tracking['status_history'] as $status ) : ?>
						<article class="daw-recruitment__tracking-step">
							<div>
								<strong><?= esc_html( (string) ( $status['TAHAP_SELEKSI'] ?? 'Seleksi' ) ) ?></strong>
								<small><?= esc_html( ! empty( $status['TANGGAL_STATUS'] ) ? date_i18n( 'd F Y', strtotime( (string) $status['TANGGAL_STATUS'] ) ) : 'Tanggal belum tersedia' ) ?></small>
							</div>
							<b><?= esc_html( (string) ( $status['STATUS_SELEKSI'] ?? 'Dalam proses' ) ) ?></b>
						</article>
					<?php endforeach; ?>
				<?php endif; ?>
			</section>
			<a class="daw-recruitment__tracking-back" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">&larr; Kembali ke Karier</a>
		</div>
	</main>
	<?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
