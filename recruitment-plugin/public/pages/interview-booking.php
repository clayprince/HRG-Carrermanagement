<?php
$preview = recruitment_is_ui_preview();
$applicant = $preview ? [ 'id' => '', 'full_name' => 'Preview Pelamar' ] : recruitment_get_current_applicant();
$database = new Recruitment_Database();
$applications = $preview || empty( $applicant['id'] )
	? []
	: $database->get_applicant_interview_applications( (string) $applicant['id'] );
$load_error = ! $preview && $database->has_error();
$notice = ! $preview && ! empty( $applicant['id'] )
	? get_transient( 'daw_interview_booking_notice_' . (string) $applicant['id'] )
	: null;
if ( ! $preview && ! empty( $applicant['id'] ) ) {
	delete_transient( 'daw_interview_booking_notice_' . (string) $applicant['id'] );
}
$notice = is_array( $notice ) ? $notice : null;
$format_interview_date = static function ( string $date ): string {
	$date_value = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
	return $date_value ? wp_date( 'l, j F Y', $date_value->getTimestamp() ) : $date;
};
$interview_data = [];
if ( ! $load_error ) {
	foreach ( $applications as $application ) {
		$application_id = (string) $application['application_id'];
		$type = (string) $application['interview_type'];
		$booking = $database->get_applicant_interview_booking( (string) $applicant['id'], $application_id, $type );
		$booking_error = $database->has_error();
		$slots = $database->get_applicant_interview_slots( (string) $applicant['id'], $application_id, $type );
		$interview_data[ $application_id ] = [ 'booking' => $booking, 'slots' => $slots ];
		if ( $booking_error || $database->has_error() ) {
			$load_error = true;
			break;
		}
	}
}
?>
<div class="daw-recruitment daw-account daw-recruitment--interview-booking">
	<?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
	<main class="daw-account__main">
		<header class="daw-account__heading">
			<div>
				<span class="daw-recruitment__eyebrow">JADWAL WAWANCARA</span>
				<h1>Jadwal wawancara</h1>
				<p>Pilih satu jadwal untuk setiap lamaran yang sudah masuk tahap wawancara.</p>
			</div>
		</header>
		<?php require recruitment_get_plugin_path( 'public/components/applicant-account-nav.php' ); ?>
		<?php if ( $preview ) : ?>
			<section class="daw-account__empty"><p>Mode preview: jadwal dan data lamaran tidak ditampilkan.</p></section>
		<?php elseif ( $notice ) : ?>
			<p class="daw-interview-booking__notice <?= ! empty( $notice['success'] ) ? 'is-success' : 'is-error' ?>" role="status"><?= esc_html( $notice['message'] ) ?></p>
		<?php endif; ?>
		<?php if ( $load_error ) : ?>
			<section class="daw-account__empty" role="alert">
				<h2>Jadwal wawancara belum dapat dimuat</h2>
				<p>Layanan Oracle tidak tersedia atau migrasi scheduler belum diterapkan. Silakan coba kembali nanti.</p>
			</section>
		<?php elseif ( ! $preview && empty( $applications ) ) : ?>
			<section class="daw-account__empty">
				<h2>Belum ada jadwal yang dapat dipilih</h2>
				<p>Pilihan jadwal akan muncul setelah status lamaran Anda memasuki tahap Wawancara HR atau Wawancara User.</p>
			</section>
		<?php elseif ( ! $preview ) : ?>
			<div class="daw-interview-booking__applications">
				<?php foreach ( $applications as $application ) :
					$application_id = (string) $application['application_id'];
					$type = (string) $application['interview_type'];
					$data = $interview_data[ $application_id ] ?? [ 'booking' => null, 'slots' => [] ];
					$stage_title = 'INTERVIEW_HR' === $type ? 'Wawancara HR' : 'Wawancara User';
					?>
					<section class="daw-interview-booking__application">
						<header>
							<div><span><?= esc_html( $stage_title ) ?></span><h2><?= esc_html( (string) $application['position'] ) ?></h2></div>
							<small><?= esc_html( (string) $application['department'] ) ?></small>
						</header>
						<?php if ( $data['booking'] ) : ?>
							<?php $booking = $data['booking']; ?>
							<div class="daw-interview-booking__confirmed">
								<strong>Jadwal Anda</strong>
								<p><?= esc_html( $format_interview_date( (string) $booking['slot_date'] ) . ' · ' . $booking['start_time'] . '–' . $booking['end_time'] . ' ' . $booking['timezone'] ) ?></p>
								<p><?= esc_html( (string) $booking['method'] . ' · ' . (string) $booking['location'] ) ?></p>
								<?php if ( 'ONLINE' === strtoupper( (string) $booking['method'] ) && '' !== (string) $booking['meeting_url'] ) : ?>
									<p><a href="<?= esc_url( $booking['meeting_url'] ) ?>" target="_blank" rel="noopener noreferrer">Buka tautan wawancara</a></p>
								<?php endif; ?>
								<?php if ( '' !== (string) $booking['interviewer'] ) : ?><p>Pewawancara: <?= esc_html( $booking['interviewer'] ) ?></p><?php endif; ?>
								<small>Jadwal yang sudah dipilih tidak dapat diubah atau dibatalkan sendiri. Hubungi HR bila membutuhkan bantuan.</small>
							</div>
						<?php elseif ( empty( $data['slots'] ) ) : ?>
							<p class="daw-interview-booking__empty">Belum ada slot tersedia untuk tahap ini. Silakan periksa kembali nanti.</p>
						<?php else : ?>
							<form class="daw-interview-booking__form" method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
								<input type="hidden" name="action" value="daw_book_interview">
								<input type="hidden" name="application_id" value="<?= esc_attr( $application_id ) ?>">
								<?php wp_nonce_field( 'daw_book_interview_' . $application_id ); ?>
								<label for="interview-slot-<?= esc_attr( $application_id ) ?>">Pilih jadwal</label>
								<select id="interview-slot-<?= esc_attr( $application_id ) ?>" name="slot_id" required>
									<option value="">Pilih tanggal dan waktu</option>
									<?php foreach ( $data['slots'] as $slot ) : ?>
										<option value="<?= esc_attr( $slot['slot_id'] ) ?>">
											<?= esc_html( $format_interview_date( (string) $slot['slot_date'] ) . ' · ' . $slot['start_time'] . '–' . $slot['end_time'] . ' ' . $slot['timezone'] . ' · ' . $slot['method'] . ' · ' . $slot['booked'] . '/' . $slot['capacity'] . ' terisi' ) ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p>Setelah dikonfirmasi, jadwal tidak dapat diubah atau dibatalkan sendiri.</p>
								<button class="daw-recruitment__button" type="submit">Konfirmasi Jadwal</button>
							</form>
						<?php endif; ?>
					</section>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</main>
	<?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
