<?php
/**
 * Formidable entries list excluding the audit forms.
 *
 * Extends HCP_MCA_Entries_List_Base, an alias set at runtime to whichever list
 * class Formidable settled on. See hcp_mca_filter_entries_list_class().
 *
 * Filtering at the query keeps the row count and pagination correct, since
 * set_total_items() reuses the same where clause.
 */

defined( 'ABSPATH' ) || exit;

class HCP_MCA_Entries_List_Guard extends HCP_MCA_Entries_List_Base {

	protected function get_search_query( &$join_form_in_query ) {
		$s_query = parent::get_search_query( $join_form_in_query );

		$s_query['it.form_id not'] = hcp_mca_restricted_form_ids();

		return $s_query;
	}
}
