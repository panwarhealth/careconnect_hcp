<?php
/**
 * Staging only: stop WordPress updating itself.
 *
 * Staging has outbound internet and prod does not, so without this the first
 * page loads auto-update core away from prod's version and can leave the site
 * stuck behind a .maintenance file. Deployed to wp-content/mu-plugins/ by
 * staging-prod-code.sh; never deployed to prod.
 */
add_filter( 'automatic_updater_disabled', '__return_true' );
add_filter( 'auto_update_plugin', '__return_false' );
add_filter( 'auto_update_theme', '__return_false' );
