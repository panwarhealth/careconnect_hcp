<?php
/**
 * Theme configuration defaults
 *
 * @package WP_SPINNR
 */

define('TBST_SPINNR_FRAMEWORK', 'tailwind-2');
define('TBST_SPINNR_TEMPLATE_ID', 0);

// Post types whose content is raw builder HTML: rendered without the
// section/sidebar wrapper (index.php) and kept off the block editor
// (inc/editor-lock.php). Formerly the lock_editor option.
define('WP_SPINNR_RAW_TYPES', array(
    'page',
    'post',
    'spinnr_header',
    'spinnr_footer',
    'spinnr_mobile_menu',
    'brands',
    'sfwd-topic',
    'sfwd-lessons',
));
