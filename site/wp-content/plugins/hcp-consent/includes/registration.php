<?php
/**
 * Terms + Privacy acceptance on the registration form.
 *
 * Form 2 is the one registration form, embedded on pages and in the header
 * pop-up alike, so the tick box is a field on the form itself and appears
 * wherever the form does. Formidable enforces it as required; the Register
 * button also stays disabled until it is ticked. Each registration writes a
 * tc-accepted row. Marketing consent is not asked here: the pop-up asks it on
 * first login.
 */

defined( 'ABSPATH' ) || exit;

const HCP_CONSENT_REG_FORM_ID   = 2;
const HCP_CONSENT_REG_USER_FIELD = 53;
const HCP_CONSENT_TERMS_FIELD_KEY = 'hcp_terms_accept';

// The tick box label, minus its links; what the log records as agreed to.
const HCP_CONSENT_TERMS_STATEMENT = 'I have read and agree to the Terms of Use and Privacy Policy';

function hcp_consent_terms_field_id(): int {
	if ( ! class_exists( 'FrmField' ) ) {
		return 0;
	}

	return (int) FrmField::get_id_by_key( HCP_CONSENT_TERMS_FIELD_KEY );
}

// After Formidable Registration (which creates the user) and the RCP
// membership hook at 30.
add_action( 'frm_after_create_entry', 'hcp_consent_record_registration', 40, 2 );
function hcp_consent_record_registration( $entry_id, $form_id ): void {
	if ( HCP_CONSENT_REG_FORM_ID !== (int) $form_id ) {
		return;
	}

	$field_id = hcp_consent_terms_field_id();
	if ( ! $field_id || empty( $_POST['item_meta'][ $field_id ] ) ) {
		return;
	}

	$user_id = (int) ( $_POST['item_meta'][ HCP_CONSENT_REG_USER_FIELD ] ?? 0 );
	if ( ! $user_id && class_exists( 'FrmEntry' ) ) {
		$entry   = FrmEntry::getOne( $entry_id );
		$user_id = $entry ? (int) $entry->user_id : 0;
	}

	if ( ! $user_id ) {
		if ( function_exists( 'hcp_sentry_capture' ) ) {
			hcp_sentry_capture( 'Registration Terms acceptance had no user to log against', \Sentry\Severity::warning(), array( 'entry_id' => (int) $entry_id ) );
		}
		return;
	}

	hcp_consent_record( $user_id, HCP_CONSENT_TC_ACCEPTED, 'registration', HCP_CONSENT_TERMS_STATEMENT );
}

// Track whether the form was rendered on this page, so the button script only
// prints where it is needed (the header pop-up renders it on most pages).
add_action( 'frm_entry_form', 'hcp_consent_mark_reg_form_rendered' );
function hcp_consent_mark_reg_form_rendered( $form ): void {
	if ( HCP_CONSENT_REG_FORM_ID === (int) ( $form->id ?? 0 ) ) {
		$GLOBALS['hcp_consent_reg_form_rendered'] = true;
	}
}

add_action( 'wp_footer', 'hcp_consent_reg_button_script', 100 );
function hcp_consent_reg_button_script(): void {
	$field_id = hcp_consent_terms_field_id();
	if ( empty( $GLOBALS['hcp_consent_reg_form_rendered'] ) || ! $field_id ) {
		return;
	}
	?>
	<script>
	(function () {
		var name = 'item_meta[<?php echo (int) $field_id; ?>][]';
		function sync(form) {
			var box = form.querySelector('input[name="' + name + '"]');
			var btn = form.querySelector('button[type="submit"], input[type="submit"]');
			if (box && btn) btn.disabled = !box.checked;
		}
		function syncAll() {
			document.querySelectorAll('form').forEach(function (form) {
				if (form.querySelector('input[name="' + name + '"]')) sync(form);
			});
		}
		// Delegated, because an AJAX submit with errors replaces the form.
		document.addEventListener('change', function (ev) {
			var form = ev.target.closest && ev.target.closest('form');
			if (form && form.querySelector('input[name="' + name + '"]')) sync(form);
		});
		if (window.jQuery) window.jQuery(document).on('frmFormErrors frmFormComplete', syncAll);
		syncAll();
	})();
	</script>
	<?php
}
