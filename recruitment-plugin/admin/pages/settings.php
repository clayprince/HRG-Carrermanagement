<?php
/**
 * Admin Screen: Email Recruitment
 * Matches original UI layout with Recipient List, Compose Email, and Template Email.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$database = new Recruitment_Database();

// Retrieve applications from database to populate recipient list
$db_applications = $database->get_applications();
$candidates = [];

if ( ! empty( $db_applications ) ) {
    foreach ( $db_applications as $app ) {
        $email = sanitize_email( (string) ( $app['applicant_email'] ?? '' ) );
        if ( ! is_email( $email ) ) {
            continue;
        }

        $stage = (string) ( $app['stage'] ?? 'Administrasi' );
        $stage_labels = [
            'administration'     => 'Administrasi',
            'psychological_test' => 'Psikotes',
            'hr_interview'       => 'Wawancara HR',
            'user_interview'     => 'Wawancara User',
            'final_decision'     => 'Final',
        ];
        $stage_clean = $stage_labels[ $stage ] ?? ( '' !== $stage ? ucfirst( str_replace( '_', ' ', $stage ) ) : 'Administrasi' );

        $status = strtoupper( trim( (string) ( $app['status'] ?? 'DALAM PROSES' ) ) );
        $status_label = 'Berlangsung';
        $status_type  = 'ongoing'; // ongoing, success, danger, warning

        if ( in_array( $status, [ 'LOLOS', 'DITERIMA', 'PASSED', 'ACCEPTED' ], true ) ) {
            $status_label = 'Lolos';
            $status_type  = 'success';
        } elseif ( in_array( $status, [ 'TIDAK LOLOS', 'DITOLAK', 'FAILED', 'REJECTED' ], true ) ) {
            $status_label = 'Tidak Lolos';
            $status_type  = 'danger';
        } elseif ( in_array( $status, [ 'MENUNGGU', 'PENDING', 'WAITING' ], true ) ) {
            $status_label = 'Menunggu';
            $status_type  = 'warning';
        } else {
            $status_label = 'Berlangsung';
            $status_type  = 'ongoing';
        }

        $candidates[] = [
            'id'       => (int) ( $app['id'] ?? 0 ),
            'name'     => (string) ( $app['applicant_name'] ?? 'Pelamar' ),
            'position' => (string) ( $app['title'] ?? 'Posisi Rekrutmen' ),
            'dealer'   => (string) ( $app['branch_name'] ?? ( $app['location'] ?? 'DAW Manado' ) ),
            'email'    => $email,
            'stage'    => $stage_clean,
            'status'   => $status_label,
            'status_type' => $status_type,
        ];
    }
}

// Fallback sample candidates matching UI if database is fresh
if ( count( $candidates ) < 4 ) {
    $candidates = [
        [ 'id' => 101, 'name' => 'Gilang Permana', 'position' => 'Mekanik Senior', 'dealer' => 'DAW Bitung', 'email' => 'gilang@email.com', 'stage' => 'Wawancara HR', 'status' => 'Berlangsung', 'status_type' => 'ongoing' ],
        [ 'id' => 102, 'name' => 'Hana Wijaya', 'position' => 'Customer Service', 'dealer' => 'DAW Maumbi', 'email' => 'hana@email.com', 'stage' => 'Administrasi', 'status' => 'Tidak Lolos', 'status_type' => 'danger' ],
        [ 'id' => 103, 'name' => 'Ivan Kurniawan', 'position' => 'Finance Staff', 'dealer' => 'DAW Bitung', 'email' => 'ivan@email.com', 'stage' => 'Wawancara User', 'status' => 'Menunggu', 'status_type' => 'warning' ],
        [ 'id' => 104, 'name' => 'Julia Ramadhani', 'position' => 'Sales Executive', 'dealer' => 'DAW Maumbi', 'email' => 'julia@email.com', 'stage' => 'Final', 'status' => 'Lolos', 'status_type' => 'success' ],
        [ 'id' => 105, 'name' => 'Kevin Alexander', 'position' => 'Marketing Officer', 'dealer' => 'DAW Manado', 'email' => 'kevin@email.com', 'stage' => 'Psikotes', 'status' => 'Berlangsung', 'status_type' => 'ongoing' ],
        [ 'id' => 106, 'name' => 'Maria Natalia', 'position' => 'Staff Administrasi', 'dealer' => 'DAW Kotamobagu', 'email' => 'maria@email.com', 'stage' => 'Administrasi', 'status' => 'Lolos', 'status_type' => 'success' ],
        [ 'id' => 107, 'name' => 'Rafi Kurniawan', 'position' => 'Sales Consultant', 'dealer' => 'DAW Airmadidi', 'email' => 'rafi@email.com', 'stage' => 'Wawancara HR', 'status' => 'Berlangsung', 'status_type' => 'ongoing' ],
        [ 'id' => 108, 'name' => 'Sari Dewi Lestari', 'position' => 'Accounting Staff', 'dealer' => 'DAW Manado', 'email' => 'sari@email.com', 'stage' => 'Final', 'status' => 'Lolos', 'status_type' => 'success' ],
        [ 'id' => 109, 'name' => 'Budi Santoso', 'position' => 'Service Advisor', 'dealer' => 'DAW Bitung', 'email' => 'budi@email.com', 'stage' => 'Psikotes', 'status' => 'Menunggu', 'status_type' => 'warning' ],
        [ 'id' => 110, 'name' => 'Anisa Putri Rahma', 'position' => 'Staff HRD', 'dealer' => 'DAW Maumbi', 'email' => 'anisa@email.com', 'stage' => 'Wawancara User', 'status' => 'Berlangsung', 'status_type' => 'ongoing' ],
    ];
}

// Pre-extract unique filter options
$filter_stages    = array_unique( array_column( $candidates, 'stage' ) );
$filter_statuses  = array_unique( array_column( $candidates, 'status' ) );
$filter_positions = array_unique( array_column( $candidates, 'position' ) );
$filter_dealers   = array_unique( array_column( $candidates, 'dealer' ) );

// SMTP Settings (retained for modal)
$email_settings   = (array) get_option( 'recruitment_email_settings', [] );
$is_enabled       = ! empty( $email_settings['is_enabled'] );
$smtp_host        = (string) ( $email_settings['smtp_host'] ?? ( defined( 'DAW_SMTP_HOST' ) ? DAW_SMTP_HOST : 'smtp.gmail.com' ) );
$smtp_port        = (int) ( $email_settings['smtp_port'] ?? ( defined( 'DAW_SMTP_PORT' ) ? DAW_SMTP_PORT : 587 ) );
$smtp_encryption  = (string) ( $email_settings['smtp_encryption'] ?? ( defined( 'DAW_SMTP_ENCRYPTION' ) ? DAW_SMTP_ENCRYPTION : 'tls' ) );
$smtp_user        = (string) ( $email_settings['smtp_user'] ?? ( defined( 'DAW_SMTP_USER' ) ? DAW_SMTP_USER : '' ) );
$smtp_pass        = (string) ( $email_settings['smtp_pass'] ?? ( defined( 'DAW_SMTP_PASS' ) ? DAW_SMTP_PASS : '' ) );
$from_email       = (string) ( $email_settings['from_email'] ?? ( defined( 'DAW_SMTP_FROM_EMAIL' ) ? DAW_SMTP_FROM_EMAIL : '' ) );
$from_name        = (string) ( $email_settings['from_name'] ?? ( defined( 'DAW_SMTP_FROM_NAME' ) ? DAW_SMTP_FROM_NAME : 'Rekrutmen PT Daya Adicipta Wisesa' ) );

$send_result = sanitize_key( $_GET['send_result'] ?? '' );
$sent_count  = absint( $_GET['sent_count'] ?? 0 );
$smtp_updated = '1' === (string) ( $_GET['updated'] ?? '' );
$test_result = sanitize_key( $_GET['test_result'] ?? '' );
$error_msg   = isset( $_GET['error_msg'] ) ? sanitize_text_field( rawurldecode( (string) $_GET['error_msg'] ) ) : '';
?>
<div class="wrap recruitment-admin daw-email-recruitment-page" style="max-width:1200px;margin:20px auto 40px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,Cantarell,sans-serif;">

    <!-- Top Breadcrumb & Actions -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <div>
            <p style="margin:0;font-size:13px;color:#6b7280;">DAW Admin &nbsp;/&nbsp; <span style="color:#111827;font-weight:600;">Email Recruitment</span></p>
        </div>
        <div>
            <button type="button" onclick="document.getElementById('daw-smtp-modal').showModal()" style="display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px solid #d1d5db;border-radius:6px;padding:6px 12px;font-size:12px;font-weight:600;color:#374151;cursor:pointer;box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                <span class="dashicons dashicons-admin-generic" style="font-size:14px;line-height:1;margin-top:1px;"></span>
                <span>Pengaturan SMTP</span>
            </button>
        </div>
    </div>

    <!-- Feedback Notices -->
    <?php if ( 'success' === $send_result ) : ?>
        <div class="notice notice-success is-dismissible" style="border-radius:8px;margin-bottom:20px;border-left-color:#10b981;padding:12px 16px;">
            <p style="margin:0;font-size:14px;color:#065f46;"><strong>Email berhasil dikirim!</strong> Pesan telah sukses dikirim ke <strong><?= esc_html( (string) $sent_count ) ?></strong> penerima.</p>
        </div>
    <?php elseif ( 'failed' === $send_result ) : ?>
        <div class="notice notice-error is-dismissible" style="border-radius:8px;margin-bottom:20px;border-left-color:#ef4444;padding:12px 16px;">
            <p style="margin:0;font-size:14px;color:#991b1b;"><strong>Gagal mengirim email.</strong> Periksa koneksi SMTP atau pastikan alamat email tujuan valid.</p>
        </div>
    <?php elseif ( 'no_recipients' === $send_result ) : ?>
        <div class="notice notice-warning is-dismissible" style="border-radius:8px;margin-bottom:20px;padding:12px 16px;">
            <p style="margin:0;font-size:14px;color:#92400e;"><strong>Peringatan:</strong> Harap pilih setidaknya satu penerima dari daftar sebelum mengirim email.</p>
        </div>
    <?php elseif ( 'empty_fields' === $send_result ) : ?>
        <div class="notice notice-warning is-dismissible" style="border-radius:8px;margin-bottom:20px;padding:12px 16px;">
            <p style="margin:0;font-size:14px;color:#92400e;"><strong>Peringatan:</strong> Subjek dan isi pesan email tidak boleh kosong.</p>
        </div>
    <?php endif; ?>

    <?php if ( $smtp_updated ) : ?>
        <div class="notice notice-success is-dismissible" style="border-radius:8px;margin-bottom:20px;padding:12px 16px;">
            <p style="margin:0;font-size:14px;color:#065f46;">Pengaturan SMTP berhasil diperbarui.</p>
        </div>
    <?php endif; ?>

    <?php if ( 'success' === $test_result ) : ?>
        <div class="notice notice-success is-dismissible" style="border-radius:8px;margin-bottom:20px;padding:12px 16px;">
            <p style="margin:0;font-size:14px;color:#065f46;"><strong>Uji coba koneksi SMTP Berhasil!</strong> Email percobaan telah diterima.</p>
        </div>
    <?php elseif ( 'failed' === $test_result ) : ?>
        <div class="notice notice-error is-dismissible" style="border-radius:8px;margin-bottom:20px;padding:12px 16px;">
            <p style="margin:0;font-size:14px;color:#991b1b;"><strong>Uji coba SMTP Gagal:</strong> <?= esc_html( $error_msg ) ?></p>
        </div>
    <?php endif; ?>

    <!-- CARD 1: DAFTAR PENERIMA -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;margin-bottom:24px;box-shadow:0 1px 3px rgba(0,0,0,0.03);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
            <h2 style="margin:0;font-size:16px;font-weight:700;color:#111827;">Daftar Penerima</h2>
            <button type="button" id="btn-toggle-recipient-list" style="background:none;border:none;color:#6b7280;font-size:13px;font-weight:500;cursor:pointer;padding:0;">Sembunyikan</button>
        </div>

        <!-- Filters Row -->
        <div style="display:grid;grid-template-columns:repeat(4, minmax(0, 1fr));gap:16px;margin-bottom:18px;">
            <div>
                <label style="display:block;font-size:12px;color:#6b7280;margin-bottom:6px;">Tahap Seleksi</label>
                <select id="filter-stage" style="width:100%;height:38px;padding:6px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;color:#111827;background:#fff;">
                    <option value="">Semua</option>
                    <?php foreach ( $filter_stages as $stg ) : ?>
                        <option value="<?= esc_attr( $stg ) ?>"><?= esc_html( $stg ) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:12px;color:#6b7280;margin-bottom:6px;">Status</label>
                <select id="filter-status" style="width:100%;height:38px;padding:6px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;color:#111827;background:#fff;">
                    <option value="">Semua</option>
                    <?php foreach ( $filter_statuses as $sts ) : ?>
                        <option value="<?= esc_attr( $sts ) ?>"><?= esc_html( $sts ) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:12px;color:#6b7280;margin-bottom:6px;">Posisi</label>
                <select id="filter-position" style="width:100%;height:38px;padding:6px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;color:#111827;background:#fff;">
                    <option value="">Semua</option>
                    <?php foreach ( $filter_positions as $pos ) : ?>
                        <option value="<?= esc_attr( $pos ) ?>"><?= esc_html( $pos ) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:12px;color:#6b7280;margin-bottom:6px;">Dealer</label>
                <select id="filter-dealer" style="width:100%;height:38px;padding:6px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;color:#111827;background:#fff;">
                    <option value="">Semua</option>
                    <?php foreach ( $filter_dealers as $dlr ) : ?>
                        <option value="<?= esc_attr( $dlr ) ?>"><?= esc_html( $dlr ) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div id="recipient-table-container">
            <!-- Count and Select All -->
            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0 10px;border-bottom:1px solid #f3f4f6;">
                <span id="recipient-summary-text" style="font-size:13px;color:#6b7280;"><?= esc_html( (string) count( $candidates ) ) ?> kandidat ditampilkan</span>
                <button type="button" id="btn-select-all" style="background:none;border:none;color:#c8102e;font-size:13px;font-weight:600;cursor:pointer;padding:0;">Pilih Semua</button>
            </div>

            <!-- Recipient List Rows -->
            <div id="recipient-list-body" style="max-height:360px;overflow-y:auto;">
                <?php foreach ( $candidates as $candidate ) : ?>
                    <div class="candidate-row"
                         data-stage="<?= esc_attr( $candidate['stage'] ) ?>"
                         data-status="<?= esc_attr( $candidate['status'] ) ?>"
                         data-position="<?= esc_attr( $candidate['position'] ) ?>"
                         data-dealer="<?= esc_attr( $candidate['dealer'] ) ?>"
                         data-email="<?= esc_attr( $candidate['email'] ) ?>"
                         style="display:flex;align-items:center;justify-content:space-between;padding:14px 4px;border-bottom:1px solid #f9fafb;">
                        <div style="display:flex;align-items:flex-start;gap:12px;">
                            <input type="checkbox" class="candidate-checkbox" value="<?= esc_attr( $candidate['email'] ) ?>" style="margin-top:3px;cursor:pointer;border-radius:4px;border-color:#d1d5db;width:16px;height:16px;">
                            <div>
                                <div style="display:flex;align-items:center;gap:8px;line-height:1.3;">
                                    <strong style="font-size:14px;color:#111827;"><?= esc_html( $candidate['name'] ) ?></strong>
                                    <span style="font-size:13px;color:#4b5563;"><?= esc_html( $candidate['position'] ) ?></span>
                                    <span style="font-size:13px;color:#6b7280;"><?= esc_html( $candidate['dealer'] ) ?></span>
                                </div>
                                <span style="font-size:12px;color:#9ca3af;display:block;margin-top:2px;"><?= esc_html( $candidate['email'] ) ?></span>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span style="background:#f9fafb;border:1px solid #e5e7eb;color:#4b5563;font-size:11px;padding:3px 10px;border-radius:12px;font-weight:500;">
                                <?= esc_html( $candidate['stage'] ) ?>
                            </span>
                            <?php if ( 'ongoing' === $candidate['status_type'] ) : ?>
                                <span style="background:#eff6ff;color:#1d4ed8;font-size:11px;padding:3px 12px;border-radius:12px;font-weight:600;">
                                    <?= esc_html( $candidate['status'] ) ?>
                                </span>
                            <?php elseif ( 'danger' === $candidate['status_type'] ) : ?>
                                <span style="background:#fef2f2;color:#b91c1c;font-size:11px;padding:3px 12px;border-radius:12px;font-weight:600;">
                                    <?= esc_html( $candidate['status'] ) ?>
                                </span>
                            <?php elseif ( 'warning' === $candidate['status_type'] ) : ?>
                                <span style="background:#fefce8;color:#a16207;font-size:11px;padding:3px 12px;border-radius:12px;font-weight:600;">
                                    <?= esc_html( $candidate['status'] ) ?>
                                </span>
                            <?php else : ?>
                                <span style="background:#f0fdf4;color:#15803d;font-size:11px;padding:3px 12px;border-radius:12px;font-weight:600;">
                                    <?= esc_html( $candidate['status'] ) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- LOWER SECTION: 2 COLUMNS (COMPOSE EMAIL & TEMPLATE EMAIL) -->
    <div style="display:grid;grid-template-columns:minmax(0, 1.8fr) minmax(0, 1fr);gap:24px;align-items:start;">

        <!-- LEFT COLUMN: COMPOSE EMAIL -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.03);">
            <h2 style="margin:0 0 16px;font-size:16px;font-weight:700;color:#111827;">Compose Email</h2>

            <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" id="daw-compose-form">
                <input type="hidden" name="action" value="daw_send_composed_email">
                <input type="hidden" name="to_recipients" id="input-to-recipients" value="">
                <?php wp_nonce_field( 'daw_send_composed_email' ); ?>

                <!-- Field To -->
                <div style="display:flex;align-items:center;padding:10px 0;border-bottom:1px solid #f3f4f6;">
                    <span style="width:50px;font-size:13px;color:#6b7280;font-weight:500;">To:</span>
                    <span id="display-selected-recipients" style="font-size:13px;color:#9ca3af;font-style:italic;">Pilih penerima dari daftar di atas</span>
                </div>

                <!-- Field CC -->
                <div style="display:flex;align-items:center;padding:8px 0;border-bottom:1px solid #f3f4f6;">
                    <span style="width:50px;font-size:13px;color:#6b7280;font-weight:500;">CC:</span>
                    <input type="text" name="cc_email" placeholder="cc@email.com" style="flex:1;border:none;outline:none;font-size:13px;background:transparent;box-shadow:none;">
                </div>

                <!-- Field BCC -->
                <div style="display:flex;align-items:center;padding:8px 0;border-bottom:1px solid #f3f4f6;">
                    <span style="width:50px;font-size:13px;color:#6b7280;font-weight:500;">BCC:</span>
                    <input type="text" name="bcc_email" placeholder="bcc@email.com" style="flex:1;border:none;outline:none;font-size:13px;background:transparent;box-shadow:none;">
                </div>

                <!-- Field Subj -->
                <div style="display:flex;align-items:center;padding:8px 0;border-bottom:1px solid #f3f4f6;margin-bottom:14px;">
                    <span style="width:50px;font-size:13px;color:#6b7280;font-weight:500;">Subj:</span>
                    <input type="text" name="subject" id="input-subject" placeholder="Subjek email" style="flex:1;border:none;outline:none;font-size:13px;background:transparent;box-shadow:none;" required>
                </div>

                <!-- Textarea Message -->
                <div style="margin-bottom:20px;">
                    <textarea name="message" id="input-message" rows="8" placeholder="Tulis isi email di sini, atau pilih template dari panel kanan..." style="width:100%;border:1px solid #e5e7eb;border-radius:8px;padding:12px;font-size:13px;color:#111827;line-height:1.6;resize:vertical;min-height:220px;" required></textarea>
                </div>

                <!-- Button Kirim -->
                <button type="submit" id="btn-submit-composed" style="width:100%;background:#e05368;color:#fff;border:none;border-radius:8px;padding:12px;font-size:14px;font-weight:600;cursor:pointer;transition:background .2s;text-align:center;">
                    Kirim ke Penerima
                </button>
            </form>
        </div>

        <!-- RIGHT COLUMN: TEMPLATE EMAIL -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.03);">
            <h2 style="margin:0 0 16px;font-size:16px;font-weight:700;color:#111827;">Template Email</h2>

            <!-- Template Categories Pills -->
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:18px;" id="template-categories-bar">
                <button type="button" class="template-filter-btn is-active" data-cat="all" style="background:#c8102e;color:#fff;border:none;border-radius:20px;padding:4px 12px;font-size:11px;font-weight:600;cursor:pointer;">Semua</button>
                <button type="button" class="template-filter-btn" data-cat="lamaran" style="background:#fff;border:1px solid #e5e7eb;color:#4b5563;border-radius:20px;padding:4px 12px;font-size:11px;cursor:pointer;">Lamaran</button>
                <button type="button" class="template-filter-btn" data-cat="administrasi" style="background:#fff;border:1px solid #e5e7eb;color:#4b5563;border-radius:20px;padding:4px 12px;font-size:11px;cursor:pointer;">Administrasi</button>
                <button type="button" class="template-filter-btn" data-cat="psikotes" style="background:#fff;border:1px solid #e5e7eb;color:#4b5563;border-radius:20px;padding:4px 12px;font-size:11px;cursor:pointer;">Psikotes</button>
                <button type="button" class="template-filter-btn" data-cat="wawancara" style="background:#fff;border:1px solid #e5e7eb;color:#4b5563;border-radius:20px;padding:4px 12px;font-size:11px;cursor:pointer;">Wawancara</button>
                <button type="button" class="template-filter-btn" data-cat="hasil" style="background:#fff;border:1px solid #e5e7eb;color:#4b5563;border-radius:20px;padding:4px 12px;font-size:11px;cursor:pointer;">Hasil</button>
                <button type="button" class="template-filter-btn" data-cat="reminder" style="background:#fff;border:1px solid #e5e7eb;color:#4b5563;border-radius:20px;padding:4px 12px;font-size:11px;cursor:pointer;">Reminder</button>
            </div>

            <!-- Templates List Cards -->
            <div style="display:flex;flex-direction:column;gap:10px;" id="template-cards-container">

                <!-- Template 1 -->
                <div class="template-card" data-cat="lamaran"
                     data-subject="Konfirmasi Penerimaan Lamaran - PT Daya Adicipta Wisesa"
                     data-body="Halo {{nama}},\n\nTerima kasih telah melamar posisi {{posisi}} di PT Daya Adicipta Wisesa. Lamaran Anda telah kami terima dan saat ini sedang dalam proses peninjauan oleh tim rekrutmen kami.\n\nSalam,\nTim HR Recruitment DAW"
                     style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;cursor:pointer;transition:all .15s;">
                    <strong style="display:block;font-size:13px;color:#111827;margin-bottom:2px;">Konfirmasi Lamaran</strong>
                    <span style="display:block;font-size:12px;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Konfirmasi Penerimaan Lamaran — {{p...</span>
                </div>

                <!-- Template 2 -->
                <div class="template-card" data-cat="administrasi"
                     data-subject="Selamat! Anda Lolos Seleksi Administrasi - PT Daya Adicipta Wisesa"
                     data-body="Halo {{nama}},\n\nSelamat! Berdasarkan hasil seleksi berkas administrasi untuk posisi {{posisi}}, Anda dinyatakan LOLOS ke tahapan seleksi berikutnya.\n\nInformasi mengenai jadwal dan petunjuk tahapan selanjutnya akan kami kirimkan melalui email ini.\n\nSalam,\nTim HR Recruitment DAW"
                     style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;cursor:pointer;transition:all .15s;">
                    <strong style="display:block;font-size:13px;color:#111827;margin-bottom:2px;">Lolos Seleksi Administrasi</strong>
                    <span style="display:block;font-size:12px;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Selamat — Anda Lolos Seleksi Adminis...</span>
                </div>

                <!-- Template 3 -->
                <div class="template-card" data-cat="administrasi hasil"
                     data-subject="Informasi Hasil Seleksi Administrasi - PT Daya Adicipta Wisesa"
                     data-body="Halo {{nama}},\n\nTerima kasih atas partisipasi dan minat Anda melamar posisi {{posisi}} di PT Daya Adicipta Wisesa.\n\nSetelah melakukan peninjauan menyeluruh terhadap kualifikasi, saat ini kami belum dapat melanjutkan proses lamaran Anda ke tahapan berikutnya. Data profil Anda akan tetap tersimpan dalam database kami untuk kesempatan mendatang.\n\nSalam,\nTim HR Recruitment DAW"
                     style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;cursor:pointer;transition:all .15s;">
                    <strong style="display:block;font-size:13px;color:#111827;margin-bottom:2px;">Tidak Lolos Seleksi Administrasi</strong>
                    <span style="display:block;font-size:12px;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Informasi Hasil Seleksi Administrasi — ...</span>
                </div>

                <!-- Template 4 -->
                <div class="template-card" data-cat="psikotes"
                     data-subject="Undangan Tes Psikologi - PT Daya Adicipta Wisesa"
                     data-body="Halo {{nama}},\n\nAnda diundang untuk mengikuti tahapan Tes Psikologi untuk posisi {{posisi}} di PT Daya Adicipta Wisesa.\n\nSilakan masuk ke portal karir rekrutmen untuk mengonfirmasi kehadiran dan memilih sesi waktu yang tersedia.\n\nSalam,\nTim HR Recruitment DAW"
                     style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;cursor:pointer;transition:all .15s;">
                    <strong style="display:block;font-size:13px;color:#111827;margin-bottom:2px;">Undangan Psikotes</strong>
                    <span style="display:block;font-size:12px;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Undangan Tes Psikologi — {{posisi}} di...</span>
                </div>

                <!-- Template 5 -->
                <div class="template-card" data-cat="reminder psikotes"
                     data-subject="Pengingat: Jadwal Tes Psikologi Besok - PT Daya Adicipta Wisesa"
                     data-body="Halo {{nama}},\n\nIni adalah pengingat bahwa jadwal Tes Psikologi Anda untuk posisi {{posisi}} akan dilaksanakan besok.\n\nPastikan Anda mempersiapkan perangkat dan hadir tepat waktu sesuai konfirmasi yang telah Anda lakukan.\n\nSalam,\nTim HR Recruitment DAW"
                     style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;cursor:pointer;transition:all .15s;">
                    <strong style="display:block;font-size:13px;color:#111827;margin-bottom:2px;">Pengingat Psikotes</strong>
                    <span style="display:block;font-size:12px;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Pengingat: Tes Psikologi Besok — {{po...</span>
                </div>

            </div>

            <!-- Button + Buat Template -->
            <button type="button" onclick="alert('Fitur tambah custom template segera hadir.')" style="width:100%;margin-top:16px;background:#fff;border:1px solid #d1d5db;border-radius:8px;padding:10px;font-size:13px;font-weight:600;color:#c8102e;cursor:pointer;text-align:center;">
                + Buat Template
            </button>
        </div>

    </div>

    <!-- MODAL DIALOG PENGATURAN SMTP -->
    <dialog id="daw-smtp-modal" style="border:none;border-radius:12px;padding:0;max-width:620px;width:90%;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1),0 10px 10px -5px rgba(0,0,0,0.04);background:#fff;">
        <div style="padding:20px 24px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;font-size:16px;font-weight:700;color:#111827;">Konfigurasi SMTP Relay</h3>
            <button type="button" onclick="document.getElementById('daw-smtp-modal').close()" style="background:none;border:none;font-size:20px;color:#9ca3af;cursor:pointer;line-height:1;">&times;</button>
        </div>
        <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" style="padding:24px;">
            <input type="hidden" name="action" value="daw_save_email_settings">
            <input type="hidden" name="smtp_auth" value="1">
            <?php wp_nonce_field( 'daw_save_email_settings' ); ?>

            <div style="margin-bottom:16px;">
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#111827;cursor:pointer;">
                    <input type="checkbox" name="is_enabled" value="1" <?= checked( $is_enabled, true, false ) ?>>
                    <span>Aktifkan Pengiriman Email via SMTP DAW</span>
                </label>
            </div>

            <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#4b5563;margin-bottom:4px;">SMTP Host</label>
                    <input type="text" name="smtp_host" value="<?= esc_attr( $smtp_host ) ?>" required style="width:100%;height:36px;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#4b5563;margin-bottom:4px;">Port</label>
                    <input type="number" name="smtp_port" value="<?= esc_attr( (string) $smtp_port ) ?>" required style="width:100%;height:36px;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#4b5563;margin-bottom:4px;">Enkripsi</label>
                    <select name="smtp_encryption" style="width:100%;height:36px;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                        <option value="tls" <?= selected( $smtp_encryption, 'tls', false ) ?>>TLS (Port 587)</option>
                        <option value="ssl" <?= selected( $smtp_encryption, 'ssl', false ) ?>>SSL (Port 465)</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#4b5563;margin-bottom:4px;">Username / Email Akun</label>
                    <input type="text" name="smtp_user" value="<?= esc_attr( $smtp_user ) ?>" required style="width:100%;height:36px;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:12px;font-weight:600;color:#4b5563;margin-bottom:4px;">Password / App Password</label>
                <input type="password" name="smtp_pass" autocomplete="new-password" placeholder="<?= '' !== $smtp_pass ? '••••••••••••••••' : 'Masukkan password atau app password' ?>" style="width:100%;height:36px;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#4b5563;margin-bottom:4px;">Email Pengirim (From)</label>
                    <input type="email" name="from_email" value="<?= esc_attr( $from_email ) ?>" required style="width:100%;height:36px;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#4b5563;margin-bottom:4px;">Nama Pengirim</label>
                    <input type="text" name="from_name" value="<?= esc_attr( $from_name ) ?>" required style="width:100%;height:36px;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" onclick="document.getElementById('daw-smtp-modal').close()" style="background:#fff;border:1px solid #d1d5db;border-radius:6px;padding:8px 16px;font-size:13px;cursor:pointer;">Tutup</button>
                <button type="submit" style="background:#c8102e;color:#fff;border:none;border-radius:6px;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer;">Simpan Pengaturan</button>
            </div>
        </form>
    </dialog>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterStage = document.getElementById('filter-stage');
    const filterStatus = document.getElementById('filter-status');
    const filterPos = document.getElementById('filter-position');
    const filterDealer = document.getElementById('filter-dealer');
    const candidateRows = Array.from(document.querySelectorAll('.candidate-row'));
    const summaryText = document.getElementById('recipient-summary-text');
    const btnSelectAll = document.getElementById('btn-select-all');
    const displayTo = document.getElementById('display-selected-recipients');
    const inputToRecipients = document.getElementById('input-to-recipients');
    const btnToggleList = document.getElementById('btn-toggle-recipient-list');
    const tableContainer = document.getElementById('recipient-table-container');

    // Toggle collapse/expand recipient list
    if (btnToggleList && tableContainer) {
        btnToggleList.addEventListener('click', function() {
            if (tableContainer.style.display === 'none') {
                tableContainer.style.display = 'block';
                btnToggleList.textContent = 'Sembunyikan';
            } else {
                tableContainer.style.display = 'none';
                btnToggleList.textContent = 'Tampilkan';
            }
        });
    }

    // Dynamic Filter
    function applyFilters() {
        const stageVal = (filterStage.value || '').toLowerCase();
        const statusVal = (filterStatus.value || '').toLowerCase();
        const posVal = (filterPos.value || '').toLowerCase();
        const dealerVal = (filterDealer.value || '').toLowerCase();

        let visibleCount = 0;
        candidateRows.forEach(row => {
            const rStage = (row.dataset.stage || '').toLowerCase();
            const rStatus = (row.dataset.status || '').toLowerCase();
            const rPos = (row.dataset.position || '').toLowerCase();
            const rDealer = (row.dataset.dealer || '').toLowerCase();

            const matchStage = !stageVal || rStage === stageVal;
            const matchStatus = !statusVal || rStatus === statusVal;
            const matchPos = !posVal || rPos === posVal;
            const matchDealer = !dealerVal || rDealer === dealerVal;

            if (matchStage && matchStatus && matchPos && matchDealer) {
                row.style.display = 'flex';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (summaryText) {
            summaryText.textContent = visibleCount + ' kandidat ditampilkan';
        }
    }

    if (filterStage) filterStage.addEventListener('change', applyFilters);
    if (filterStatus) filterStatus.addEventListener('change', applyFilters);
    if (filterPos) filterPos.addEventListener('change', applyFilters);
    if (filterDealer) filterDealer.addEventListener('change', applyFilters);

    // Update To recipients in compose box
    function updateSelectedRecipients() {
        const checkedBoxes = Array.from(document.querySelectorAll('.candidate-checkbox:checked'));
        const emails = checkedBoxes.map(cb => cb.value.trim()).filter(Boolean);

        if (inputToRecipients) {
            inputToRecipients.value = emails.join(',');
        }

        if (displayTo) {
            if (emails.length === 0) {
                displayTo.textContent = 'Pilih penerima dari daftar di atas';
                displayTo.style.fontStyle = 'italic';
                displayTo.style.color = '#9ca3af';
            } else if (emails.length === 1) {
                displayTo.textContent = emails[0];
                displayTo.style.fontStyle = 'normal';
                displayTo.style.color = '#111827';
            } else {
                displayTo.textContent = emails.length + ' penerima dipilih (' + emails.slice(0, 2).join(', ') + (emails.length > 2 ? ', ...' : '') + ')';
                displayTo.style.fontStyle = 'normal';
                displayTo.style.color = '#111827';
            }
        }
    }

    // Checkbox changes
    document.querySelectorAll('.candidate-checkbox').forEach(cb => {
        cb.addEventListener('change', updateSelectedRecipients);
    });

    // Select All Toggle
    if (btnSelectAll) {
        btnSelectAll.addEventListener('click', function(e) {
            e.preventDefault();
            const visibleBoxes = candidateRows
                .filter(row => row.style.display !== 'none')
                .map(row => row.querySelector('.candidate-checkbox'))
                .filter(Boolean);

            const allChecked = visibleBoxes.every(cb => cb.checked);
            visibleBoxes.forEach(cb => { cb.checked = !allChecked; });
            btnSelectAll.textContent = allChecked ? 'Pilih Semua' : 'Batal Pilih Semua';
            updateSelectedRecipients();
        });
    }

    // Template selection
    const inputSubject = document.getElementById('input-subject');
    const inputMessage = document.getElementById('input-message');
    const templateCards = document.querySelectorAll('.template-card');

    templateCards.forEach(card => {
        card.addEventListener('mouseenter', () => { card.style.borderColor = '#c8102e'; });
        card.addEventListener('mouseleave', () => { card.style.borderColor = '#e5e7eb'; });
        card.addEventListener('click', () => {
            const subj = card.dataset.subject || '';
            let body = (card.dataset.body || '').replace(/\\n/g, '\n');

            if (inputSubject) inputSubject.value = subj;
            if (inputMessage) inputMessage.value = body;

            // Highlight chosen card
            templateCards.forEach(c => {
                c.style.background = '#f9fafb';
                c.style.boxShadow = 'none';
            });
            card.style.background = '#fef2f2';
            card.style.boxShadow = '0 0 0 1px #c8102e';
        });
    });

    // Template category filtering
    const catButtons = document.querySelectorAll('.template-filter-btn');
    catButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            catButtons.forEach(b => {
                b.style.background = '#fff';
                b.style.color = '#4b5563';
                b.style.border = '1px solid #e5e7eb';
                b.style.fontWeight = '400';
            });
            btn.style.background = '#c8102e';
            btn.style.color = '#fff';
            btn.style.border = 'none';
            btn.style.fontWeight = '600';

            const cat = btn.dataset.cat;
            templateCards.forEach(card => {
                const cardCats = (card.dataset.cat || '').split(' ');
                if (cat === 'all' || cardCats.includes(cat)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });

    // Validation on form submission
    const composeForm = document.getElementById('daw-compose-form');
    if (composeForm) {
        composeForm.addEventListener('submit', function(e) {
            const emails = (inputToRecipients.value || '').trim();
            if (!emails) {
                e.preventDefault();
                alert('Pilih setidaknya 1 penerima dari daftar penerima di atas.');
            }
        });
    }
});
</script>
