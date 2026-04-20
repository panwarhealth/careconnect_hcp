<?php
/**
 * Mini Audit Admin — admin tools page.
 *
 * Provides a user-search + RACGP-number editor so admins can fix
 * RACGP numbers without opening the Formidable registration form
 * (which demands the user's password).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'hcp_mca_admin_tools_menu' );
add_action( 'wp_ajax_hcp_mca_search_users', 'hcp_mca_ajax_search_users' );
add_action( 'wp_ajax_hcp_mca_save_racgp', 'hcp_mca_ajax_save_racgp' );

function hcp_mca_admin_tools_menu(): void {
	add_menu_page(
		'Mini Audit Admin',
		'Mini Audit Admin',
		'manage_options',
		'hcp-mca-admin',
		'hcp_mca_admin_tools_render',
		'dashicons-clipboard',
		71
	);
}

function hcp_mca_admin_tools_render(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}
	?>
	<div class="wrap">
		<h1>Mini Audit Admin</h1>

		<div id="hcp-mca-tools" style="max-width:720px;">
			<h2>RACGP Number Editor</h2>
			<p class="description">Search for a user by name, email, or AHPRA number to view and edit their RACGP number.</p>

			<div style="display:flex;gap:8px;margin:16px 0;">
				<input type="text" id="hcp-mca-user-search" class="regular-text"
					   placeholder="Search by name, email, or AHPRA number…"
					   style="flex:1;">
				<button type="button" id="hcp-mca-search-btn" class="button button-primary">Search</button>
			</div>

			<div id="hcp-mca-results"></div>
			<div id="hcp-mca-editor" style="display:none;margin-top:20px;padding:16px;background:#f9f9f9;border:1px solid #ddd;">
			</div>
		</div>
	</div>

	<script type="text/javascript">
	(function($) {
		var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
		var nonce   = '<?php echo wp_create_nonce( 'hcp_mca_admin_tools' ); ?>';

		function doSearch() {
			var q = $('#hcp-mca-user-search').val().trim();
			if (q.length < 2) { alert('Enter at least 2 characters.'); return; }
			$('#hcp-mca-results').html('<p>Searching…</p>');
			$('#hcp-mca-editor').hide();
			$.post(ajaxUrl, { action: 'hcp_mca_search_users', q: q, _wpnonce: nonce }, function(res) {
				if (!res.success) { $('#hcp-mca-results').html('<p>Error: ' + res.data + '</p>'); return; }
				if (!res.data.length) { $('#hcp-mca-results').html('<p>No users found.</p>'); return; }
				var html = '<table class="widefat striped"><thead><tr>'
					+ '<th>Name</th><th>Email</th><th>AHPRA</th><th>RACGP</th><th>Membership</th><th></th>'
					+ '</tr></thead><tbody>';
				$.each(res.data, function(i, u) {
					html += '<tr>'
						+ '<td>' + esc(u.display_name) + '</td>'
						+ '<td>' + esc(u.email) + '</td>'
						+ '<td>' + esc(u.ahpra) + '</td>'
						+ '<td>' + esc(u.racgp || '—') + '</td>'
						+ '<td>' + esc(u.membership) + '</td>'
						+ '<td><button type="button" class="button hcp-mca-edit-btn" '
						+ 'data-id="' + u.id + '" data-name="' + esc(u.display_name) + '" '
						+ 'data-racgp="' + esc(u.racgp) + '">Edit</button></td>'
						+ '</tr>';
				});
				html += '</tbody></table>';
				$('#hcp-mca-results').html(html);
			});
		}

		$('#hcp-mca-search-btn').on('click', doSearch);
		$('#hcp-mca-user-search').on('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); doSearch(); } });

		$(document).on('click', '.hcp-mca-edit-btn', function() {
			var btn = $(this);
			var userId = btn.data('id');
			var name   = btn.data('name');
			var racgp  = btn.data('racgp') || '';
			$('#hcp-mca-editor').show().html(
				'<h3>Edit RACGP for: ' + esc(name) + '</h3>'
				+ '<div style="display:flex;gap:8px;align-items:center;">'
				+ '<input type="text" id="hcp-mca-racgp-input" class="regular-text" value="' + esc(racgp) + '">'
				+ '<button type="button" id="hcp-mca-save-btn" class="button button-primary" data-id="' + userId + '">Save</button>'
				+ '</div>'
				+ '<div id="hcp-mca-save-msg" style="margin-top:8px;"></div>'
			);
			$('#hcp-mca-racgp-input').focus();
		});

		$(document).on('click', '#hcp-mca-save-btn', function() {
			var userId = $(this).data('id');
			var val    = $('#hcp-mca-racgp-input').val().trim();
			if (!val) { alert('RACGP number cannot be empty.'); return; }
			$('#hcp-mca-save-msg').html('Saving…');
			$.post(ajaxUrl, { action: 'hcp_mca_save_racgp', user_id: userId, racgp: val, _wpnonce: nonce }, function(res) {
				if (res.success) {
					$('#hcp-mca-save-msg').html('<span style="color:green;">&#10003; Saved successfully.</span>');
					// Update the table cell too
					$('.hcp-mca-edit-btn[data-id="' + userId + '"]')
						.data('racgp', val)
						.closest('tr').find('td:eq(3)').text(val);
				} else {
					$('#hcp-mca-save-msg').html('<span style="color:red;">Error: ' + res.data + '</span>');
				}
			});
		});

		function esc(s) { return $('<span>').text(s || '').html(); }
	})(jQuery);
	</script>
	<?php
}

function hcp_mca_ajax_search_users(): void {
	check_ajax_referer( 'hcp_mca_admin_tools' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	$q = sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) );
	if ( strlen( $q ) < 2 ) {
		wp_send_json_error( 'Query too short' );
	}

	global $wpdb;
	$like = '%' . $wpdb->esc_like( $q ) . '%';

	$users = $wpdb->get_results( $wpdb->prepare(
		"SELECT DISTINCT u.ID, u.user_login, u.user_email, u.display_name
		 FROM {$wpdb->users} u
		 LEFT JOIN {$wpdb->usermeta} um_racgp ON um_racgp.user_id = u.ID AND um_racgp.meta_key = 'racgp_number'
		 WHERE u.display_name LIKE %s
		    OR u.user_email LIKE %s
		    OR u.user_login LIKE %s
		    OR um_racgp.meta_value LIKE %s
		 ORDER BY u.display_name ASC
		 LIMIT 20",
		$like, $like, $like, $like
	) );

	$results = [];
	foreach ( $users as $u ) {
		$racgp = get_user_meta( $u->ID, 'racgp_number', true );

		$membership = '—';
		if ( function_exists( 'rcp_get_customer_by_user_id' ) ) {
			$customer = rcp_get_customer_by_user_id( $u->ID );
			if ( $customer ) {
				$memberships = rcp_get_customer_memberships( $customer->get_id() );
				if ( $memberships ) {
					$statuses = [];
					foreach ( $memberships as $m ) {
						$statuses[] = $m->get_membership_level_name() . ' (' . $m->get_status() . ')';
					}
					$membership = implode( ', ', $statuses );
				}
			}
		}

		$results[] = [
			'id'           => (int) $u->ID,
			'display_name' => $u->display_name,
			'email'        => $u->user_email,
			'ahpra'        => $u->user_login,
			'racgp'        => $racgp ?: '',
			'membership'   => $membership,
		];
	}

	wp_send_json_success( $results );
}

function hcp_mca_ajax_save_racgp(): void {
	check_ajax_referer( 'hcp_mca_admin_tools' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	$user_id = (int) ( $_POST['user_id'] ?? 0 );
	$racgp   = sanitize_text_field( wp_unslash( $_POST['racgp'] ?? '' ) );

	if ( $user_id <= 0 ) {
		wp_send_json_error( 'Invalid user ID' );
	}
	if ( $racgp === '' ) {
		wp_send_json_error( 'RACGP number cannot be empty' );
	}

	$user = get_user_by( 'id', $user_id );
	if ( ! $user ) {
		wp_send_json_error( 'User not found' );
	}

	// 1. Update usermeta
	update_user_meta( $user_id, 'racgp_number', $racgp );

	// 2. Update Formidable entry (form 129, field 4273)
	global $wpdb;
	$frm_item_id = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM {$wpdb->prefix}frm_items WHERE form_id = 129 AND user_id = %d ORDER BY id DESC LIMIT 1",
		$user_id
	) );

	if ( $frm_item_id ) {
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}frm_item_metas WHERE item_id = %d AND field_id = 4273",
			$frm_item_id
		) );

		if ( $existing ) {
			$wpdb->update(
				$wpdb->prefix . 'frm_item_metas',
				[ 'meta_value' => $racgp ],
				[ 'item_id' => $frm_item_id, 'field_id' => 4273 ]
			);
		} else {
			$wpdb->insert(
				$wpdb->prefix . 'frm_item_metas',
				[ 'item_id' => $frm_item_id, 'field_id' => 4273, 'meta_value' => $racgp ]
			);
		}
	}

	wp_send_json_success( 'Saved' );
}
