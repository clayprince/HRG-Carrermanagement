<?php
$hr_identity = recruitment_get_current_hr_identity();
$display_name = $hr_identity['name'];
$user_email = $hr_identity['email'] ?: 'hr@daw.co.id';
$logout_form = recruitment_logout_form( 'Keluar', home_url( '/' ), 'daw-user-interview__logout' );
$schedule_month = sanitize_text_field( wp_unslash( $_GET['month'] ?? wp_date( 'Y-m' ) ) );
if ( ! preg_match( '/\A\d{4}-(0[1-9]|1[0-2])\z/', $schedule_month ) ) {
	$schedule_month = wp_date( 'Y-m' );
}
$schedule_type = 'INTERVIEW_HR';
$schedule_enabled = true;
$schedule_database = new Recruitment_Database();
$schedule_slots = $schedule_database->get_interview_slots_for_month( $schedule_type, null, $schedule_month );
$schedule_load_error = $schedule_database->has_error();
$schedule_bookings = $schedule_database->get_interview_bookings_for_month( $schedule_type, null, $schedule_month );
$schedule_load_error = $schedule_load_error || $schedule_database->has_error();
$schedule_notice = get_transient( 'daw_interview_schedule_notice_' . get_current_user_id() );
delete_transient( 'daw_interview_schedule_notice_' . get_current_user_id() );
$schedule_notice = is_array( $schedule_notice ) ? $schedule_notice : null;
if ( $schedule_load_error ) {
	$schedule_notice = [ 'success' => false, 'message' => 'Data jadwal belum dapat dimuat. Pastikan migrasi scheduler telah diterapkan.' ];
}
$schedule_base_url = add_query_arg( 'page', 'recruitment-interview-schedule', admin_url( 'admin.php' ) );
?>
<div class="daw-user-interview daw-interview-schedule">
	<header class="daw-user-interview__header">
		<nav class="daw-user-interview__breadcrumb" aria-label="Breadcrumb">
			<span class="daw-user-interview__breadcrumb-root">DAW Admin</span>
			<span aria-hidden="true">/</span>
			<strong>Jadwal Wawancara</strong>
		</nav>
		<div class="daw-user-interview__account">
			<span class="daw-user-interview__avatar" aria-hidden="true"><?= esc_html( $hr_identity['avatar'] ) ?></span>
			<span class="daw-user-interview__account-name"><strong><?= esc_html( $display_name ) ?></strong><small><?= esc_html( $user_email ) ?></small></span>
			<?= $logout_form ?>
		</div>
	</header>
	<?php require recruitment_get_plugin_path( 'admin/components/interview-schedule-calendar.php' ); ?>
</div>
