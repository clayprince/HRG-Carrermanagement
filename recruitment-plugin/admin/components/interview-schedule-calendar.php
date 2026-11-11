<?php
$schedule_month_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $schedule_month . '-01', wp_timezone() );
$schedule_month_date = $schedule_month_date ?: new DateTimeImmutable( 'first day of this month', wp_timezone() );
$schedule_days_in_month = (int) $schedule_month_date->format( 't' );
$schedule_first_weekday = (int) $schedule_month_date->format( 'w' );
$schedule_calendar_cells = array_merge( array_fill( 0, $schedule_first_weekday, null ), range( 1, $schedule_days_in_month ) );
while ( 0 !== count( $schedule_calendar_cells ) % 7 ) {
	$schedule_calendar_cells[] = null;
}
$schedule_slots_by_date = [];
foreach ( $schedule_slots as $schedule_slot ) {
	$schedule_slots_by_date[ (string) $schedule_slot['slot_date'] ][] = $schedule_slot;
}
$schedule_bookings_by_slot = [];
foreach ( $schedule_bookings as $schedule_booking ) {
	$schedule_bookings_by_slot[ (string) $schedule_booking['slot_id'] ][] = $schedule_booking;
}
$schedule_prev_url = add_query_arg( 'month', $schedule_month_date->modify( '-1 month' )->format( 'Y-m' ), $schedule_base_url );
$schedule_next_url = add_query_arg( 'month', $schedule_month_date->modify( '+1 month' )->format( 'Y-m' ), $schedule_base_url );
?>
<div class="daw-interview-schedule" data-interview-schedule>
	<section class="daw-schedule__main">
		<div class="daw-schedule__intro">
			<div>
				<div class="daw-schedule__month-heading">
					<h1><?= esc_html( wp_date( 'F Y', $schedule_month_date->getTimestamp() ) ) ?></h1>
					<nav class="daw-schedule__month-nav" aria-label="Navigasi bulan">
						<a href="<?= esc_url( $schedule_prev_url ) ?>" aria-label="Bulan sebelumnya">‹</a>
						<a href="<?= esc_url( $schedule_next_url ) ?>" aria-label="Bulan berikutnya">›</a>
					</nav>
				</div>
				<p>Klik tanggal untuk memilih dan mengatur slot wawancara. Waktu mengikuti zona <?= esc_html( wp_timezone_string() ) ?>.</p>
			</div>
			<div class="daw-schedule__legend" aria-label="Status jadwal">
				<span><i class="is-available" aria-hidden="true"></i>Tersedia</span>
				<span><i class="is-partial" aria-hidden="true"></i>Sebagian</span>
				<span><i class="is-full" aria-hidden="true"></i>Penuh</span>
				<span><i class="is-selected" aria-hidden="true"></i>Dipilih</span>
			</div>
		</div>

		<?php if ( is_array( $schedule_notice ) ) : ?>
			<p class="daw-schedule__notice <?= ! empty( $schedule_notice['success'] ) ? 'is-success' : 'is-error' ?>" role="status"><?= esc_html( $schedule_notice['message'] ) ?></p>
		<?php endif; ?>

		<div class="daw-schedule__layout">
			<section class="daw-schedule__calendar" aria-label="Kalender wawancara">
				<div class="daw-schedule__weekdays" aria-hidden="true">
					<?php foreach ( [ 'Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab' ] as $weekday ) : ?>
						<span><?= esc_html( $weekday ) ?></span>
					<?php endforeach; ?>
				</div>
				<div class="daw-schedule__days">
					<?php foreach ( $schedule_calendar_cells as $day ) : ?>
						<div class="daw-schedule__cell">
							<?php if ( null === $day ) : ?>
								<span class="daw-schedule__blank" aria-hidden="true"></span>
							<?php else :
								$date = $schedule_month_date->setDate(
									(int) $schedule_month_date->format( 'Y' ),
									(int) $schedule_month_date->format( 'm' ),
									$day
								)->format( 'Y-m-d' );
								$day_slots = $schedule_slots_by_date[ $date ] ?? [];
								$day_capacity = array_sum( array_map( static fn ( array $slot ): int => (int) $slot['capacity'], $day_slots ) );
								$day_booked = array_sum( array_map( static fn ( array $slot ): int => (int) $slot['booked'], $day_slots ) );
								$is_full = $day_capacity > 0 && $day_booked >= $day_capacity;
								$date_classes = [ 'daw-schedule__date' ];
								if ( $day_booked > 0 ) {
									$date_classes[] = $is_full ? 'is-full' : 'is-partial';
								}
								$day_date = $schedule_month_date->setDate(
									(int) $schedule_month_date->format( 'Y' ),
									(int) $schedule_month_date->format( 'm' ),
									$day
								);
								$is_past = $day_date->setTime( 23, 59 ) < new DateTimeImmutable( 'now', wp_timezone() );
								$aria_label = wp_date( 'j F Y', $day_date->getTimestamp() );
								if ( $day_capacity > 0 ) {
									$aria_label .= ', ' . $day_booked . ' dari ' . $day_capacity . ' kapasitas terisi';
								}
								?>
								<button
									class="<?= esc_attr( implode( ' ', $date_classes ) ) ?>"
									type="button"
									data-schedule-date="<?= esc_attr( $date ) ?>"
									data-schedule-label="<?= esc_attr( wp_date( 'j M', $day_date->getTimestamp() ) ) ?>"
									aria-pressed="false"
									aria-label="<?= esc_attr( $aria_label ) ?>"
									<?= $is_past ? 'disabled' : '' ?>
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
						<button class="daw-schedule__button daw-schedule__button--primary" type="button" data-open-config disabled>Atur Slot Waktu <span aria-hidden="true">›</span></button>
					</div>
				</footer>
				<p class="daw-schedule__feedback" data-schedule-feedback role="status" hidden></p>
			</section>

			<aside class="daw-schedule__side">
				<form class="daw-schedule__config" data-schedule-config data-schedule-form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" hidden>
					<input type="hidden" name="action" value="daw_save_interview_schedule">
					<?php wp_nonce_field( 'daw_save_interview_schedule' ); ?>
					<input type="hidden" name="schedule_type" value="<?= esc_attr( $schedule_type ) ?>">
					<input type="hidden" name="month" value="<?= esc_attr( $schedule_month ) ?>">
					<input type="hidden" name="dates_json" value="" data-schedule-dates>
					<input type="hidden" name="slots_json" value="" data-schedule-slots>
					<header class="daw-schedule__config-header">
						<div>
							<h2>Konfigurasi Slot Waktu</h2>
							<p data-config-dates></p>
						</div>
						<button class="daw-schedule__text-button" type="button" data-close-config>← Ubah</button>
					</header>
					<div class="daw-schedule__slot-list" data-slot-list>
						<article class="daw-schedule__slot" data-schedule-slot>
							<header><strong data-slot-title>Slot 1</strong><button class="daw-schedule__text-button" type="button" data-remove-slot disabled>Hapus</button></header>
							<div class="daw-schedule__time-fields">
								<label>Mulai<input type="time" value="09:00" data-slot-start required></label>
								<label>Selesai<input type="time" value="09:30" data-slot-end required></label>
							</div>
							<div class="daw-schedule__mode" role="group" aria-label="Metode wawancara">
								<button class="is-active" type="button" data-slot-mode="ONLINE" aria-pressed="true">ONLINE</button>
								<button type="button" data-slot-mode="OFFLINE" aria-pressed="false">OFFLINE</button>
							</div>
							<div class="daw-schedule__slot-online" data-slot-online>
								<label><span class="screen-reader-text">Platform wawancara</span><input type="text" value="Google Meet" placeholder="Platform wawancara" data-slot-platform required></label>
								<label><span class="screen-reader-text">Link wawancara</span><input type="url" placeholder="Link wawancara" data-slot-link required></label>
							</div>
							<label class="daw-schedule__slot-location" data-slot-location hidden>Lokasi wawancara<input type="text" placeholder="Masukkan lokasi wawancara" data-slot-location-value></label>
							<label class="daw-schedule__capacity">Kapasitas per slot<input type="number" min="1" max="100" value="1" data-slot-capacity required></label>
							<label class="daw-schedule__capacity">Pewawancara<input type="text" maxlength="255" data-slot-interviewer></label>
						</article>
					</div>
					<div class="daw-schedule__config-add">
						<button class="daw-schedule__button daw-schedule__button--secondary" type="button" data-add-slot>+ Tambah Slot Waktu</button>
					</div>
					<footer class="daw-schedule__config-footer">
						<button class="daw-schedule__button daw-schedule__button--primary" type="button" data-save-schedule <?= empty( $schedule_enabled ) ? 'disabled' : '' ?>>Simpan Jadwal untuk <span data-save-date-count>0</span> Tanggal</button>
					</footer>
				</form>

				<section class="daw-schedule__saved" aria-label="Jadwal tersimpan">
					<header class="daw-schedule__saved-heading"><h2>Jadwal Tersimpan</h2><span><?= esc_html( count( $schedule_slots_by_date ) ) ?> tanggal</span></header>
					<div data-saved-entries>
						<?php if ( empty( $schedule_slots_by_date ) ) : ?>
							<p class="daw-schedule__empty">Belum ada jadwal pada bulan ini.</p>
						<?php endif; ?>
						<?php foreach ( $schedule_slots_by_date as $date => $day_slots ) :
							$day_capacity = array_sum( array_map( static fn ( array $slot ): int => (int) $slot['capacity'], $day_slots ) );
							$day_booked = array_sum( array_map( static fn ( array $slot ): int => (int) $slot['booked'], $day_slots ) );
							$is_full = $day_capacity > 0 && $day_booked >= $day_capacity;
							$status_class = $is_full ? 'is-full' : ( $day_booked > 0 ? 'is-partial' : 'is-available' );
							$saved_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
							?>
							<details class="daw-schedule__entry" data-saved-day="<?= esc_attr( $date ) ?>" data-booked="<?= esc_attr( $day_booked ) ?>" data-capacity="<?= esc_attr( $day_capacity ) ?>">
								<summary>
									<span class="daw-schedule__entry-date"><strong><?= esc_html( $saved_date ? wp_date( 'j M Y', $saved_date->getTimestamp() ) : $date ) ?></strong><small><?= esc_html( count( $day_slots ) . ' slot · ' . $day_booked . '/' . $day_capacity . ' terisi' ) ?></small></span>
									<span class="daw-schedule__entry-status <?= esc_attr( $status_class ) ?>"><?= esc_html( $is_full ? 'Penuh' : ( $day_booked > 0 ? 'Sebagian' : 'Tersedia' ) ) ?></span>
									<span class="daw-schedule__chevron" aria-hidden="true"></span>
								</summary>
								<div class="daw-schedule__entry-details">
									<?php foreach ( $day_slots as $slot ) :
										$slot_is_booked = 0 < (int) $slot['booked'];
										$slot_bookings = $schedule_bookings_by_slot[ (string) $slot['slot_id'] ] ?? [];
										?>
										<details class="daw-schedule__slot-details">
											<summary><?= esc_html( $slot['start_time'] . '–' . $slot['end_time'] . ' · ' . $slot['method'] . ' · ' . $slot['booked'] . '/' . $slot['capacity'] ) ?></summary>
											<?php if ( $slot_is_booked ) : ?>
												<ul class="daw-schedule__booking-list">
													<?php foreach ( $slot_bookings as $slot_booking ) : ?>
														<li><?= esc_html( $slot_booking['candidate_name'] . ' · Lamaran ' . $slot_booking['application_id'] ) ?></li>
													<?php endforeach; ?>
												</ul>
												<p>Slot sudah dipesan dan tidak dapat diubah atau dihapus.</p>
											<?php else : ?>
												<form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" class="daw-schedule__edit-form">
													<input type="hidden" name="action" value="daw_save_interview_schedule">
													<?php wp_nonce_field( 'daw_save_interview_schedule' ); ?>
													<input type="hidden" name="schedule_type" value="<?= esc_attr( $schedule_type ) ?>">
													<input type="hidden" name="month" value="<?= esc_attr( $schedule_month ) ?>">
													<input type="hidden" name="slot_id" value="<?= esc_attr( $slot['slot_id'] ) ?>">
													<div class="daw-schedule__edit-grid">
														<label>Tanggal<input type="date" name="date" value="<?= esc_attr( $slot['slot_date'] ) ?>" required></label>
														<label>Mulai<input type="time" name="start" value="<?= esc_attr( $slot['start_time'] ) ?>" required></label>
														<label>Selesai<input type="time" name="end" value="<?= esc_attr( $slot['end_time'] ) ?>" required></label>
														<label>Metode<select name="mode"><option value="ONLINE" <?= selected( 'ONLINE', $slot['method'], false ) ?>>Online</option><option value="OFFLINE" <?= selected( 'OFFLINE', $slot['method'], false ) ?>>Offline</option></select></label>
														<label>Kapasitas<input type="number" name="capacity" min="1" max="100" value="<?= esc_attr( $slot['capacity'] ) ?>" required></label>
														<label>Platform<input type="text" name="platform" value="<?= esc_attr( 'ONLINE' === $slot['method'] ? $slot['location'] : '' ) ?>"></label>
														<label>Link online<input type="url" name="meeting_url" value="<?= esc_attr( $slot['meeting_url'] ) ?>"></label>
														<label>Lokasi<input type="text" name="location" value="<?= esc_attr( 'OFFLINE' === $slot['method'] ? $slot['location'] : '' ) ?>"></label>
														<label>Pewawancara<input type="text" name="interviewer" value="<?= esc_attr( $slot['interviewer'] ) ?>"></label>
													</div>
													<button class="daw-schedule__button daw-schedule__button--primary" type="submit">Simpan Perubahan</button>
												</form>
												<form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" class="daw-schedule__delete-form" data-confirm="Hapus slot wawancara ini?">
													<input type="hidden" name="action" value="daw_delete_interview_slot">
													<?php wp_nonce_field( 'daw_delete_interview_slot_' . $slot['slot_id'] ); ?>
													<input type="hidden" name="schedule_type" value="<?= esc_attr( $schedule_type ) ?>">
													<input type="hidden" name="month" value="<?= esc_attr( $schedule_month ) ?>">
													<input type="hidden" name="slot_id" value="<?= esc_attr( $slot['slot_id'] ) ?>">
													<button class="daw-schedule__text-button" type="submit">Hapus Slot</button>
												</form>
											<?php endif; ?>
										</details>
									<?php endforeach; ?>
								</div>
							</details>
						<?php endforeach; ?>
					</div>
				</section>
			</aside>
		</div>
	</section>

	<template data-schedule-slot-template>
		<article class="daw-schedule__slot" data-schedule-slot>
			<header><strong data-slot-title></strong><button class="daw-schedule__text-button" type="button" data-remove-slot>Hapus</button></header>
			<div class="daw-schedule__time-fields">
				<label>Mulai<input type="time" value="10:00" data-slot-start required></label>
				<label>Selesai<input type="time" value="10:30" data-slot-end required></label>
			</div>
			<div class="daw-schedule__mode" role="group" aria-label="Metode wawancara">
				<button class="is-active" type="button" data-slot-mode="ONLINE" aria-pressed="true">ONLINE</button>
				<button type="button" data-slot-mode="OFFLINE" aria-pressed="false">OFFLINE</button>
			</div>
			<div class="daw-schedule__slot-online" data-slot-online>
				<label><span class="screen-reader-text">Platform wawancara</span><input type="text" value="Google Meet" placeholder="Platform wawancara" data-slot-platform required></label>
				<label><span class="screen-reader-text">Link wawancara</span><input type="url" placeholder="Link wawancara" data-slot-link required></label>
			</div>
			<label class="daw-schedule__slot-location" data-slot-location hidden>Lokasi wawancara<input type="text" placeholder="Masukkan lokasi wawancara" data-slot-location-value></label>
			<label class="daw-schedule__capacity">Kapasitas per slot<input type="number" min="1" max="100" value="1" data-slot-capacity required></label>
			<label class="daw-schedule__capacity">Pewawancara<input type="text" maxlength="255" data-slot-interviewer></label>
		</article>
	</template>
</div>
