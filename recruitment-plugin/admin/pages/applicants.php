<?php
$database = new Recruitment_Database();
$applications = $database->get_applications();
$applications_error = $database->has_error();
$applicants = $database->get_applicants();
$vacancies = $database->get_vacancies();
if ( function_exists( 'get_posts' ) ) {
    $wp_vacancies = get_posts( [ 'post_type' => 'daw_vacancy', 'post_status' => 'any', 'numberposts' => -1 ] );
    foreach ( $wp_vacancies as $wp_v ) {
        $vacancies[] = [
            'id'       => $wp_v->ID,
            'title'    => get_the_title( $wp_v ),
            'dealer'   => (string) get_post_meta( $wp_v->ID, '_daw_dealer', true ),
            'location' => (string) get_post_meta( $wp_v->ID, '_daw_location', true ),
            'region'   => (string) get_post_meta( $wp_v->ID, '_daw_region', true ),
            'type'     => (string) get_post_meta( $wp_v->ID, '_daw_type', true ),
        ];
    }
}

$search = trim( (string) ( $_GET['search'] ?? '' ) );
$year_filter = sanitize_text_field( (string) ( $_GET['year'] ?? '' ) );
$position_filter = sanitize_text_field( (string) ( $_GET['position'] ?? '' ) );
$dealer_filter = sanitize_text_field( (string) ( $_GET['dealer'] ?? '' ) );
$status_filter = sanitize_text_field( (string) ( $_GET['status'] ?? '' ) );
$selected_candidate_id = absint( $_GET['candidate_id'] ?? 0 );
$selected_application_id = absint( $_GET['application_id'] ?? 0 );

$all_statuses = [
    'all' => 'Semua Status',
    'active' => 'Semua Status Aktif',
    'diterima' => 'Diterima',
    'dalam_proses' => 'Dalam Proses',
    'tidak_diterima' => 'Tidak Diterima',
    'dipertimbangkan_kembali' => 'Dipertimbangkan Kembali',
    'belum_melamar' => 'Belum Melamar',
];

$normalize_stage = static function ( $raw_status ): string {
    $status_key = strtolower( (string) $raw_status );
    $status_key = str_replace( [ ' ', '-', '/', '_' ], '_', $status_key );
    $status_key = preg_replace( '/_+/', '_', $status_key );

    if ( in_array( $status_key, [ 'accepted', 'diterima', 'lolos', 'final', 'completed' ], true ) ) {
        return 'final';
    }

    if ( in_array( $status_key, [ 'rejected', 'ditolak', 'tidak_diterima', 'tidak_lolos', 'tolak' ], true ) ) {
        return 'final';
    }

    if ( in_array( $status_key, [ 'administrasi', 'administration', 'screening', 'submitted', 'dalam_proses' ], true ) ) {
        return 'administration';
    }

    if ( in_array( $status_key, [ 'psychological_test', 'psych_test', 'psikotes' ], true ) ) {
        return 'psychological_test';
    }

    if ( in_array( $status_key, [ 'interview', 'interview_hr', 'wawancara_hr', 'hr_interview' ], true ) ) {
        return 'hr_interview';
    }

    if ( in_array( $status_key, [ 'interview_user', 'wawancara_user', 'user_interview' ], true ) ) {
        return 'user_interview';
    }

    return 'administration';
};

$normalize_status_bucket = static function ( $raw_status ): string {
    $status_key = strtolower( (string) $raw_status );
    $status_key = str_replace( [ ' ', '-', '/', '_' ], '_', $status_key );
    $status_key = preg_replace( '/_+/', '_', $status_key );

    if ( in_array( $status_key, [ 'accepted', 'diterima', 'lolos', 'final', 'completed' ], true ) ) {
        return 'diterima';
    }

    if ( in_array( $status_key, [ 'rejected', 'ditolak', 'tidak_diterima', 'tidak_lolos', 'tolak' ], true ) ) {
        return 'tidak_diterima';
    }

    if ( in_array( $status_key, [ 'considered_again', 'dipertimbangkan_kembali', 'reconsider', 'reconsideration' ], true ) ) {
        return 'dipertimbangkan_kembali';
    }

    if ( in_array( $status_key, [ 'not_applied', 'belum_melamar' ], true ) ) {
        return 'belum_melamar';
    }

    return 'dalam_proses';
};

$normalize_branch = static function ( $raw_branch ): string {
    $branch = strtolower( trim( (string) $raw_branch ) );
    if ( false !== strpos( $branch, 'bitung' ) ) {
        return 'DAW BITUNG';
    }
    if ( false !== strpos( $branch, 'maumbi' ) || false !== strpos( $branch, 'main dealer' ) ) {
        return 'Main Dealer DAW MAUMBI';
    }
    return '';
};

$applicant_map = [];
foreach ( $applicants as $applicant ) {
    $applicant = array_change_key_case( (array) $applicant, CASE_LOWER );
    $applicant_map[ (string) $applicant['id'] ] = $applicant;
}

$vacancy_map = [];
foreach ( $vacancies as $vacancy ) {
    $vacancy = array_change_key_case( (array) $vacancy, CASE_LOWER );
    $vacancy_map[ (string) $vacancy['id'] ] = $vacancy;
}

$latest_candidates = [];
$applicants_with_applications = [];
foreach ( $applications as $application ) {
    $application = array_change_key_case( (array) $application, CASE_LOWER );
    $applicant_id = (int) ( $application['applicant_id'] ?? 0 );
    if ( $applicant_id > 0 ) {
        $applicants_with_applications[ (string) $applicant_id ] = true;
    }
    $candidate = $applicant_map[ (string) $applicant_id ] ?? [];
    $vacancy = $vacancy_map[ (string) ( $application['vacancy_id'] ?? 0 ) ] ?? [];

    if ( empty( $candidate ) && empty( $application['title'] ) ) {
        continue;
    }

    $created_at = (string) ( $application['created_at'] ?? $candidate['created_at'] ?? '' );
    $year = (string) date_i18n( 'Y', strtotime( $created_at ) );
    $position = (string) ( $application['title'] ?? $vacancy['title'] ?? $vacancy['job_title'] ?? $candidate['position'] ?? 'Posisi belum ditentukan' );
    $dealer_source = '';
    foreach ( [ $vacancy['dealer'] ?? '', $vacancy['location'] ?? '', $application['location'] ?? '', $candidate['dealer'] ?? '' ] as $branch_value ) {
        if ( is_scalar( $branch_value ) && '' !== trim( (string) $branch_value ) ) {
            $dealer_source = trim( (string) $branch_value );
            break;
        }
    }
    $dealer = $normalize_branch( $dealer_source );
    $category = trim( (string) ( $application['category'] ?? '' ) );
    if ( '' === $category ) {
        $category = trim( (string) ( $vacancy['type'] ?? '' ) );
    }
    $region = (string) ( $vacancy['region'] ?? $vacancy['location'] ?? $application['province'] ?? $candidate['region'] ?? '' );
    $effective_status = (string) ( $application['final_status'] ?? '' );
    if ( '' === trim( $effective_status ) ) {
        $effective_status = (string) ( $application['hr_final_decision'] ?? '' );
    }
    if ( '' === trim( $effective_status ) ) {
        $effective_status = (string) ( $application['status'] ?? $candidate['status'] ?? 'submitted' );
    }
    $status_bucket = $normalize_status_bucket( $effective_status );
    $stage_source = trim( (string) ( $application['stage'] ?? '' ) );
    $stage_key = $normalize_stage( '' !== $stage_source ? $stage_source : $effective_status );
    $stage_label = [
        'administration' => 'Seleksi Administrasi',
        'psychological_test' => 'Psikotes',
        'hr_interview' => 'Wawancara HR',
        'user_interview' => 'Wawancara User',
        'final' => 'Keputusan Akhir',
    ][ $stage_key ] ?? 'Seleksi Administrasi';
    $status_label = 'dalam_proses' === $status_bucket
        ? $stage_label
        : ( $all_statuses[ $status_bucket ] ?? 'Dalam Proses' );

    $code = 'DAW-' . $year . '-' . str_pad( (string) $applicant_id, 3, '0', STR_PAD_LEFT );
    $applicant_name = (string) ( $candidate['name'] ?? $application['applicant_name'] ?? 'Pelamar #' . $applicant_id );
    $applicant_email = (string) ( $candidate['email'] ?? $application['applicant_email'] ?? '' );
    $search_text = strtolower( trim( $applicant_name . ' ' . $code . ' ' . $position . ' ' . $dealer_source . ' ' . $applicant_email ) );
    $application_key = (string) ( $application['id'] ?? $applicant_id );
    $latest_candidates[ $application_key ] = [
        'id' => $applicant_id,
        'application_id' => (int) ( $application['id'] ?? 0 ),
        'name' => $applicant_name,
        'code' => $code,
        'email' => $applicant_email,
        'phone' => (string) ( $candidate['phone'] ?? $application['applicant_phone'] ?? '' ),
        'position' => $position,
        'dealer' => '' !== $dealer ? $dealer : 'Cabang belum ditentukan',
        'branch' => $dealer,
        'category' => '' !== $category ? $category : 'Kategori belum tersedia',
        'region' => $region,
        'year' => $year,
        'stage' => $stage_label,
        'raw_status' => strtoupper( $effective_status ),
        'status_key' => $status_bucket,
        'status_label' => $status_label,
        'created_at' => $created_at,
        'search' => $search_text,
        'notes' => '',
        'social' => [
            'instagram' => '',
            'linkedin' => '',
        ],
    ];
}

