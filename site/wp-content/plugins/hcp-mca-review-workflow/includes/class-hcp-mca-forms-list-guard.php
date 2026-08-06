<?php
/**
 * Formidable forms list with the audit forms removed.
 *
 * Extends HCP_MCA_Forms_List_Base, an alias set at runtime to whichever list
 * class Formidable settled on, so the Pro list helper is preserved rather than
 * displaced. See hcp_mca_filter_forms_list_class().
 *
 * The parent builds and runs its query inside prepare_items() with no
 * insertion point, so rows are dropped afterwards and the pagination total
 * adjusted to match.
 */

defined( 'ABSPATH' ) || exit;

class HCP_MCA_Forms_List_Guard extends HCP_MCA_Forms_List_Base {

	public function prepare_items() {
		parent::prepare_items();

		$restricted = hcp_mca_restricted_form_ids();
		$removed    = 0;

		foreach ( (array) $this->items as $key => $item ) {
			if ( in_array( (int) $item->id, $restricted, true ) ) {
				unset( $this->items[ $key ] );
				++$removed;
			}
		}

		if ( ! $removed ) {
			return;
		}

		$this->items       = array_values( $this->items );
		$this->total_items = max( 0, (int) $this->total_items - $removed );

		$this->set_pagination_args(
			[
				'total_items' => $this->total_items,
				'per_page'    => $GLOBALS['per_page'],
			]
		);
	}
}
