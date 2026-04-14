<?php
/**
 * Plugin Name:       TBST Custom Report
 * Plugin URI:        https://tbstdigital.com.au/
 * Description:       Generate courses completion report
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            TBST Digital
 * License:           GPL v2 or later
 * Text Domain:       tbst-custom-reports
 */

defined( 'ABSPATH' ) || exit;

/* -----------------------------------------------------------------------
 * 1.  ADMIN MENU
 * --------------------------------------------------------------------- */

add_action( 'admin_menu', 'csvr_register_settings_page' );

function csvr_register_settings_page() {
	add_options_page(
		__( 'TBST Custom Reports', 'tbst-custom-reports' ),   // page <title>
		__( 'TBST Custom Reports', 'tbst-custom-reports' ),   // menu label
		'manage_options',                      // capability
		'tbst-custom-reports',                         // menu slug
		'csvr_render_page'                     // callback
	);
}

/* -----------------------------------------------------------------------
 * 2.  HANDLE DOWNLOAD REQUESTS  (runs before any output)
 * --------------------------------------------------------------------- */

add_action( 'admin_init', 'csvr_maybe_download' );

function csvr_maybe_download() {

	if ( ! isset( $_GET['page'], $_GET['csvr_action'] ) || $_GET['page'] !== 'tbst-custom-reports' ) {
		return;
	}

	// Verify nonce
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'csvr_download' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'tbst-custom-reports' ) );
	}

	$action     = sanitize_key( $_GET['csvr_action'] );
	$date_from  = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
	$date_to    = isset( $_GET['date_to'] )   ? sanitize_text_field( $_GET['date_to'] )   : '';

	// Validate dates
	$from = DateTime::createFromFormat( 'Y-m-d', $date_from );
	$to   = DateTime::createFromFormat( 'Y-m-d', $date_to );

	if ( ! $from || ! $to || $from > $to ) {
		// Redirect back with an error flag
		wp_safe_redirect(
			add_query_arg( [ 'page' => 'tbst-custom-reports', 'csvr_error' => 'invalid_dates' ], admin_url( 'options-general.php' ) )
		);
		exit;
	}

	switch ( $action ) {
		case 'report_olm':
			csvr_stream_csv( csvr_build_olm_report( $date_from, $date_to ), "online-learning-module-report-{$date_from}-{$date_to}.csv" );
			break;
		case 'report_mca':
			csvr_stream_csv( csvr_build_mca_report( $date_from, $date_to ), "mini-clinical-audit-report-{$date_from}-{$date_to}.csv" );
			break;
	}
}

/* -----------------------------------------------------------------------
 * 3.  REPORT BUILDERS  (replace with real queries as needed)
 * --------------------------------------------------------------------- */
 
function csvr_build_olm_report( string $date_from, string $date_to ): array {

	global $wpdb;
 
	$rows[] = [ 'Name', 'RACGP Number', 'Date of Completion', "Explain the risk factors and mechanisms leading to the development and progression of anal fissures","Describe appropriate clinical assessment of anal fissures in adults","Comments:","Outline the recommended management of primary anal fissures in adults","Recall essential patient counselling points for when anal fissure is identified","Summarise a screening protocol for anal fissures amongst adult patients","Content – current, contemporary, evidence-based and relevant to general practice","Comments (optional):","Delivery – engaging/interactive (e.g., with opportunity for questions and feedback)","Comments (optional):","Learning Management System – user friendly and easily navigated","Comments (optional):","3. Would you likely recommend this CPD activity to a colleague?","Please provide a reason for your answer:","4. Would you likely change anything in your practice as a result of this CPD activity?","Please provide a reason for your answer:"];

    $ts_from = strtotime( $date_from . ' 00:00:00' );
	$ts_to   = strtotime( $date_to   . ' 23:59:59' );

    $cid        = 95553;
    $meta_key   = 'course_completed_' . $cid;
    $course_obj = get_post( $cid );
    $course_title = $course_obj ? $course_obj->post_title : "Course #{$cid}";

    // Fetch all users who have a completion timestamp for this course
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT u.ID, u.user_login, u.user_email, u.display_name, um.meta_value AS completed_ts
                FROM {$wpdb->users}   u
                JOIN {$wpdb->usermeta} um ON um.user_id = u.ID
                WHERE um.meta_key = %s
                AND um.meta_value != ''
                AND um.meta_value != '0'
                ORDER BY um.meta_value DESC",
            $meta_key
        ),
        ARRAY_A
    );

    foreach ( $results as $r ) {
        $ts = (int) $r['completed_ts'];

        // Filter by date range
        if ( $ts < $ts_from || $ts > $ts_to ) {
            continue;
        }

        $racgp_number = get_user_meta(  $r['ID'], 'racgp_number', true );
        $form_id = 97;

        $entries = FrmEntry::getAll(
            array(
                'it.form_id' => $form_id,
                'it.user_id' => $r['ID'],
            ),
            ' ORDER BY it.created_at DESC',
            1 // Limit to 1 (most recent entry)
        );
        
        $f_array = array(12567, 3089, 3105, 3153, 3137, 3121, 2881, 2897, 2913, 2945, 2929, 2961, 2641, 2657, 2673, 2689);
        foreach($f_array as $f){ ${"f".$f} = ""; }
        
        if (!empty($entries)) {
            $entry = reset($entries); // Get first result
	    $entry = FrmEntry::getOne($entry->id);
 
            foreach($f_array as $f){
                $field_id = $f; // Replace with your field ID
                $value = FrmEntryMeta::get_meta_value($entry, $field_id);
                ${"f".$f} = $value;
            }
        }  
        
        $new_arr = array($r['display_name'],
            $racgp_number,
            gmdate( 'Y-m-d H:i:s', $ts ));
        
        foreach($f_array as $f){ array_push($new_arr, (${"f".$f} ?? "-")); }

        $rows[] = $new_arr;
    }

	return $rows;
}