$applicants_without_applications = $applications_error ? [] : $applicant_map;
foreach ( $applicants_without_applications as $applicant_id => $candidate ) {
    if ( isset( $applicants_with_applications[ (string) $applicant_id ] ) ) {
        continue;
    }

    $created_at = (string) ( $candidate['created_at'] ?? '' );
    $timestamp = '' !== $created_at ? strtotime( $created_at ) : false;
    $year = false !== $timestamp ? (string) date_i18n( 'Y', $timestamp ) : (string) date_i18n( 'Y' );
    $code = 'DAW-' . $year . '-' . str_pad( (string) $applicant_id, 3, '0', STR_PAD_LEFT );
    $applicant_name = (string) ( $candidate['name'] ?? 'Pelamar #' . $applicant_id );
    $latest_candidates[ 'profile-' . $applicant_id ] = [
        'id' => (int) $applicant_id,
        'application_id' => 0,
        'name' => $applicant_name,
        'code' => $code,
        'email' => (string) ( $candidate['email'] ?? '' ),
        'phone' => (string) ( $candidate['phone'] ?? '' ),
        'position' => 'Belum ada lamaran',
        'dealer' => '-',
        'region' => '',
        'year' => $year,
        'stage' => 'Belum Melamar',
        'raw_status' => 'BELUM_MELAMAR',
        'status_key' => 'belum_melamar',
        'status_label' => $all_statuses['belum_melamar'],
        'created_at' => $created_at,
        'search' => strtolower( trim( $applicant_name . ' ' . $code . ' ' . (string) ( $candidate['email'] ?? '' ) ) ),
        'notes' => '',
        'social' => [
            'instagram' => '',
            'linkedin' => '',
        ],
    ];
}

$all_candidates = array_values( $latest_candidates );
$filtered_candidates = [];
foreach ( $all_candidates as $candidate ) {
    $matches_search = '' === $search || false !== strpos( $candidate['search'], strtolower( $search ) );
    $matches_year = '' === $year_filter || (string) $candidate['year'] === $year_filter;
    $matches_position = '' === $position_filter || strtolower( $candidate['position'] ) === strtolower( $position_filter );
    $matches_dealer = '' === $dealer_filter || strtolower( (string) ( $candidate['branch'] ?? '' ) ) === strtolower( $dealer_filter );
    $matches_status = '' === $status_filter || 'all' === $status_filter;
    if ( 'active' === $status_filter ) {
        $matches_status = ! empty( $candidate['application_id'] )
            && in_array( $candidate['status_key'], [ 'dalam_proses', 'dipertimbangkan_kembali' ], true );
    } elseif ( '' !== $status_filter && 'all' !== $status_filter ) {
        $matches_status = $candidate['status_key'] === $status_filter;
    }

    if ( $matches_search && $matches_year && $matches_position && $matches_dealer && $matches_status ) {
        $filtered_candidates[] = $candidate;
    }
}

$years = [];
foreach ( $all_candidates as $candidate ) {
    $years[ (string) $candidate['year'] ] = (string) $candidate['year'];
}
ksort( $years );
$years = array_reverse( $years, true );

$positions = [];
foreach ( $all_candidates as $candidate ) {
    if ( '' !== $candidate['position'] ) {
        $positions[ strtolower( $candidate['position'] ) ] = $candidate['position'];
    }
}
ksort( $positions );

$dealers = [
    'Main Dealer DAW MAUMBI' => 'Main Dealer DAW MAUMBI',
    'DAW BITUNG' => 'DAW BITUNG',
];

$stats = [
    'total' => count( $filtered_candidates ),
    'diterima' => 0,
    'dalam_proses' => 0,
    'tidak_diterima' => 0,
    'dipertimbangkan_kembali' => 0,
];
foreach ( $filtered_candidates as $candidate ) {
    if ( isset( $stats[ $candidate['status_key'] ] ) ) {
        $stats[ $candidate['status_key'] ]++;
    }
}

$summary_total = count( $all_candidates );
$selected_candidate = null;
if ( $selected_candidate_id > 0 ) {
    foreach ( $all_candidates as $candidate ) {
        if ( ( $selected_application_id > 0 && (int) $candidate['application_id'] === $selected_application_id )
            || ( 0 === $selected_application_id && (int) $candidate['id'] === $selected_candidate_id ) ) {
            $selected_candidate = $candidate;
            break;
        }

    }
}

$selected_profile = $selected_candidate ? $database->get_admin_applicant( (string) $selected_candidate['id'] ) : null;
$selected_answers = $selected_candidate ? $database->get_admin_application_answers( (string) ( $selected_candidate['application_id'] ?? 0 ) ) : [];
$selected_attendance = null;
$selected_attendance_lookup_failed = false;
$psychotest_attendance_window = recruitment_psychotest_attendance_window(
    (array) get_option( 'recruitment_psychotest_settings', [] )
);
if (
    $selected_candidate
    && ! empty( $selected_candidate['application_id'] )
    && 'Psikotes' === ( $selected_candidate['stage'] ?? '' )
) {
    $selected_attendance = $database->get_psychotest_attendance(
        (string) $selected_candidate['id'],
        (string) $selected_candidate['application_id']
    );
    $selected_attendance_lookup_failed = $database->has_error();
}
if ( is_array( $selected_profile ) ) {
    $selected_candidate['name'] = (string) ( $selected_profile['name'] ?? $selected_candidate['name'] );
    $selected_candidate['email'] = (string) ( $selected_profile['email'] ?? $selected_candidate['email'] );
    $selected_candidate['phone'] = (string) ( $selected_profile['phone'] ?? $selected_candidate['phone'] );
}
$selected_documents = is_array( $selected_profile['profile_details']['documents'] ?? null ) ? $selected_profile['profile_details']['documents'] : [];
$profile_photo_url = ! empty( $selected_documents['profile_photo'] )
    ? recruitment_get_public_url( 'applicant-file', [ 'candidate_id' => $selected_candidate['id'], 'document' => 'profile_photo' ] )
    : '';
$email_preview_html = '';
$email_recipient = (string) ( $selected_candidate['email'] ?? '' );
$pending_email_status = $selected_candidate
    ? get_transient( 'daw_app_status_email_' . get_current_user_id() . '_' . (int) $selected_candidate['application_id'] )
    : false;
$email_confirmation_pending = is_string( $pending_email_status ) && '' !== $pending_email_status;
$show_email_preview = ( '1' === (string) ( $_GET['email_preview'] ?? '' ) || '1' === (string) ( $_GET['status_updated'] ?? '' ) ) && $email_confirmation_pending;

$all_email_templates = recruitment_get_email_templates();
$candidate_vars = [
    'nama'    => (string) ( $selected_candidate['name'] ?? 'Pelamar' ),
    'posisi'  => (string) ( $selected_candidate['position'] ?? 'Posisi Terkait' ),
    'dealer'  => (string) ( $selected_candidate['dealer'] ?? 'PT Daya Adicipta Wisesa' ),
    'tahap'   => (string) ( $pending_email_status ?: ( $selected_candidate['raw_status'] ?? '' ) ),
    'tanggal' => wp_date( 'j F Y' ),
];

