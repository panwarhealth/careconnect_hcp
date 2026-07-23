<?php
/**
 * Keep the block editor off post types whose content is raw builder HTML.
 * Gutenberg rewrites markup on save; the classic editor's Text view does not.
 *
 * @package WP_SPINNR
 */

add_filter('use_block_editor_for_post_type', function ($use, $post_type) {
    if (in_array($post_type, WP_SPINNR_RAW_TYPES, true)) {
        return false;
    }
    return $use;
}, 10, 2);
