<?php
$settings_section = sanitize_key( $_GET['section'] ?? 'email' );
$settings_sections = [
    'email' => [ 'title' => 'Email Recruitment', 'message' => 'Pengaturan email Recruitment belum tersedia.' ],
    'psychotest' => [ 'title' => 'Setting Psikotes', 'message' => 'Pengaturan psikotes belum tersedia.' ],
    'question-bank' => [ 'title' => 'Bank Soal', 'message' => 'Bank soal belum tersedia.' ],
];
$settings_page = $settings_sections[ $settings_section ] ?? [ 'title' => 'Pengaturan', 'message' => 'Pengaturan Recruitment belum tersedia.' ];
?>
<div class="wrap recruitment-admin">
    <h1><?= esc_html( $settings_page['title'] ) ?></h1>
    <p><?= esc_html( $settings_page['message'] ) ?></p>
</div>
