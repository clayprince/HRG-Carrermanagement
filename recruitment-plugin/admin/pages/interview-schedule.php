<?php
$user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
$display_name = $user && ! empty( $user->display_name ) ? $user->display_name : 'Admin HR';
$user_email = $user && ! empty( $user->user_email ) ? $user->user_email : 'hr@daw.co.id';
$logout_form = recruitment_logout_form( 'Keluar', home_url( '/' ), 'daw-user-interview__logout' );
$schedule_days = [
	21 => [ 'status' => 'Penuh', 'class' => 'is-full', 'slots' => 3, 'booked' => 3 ],
	22 => [ 'status' => 'Penuh', 'class' => 'is-full', 'slots' => 3, 'booked' => 3 ],
	25 => [ 'status' => 'Sebagian', 'class' => 'is-partial', 'slots' => 2, 'booked' => 1 ],
	26 => [ 'status' => 'Penuh', 'class' => 'is-full', 'slots' => 2, 'booked' => 2 ],
];
$calendar_days = array_merge( array_fill( 0, 2, null ), range( 1, 30 ) );
while ( 0 !== count( $calendar_days ) % 7 ) {
	$calendar_days[] = null;
}
?>
<div class="daw-user-interview daw-interview-schedule">
	<header class="daw-user-interview__header">
		<nav class="daw-user-interview__breadcrumb" aria-label="Breadcrumb">
			<span class="daw-user-interview__breadcrumb-root">DAW Admin</span>
			<span aria-hidden="true">/</span>
			<strong>Jadwal Wawancara</strong>
		</nav>
		<div class="daw-user-interview__account">
			<span class="daw-user-interview__avatar" aria-hidden="true">HR</span>
			<span class="daw-user-interview__account-name"><strong><?= esc_html( $display_name ) ?></strong><small><?= esc_html( $user_email ) ?></small></span>
			<?= $logout_form ?>
		</div>
	</header>

	<main class="daw-schedule__main">
		<div class="daw-schedule__intro">
			<div>
				<h1>September 2026</h1>
				<p>Klik tanggal untuk memilih, lalu atur slot waktu wawancara secara massal.</p>
			</div>
			<div class="daw-schedule__legend" aria-label="Status jadwal">
				<span><i class="is-available" aria-hidden="true"></i>Tersedia</span>
				<span><i class="is-partial" aria-hidden="true"></i>Sebagian</span>
				<span><i class="is-full" aria-hidden="true"></i>Penuh</span>
				<span><i class="is-selected" aria-hidden="true"></i>Dipilih</span>
			</div>
		</div>

		<div class="daw-schedule__layout">
			<section class="daw-schedule__calendar" aria-label="Kalender September 2026">
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
								$schedule = $schedule_days[ $day ] ?? null;
								$is_full = $schedule && 'is-full' === $schedule['class'];
								$is_selected = in_array( $day, [ 17, 18 ], true );
								$date_classes = [ 'daw-schedule__date' ];
								if ( $schedule ) {
									$date_classes[] = $schedule['class'];
								}
								if ( $is_selected ) {
									$date_classes[] = 'is-selected';
								}
								$aria_label = $day . ' September 2026';
								if ( $schedule ) {
									$aria_label .= ', ' . $schedule['status'] . ', ' . $schedule['booked'] . ' dari ' . $schedule['slots'] . ' slot terisi';
								}
								?>
								<button
									class="<?= esc_attr( implode( ' ', $date_classes ) ) ?>"
									type="button"
									data-schedule-date="<?= esc_attr( $day ) ?>"
									aria-pressed="<?= $is_selected ? 'true' : 'false' ?>"
									aria-label="<?= esc_attr( $aria_label ) ?>"
									<?= $is_full ? 'disabled' : '' ?>
								><?= esc_html( $day ) ?></button>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
				<footer class="daw-schedule__calendar-footer">
					<div class="daw-schedule__selection-summary" aria-live="polite">
						<strong><span data-selected-count>2</span> tanggal dipilih</strong>
						<small data-selected-dates>17 Sep · 18 Sep</small>
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
								<label>Mulai<input type="time" value="09:00" data-slot-start></label>
								<label>Selesai<input type="time" value="09:30" data-slot-end></label>
							</div>
							<div class="daw-schedule__mode" role="group" aria-label="Metode wawancara">
								<button class="is-active" type="button" data-slot-mode="online" aria-pressed="true">ONLINE</button>
								<button type="button" data-slot-mode="offline" aria-pressed="false">OFFLINE</button>
							</div>
							<div class="daw-schedule__slot-online" data-slot-online>
								<label><span class="screen-reader-text">Platform wawancara</span><input type="text" value="Google Meet" placeholder="Platform wawancara" data-slot-platform></label>
								<label><span class="screen-reader-text">Link wawancara</span><input type="url" value="https://meet.google.com/xxx" placeholder="Link wawancara" data-slot-link></label>
							</div>
							<label class="daw-schedule__slot-location" data-slot-location hidden>Lokasi wawancara<input type="text" placeholder="Masukkan lokasi wawancara" data-slot-location-value></label>
							<label class="daw-schedule__capacity">Kapasitas per slot<input type="number" min="1" max="100" value="1" data-slot-capacity></label>
						</article>
						<article class="daw-schedule__slot" data-schedule-slot>
							<header><strong data-slot-title>Slot 2</strong><button class="daw-schedule__text-button" type="button" data-remove-slot>Hapus</button></header>
							<div class="daw-schedule__time-fields">
								<label>Mulai<input type="time" value="09:30" data-slot-start></label>
								<label>Selesai<input type="time" value="10:00" data-slot-end></label>
							</div>
							<div class="daw-schedule__mode" role="group" aria-label="Metode wawancara">
								<button class="is-active" type="button" data-slot-mode="online" aria-pressed="true">ONLINE</button>
								<button type="button" data-slot-mode="offline" aria-pressed="false">OFFLINE</button>
							</div>
							<div class="daw-schedule__slot-online" data-slot-online>
								<label><span class="screen-reader-text">Platform wawancara</span><input type="text" value="Google Meet" placeholder="Platform wawancara" data-slot-platform></label>
								<label><span class="screen-reader-text">Link wawancara</span><input type="url" value="https://meet.google.com/xxx" placeholder="Link wawancara" data-slot-link></label>
							</div>
							<label class="daw-schedule__slot-location" data-slot-location hidden>Lokasi wawancara<input type="text" placeholder="Masukkan lokasi wawancara" data-slot-location-value></label>
							<label class="daw-schedule__capacity">Kapasitas per slot<input type="number" min="1" max="100" value="1" data-slot-capacity></label>
						</article>
					</div>
					<div class="daw-schedule__config-add">
						<button class="daw-schedule__button daw-schedule__button--secondary" type="button" data-add-slot>+ Tambah Slot Waktu</button>
					</div>
					<footer class="daw-schedule__config-footer">
						<button class="daw-schedule__button daw-schedule__button--primary" type="button" data-save-schedule>Simpan Jadwal untuk <span data-save-date-count>2</span> Tanggal</button>
					</footer>
				</section>

				<section class="daw-schedule__saved" aria-label="Jadwal tersimpan">
					<header class="daw-schedule__saved-heading"><h2>Jadwal Tersimpan</h2><span data-saved-count><?= esc_html( count( $schedule_days ) ) ?> tanggal</span></header>
					<div data-saved-entries>
						<?php foreach ( $schedule_days as $day => $schedule ) : ?>
							<details class="daw-schedule__entry" data-saved-day="<?= esc_attr( $day ) ?>" data-booked="<?= esc_attr( $schedule['booked'] ) ?>" data-capacity="<?= esc_attr( $schedule['slots'] ) ?>">
								<summary>
									<span class="daw-schedule__entry-date"><strong><?= esc_html( $day . ' Sep 2026' ) ?></strong><small><?= esc_html( $schedule['slots'] . ' slot · ' . $schedule['booked'] . '/' . $schedule['slots'] . ' terisi' ) ?></small></span>
									<span class="daw-schedule__entry-status <?= esc_attr( $schedule['class'] ) ?>"><?= esc_html( $schedule['status'] ) ?></span>
									<span class="daw-schedule__chevron" aria-hidden="true"></span>
								</summary>
								<div class="daw-schedule__entry-details"><p><?= esc_html( $schedule['booked'] . ' dari ' . $schedule['slots'] . ' slot wawancara telah terisi.' ) ?></p></div>
							</details>
						<?php endforeach; ?>
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
		</article>
	</template>
</div>