$prepared_templates = [];
foreach ( $all_email_templates as $tid => $tpl ) {
    $prepared_templates[ $tid ] = [
        'id'       => $tpl['id'],
        'title'    => $tpl['title'],
        'category' => $tpl['category'],
        'stage'    => $tpl['stage'],
        'subject'  => recruitment_replace_template_placeholders( $tpl['subject'], $candidate_vars ),
        'body'     => recruitment_replace_template_placeholders( $tpl['body'], $candidate_vars ),
    ];
}

$default_template_id = $selected_candidate
    ? (string) get_transient( 'daw_app_status_email_' . get_current_user_id() . '_' . (int) $selected_candidate['application_id'] . '_tpl' )
    : '';

if ( '' === $default_template_id || ! isset( $prepared_templates[ $default_template_id ] ) ) {
    $st = strtoupper( (string) ( $pending_email_status ?: ( $selected_candidate['raw_status'] ?? '' ) ) );
    if ( 'DITOLAK' === $st ) {
        $default_template_id = 'tidak_lolos_administrasi';
    } elseif ( 'DITERIMA' === $st ) {
        $default_template_id = 'pengumuman_diterima';
    } elseif ( 'PSIKOTES' === $st ) {
        $default_template_id = 'undangan_psikotes';
    } elseif ( 'INTERVIEW_HR' === $st ) {
        $default_template_id = 'undangan_wawancara_hr';
    } elseif ( 'INTERVIEW_USER' === $st ) {
        $default_template_id = 'undangan_wawancara_user';
    } else {
        $default_template_id = 'konfirmasi_lamaran';
    }
}

$active_template = $prepared_templates[ $default_template_id ] ?? reset( $prepared_templates );
$initial_subject = $active_template['subject'] ?? 'Pembaruan Status Lamaran';
$initial_body    = $active_template['body'] ?? '';
$email_preview_html = recruitment_build_status_email_html( $initial_subject, $initial_body, (string) ( $pending_email_status ?: ( $selected_candidate['raw_status'] ?? '' ) ) );

$answers_map = [];
foreach ( $selected_answers as $ans_item ) {
    $k = (string) ( $ans_item['field_key'] ?? '' );
    $answers_map[ $k ] = trim( (string) ( $ans_item['answer_value'] ?? $ans_item['file_name'] ?? '' ) );
}
$profile_answers = is_array( $selected_profile['profile_details']['application_answers'] ?? null ) ? $selected_profile['profile_details']['application_answers'] : [];
$merged_answers = array_merge( $profile_answers, $answers_map );

$filter_query = array_filter(
    [
        'page' => 'recruitment-applicants',
        'search' => $search !== '' ? $search : null,
        'year' => $year_filter !== '' ? $year_filter : null,
        'position' => $position_filter !== '' ? $position_filter : null,
        'dealer' => $dealer_filter !== '' ? $dealer_filter : null,
        'status' => $status_filter !== '' ? $status_filter : null,
    ],
    static fn ( $value ) => null !== $value && '' !== $value
);
$back_url = add_query_arg( $filter_query, admin_url( 'admin.php' ) );

$detail_tabs = [
    [ 'id' => 'data-diri', 'label' => 'Data Diri' ],
    [ 'id' => 'pendidikan', 'label' => 'Pendidikan' ],
    [ 'id' => 'pengalaman', 'label' => 'Pengalaman' ],
    [ 'id' => 'dokumen', 'label' => 'Dokumen' ],
    [ 'id' => 'administrasi', 'label' => 'Form Administrasi' ],
    [ 'id' => 'riwayat-seleksi', 'label' => 'Riwayat Seleksi' ],
];

$detail_fields = [
    [ 'label' => 'Nama Lengkap', 'value' => $selected_candidate['name'] ?? 'Belum diisi' ],
    [ 'label' => 'NIK', 'value' => (string) ( $merged_answers['nomor_ktp'] ?? $merged_answers['nik'] ?? 'Belum diisi' ) ],
    [ 'label' => 'Tempat Lahir', 'value' => (string) ( $selected_profile['birth_place'] ?? 'Belum diisi' ) ],
    [ 'label' => 'Tanggal Lahir', 'value' => (string) ( $selected_profile['birth_date'] ?? 'Belum diisi' ) ],
    [ 'label' => 'Jenis Kelamin', 'value' => (string) ( $selected_profile['gender'] ?? 'Belum diisi' ) ],
    [ 'label' => 'Agama', 'value' => (string) ( $merged_answers['agama'] ?? 'Belum diisi' ) ],
    [ 'label' => 'Status Pernikahan', 'value' => (string) ( $selected_profile['profile_details']['marital_status'] ?? $merged_answers['status_pernikahan'] ?? 'Belum diisi' ) ],
    [ 'label' => 'Kewarganegaraan', 'value' => (string) ( $merged_answers['kewarganegaraan'] ?? 'WNI' ) ],
    [ 'label' => 'Alamat Domisili', 'value' => (string) ( $selected_profile['address'] ?? $merged_answers['alamat_domisili'] ?? 'Belum diisi' ) ],
    [ 'label' => 'Kota', 'value' => (string) ( $selected_profile['city'] ?? 'Belum diisi' ) ],
    [ 'label' => 'Provinsi', 'value' => (string) ( $selected_profile['province'] ?? $selected_candidate['region'] ?? 'Belum diisi' ) ],
    [ 'label' => 'Email', 'value' => $selected_candidate['email'] ?? 'Belum diisi' ],
    [ 'label' => 'Nomor Telepon / HP', 'value' => $selected_candidate['phone'] ?? 'Belum diisi' ],
];

$actual_history = [];
if ( ! empty( $selected_candidate['application_id'] ) ) {
    $actual_history = $database->get_selection_history( (string) $selected_candidate['application_id'] );
}

$timeline_steps = [
    [ 'key' => 'lamaran_diterima', 'label' => 'Lamaran Diterima', 'status' => 'Selesai' ],
    [ 'key' => 'seleksi_administrasi', 'label' => 'Seleksi Administrasi', 'status' => 'Selesai' ],
    [ 'key' => 'psikotes', 'label' => 'Psikotes', 'status' => 'Sedang Berlangsung' ],
    [ 'key' => 'wawancara_hr', 'label' => 'Wawancara HR', 'status' => 'Belum Dimulai' ],
    [ 'key' => 'wawancara_user', 'label' => 'Wawancara User', 'status' => 'Belum Dimulai' ],
    [ 'key' => 'keputusan_akhir', 'label' => 'Keputusan Akhir', 'status' => 'Belum Dimulai' ],
];

if ( 'diterima' === ( $selected_candidate['status_key'] ?? '' ) ) {
    $timeline_steps[0]['status'] = 'Selesai';
    $timeline_steps[1]['status'] = 'Selesai';
    $timeline_steps[2]['status'] = 'Selesai';
    $timeline_steps[3]['status'] = 'Selesai';
    $timeline_steps[4]['status'] = 'Selesai';
    $timeline_steps[5]['status'] = 'Selesai';
} elseif ( 'dalam_proses' === ( $selected_candidate['status_key'] ?? '' ) ) {
    $timeline_steps[0]['status'] = 'Selesai';
    $timeline_steps[1]['status'] = 'Sedang Berlangsung';
    $timeline_steps[2]['status'] = 'Belum Dimulai';
    $timeline_steps[3]['status'] = 'Belum Dimulai';
    $timeline_steps[4]['status'] = 'Belum Dimulai';
    $timeline_steps[5]['status'] = 'Belum Dimulai';
} elseif ( 'tidak_diterima' === ( $selected_candidate['status_key'] ?? '' ) ) {
    $timeline_steps[0]['status'] = 'Selesai';
    $timeline_steps[1]['status'] = 'Selesai';
    $timeline_steps[2]['status'] = 'Selesai';
    $timeline_steps[3]['status'] = 'Selesai';
    $timeline_steps[4]['status'] = 'Selesai';
    $timeline_steps[5]['status'] = 'Tidak Lolos';
} elseif ( 'dipertimbangkan_kembali' === ( $selected_candidate['status_key'] ?? '' ) ) {
    $timeline_steps[0]['status'] = 'Selesai';
    $timeline_steps[1]['status'] = 'Selesai';
    $timeline_steps[2]['status'] = 'Menunggu Review';
    $timeline_steps[3]['status'] = 'Belum Dimulai';
    $timeline_steps[4]['status'] = 'Belum Dimulai';
    $timeline_steps[5]['status'] = 'Belum Dimulai';
}

