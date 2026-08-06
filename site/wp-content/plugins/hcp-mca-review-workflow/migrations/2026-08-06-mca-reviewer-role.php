<?php
/**
 * Registers the hcp_mca_reviewer role and grants it to the Panwar Health CPD
 * accounts. The role is additive: it sits alongside administrator rather than
 * replacing it, so the accounts keep every capability they already had.
 *
 * Until this runs, hcp_mca_current_user_can_view() falls back to
 * manage_options and nothing is restricted.
 */

defined( 'ABSPATH' ) || exit;

return [
	'description' => 'Register hcp_mca_reviewer role and grant to Rob-Panwar + Panwar-education',
	'up'          => function (): void {

		if ( null === get_role( HCP_MCA_ROLE ) ) {
			add_role(
				HCP_MCA_ROLE,
				'MCA Reviewer',
				[
					'read'           => true,
					HCP_MCA_VIEW_CAP => true,
				]
			);
		} else {
			// Role exists but may predate the capability, e.g. a partial run.
			get_role( HCP_MCA_ROLE )->add_cap( HCP_MCA_VIEW_CAP );
		}

		$logins = [ 'Rob-Panwar', 'Panwar-education' ];

		foreach ( $logins as $login ) {
			$user = get_user_by( 'login', $login );

			if ( ! $user ) {
				continue;
			}

			if ( in_array( HCP_MCA_ROLE, (array) $user->roles, true ) ) {
				continue;
			}

			$user->add_role( HCP_MCA_ROLE );
		}
	},
];