function csvr_build_mca_report( string $date_from, string $date_to ): array {

	global $wpdb;

	$rows[] = [ 'Name', 'RACGP Number', 'Date of Completion'];

    $ts_from = strtotime( $date_from . ' 00:00:00' );
	$ts_to   = strtotime( $date_to   . ' 23:59:59' );

    $cid        = 111793;
    $meta_key   = 'course_completed_' . $cid;
    $course_obj = get_post( $cid );
    $course_title = $course_obj ? $course_obj->post_title : "Course #{$cid}";

    // Fetch all users who have a completion timestamp for this course
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT u.ID, u.user_login, u.user_email, u.display_name, um.meta_value AS completed_ts
                FROM {$wpdb->users}   u
                JOIN {$wpdb->usermeta} um ON um.user_id = u.ID
                WHERE um.meta_key = %s
                AND um.meta_value != ''
                AND um.meta_value != '0'
                ORDER BY um.meta_value DESC",
            $meta_key
        ),
        ARRAY_A
    );

    foreach ( $results as $r ) {
        $ts = (int) $r['completed_ts'];

        // Filter by date range
        if ( $ts < $ts_from || $ts > $ts_to ) {
            continue;
        }

        $racgp_number = get_user_meta(  $r['ID'], 'racgp_number', true );

        $rows[] = [
            $r['display_name'],
            $racgp_number,
            gmdate( 'Y-m-d H:i:s', $ts ),
        ];
    } 

	return $rows;
}

/* -----------------------------------------------------------------------
 * 4.  CSV STREAMING HELPER
 * --------------------------------------------------------------------- */

function csvr_stream_csv( array $rows, string $filename ): void {

	// Disable caching
	nocache_headers();

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	$out = fopen( 'php://output', 'w' );

	// UTF-8 BOM so Excel auto-detects encoding
	fputs( $out, "\xEF\xBB\xBF" );

	foreach ( $rows as $row ) {
		fputcsv( $out, $row );
	}

	fclose( $out );
	exit;
}

/* -----------------------------------------------------------------------
 * 5.  ADMIN PAGE HTML
 * --------------------------------------------------------------------- */

