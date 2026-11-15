<?php
$schedule_type = 'interview-schedule-user' === ( $page ?? '' ) ? 'USER' : 'HR';
$schedule_title = 'USER' === $schedule_type ? 'Jadwal Wawancara User' : 'Jadwal Wawancara HR';
$schedule_page = 'USER' === $schedule_type ? 'interview-schedule-user' : 'interview-schedule';
$hr_identity = recruitment_get_current_hr_identity();
$display_name = $hr_identity['name'];
$user_email = $hr_identity['email'] ?: 'hr@daw.co.id';
$logout_form = recruitment_logout_form( 'Keluar', home_url( '/' ), 'daw-user-interview__logout' );
$requested_month = isset( $_GET['month'] ) && is_string( $_GET['month'] )
	? sanitize_text_field( wp_unslash( $_GET['month'] ) )
	: '';
$month_start = DateTimeImmutable::createFromFormat( '!Y-m', $requested_month );
$month_errors = DateTimeImmutable::getLastErrors();
if (
	! $month_start instanceof DateTimeImmutable
	|| $month_start->format( 'Y-m' ) !== $requested_month
	|| ( false !== $month_errors && ( 0 < $month_errors['warning_count'] || 0 < $month_errors['error_count'] ) )
) {
	$month_start = DateTimeImmutable::createFromFormat( '!Y-m', current_time( 'Y-m' ) );
}
$month_end = $month_start->modify( 'first day of next month' );
$calendar_days = array_merge(
	array_fill( 0, (int) $month_start->format( 'w' ), null ),
	range( 1, (int) $month_start->format( 't' ) )
);
while ( 0 !== count( $calendar_days ) % 7 ) {
	$calendar_days[] = null;
}
$database = new Recruitment_Database();
$schedule_rows = $database->get_interview_schedules(
	$schedule_type,
	$month_start->format( 'Y-m-d' ),
	$month_end->format( 'Y-m-d' )
);
$schedule_query_error = $database->has_error();
$booking_rows = $database->get_interview_schedule_bookings(
	$schedule_type,
	$month_start->format( 'Y-m-d' ),
	$month_end->format( 'Y-m-d' )
);
$schedule_error = $schedule_query_error || $database->has_error();
$schedules_by_date = [];
foreach ( $schedule_rows as $schedule_row ) {
	$schedules_by_date[ (string) $schedule_row['schedule_date'] ][] = $schedule_row;
}
$bookings_by_schedule_id = [];
foreach ( $booking_rows as $booking_row ) {
	$bookings_by_schedule_id[ (string) $booking_row['schedule_id'] ][] = $booking_row;
}
$month_title = wp_date( 'F Y', $month_start->getTimestamp() );
$schedule_url = static function ( DateTimeImmutable $month ) use ( $schedule_page ): string {
	return add_query_arg(
		[ 'page' => 'recruitment-' . $schedule_page, 'month' => $month->format( 'Y-m' ) ],
		admin_url( 'admin.php' )
	);
};
?>
<div class="daw-user-interview daw-interview-schedule" data-schedule-type="<?= esc_attr( $schedule_type ) ?>" data-ajax-url="<?= esc_url( admin_url( 'admin-ajax.php' ) ) ?>" data-schedule-nonce="<?= esc_attr( wp_create_nonce( 'daw_save_interview_schedule' ) ) ?>" data-month-year="<?= esc_attr( $month_start->format( 'Y' ) ) ?>" data-month-number="<?= esc_attr( $month_start->format( 'n' ) ) ?>">
	<header class="daw-user-interview__header">
		<nav class="daw-user-interview__breadcrumb" aria-label="Breadcrumb">
			<span class="daw-user-interview__breadcrumb-root">DAW Admin</span>
			<span aria-hidden="true">/</span>
			<strong><?= esc_html( $schedule_title ) ?></strong>
		</nav>
		<div class="daw-user-interview__account">
			<span class="daw-user-interview__avatar" aria-hidden="true"><?= esc_html( $hr_identity['avatar'] ) ?></span>
			<span class="daw-user-interview__account-name"><strong><?= esc_html( $display_name ) ?></strong><small><?= esc_html( $user_email ) ?></small></span>
			<?= $logout_form ?>
		</div>
	</header>

	<main class="daw-schedule__main">
		<nav class="daw-schedule__type-switch" aria-label="Jenis jadwal wawancara">
			<a href="<?= esc_url( recruitment_get_admin_url( 'interview-schedule' ) ) ?>" class="<?= 'HR' === $schedule_type ? 'is-active' : '' ?>" <?= 'HR' === $schedule_type ? 'aria-current="page"' : '' ?>>Jadwal Wawancara HR</a>
			<a href="<?= esc_url( recruitment_get_admin_url( 'interview-schedule-user' ) ) ?>" class="<?= 'USER' === $schedule_type ? 'is-active' : '' ?>" <?= 'USER' === $schedule_type ? 'aria-current="page"' : '' ?>>Jadwal Wawancara User</a>
		</nav>
		<?php if ( $schedule_error ) : ?><div class="notice notice-error" role="alert"><p>Jadwal belum dapat dimuat dari Oracle. Pastikan migrasi 018, 019, dan 020 sudah diterapkan serta koneksi database tersedia.</p></div><?php endif; ?>
		<div class="daw-schedule__intro">
			<div>
				<h1><?= esc_html( $month_title ) ?></h1>
				<p>Pilih tanggal untuk <?= esc_html( strtolower( $schedule_title ) ) ?>, lalu atur slot waktunya.</p>
			</div>
			<nav class="daw-schedule__month-nav" aria-label="Navigasi bulan">
				<a href="<?= esc_url( $schedule_url( $month_start->modify( 'first day of previous month' ) ) ) ?>" aria-label="Bulan sebelumnya">&lsaquo;</a>
				<a href="<?= esc_url( $schedule_url( $month_start->modify( 'first day of next month' ) ) ) ?>" aria-label="Bulan berikutnya">&rsaquo;</a>
			</nav>
			<div class="daw-schedule__legend" aria-label="Status jadwal">
				<span><i class="is-available" aria-hidden="true"></i>Tersedia</span>
				<span><i class="is-partial" aria-hidden="true"></i>Jadwal dibuat</span>
				<span><i class="is-selected" aria-hidden="true"></i>Dipilih</span>
			</div>
		</div>

		<div class="daw-schedule__layout">
			<section class="daw-schedule__calendar" aria-label="Kalender <?= esc_attr( $month_title ) ?>">
				<div class="daw-schedule__weekdays" aria-hidden="true">
					<?php foreach ( [ 'Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab' ] as $weekday ) : ?>
						<span><?= esc_html( $weekday ) ?></span>
					<?php endforeach; ?>
				</div>
				<div class="daw-schedule__days">
					<?php foreach ( $calendar_days as $day ) : ?>
						<div class="daw-schedule__cell">
							<?php if ( null === $day ) : ?>
								<span class="daw-schedule__blank" aria-hidden="true"></span>
							<?php else :
								$date = $month_start->format( 'Y-m-' ) . str_pad( (string) $day, 2, '0', STR_PAD_LEFT );
								$schedule = $schedules_by_date[ $date ] ?? [];
								$is_selected = false;
								$date_classes = [ 'daw-schedule__date' ];
								if ( ! empty( $schedule ) ) {
									$date_classes[] = 'is-partial';
								}
								$aria_label = wp_date( 'j F Y', strtotime( $date ) );
								$aria_label .= empty( $schedule ) ? ', belum ada jadwal' : ', jadwal tersedia';
								?>
								<button
									class="<?= esc_attr( implode( ' ', $date_classes ) ) ?>"
									type="button"
									data-schedule-date="<?= esc_attr( $date ) ?>"
									aria-pressed="<?= $is_selected ? 'true' : 'false' ?>"
									aria-label="<?= esc_attr( $aria_label ) ?>"
								><?= esc_html( $day ) ?></button>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
				<footer class="daw-schedule__calendar-footer">
					<div class="daw-schedule__selection-summary" aria-live="polite">
						<strong><span data-selected-count>0</span> tanggal dipilih</strong>
						<small data-selected-dates>Belum ada tanggal dipilih</small>
					</div>
					<div class="daw-schedule__calendar-actions">
						<button class="daw-schedule__button daw-schedule__button--secondary" type="button" data-reset-selection>Reset</button>
						<button class="daw-schedule__button daw-schedule__button--primary" type="button" data-open-config>Atur Slot Waktu <span aria-hidden="true">›</span></button>
					</div>
				</footer>
				<p class="daw-schedule__feedback" data-schedule-feedback role="status" hidden></p>
			</section>

			<aside class="daw-schedule__side">
				<section class="daw-schedule__config" data-schedule-config hidden>
					<header class="daw-schedule__config-header">
						<div>
							<h2>Konfigurasi Slot Waktu</h2>
							<p data-config-dates></p>
						</div>
						<button class="daw-schedule__text-button" type="button" data-close-config>← Ubah</button>
					</header>
					<div class="daw-schedule__slot-list" data-slot-list>
						<article class="daw-schedule__slot" data-schedule-slot>
							<header><strong data-slot-title>Slot 1</strong><button class="daw-schedule__text-button" type="button" data-remove-slot>Hapus</button></header>
							<div class="daw-schedule__time-fields">
								<label>Mulai<input type="time" value="09:00" data-slot-start required></label>
								<label>Selesai<input type="time" value="09:30" data-slot-end required></label>
							</div>
							<div class="daw-schedule__mode" role="group" aria-label="Metode wawancara">
								<button class="is-active" type="button" data-slot-mode="online" aria-pressed="true">ONLINE</button>
								<button type="button" data-slot-mode="offline" aria-pressed="false">OFFLINE</button>
							</div>
							<div class="daw-schedule__slot-online" data-slot-online>
								<label><span class="screen-reader-text">Platform wawancara</span><input type="text" value="Google Meet" placeholder="Platform wawancara" data-slot-platform></label>
								<label><span class="screen-reader-text">Link wawancara</span><input type="url" placeholder="Link wawancara" data-slot-link></label>
							</div>
							<label class="daw-schedule__slot-location" data-slot-location hidden>Lokasi wawancara<input type="text" placeholder="Masukkan lokasi wawancara" data-slot-location-value></label>
							<label class="daw-schedule__capacity">Kapasitas per slot<input type="number" min="1" max="100" value="1" data-slot-capacity></label>
							<label>Pewawancara <b>*</b><input type="text" maxlength="100" placeholder="Nama pewawancara" data-slot-interviewer required></label>
						</article>
					</div>
					<div class="daw-schedule__config-add">
						<button class="daw-schedule__button daw-schedule__button--secondary" type="button" data-add-slot>+ Tambah Slot Waktu</button>
					</div>
					<footer class="daw-schedule__config-footer">
						<button class="daw-schedule__button daw-schedule__button--primary" type="button" data-save-schedule>Simpan Jadwal untuk <span data-save-date-count>0</span> Tanggal</button>
					</footer>
				</section>

				<section class="daw-schedule__saved" aria-label="Jadwal tersimpan">
					<header class="daw-schedule__saved-heading"><h2>Jadwal Tersimpan</h2><span data-saved-count><?= esc_html( count( $schedules_by_date ) ) ?> tanggal</span></header>
					<div data-saved-entries>
						<?php foreach ( $schedules_by_date as $date => $schedule_slots ) :
							$capacity = array_sum( array_map( static fn ( array $slot ): int => (int) $slot['capacity'], $schedule_slots ) );
							$booked_count = array_sum( array_map( static fn ( array $slot ): int => (int) $slot['booked_count'], $schedule_slots ) );
							$completed_count = count( array_filter( $schedule_slots, static fn ( array $slot ): bool => ! empty( $slot['is_complete'] ) ) );
							$all_slots_complete = $completed_count === count( $schedule_slots );
							$edit_slots = array_map(
								static function ( array $slot ): array {
									return [
										'schedule_id' => (string) $slot['id'],
										'start' => substr( (string) $slot['start_time'], 0, 5 ),
										'end' => substr( (string) $slot['end_time'], 0, 5 ),
										'method' => (string) $slot['method'],
										'platform' => (string) ( $slot['platform'] ?? '' ),
										'meeting_link' => (string) ( $slot['meeting_link'] ?? '' ),
										'location' => (string) ( $slot['location'] ?? '' ),
										'capacity' => (int) $slot['capacity'],
										'interviewer_name' => (string) ( $slot['interviewer_name'] ?? '' ),
										'booked_count' => (int) $slot['booked_count'],
									];
								},
								$schedule_slots
							);
							?>
							<details class="daw-schedule__entry" data-saved-day="<?= esc_attr( $date ) ?>" data-booked="<?= esc_attr( $booked_count ) ?>" data-capacity="<?= esc_attr( $capacity ) ?>">
								<summary>
									<span class="daw-schedule__entry-date"><strong><?= esc_html( wp_date( 'j F Y', strtotime( $date ) ) ) ?></strong><small><?= esc_html( count( $schedule_slots ) . ' slot · kapasitas ' . $capacity ) ?></small></span>
									<span class="daw-schedule__entry-status <?= $all_slots_complete ? 'is-full' : ( 0 < $booked_count ? 'is-partial' : 'is-available' ) ?>"><?= $all_slots_complete ? 'Selesai' : ( 0 < $completed_count ? 'Sebagian selesai' : ( 0 < $booked_count ? 'Ada pemesanan' : 'Tersedia' ) ) ?></span>
									<span class="daw-schedule__chevron" aria-hidden="true"></span>
								</summary>
								<div class="daw-schedule__entry-details">
									<?php foreach ( $schedule_slots as $slot_index => $slot ) :
										$schedule_bookings = $bookings_by_schedule_id[ (string) $slot['id'] ] ?? [];
										?>
										<div class="daw-schedule__slot-summary">
											<p><strong><?= esc_html( $slot['start_time'] . '–' . $slot['end_time'] ) ?></strong> · <?= esc_html( 'ONLINE' === $slot['method'] ? (string) $slot['platform'] : (string) $slot['location'] ) ?> · <?= esc_html( (string) $slot['booked_count'] . '/' . (string) $slot['capacity'] . ' peserta' ) ?> · <?= esc_html( ! empty( $slot['is_complete'] ) ? 'Selesai' : 'Aktif' ) ?></p>
											<p>Pewawancara: <?= esc_html( '' !== trim( (string) ( $slot['interviewer_name'] ?? '' ) ) ? (string) $slot['interviewer_name'] : 'Belum diisi' ) ?><?php if ( ! empty( $slot['meeting_link'] ) ) : ?> · <a href="<?= esc_url( $slot['meeting_link'] ) ?>" target="_blank" rel="noopener noreferrer">Buka link</a><?php endif; ?></p>
											<?php if ( empty( $schedule_bookings ) ) : ?>
												<p>Belum ada peserta yang memesan slot ini.</p>
											<?php else : ?>
												<ul>
													<?php foreach ( $schedule_bookings as $booking ) : ?>
														<li><?= esc_html( (string) $booking['candidate_name'] ) ?> — <?= esc_html( ! empty( $booking['result'] ) ? (string) $booking['result'] : 'Menunggu hasil wawancara' ) ?></li>
													<?php endforeach; ?>
												</ul>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
									<button class="daw-schedule__button daw-schedule__button--secondary" type="button" data-edit-schedule="<?= esc_attr( wp_json_encode( $edit_slots ) ) ?>" data-edit-date="<?= esc_attr( $date ) ?>">Edit jadwal tanggal ini</button>
								</div>
							</details>
						<?php endforeach; ?>
						<?php if ( empty( $schedules_by_date ) && ! $schedule_error ) : ?><p>Belum ada jadwal untuk bulan ini.</p><?php endif; ?>
					</div>
				</section>
			</aside>
		</div>
	</main>

	<template data-schedule-slot-template>
		<article class="daw-schedule__slot" data-schedule-slot>
			<header><strong data-slot-title></strong><button class="daw-schedule__text-button" type="button" data-remove-slot>Hapus</button></header>
			<div class="daw-schedule__time-fields">
				<label>Mulai<input type="time" value="10:00" data-slot-start required></label>
				<label>Selesai<input type="time" value="10:30" data-slot-end required></label>
			</div>
			<div class="daw-schedule__mode" role="group" aria-label="Metode wawancara">
				<button class="is-active" type="button" data-slot-mode="online" aria-pressed="true">ONLINE</button>
				<button type="button" data-slot-mode="offline" aria-pressed="false">OFFLINE</button>
			</div>
			<div class="daw-schedule__slot-online" data-slot-online>
				<label><span class="screen-reader-text">Platform wawancara</span><input type="text" value="Google Meet" placeholder="Platform wawancara" data-slot-platform></label>
				<label><span class="screen-reader-text">Link wawancara</span><input type="url" placeholder="Link wawancara" data-slot-link></label>
			</div>
			<label class="daw-schedule__slot-location" data-slot-location hidden>Lokasi wawancara<input type="text" placeholder="Masukkan lokasi wawancara" data-slot-location-value></label>
			<label class="daw-schedule__capacity">Kapasitas per slot<input type="number" min="1" max="100" value="1" data-slot-capacity required></label>
			<label>Pewawancara <b>*</b><input type="text" maxlength="100" placeholder="Nama pewawancara" data-slot-interviewer required></label>
		</article>
	</template>
</div>