$view_mode = $selected_candidate ? 'detail' : 'list';
?>
<div class="wrap recruitment-admin daw-applicants-page" data-applicants-screen>
    <?php if ( $applications_error ) : ?><div class="notice notice-error inline" role="alert"><p>Data lamaran belum dapat dimuat dari Oracle. Status kandidat tidak ditandai sebagai “Belum Melamar” sampai data berhasil dibaca.</p></div><?php endif; ?>
    <?php if ( 'detail' === $view_mode && $selected_candidate ) : ?>
        <div class="daw-applicants__header">
            <p class="daw-applicants__breadcrumb">DAW Admin / Detail Kandidat</p>
            <a class="daw-applicants__back" href="<?= esc_url( $back_url ) ?>">← Kembali ke Daftar Pelamar</a>
        </div>

        <section class="daw-applicant-profile-card">
            <?php if ( '' !== $profile_photo_url ) : ?>
                <img class="daw-applicant-profile-card__avatar daw-applicant-profile-card__photo" src="<?= esc_url( $profile_photo_url ) ?>" alt="Foto <?= esc_attr( $selected_candidate['name'] ) ?>">
            <?php else : ?>
                <div class="daw-applicant-profile-card__avatar"><?= esc_html( strtoupper( substr( $selected_candidate['name'], 0, 1 ) ) ) ?></div>
            <?php endif; ?>
            <div class="daw-applicant-profile-card__identity">
                <h1><?= esc_html( $selected_candidate['name'] ) ?></h1>
                <p class="daw-applicant-profile-card__code"><?= esc_html( $selected_candidate['code'] ) ?></p>
                <div class="daw-applicant-profile-card__meta">
                    <span><?= esc_html( $selected_candidate['position'] ) ?></span>
                    <span><?= esc_html( $selected_candidate['category'] ) ?></span>
                    <span><?= esc_html( $selected_candidate['dealer'] ) ?></span>
                </div>
            </div>
            <div class="daw-applicant-profile-card__stage">
                <span class="daw-stage-badge daw-stage-badge--<?= esc_attr( strtolower( str_replace( [ ' ', '/' ], '-', $selected_candidate['stage'] ) ) ) ?>"><?= esc_html( $selected_candidate['stage'] ) ?></span>
            </div>
        </section>

        <section class="daw-detail-contact-row">
            <div class="daw-detail-contact-row__item">
                <span>Tanggal Daftar</span>
                <strong><?= esc_html( date_i18n( 'd F Y', strtotime( $selected_candidate['created_at'] ) ) ) ?></strong>
            </div>
            <div class="daw-detail-contact-row__item">
                <span>Email</span>
                <strong><?= $selected_candidate['email'] ? '<a href="mailto:' . esc_attr( $selected_candidate['email'] ) . '">' . esc_html( $selected_candidate['email'] ) . '</a>' : '-' ?></strong>
            </div>
            <div class="daw-detail-contact-row__item">
                <span>Telepon</span>
                <strong><?= $selected_candidate['phone'] ? '<a href="tel:' . esc_attr( $selected_candidate['phone'] ) . '">' . esc_html( $selected_candidate['phone'] ) . '</a>' : '-' ?></strong>
            </div>
        </section>

        <?php if ( ! empty( $selected_candidate['application_id'] ) ) : ?>
            <?php if ( '1' === (string) ( $_GET['status_updated'] ?? '' ) ) : ?><div class="notice notice-success inline"><p>Status lamaran berhasil diperbarui. Tracker kandidat akan membaca status terbaru dari Oracle.</p></div><?php endif; ?>
            <?php
            $attendance_time_result = isset( $_GET['attendance_time_result'] ) && is_scalar( $_GET['attendance_time_result'] )
                ? sanitize_key( wp_unslash( (string) $_GET['attendance_time_result'] ) )
                : '';
            ?>
            <?php if ( 'updated' === $attendance_time_result ) : ?><div class="notice notice-success inline"><p>Waktu konfirmasi psikotes berhasil diperbarui.</p></div><?php elseif ( in_array( $attendance_time_result, [ 'invalid', 'error' ], true ) ) : ?><div class="notice notice-error inline"><p>Waktu konfirmasi psikotes tidak berhasil diperbarui. Periksa tanggal dan jam, lalu coba lagi.</p></div><?php endif; ?>
            <?php if ( '1' === (string) ( $_GET['status_email_sent'] ?? '' ) ) : ?><div class="notice notice-success inline" style="padding:10px 14px;border-left:4px solid #10b981;background:#ecfdf5;margin:12px 0;border-radius:6px;"><p style="margin:0;font-size:13px;color:#065f46;font-weight:600;">✓ Email pembaruan status berhasil dikirim ke <strong><?= esc_html( ! empty( $_GET['sent_to'] ) ? urldecode( (string) $_GET['sent_to'] ) : ( $email_recipient ?: 'pelamar' ) ) ?></strong>. Silakan periksa kotak masuk (Inbox) atau folder Spam.</p></div><?php endif; ?>
            <?php if ( '1' === (string) ( $_GET['status_email_error'] ?? '' ) ) : ?><div class="notice notice-error inline" style="padding:10px 14px;border-left:4px solid #ef4444;background:#fef2f2;margin:12px 0;border-radius:6px;"><p style="margin:0;font-size:13px;color:#991b1b;font-weight:600;">⚠ Email belum berhasil dikirim. <?= ! empty( $_GET['mail_error_msg'] ) ? esc_html( urldecode( (string) $_GET['mail_error_msg'] ) ) : 'Periksa konfigurasi email lalu coba ACC kembali.' ?></p></div><?php endif; ?>
            <?php if ( $email_confirmation_pending && ! $show_email_preview ) : ?><div class="notice notice-info inline"><p>Status sudah tersimpan tetapi email belum dikirim. <a href="<?= esc_url( add_query_arg( 'email_preview', '1' ) ) ?>">Buka kembali pratinjau email</a>.</p></div><?php endif; ?>
            <?php if ( is_array( $selected_attendance ) && ! empty( $selected_attendance['confirmed'] ) ) :
                $attendance_datetime_raw = (string) ( $selected_attendance['confirmed_at'] ?? '' );
                $attendance_datetime = '' !== $attendance_datetime_raw
                    ? DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $attendance_datetime_raw, wp_timezone() )
                    : false;
                $attendance_datetime_valid = $attendance_datetime instanceof DateTimeImmutable
                    && $attendance_datetime->format( 'Y-m-d H:i:s' ) === $attendance_datetime_raw;
                ?>
                <section class="daw-documents-panel daw-psychotest-attendance-edit" aria-labelledby="daw-psychotest-attendance-edit-title">
                    <h2 id="daw-psychotest-attendance-edit-title">Konfirmasi Kehadiran Psikotes</h2>
                    <?php if ( $attendance_datetime_valid ) : ?>
                        <p>Waktu konfirmasi saat ini: <strong><?= esc_html( wp_date( 'j F Y', $attendance_datetime->getTimestamp() ) . ' pukul ' . wp_date( 'H:i', $attendance_datetime->getTimestamp() ) ) ?></strong></p>
                        <p>Perubahan hanya untuk catatan waktu konfirmasi lamaran ini dan harus berada dalam rentang tanggal jadwal HR: <?= null !== $psychotest_attendance_window ? esc_html( $psychotest_attendance_window['start_date'] . ' sampai ' . $psychotest_attendance_window['end_date'] ) : 'jadwal tanggal belum lengkap atau tidak valid' ?>.</p>
                        <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" class="daw-psychotest-attendance__edit-form">
                            <input type="hidden" name="action" value="daw_update_psychotest_attendance_time">
                            <input type="hidden" name="application_id" value="<?= esc_attr( (int) $selected_candidate['application_id'] ) ?>">
                            <input type="hidden" name="candidate_id" value="<?= esc_attr( (int) $selected_candidate['id'] ) ?>">
                            <?php wp_nonce_field( 'daw_update_psychotest_attendance_time_' . (int) $selected_candidate['application_id'] . '_' . (int) $selected_candidate['id'] ); ?>
                            <?php if ( null !== $psychotest_attendance_window ) : ?>
                                <label for="daw-admin-psychotest-confirmed-date">Edit tanggal konfirmasi</label>
                                <input id="daw-admin-psychotest-confirmed-date" type="date" name="confirmed_date" min="<?= esc_attr( $psychotest_attendance_window['start_date'] ) ?>" max="<?= esc_attr( $psychotest_attendance_window['end_date'] ) ?>" value="<?= esc_attr( $attendance_datetime->format( 'Y-m-d' ) ) ?>" required>
                                <label for="daw-admin-psychotest-confirmed-time">Edit jam konfirmasi</label>
                                <input id="daw-admin-psychotest-confirmed-time" type="time" name="confirmed_time" value="<?= esc_attr( $attendance_datetime->format( 'H:i' ) ) ?>" required>
                                <button type="submit" class="button button-primary">Simpan Perubahan</button>
                            <?php endif; ?>
                        </form>
                    <?php else : ?>
                        <p class="notice notice-error inline">Waktu konfirmasi tidak dapat dibaca untuk diedit.</p>
                    <?php endif; ?>
                </section>
            <?php elseif ( $selected_attendance_lookup_failed ) : ?>
                <div class="notice notice-error inline"><p>Data konfirmasi psikotes belum dapat dimuat dari Oracle.</p></div>
            <?php endif; ?>
            <?php
            $raw_status = strtoupper( trim( (string) ( $selected_candidate['raw_status'] ?? 'SUBMITTED' ) ) );
            $current_stage_key = 'ADMINISTRASI';
            if ( in_array( $raw_status, [ 'PSIKOTES' ], true ) ) {
                $current_stage_key = 'PSIKOTES';
            } elseif ( in_array( $raw_status, [ 'INTERVIEW_HR' ], true ) ) {
                $current_stage_key = 'INTERVIEW_HR';
            } elseif ( in_array( $raw_status, [ 'INTERVIEW_USER' ], true ) ) {
                $current_stage_key = 'INTERVIEW_USER';
            } elseif ( in_array( $raw_status, [ 'FINAL', 'DITERIMA', 'DITOLAK' ], true ) ) {
                $current_stage_key = 'FINAL';
            }

            $stage_configs = [
                'ADMINISTRASI' => [
                    'pass_label'  => 'Lolos Seleksi Administrasi',
                    'fail_label'  => 'Tidak Lolos Seleksi Administrasi',
                    'default_nxt' => 'PSIKOTES',
                    'next_stages' => [
                        'PSIKOTES'       => 'Psikotes (Tes Psikologi)',
                        'INTERVIEW_HR'   => 'Wawancara HR',
                        'INTERVIEW_USER' => 'Wawancara User (Departemen)',
                    ],
                ],
                'PSIKOTES' => [
                    'pass_label'  => 'Lolos Tes Psikologi',
                    'fail_label'  => 'Tidak Lolos Tes Psikologi',
                    'default_nxt' => 'INTERVIEW_HR',
                    'next_stages' => [
                        'INTERVIEW_HR'   => 'Wawancara HR',
                        'INTERVIEW_USER' => 'Wawancara User (Departemen)',
                    ],
                ],
                'INTERVIEW_HR' => [
                    'pass_label'  => 'Lolos Wawancara HR',
                    'fail_label'  => 'Tidak Lolos Wawancara HR',
                    'default_nxt' => 'INTERVIEW_USER',
                    'next_stages' => [
                        'INTERVIEW_USER' => 'Wawancara User (Departemen)',
                    ],
                ],
                'INTERVIEW_USER' => [
                    'pass_label'  => 'Lolos Wawancara User',
                    'fail_label'  => 'Tidak Lolos Wawancara User',
                    'default_nxt' => 'FINAL',
                    'next_stages' => [
                        'FINAL'    => 'Keputusan Akhir (Offering)',
                        'DITERIMA' => 'Diterima Bergabung Langsung',
                    ],
                ],
                'FINAL' => [
                    'pass_label'  => 'Diterima Bergabung di DAW (Offering)',
                    'fail_label'  => 'Tidak Lolos Seleksi Akhir (Ditolak)',
                    'default_nxt' => 'DITERIMA',
                    'next_stages' => [
                        'DITERIMA' => 'Diterima Bergabung (Offering Letter)',
                        'DITOLAK'  => 'Ditolak / Belum Memenuhi Syarat',
                    ],
                ],
            ];

            $cfg = $stage_configs[ $current_stage_key ] ?? $stage_configs['ADMINISTRASI'];
            ?>
            <section class="daw-documents-panel daw-application-decision">
                <h2>Perbarui Status Lamaran</h2>
                <div class="daw-application-decision__current" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <div>
                        <span style="font-size:12px;color:#6b7280;display:block;">Tahap Seleksi Saat Ini</span>
                        <strong style="font-size:16px;color:#111827;"><?= esc_html( $selected_candidate['stage'] ) ?></strong>
                    </div>
                    <div>
                        <span class="daw-status-badge daw-status-badge--<?= esc_attr( $selected_candidate['status_key'] ) ?>"><?= esc_html( $selected_candidate['status_label'] ) ?></span>
                    </div>
                </div>

                <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" class="daw-application-decision__form" data-administrative-decision-form>
                    <input type="hidden" name="action" value="daw_update_application_status">
                    <input type="hidden" name="application_id" value="<?= esc_attr( (int) $selected_candidate['application_id'] ) ?>">
                    <input type="hidden" name="candidate_id" value="<?= esc_attr( (int) $selected_candidate['id'] ) ?>">
                    <input type="hidden" name="current_stage" value="<?= esc_attr( $current_stage_key ) ?>">
                    <?php wp_nonce_field( 'daw_update_application_status_' . (int) $selected_candidate['application_id'] ); ?>

                    <p class="daw-application-decision__field"><label>Hasil Keputusan Seleksi<br>
                        <select name="administrative_result" id="daw-selection-decision-select" required style="width:100%;height:38px;padding:6px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                            <option value="">Pilih keputusan untuk tahap ini</option>
                            <option value="passed"><?= esc_html( $cfg['pass_label'] ) ?></option>
                            <option value="failed"><?= esc_html( $cfg['fail_label'] ) ?></option>
                        </select>
                    </label></p>

                    <p class="daw-application-decision__field" data-administrative-next-stage hidden><label>Tahap Berikutnya<br>
                        <select name="next_stage" id="daw-selection-next-stage-select" disabled style="width:100%;height:38px;padding:6px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                            <option value="">Pilih tahap berikutnya</option>
                            <?php foreach ( $cfg['next_stages'] as $stg_val => $stg_lbl ) : ?>
                                <option value="<?= esc_attr( $stg_val ) ?>" <?= $stg_val === $cfg['default_nxt'] ? 'selected' : '' ?>>
                                    <?= esc_html( $stg_lbl ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label></p>

                    <p class="daw-application-decision__field daw-application-decision__field--note"><label>Catatan untuk pelamar (opsional)<br>
                        <textarea name="note" rows="2" placeholder="Tulis catatan jika ada..." style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;"></textarea>
                    </label></p>

                    <button type="submit" class="button button-primary daw-application-decision__submit" style="background:#c8102e;border-color:#b00e28;color:#fff;padding:8px 20px;font-weight:600;border-radius:6px;cursor:pointer;">
                        Simpan Status &amp; Siapkan Email
                    </button>
                </form>
            </section>
        <?php endif; ?>

        <?php if ( $show_email_preview ) : ?>
            <div class="daw-email-confirmation" role="presentation">
                <section class="daw-email-confirmation__dialog" role="dialog" aria-modal="true" aria-labelledby="daw-email-confirmation-title" tabindex="-1" style="max-width:700px;max-height:92vh;display:flex;flex-direction:column;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
                    <div class="daw-email-confirmation__header" style="padding:18px 24px 12px;border-bottom:1px solid #e5e7eb;">
                        <div>
                            <span class="daw-email-confirmation__eyebrow" style="color:#059669;font-weight:700;font-size:11px;letter-spacing:0.5px;">STATUS SUDAH TERSIMPAN</span>
                            <h2 id="daw-email-confirmation-title" style="margin:4px 0 6px;font-size:20px;font-weight:700;color:#111827;">Konfirmasi email pelamar</h2>
                            <p style="margin:0;font-size:13px;color:#6b7280;">Periksa template dan sesuaikan pesan jika diperlukan. Email hanya dikirim setelah Anda menekan tombol ACC.</p>
                        </div>
                        <a class="daw-email-confirmation__close" href="<?= esc_url( remove_query_arg( [ 'email_preview', 'status_updated' ] ) ) ?>" aria-label="Tutup pratinjau email" style="font-size:24px;text-decoration:none;color:#9ca3af;line-height:1;">&times;</a>
                    </div>

                    <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>" id="daw-status-email-form" style="display:flex;flex-direction:column;flex:1;min-height:0;margin:0;">
                        <input type="hidden" name="action" value="daw_send_application_status_email">
                        <input type="hidden" name="application_id" value="<?= esc_attr( (int) $selected_candidate['application_id'] ) ?>">
                        <input type="hidden" name="candidate_id" value="<?= esc_attr( (int) $selected_candidate['id'] ) ?>">
                        <?php wp_nonce_field( 'daw_send_application_status_email_' . (int) $selected_candidate['application_id'] . '_' . (int) $selected_candidate['id'] ); ?>

                        <div style="padding:18px 24px;overflow-y:auto;flex:1;">
                            <div class="daw-email-confirmation__recipient" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;margin-bottom:16px;">
                                <label for="daw-modal-recipient-input" style="font-size:11px;color:#374151;display:block;text-transform:uppercase;font-weight:700;letter-spacing:0.5px;margin-bottom:6px;">
                                    Email Penerima / Tujuan *
                                </label>
                                <input type="email" id="daw-modal-recipient-input" name="recipient_email" value="<?= esc_attr( $email_recipient ) ?>" required style="width:100%;height:38px;padding:6px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;color:#111827;background:#fff;">
                                <small style="display:block;color:#6b7280;font-size:11px;margin-top:4px;">Pastikan email penerima benar. Anda dapat mengedit alamat email tujuan pengiriman di sini.</small>
                            </div>

                            <div style="margin-bottom:14px;">
                                <label for="daw-modal-template-select" style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px;">
                                    Template Email (dari Email Recruitment)
                                </label>
                                <select id="daw-modal-template-select" name="selected_template_id" style="width:100%;height:38px;padding:6px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;color:#111827;background:#fff;">
                                    <?php foreach ( $prepared_templates as $tid => $tpl ) : ?>
                                        <option value="<?= esc_attr( $tid ) ?>" <?= $tid === $default_template_id ? 'selected' : '' ?>>
                                            [<?= esc_html( strtoupper( $tpl['category'] ) ) ?>] <?= esc_html( $tpl['title'] ) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="margin-bottom:14px;">
                                <label for="daw-modal-email-subject" style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px;">
                                    Subjek Email
                                </label>
                                <input type="text" id="daw-modal-email-subject" name="email_subject" value="<?= esc_attr( $initial_subject ) ?>" required style="width:100%;height:38px;padding:6px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;color:#111827;">
                            </div>

                            <div style="margin-bottom:14px;">
                                <label for="daw-modal-email-body" style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px;">
                                    Isi Pesan Email
                                </label>
                                <textarea id="daw-modal-email-body" name="email_body" rows="6" required style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;color:#111827;line-height:1.6;resize:vertical;"><?= esc_textarea( $initial_body ) ?></textarea>
                            </div>

                            <div style="margin-bottom:8px;">
                                <span style="display:block;font-size:12px;font-weight:600;color:#6b7280;margin-bottom:5px;">Pratinjau Tampilan Email:</span>
                                <iframe id="daw-status-preview-frame" class="daw-email-confirmation__preview" title="Pratinjau isi email pembaruan status" sandbox="" srcdoc="<?= esc_attr( $email_preview_html ) ?>" style="width:100%;height:190px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;"></iframe>
                            </div>
                        </div>

                        <div class="daw-email-confirmation__actions" style="padding:14px 24px;border-top:1px solid #e5e7eb;background:#f9fafb;display:flex;justify-content:flex-end;gap:12px;align-items:center;">
                            <a class="button daw-email-confirmation__cancel" href="<?= esc_url( remove_query_arg( [ 'email_preview', 'status_updated' ] ) ) ?>" style="border-radius:6px;padding:8px 18px;font-size:13px;text-decoration:none;color:#4b5563;border:1px solid #d1d5db;background:#fff;">Nanti saja</a>
                            <button type="submit" id="daw-status-email-submit-btn" class="button button-primary daw-email-confirmation__approve" style="background:#2563eb;border-color:#1d4ed8;padding:8px 22px;font-weight:600;color:#fff;border-radius:6px;cursor:pointer;font-size:13px;">
                                ACC &amp; Kirim Status Terbaru
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <script>
            (function() {
                const templates = <?= wp_json_encode( $prepared_templates ) ?>;
                const select = document.getElementById('daw-modal-template-select');
                const subjInput = document.getElementById('daw-modal-email-subject');
                const bodyInput = document.getElementById('daw-modal-email-body');
                const frame = document.getElementById('daw-status-preview-frame');
                const form = document.getElementById('daw-status-email-form');
                const submitBtn = document.getElementById('daw-status-email-submit-btn');
                const trackingUrl = <?= wp_json_encode( home_url( '/career/?recruitment_page=applicant-tracking' ) ) ?>;
                const stageBadge = <?= wp_json_encode( (string) ( $pending_email_status ?: ( $selected_candidate['raw_status'] ?? '' ) ) ) ?>;

                if (form && submitBtn) {
                    form.addEventListener('submit', function() {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Mengirim email...';
                    });
                }

                function escapeHtml(str) {
                    return (str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                }

                function buildPreviewHtml(subject, body) {
                    const bodyLines = escapeHtml(body).replace(/\n/g, '<br>');
                    let badgeHtml = '';
                    if (stageBadge) {
                        badgeHtml = '<div style="margin-bottom:20px;padding:12px 16px;background:#fef2f2;border-left:4px solid #c8102e;border-radius:6px;"><span style="font-size:12px;font-weight:700;color:#c8102e;text-transform:uppercase;">Status Lamaran:</span> <strong style="font-size:14px;color:#111827;">' + escapeHtml(stageBadge) + '</strong></div>';
                    }
                    return '<!doctype html><html lang="id"><body style="margin:0;padding:24px 16px;background:#f3f4f6;font-family:Arial,sans-serif;"><table style="width:100%;max-width:580px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;"><tr><td style="padding:16px 22px;background:#c8102e;color:#fff;font-weight:800;font-size:16px;">DAW <span style="font-weight:400;font-size:13px;color:#fee2e2;">| REKRUTMEN</span></td></tr><tr><td style="padding:24px 22px 18px;">' + badgeHtml + '<div style="font-size:13px;line-height:1.6;color:#374151;">' + bodyLines + '</div><div style="margin-top:20px;text-align:center;"><a href="' + trackingUrl + '" style="display:inline-block;background:#c8102e;color:#fff;padding:10px 22px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600;">Masuk ke Tracking Lamaran</a></div></td></tr><tr><td style="padding:14px 22px;background:#f9fafb;border-top:1px solid #e5e7eb;font-size:11px;color:#6b7280;">PT Daya Adicipta Wisesa &bull; Rekrutmen Resmi</td></tr></table></body></html>';
                }

                if (select) {
                    select.addEventListener('change', function() {
                        const chosen = templates[this.value];
                        if (chosen) {
                            if (subjInput) subjInput.value = chosen.subject;
                            if (bodyInput) bodyInput.value = chosen.body;
                            if (frame) frame.srcdoc = buildPreviewHtml(chosen.subject, chosen.body);
                        }
                    });
                }

                if (bodyInput && frame) {
                    bodyInput.addEventListener('input', function() {
                        frame.srcdoc = buildPreviewHtml(subjInput ? subjInput.value : '', this.value);
                    });
                }
            })();
            </script>
        <?php endif; ?>

        <section class="daw-documents-panel daw-applicant-downloads">
            <h2>Dokumen Pelamar</h2>
            <div class="daw-documents-panel__actions">
                <?php if ( $selected_profile ) : ?>
                    <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
                        <input type="hidden" name="action" value="daw_download_applicant_profile">
                        <input type="hidden" name="candidate_id" value="<?= esc_attr( (int) $selected_candidate['id'] ) ?>">
                        <input type="hidden" name="application_id" value="<?= esc_attr( (int) $selected_candidate['application_id'] ) ?>">
                        <?php wp_nonce_field( 'daw_download_applicant_profile_' . (int) $selected_candidate['id'] ); ?>
                        <button type="submit" class="button button-primary daw-document-btn">Download Profil Pelamar</button>
                    </form>
                <?php endif; ?>
                <?php foreach ( [ 'resume' => 'Download CV Pelamar', 'portfolio' => 'Download Portofolio Pelamar' ] as $document_key => $document_label ) : ?>
                    <?php if ( ! empty( $selected_documents[ $document_key ] ) ) : ?>
                        <a class="button daw-document-btn daw-document-btn--light" href="<?= esc_url( recruitment_get_public_url( 'applicant-file', [ 'candidate_id' => $selected_candidate['id'], 'document' => $document_key ] ) ) ?>"><?= esc_html( $document_label ) ?></a>
                    <?php else : ?>
                        <span class="button daw-document-btn daw-document-btn--disabled" aria-disabled="true"><?= esc_html( $document_label ) ?> · Belum tersedia</span>
                    <?php endif; ?>
                <?php endforeach; ?>
                <span class="button daw-document-btn daw-document-btn--disabled" aria-disabled="true">Download Laporan Psikotes · Belum tersedia</span>
            </div>
            <p class="daw-documents-panel__hint">Profil diunduh sebagai laporan HTML. Laporan psikotes belum dapat dihubungkan secara aman karena skema Oracle saat ini belum mengaitkan hasil tes ke kandidat atau lamaran.</p>
        </section>

        <section class="daw-applicant-detail-tabs" data-applicant-tabs>
            <div class="daw-applicant-detail-tabs__nav" role="tablist" aria-label="Detail kandidat">
                <?php foreach ( $detail_tabs as $index => $tab ) : ?>
                    <button
                        type="button"
                        class="daw-applicant-detail-tabs__tab <?= 0 === $index ? 'is-active' : '' ?>"
                        data-applicant-tab="<?= esc_attr( $tab['id'] ) ?>"
                        role="tab"
                        aria-selected="<?= 0 === $index ? 'true' : 'false' ?>"
                    >
                        <?= esc_html( $tab['label'] ) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="daw-applicant-detail-tabs__content">
                <div class="daw-applicant-detail-panel is-active" data-applicant-panel="data-diri" role="tabpanel">
                    <div class="daw-detail-grid">
                        <?php foreach ( $detail_fields as $field ) : ?>
                            <div class="daw-detail-field">
                                <span><?= esc_html( $field['label'] ) ?></span>
                                <strong><?= esc_html( $field['value'] ) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="daw-applicant-detail-panel" data-applicant-panel="pendidikan" role="tabpanel" hidden>
                    <?php
                    $edu_level = (string) ( $selected_profile['profile_details']['education_level'] ?? $selected_profile['education'] ?? $merged_answers['pendidikan_formal_1_tingkat'] ?? '' );
                    $institution = (string) ( $selected_profile['profile_details']['institution'] ?? $merged_answers['pendidikan_formal_1_nama_sekolah'] ?? '' );
                    $major = (string) ( $selected_profile['profile_details']['major'] ?? $merged_answers['pendidikan_formal_1_program_studi'] ?? '' );
                    $gpa = (string) ( $selected_profile['profile_details']['gpa'] ?? $merged_answers['pendidikan_formal_1_ipk'] ?? '' );
                    $grad_year = (string) ( $selected_profile['profile_details']['graduation_year'] ?? $merged_answers['pendidikan_formal_1_tahun_lulus'] ?? '' );
                    $has_edu = '' !== $edu_level || '' !== $institution || '' !== $major;
                    ?>
                    <?php if ( $has_edu ) : ?>
                        <div class="daw-profile__table-wrap" style="margin-bottom:24px;">
                            <h3 style="font-size:16px; margin:0 0 12px; font-weight:600;">Pendidikan Formal</h3>
                            <table class="daw-profile__work-table">
                                <thead>
                                    <tr>
                                        <th>Jenjang</th>
                                        <th>Nama Institusi / Sekolah</th>
                                        <th>Jurusan / Program Studi</th>
                                        <th>IPK / Nilai</th>
                                        <th>Tahun Lulus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><?= esc_html( $edu_level ?: '—' ) ?></td>
                                        <td><?= esc_html( $institution ?: '—' ) ?></td>
                                        <td><?= esc_html( $major ?: '—' ) ?></td>
                                        <td><?= esc_html( $gpa ?: '—' ) ?></td>
                                        <td><?= esc_html( $grad_year ?: '—' ) ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <div class="daw-empty-state">Data pendidikan formal belum tersedia untuk kandidat ini.</div>
                    <?php endif; ?>
                </div>

                <div class="daw-applicant-detail-panel" data-applicant-panel="pengalaman" role="tabpanel" hidden>
                    <?php
                    $work_experiences = is_array( $selected_profile['profile_details']['work_experiences'] ?? null ) ? $selected_profile['profile_details']['work_experiences'] : [];
                    ?>
                    <?php if ( ! empty( $work_experiences ) ) : ?>
                        <div class="daw-profile__table-wrap" style="margin-bottom:24px;">
                            <h3 style="font-size:16px; margin:0 0 12px; font-weight:600;">Pengalaman Kerja</h3>
                            <table class="daw-profile__work-table">
                                <thead>
                                    <tr>
                                        <th>Nama Perusahaan</th>
                                        <th>Posisi / Jabatan</th>
                                        <th>Periode</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $work_experiences as $exp ) : ?>
                                        <tr>
                                            <td><?= esc_html( $exp['company'] ?? '—' ) ?></td>
                                            <td><?= esc_html( $exp['position'] ?? '—' ) ?></td>
                                            <td><?= esc_html( $exp['period'] ?? '—' ) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <div class="daw-empty-state">Riwayat pengalaman kerja belum diisi oleh kandidat.</div>
                    <?php endif; ?>
                </div>

                <div class="daw-applicant-detail-panel" data-applicant-panel="dokumen" role="tabpanel" hidden>
                    <?php
                    $documents = is_array( $selected_profile['profile_details']['documents'] ?? null ) ? $selected_profile['profile_details']['documents'] : [];
                    $doc_labels = [
                        'profile_photo' => 'Foto Profil / Foto Terkini',
                        'resume'        => 'Curriculum Vitae (CV) / Resume',
                        'portfolio'     => 'Portofolio',
                        'ktp'           => 'Kartu Tanda Penduduk (KTP)',
                        'ijazah'        => 'Ijazah Terakhir',
                        'transkrip'     => 'Transkrip Nilai',
                    ];
                    ?>
                    <?php if ( ! empty( $documents ) ) : ?>
                        <div class="daw-profile__table-wrap">
                            <table class="daw-profile__work-table">
                                <thead>
                                    <tr>
                                        <th>Jenis Dokumen</th>
                                        <th>Nama File</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $documents as $doc_key => $doc_item ) : ?>
                                        <?php if ( is_array( $doc_item ) && ! empty( $doc_item['name'] ) ) : ?>
                                            <tr>
                                                <td><strong><?= esc_html( $doc_labels[ $doc_key ] ?? ucwords( str_replace( '_', ' ', (string) $doc_key ) ) ) ?></strong></td>
                                                <td><?= esc_html( $doc_item['name'] ) ?></td>
                                                <td>
                                                    <a class="button button-small" href="<?= esc_url( recruitment_get_public_url( 'applicant-file', [ 'candidate_id' => $selected_candidate['id'], 'document' => $doc_key ] ) ) ?>" target="_blank" rel="noopener">
                                                        Lihat / Unduh Dokumen
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <div class="daw-empty-state">Belum ada dokumen yang diunggah untuk kandidat ini.</div>
                    <?php endif; ?>
                </div>

                <div class="daw-applicant-detail-panel" data-applicant-panel="administrasi" role="tabpanel" hidden>
                    <?php if ( ! empty( $selected_answers ) || ! empty( $merged_answers ) ) : ?>
                        <div class="daw-profile__table-wrap">
                            <table class="daw-profile__qa-table">
                                <thead>
                                    <tr>
                                        <th style="width:40%;">Pertanyaan / Field Administrasi</th>
                                        <th style="width:60%;">Jawaban Pelamar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ( ! empty( $selected_answers ) ) : ?>
                                        <?php foreach ( $selected_answers as $ans ) : ?>
                                            <?php
                                            $val = trim( (string) ( $ans['answer_value'] ?? '' ) );
                                            if ( '' === $val && ! empty( $ans['file_name'] ) ) {
                                                $val = 'File: ' . $ans['file_name'];
                                            }
                                            ?>
                                            <tr>
                                                <td><strong><?= esc_html( $ans['field_title'] ?? $ans['field_key'] ?? 'Pertanyaan' ) ?></strong></td>
                                                <td><?= esc_html( '' !== $val ? $val : '—' ) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <?php foreach ( $merged_answers as $key => $val ) : ?>
                                            <?php if ( is_scalar( $val ) && '' !== trim( (string) $val ) ) : ?>
                                                <tr>
                                                    <td><strong><?= esc_html( ucwords( str_replace( '_', ' ', (string) $key ) ) ) ?></strong></td>
                                                    <td><?= esc_html( (string) $val ) ?></td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <div class="daw-empty-state">Data administrasi lamaran belum tersedia untuk kandidat ini.</div>
                    <?php endif; ?>
                </div>

                <div class="daw-applicant-detail-panel" data-applicant-panel="riwayat-seleksi" role="tabpanel" hidden>
                    <div class="daw-selection-history">
                        <?php if ( ! empty( $actual_history ) ) : ?>
                            <?php foreach ( $actual_history as $hist ) : ?>
                                <div class="daw-selection-history__item">
                                    <span class="daw-selection-history__marker daw-selection-history__marker--selesai"></span>
                                    <div class="daw-selection-history__content">
                                        <div class="daw-selection-history__header">
                                            <strong><?= esc_html( $hist['stage'] ?: 'Tahap Seleksi' ) ?></strong>
                                            <span class="daw-status-badge daw-status-badge--selesai"><?= esc_html( $hist['status'] ?: 'Tercatat' ) ?></span>
                                        </div>
                                        <p><?= esc_html( $hist['note'] ?: 'Status lamaran dicatat di Oracle Database.' ) ?> <small style="display:block; color:#6b7280;"><?= esc_html( $hist['date'] ) ?></small></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <?php foreach ( $timeline_steps as $step ) : ?>
                                <div class="daw-selection-history__item">
                                    <span class="daw-selection-history__marker daw-selection-history__marker--<?= esc_attr( strtolower( str_replace( ' ', '-', $step['status'] ) ) ) ?>"></span>
                                    <div class="daw-selection-history__content">
                                        <div class="daw-selection-history__header">
                                            <strong><?= esc_html( $step['label'] ) ?></strong>
                                            <span class="daw-status-badge daw-status-badge--<?= esc_attr( strtolower( str_replace( ' ', '-', $step['status'] ) ) ) ?>"><?= esc_html( $step['status'] ) ?></span>
                                        </div>
                                        <p><?= esc_html( 'Tanggal proses belum tersedia untuk tahap ini.' ) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    <?php else : ?>
        <div class="daw-applicants__header">
            <div>
                <p class="daw-applicants__breadcrumb">DAW Admin / Database Pelamar</p>
                <h1>Database Pelamar</h1>
                <p class="daw-applicants__subtitle">Arsip kandidat lintas tahun rekrutmen DAW Group</p>
            </div>
        </div>

        <form method="get" class="daw-applicant-filter-panel" action="<?= esc_url( admin_url( 'admin.php' ) ) ?>">
            <input type="hidden" name="page" value="recruitment-applicants" />

            <div class="daw-applicant-filter-panel__row">
                <label class="daw-applicant-search">
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <input type="search" name="search" value="<?= esc_attr( $search ) ?>" placeholder="Cari nama atau kode..." />
                </label>

                <select name="year">
                    <option value="">Tahun</option>
                    <?php foreach ( $years as $year ) : ?>
                        <option value="<?= esc_attr( $year ) ?>" <?= selected( $year_filter, $year, false ) ?>><?= esc_html( $year ) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="position">
                    <option value="">Posisi</option>
                    <?php foreach ( $positions as $position ) : ?>
                        <option value="<?= esc_attr( $position ) ?>" <?= selected( $position_filter, $position, false ) ?>><?= esc_html( $position ) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="dealer">
                    <option value="">Dealer / Cabang</option>
                    <?php foreach ( $dealers as $dealer ) : ?>
                        <option value="<?= esc_attr( $dealer ) ?>" <?= selected( $dealer_filter, $dealer, false ) ?>><?= esc_html( $dealer ) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="status">
                    <option value="">Status</option>
                    <?php foreach ( $all_statuses as $status_key => $status_label ) : ?>
                        <option value="<?= esc_attr( $status_key ) ?>" <?= selected( $status_filter, $status_key, false ) ?>><?= esc_html( $status_label ) ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="daw-applicant-filter-panel__submit">Terapkan</button>
                <a href="<?= esc_url( admin_url( 'admin.php?page=recruitment-applicants' ) ) ?>" class="daw-applicant-filter-panel__reset">Reset</a>
            </div>
        </form>

        <section class="daw-applicant-stats" aria-label="Statistik pelamar">
            <div class="daw-applicant-stat-card daw-applicant-stat-card--dark">
                <span>Total Pelamar</span>
                <strong><?= esc_html( number_format_i18n( $stats['total'] ) ) ?></strong>
            </div>
            <div class="daw-applicant-stat-card daw-applicant-stat-card--green">
                <span>Diterima</span>
                <strong><?= esc_html( number_format_i18n( $stats['diterima'] ) ) ?></strong>
            </div>
            <div class="daw-applicant-stat-card daw-applicant-stat-card--blue">
                <span>Dalam Proses</span>
                <strong><?= esc_html( number_format_i18n( $stats['dalam_proses'] ) ) ?></strong>
            </div>
            <div class="daw-applicant-stat-card daw-applicant-stat-card--red">
                <span>Tidak Diterima</span>
                <strong><?= esc_html( number_format_i18n( $stats['tidak_diterima'] ) ) ?></strong>
            </div>
        </section>

        <section class="daw-applicant-table-card">
            <div class="daw-applicant-table-card__top">
                <div>
                    <span class="daw-applicant-table-card__label">Jumlah Data</span>
                    <strong><?= esc_html( number_format_i18n( count( $filtered_candidates ) ) ) ?> dari <?= esc_html( number_format_i18n( $summary_total ) ) ?></strong>
                </div>
            </div>

            <?php if ( empty( $filtered_candidates ) ) : ?>
                <div class="daw-empty-state">Kandidat tidak ditemukan.</div>
            <?php else : ?>
                <div class="daw-applicant-table-wrap">
                    <table class="daw-applicant-table">
                        <thead>
                            <tr>
                                <th>Nama / Kode</th>
                                <th>Posisi / Dealer</th>
                                <th>Tahun</th>
                                <th>Tahap Terakhir</th>
                                <th>Status</th>
                                <th>Catatan</th>
                                <th>Sosmed</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $filtered_candidates as $candidate ) : ?>
                                <tr>
                                    <td>
                                        <div class="daw-applicant-name-cell">
                                            <span class="daw-applicant-name-cell__avatar"><?= esc_html( strtoupper( substr( $candidate['name'], 0, 1 ) ) ) ?></span>
                                            <div>
                                                <strong><?= esc_html( $candidate['name'] ) ?></strong>
                                                <small><?= esc_html( $candidate['code'] ) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="daw-applicant-position-cell">
                                            <strong><?= esc_html( $candidate['position'] ) ?></strong>
                                            <small><?= esc_html( $candidate['dealer'] ) ?></small>
                                        </div>
                                    </td>
                                    <td><span class="daw-year-badge"><?= esc_html( $candidate['year'] ) ?></span></td>
                                    <td><span class="daw-stage-badge daw-stage-badge--<?= esc_attr( strtolower( str_replace( ' ', '-', $candidate['stage'] ) ) ) ?>"><?= esc_html( $candidate['stage'] ) ?></span></td>
                                    <td><span class="daw-status-badge daw-status-badge--<?= esc_attr( $candidate['status_key'] ) ?>"><?= esc_html( $candidate['status_label'] ) ?></span></td>
                                    <td class="daw-note-cell"><?= esc_html( $candidate['notes'] ) ?></td>
                                    <td class="daw-social-cell">
                                        <?php if ( ! empty( $candidate['social']['instagram'] ) ) : ?>
                                            <a href="<?= esc_url( $candidate['social']['instagram'] ) ?>" target="_blank" rel="noopener noreferrer" aria-label="Instagram">📷</a>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $candidate['social']['linkedin'] ) ) : ?>
                                            <a href="<?= esc_url( $candidate['social']['linkedin'] ) ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">in</a>
                                        <?php endif; ?>
                                        <?php if ( empty( $candidate['social']['instagram'] ) && empty( $candidate['social']['linkedin'] ) ) : ?>
                                            <span>-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a class="daw-detail-button" href="<?= esc_url( add_query_arg( array_merge( $filter_query, [ 'candidate_id' => (int) $candidate['id'], 'application_id' => (int) $candidate['application_id'] ] ), admin_url( 'admin.php' ) ) ) ?>">Detail</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