function csvr_render_page(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Default date range: current month
	$default_from = gmdate( 'Y-m-01' );
	$default_to   = gmdate( 'Y-m-d' );

	$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : $default_from;
	$date_to   = isset( $_GET['date_to'] )   ? sanitize_text_field( $_GET['date_to'] )   : $default_to;
	$error     = isset( $_GET['csvr_error'] ) ? sanitize_key( $_GET['csvr_error'] ) : '';

	$nonce = wp_create_nonce( 'csvr_download' );

	$orders_url = add_query_arg( [
		'page'        => 'tbst-custom-reports',
		'csvr_action' => 'report_olm',
		'date_from'   => $date_from,
		'date_to'     => $date_to,
		'_wpnonce'    => $nonce,
	], admin_url( 'options-general.php' ) );

	$users_url = add_query_arg( [
		'page'        => 'tbst-custom-reports',
		'csvr_action' => 'report_mca',
		'date_from'   => $date_from,
		'date_to'     => $date_to,
		'_wpnonce'    => $nonce,
	], admin_url( 'options-general.php' ) );

	?>
	<div class="wrap" id="csvr-wrap">

		<style>
			/* ---- Variables ---- */
			#csvr-wrap {
				--ink:       #0f1117;
				--ink-soft:  #5a6070;
				--border:    #dde1ea;
				--accent:    #2563eb;
				--accent-h:  #1d4ed8;
				--success:   #16a34a;
				--danger:    #dc2626;
				--card-bg:   #ffffff;
				--page-bg:   #f4f6fb;
				--radius:    10px;
				--shadow:    0 2px 12px rgba(0,0,0,.08);
				font-family: 'Inter', system-ui, sans-serif;
				color: var(--ink);
			}

			/* ---- Layout ---- */
			.csvr-header {
				display: flex;
				align-items: center;
				gap: 14px;
				margin: 28px 0 24px;
			}
			.csvr-icon {
				width: 42px; height: 42px;
				background: var(--accent);
				border-radius: 10px;
				display: grid; place-items: center;
				flex-shrink: 0;
			}
			.csvr-icon svg { fill: #fff; }
			.csvr-header h1 {
				font-size: 1.55rem;
				font-weight: 700;
				margin: 0;
				line-height: 1.2;
			}
			.csvr-header p { margin: 2px 0 0; font-size: .875rem; color: var(--ink-soft); }

			/* ---- Card ---- */
			.csvr-card {
				background: var(--card-bg);
				border: 1px solid var(--border);
				border-radius: var(--radius);
				box-shadow: var(--shadow);
				padding: 28px 32px;
				max-width: 720px;
				margin-bottom: 24px;
			}
			.csvr-card h2 {
				font-size: 1rem;
				font-weight: 600;
				margin: 0 0 18px;
				color: var(--ink);
				display: flex;
				align-items: center;
				gap: 8px;
			}
			.csvr-card h2 span.csvr-badge {
				font-size: .7rem;
				font-weight: 600;
				letter-spacing: .04em;
				text-transform: uppercase;
				background: #e8f0fe;
				color: var(--accent);
				padding: 2px 8px;
				border-radius: 20px;
			}

			/* ---- Date row ---- */
			.csvr-date-row {
				display: flex;
				flex-wrap: wrap;
				gap: 16px;
				align-items: flex-end;
			}
			.csvr-field { display: flex; flex-direction: column; gap: 5px; }
			.csvr-field label {
				font-size: .78rem;
				font-weight: 600;
				color: var(--ink-soft);
				text-transform: uppercase;
				letter-spacing: .05em;
			}
			.csvr-field input[type="date"] {
				padding: 8px 12px;
				border: 1.5px solid var(--border);
				border-radius: 7px;
				font-size: .92rem;
				color: var(--ink);
				background: #fafbfc;
				outline: none;
				transition: border-color .15s;
				cursor: pointer;
			}
			.csvr-field input[type="date"]:focus { border-color: var(--accent); background: #fff; }
			.csvr-apply-btn {
				padding: 9px 20px;
				background: var(--ink);
				color: #fff;
				border: none;
				border-radius: 7px;
				font-size: .88rem;
				font-weight: 600;
				cursor: pointer;
				transition: background .15s, transform .1s;
				text-decoration: none;
				display: inline-flex;
				align-items: center;
				gap: 6px;
			}
			.csvr-apply-btn:hover { background: #1e2330; transform: translateY(-1px); }

			/* ---- Divider ---- */
			.csvr-divider {
				border: none;
				border-top: 1px solid var(--border);
				margin: 22px 0;
			}

			/* ---- Report cards ---- */
			.csvr-reports-grid {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 16px;
			}
			@media (max-width: 580px) { .csvr-reports-grid { grid-template-columns: 1fr; } }

			.csvr-report-tile {
				border: 1.5px solid var(--border);
				border-radius: var(--radius);
				padding: 20px 22px;
				display: flex;
				flex-direction: column;
				gap: 10px;
				transition: border-color .2s, box-shadow .2s;
			}
			.csvr-report-tile:hover {
				border-color: var(--accent);
				box-shadow: 0 0 0 3px rgba(37,99,235,.1);
			}
			.csvr-tile-icon {
				width: 36px; height: 36px;
				border-radius: 8px;
				display: grid; place-items: center;
			}
			.csvr-tile-icon.blue  { background: #dbeafe; }
			.csvr-tile-icon.green { background: #dcfce7; }
			.csvr-tile-icon.blue  svg { fill: #2563eb; }
			.csvr-tile-icon.green svg { fill: #16a34a; }

			.csvr-report-tile h3 { font-size: .95rem; font-weight: 600; margin: 0; }
			.csvr-report-tile p  { font-size: .8rem; color: var(--ink-soft); margin: 0; line-height: 1.5; }

			.csvr-dl-btn {
				display: inline-flex;
				align-items: center;
				gap: 7px;
				padding: 9px 18px;
				border-radius: 7px;
				font-size: .85rem;
				font-weight: 600;
				text-decoration: none;
				transition: background .15s, transform .1s;
				margin-top: 4px;
				align-self: flex-start;
			}
			.csvr-dl-btn.blue  { background: var(--accent);  color: #fff; }
			.csvr-dl-btn.blue:hover  { background: var(--accent-h); transform: translateY(-1px); }
			.csvr-dl-btn.green { background: var(--success); color: #fff; }
			.csvr-dl-btn.green:hover { background: #15803d; transform: translateY(-1px); }
			.csvr-dl-btn svg { flex-shrink: 0; }

			/* ---- Range pill ---- */
			.csvr-range-pill {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				font-size: .78rem;
				color: var(--ink-soft);
				background: #f0f4ff;
				border-radius: 20px;
				padding: 3px 10px;
				border: 1px solid #dbe5ff;
			}
			.csvr-range-pill strong { color: var(--accent); }

			/* ---- Notice ---- */
			.csvr-notice {
				max-width: 720px;
				padding: 12px 16px;
				border-radius: 8px;
				display: flex;
				align-items: center;
				gap: 10px;
				font-size: .88rem;
				margin-bottom: 18px;
			}
			.csvr-notice.error   { background: #fef2f2; border: 1px solid #fca5a5; color: var(--danger); }

			/* ---- Footer note ---- */
			.csvr-footer-note {
				font-size: .78rem;
				color: var(--ink-soft);
				margin-top: 6px;
			}
		</style>

		<!-- Page header -->
		<div class="csvr-header">
			<div class="csvr-icon">
				<svg width="22" height="22" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm1 7H9v2h6zm0 4H9v2h6zm-3-8V3.5L18.5 9z"/></svg>
			</div>
			<div>
				<h1><?php esc_html_e( 'TBST Custom Reports', 'tbst-custom-reports' ); ?></h1>
				<p><?php esc_html_e( 'Export site data as CSV files for any date range.', 'tbst-custom-reports' ); ?></p>
			</div>
		</div>

		<!-- Error notice -->
		<?php if ( $error === 'invalid_dates' ) : ?>
		<div class="csvr-notice error" role="alert">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2zm1 15h-2v-2h2zm0-4h-2V7h2z"/></svg>
			<?php esc_html_e( 'Invalid date range. "From" must be before or equal to "To".', 'tbst-custom-reports' ); ?>
		</div>
		<?php endif; ?>

		<!-- Date range card -->
		<div class="csvr-card">
			<h2>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="color:#6b7280"><path d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 16H5V9h14zm0-13H5V6h14z"/></svg>
				<?php esc_html_e( 'Select Date Range', 'tbst-custom-reports' ); ?>
			</h2>

			<form method="get" action="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>">
				<input type="hidden" name="page" value="tbst-custom-reports">

				<div class="csvr-date-row">
					<div class="csvr-field">
						<label for="csvr-from"><?php esc_html_e( 'From', 'tbst-custom-reports' ); ?></label>
						<input type="date" id="csvr-from" name="date_from"
							value="<?php echo esc_attr( $date_from ); ?>"
							max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>">
					</div>
					<div class="csvr-field">
						<label for="csvr-to"><?php esc_html_e( 'To', 'tbst-custom-reports' ); ?></label>
						<input type="date" id="csvr-to" name="date_to"
							value="<?php echo esc_attr( $date_to ); ?>"
							max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>">
					</div>
					<button type="submit" class="csvr-apply-btn">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M17 12h-5v5h5zm-1-11v2H8V1H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-1V1zm3 18H5V8h14z"/></svg>
						<?php esc_html_e( 'Apply Range', 'tbst-custom-reports' ); ?>
					</button>
				</div>
			</form>

			<hr class="csvr-divider">

			<p style="margin:0 0 16px;font-size:.88rem;color:var(--ink-soft)">
				<?php esc_html_e( 'Download your reports for the selected period:', 'tbst-custom-reports' ); ?>
				&nbsp;
				<span class="csvr-range-pill">
					<svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M17 12h-5v5h5zm-1-11v2H8V1H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-1V1zm3 18H5V8h14z"/></svg>
					<strong><?php echo esc_html( $date_from ); ?></strong>
					&rarr;
					<strong><?php echo esc_html( $date_to ); ?></strong>
				</span>
			</p>

			<div class="csvr-reports-grid">

				<!-- Report 1: Online Learning Module -->
				<div class="csvr-report-tile">
					<div class="csvr-tile-icon blue">
						<svg width="18" height="18" viewBox="0 0 24 24"><path d="M7 4H2v2h5zm0 4H2v2h5zm0 4H2v2h5zm14-7h-9v2h9zm0 4h-9v2h9zm0 4h-9v2h9zM2 16h20v2H2zm0 4h20v2H2z"/></svg>
					</div>
					<h3><?php esc_html_e( 'Online Learning Module Completion Report', 'tbst-custom-reports' ); ?></h3>
					<p><?php esc_html_e( 'All completed / completion in the selected period.', 'tbst-custom-reports' ); ?></p>
					<a href="<?php echo esc_url( $orders_url ); ?>" class="csvr-dl-btn blue">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M5 20h14v-2H5zm7-18L5.33 9h4.34v6h4.66V9h4.34z"/></svg>
						<?php esc_html_e( 'Download CSV', 'tbst-custom-reports' ); ?>
					</a>
				</div>

				<!-- Report 2: Users -->
				<div class="csvr-report-tile">
					<div class="csvr-tile-icon green">
						<svg width="18" height="18" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
					</div>
					<h3><?php esc_html_e( 'Mini Clinical Audit Completion Report', 'tbst-custom-reports' ); ?></h3>
					<p><?php esc_html_e( 'All completed / completion in the selected period.', 'tbst-custom-reports' ); ?></p>
					<a href="<?php echo esc_url( $users_url ); ?>" class="csvr-dl-btn green">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M5 20h14v-2H5zm7-18L5.33 9h4.34v6h4.66V9h4.34z"/></svg>
						<?php esc_html_e( 'Download CSV', 'tbst-custom-reports' ); ?>
					</a>
				</div>

			</div><!-- .csvr-reports-grid -->

			<p class="csvr-footer-note">
				<?php esc_html_e( '* Files include a UTF-8 BOM for correct encoding in Microsoft Excel.', 'tbst-custom-reports' ); ?>
			</p>

		</div><!-- .csvr-card -->

	</div><!-- .wrap -->
	<?php
}

/**
 * Get all users who completed a LearnDash course within a date range.
 *
 * @param int    $course_id  The LearnDash course post ID.
 * @param string $date_from  Start date in 'Y-m-d' format (inclusive).
 * @param string $date_to    End date in 'Y-m-d' format (inclusive).
 *
 * @return array Array of WP_User objects with a 'completed_date' property added.
 */
function get_learndash_course_completions( int $course_id, string $date_from, string $date_to ): array {
    // Convert date strings to Unix timestamps for comparison
    $ts_from = strtotime( $date_from . ' 00:00:00' );
    $ts_to   = strtotime( $date_to   . ' 23:59:59' );

    if ( ! $ts_from || ! $ts_to ) {
        return [];
    }

    /*
     * Pull every user who has a progress record for this course.
     * LearnDash stores progress under the meta key:
     *   _sfwd-course_progress
     * as a serialized array keyed by course ID.
     */
    $user_query = new WP_User_Query( [
        'number'      => -1,        // all users
        'fields'      => 'all',
        'meta_query'  => [
            [
                'key'     => '_sfwd-course_progress',
                'compare' => 'EXISTS',
            ],
        ],
    ] );

    $completed_users = [];

    foreach ( $user_query->get_results() as $user ) {
        $progress_all = get_user_meta( $user->ID, '_sfwd-course_progress', true );

        // Bail if no progress or no entry for this specific course
        if ( empty( $progress_all ) || ! isset( $progress_all[ $course_id ] ) ) {
            continue;
        }

        $course_progress = $progress_all[ $course_id ];

        /*
         * LearnDash marks a course complete when:
         *   $course_progress['completed'] === 1
         *
         * The completion timestamp lives at:
         *   $course_progress['last_time']   (Unix timestamp)
         */
        $is_completed = isset( $course_progress['completed'] ) && (int) $course_progress['completed'] === 1;

        if ( ! $is_completed ) {
            continue;
        }

        $completed_at = isset( $course_progress['last_time'] ) ? (int) $course_progress['last_time'] : 0;

        if ( $completed_at < $ts_from || $completed_at > $ts_to ) {
            continue;
        }

        // Attach the completion date to the user object for convenience
        $user->completed_date = date( 'Y-m-d H:i:s', $completed_at );

        $completed_users[] = $user;
    }

    return $completed_users;
}