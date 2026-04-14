<?php

/**
 * The search form template
 *
 * @link https://codex.wordpress.org/Function_Reference/get_search_form
 *
 * @package WP_SPINNR
 */

?>

<form role="search" method="get" class="search-form" action="<?php echo home_url('/'); ?>">
    <div class="flex items-center space-x-xs">
        <input type="search" class="w-full m-0" placeholder="<?php echo esc_attr_x('Start typing...', 'placeholder') ?>" value="<?php echo get_search_query() ?>" name="s" title="<?php echo esc_attr_x('Search', 'label') ?>" />
        <input type="submit" class="btn cta m-0" value="<?php echo esc_attr_x('Search', 'submit button') ?>" />
    </div>
</form>