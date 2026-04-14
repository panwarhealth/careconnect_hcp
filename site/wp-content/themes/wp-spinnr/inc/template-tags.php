<?php

/**
 * Custom template tags for this theme
 *
 * Eventually, some of the functionality here could be replaced by core features.
 *
 * @package WP_SPINNR
 */

if (!function_exists('wp_spinnr_comment')) :
    /**
     * Template for comments and pingbacks.
     *
     * Used as a callback by wp_list_comments() for displaying the comments.
     */
    function wp_spinnr_comment($comment, $args, $depth)
    {

        if ('pingback' == $comment->comment_type || 'trackback' == $comment->comment_type) : ?>

            <li id="comment-<?php comment_ID(); ?>" <?php comment_class('media'); ?>>
                <div class="comment-body">
                    <?php _e('Pingback:', 'wp-spinnr'); ?> <?php comment_author_link(); ?> <?php edit_comment_link(__('Edit', 'wp-spinnr'), '<span class="edit-link">', '</span>'); ?>
                </div>

            <?php else : ?>

            <li id="comment-<?php comment_ID(); ?>" <?php comment_class(empty($args['has_children']) ? '' : 'parent'); ?>>
                <article id="div-comment-<?php comment_ID(); ?>" class="comment-body media flex mb-lg">
                    <a class="flex-shrink-0 mr-base" href="#">
                        <?php echo get_avatar($comment); ?>
                    </a>

                    <div class="media-body">
                        <div class="card max-w-3xl">
                            <div class="card-body">
                                <p class="mt-0"><?php printf(__('%s <span class="says">says:</span>', 'wp-spinnr'), sprintf('<cite class="fn">%s</cite>', get_comment_author_link())); ?></p>
                                <div class="comment-meta mb-lg">
                                    <a href="<?php echo esc_url(get_comment_link($comment->comment_ID)); ?>">
                                        <time datetime="<?php comment_time('c'); ?>">
                                            <?php printf(_x('%1$s at %2$s', '1: date, 2: time', 'wp-spinnr'), get_comment_date(), get_comment_time()); ?>
                                        </time>
                                    </a>
                                    <?php edit_comment_link(__('<span style="margin-left: 5px;" class="glyphicon glyphicon-edit"></span> Edit', 'wp-spinnr'), '<span class="edit-link">', '</span>'); ?>
                                </div>

                                <?php if ('0' == $comment->comment_approved) : ?>
                                    <p class="comment-awaiting-moderation"><?php _e('Your comment is awaiting moderation.', 'wp-spinnr'); ?></p>
                                <?php endif; ?>

                                <div class="comment-content card-block">
                                    <?php comment_text(); ?>
                                </div><!-- .comment-content -->

                                <?php comment_reply_link(
                                    array_merge(
                                        $args,
                                        array(
                                            'add_below' => 'div-comment',
                                            'depth'     => $depth,
                                            'max_depth' => $args['max_depth'],
                                            'before'     => '<footer class="reply comment-reply card-footer">',
                                            'after'     => '</footer><!-- .reply -->'
                                        )
                                    )
                                ); ?>
                            </div>

                        </div>
                    </div><!-- .media-body -->
                </article><!-- .comment-body -->
    <?php
        endif;
    }
endif; // ends check for wp_spinnr_comment()


/**
 * Function to override the password form for password protected pages.
 */
if (!function_exists('wp_spinnr_password_form')) :
    function wp_spinnr_password_form()
    {
        global $post;

        $label = 'pwbox-' . (empty($post->ID) ? rand() : $post->ID);
        $output = '<div class="max-w-lg mx-auto my-3xl"><form action="' . get_option('siteurl') . '/wp-login.php?action=postpass" method="post">
    <p>' . __("This content is password protected.<br>To view it please enter your password below:", 'wp-spinnr') . '</p>
    <div class="flex items-center space-x-sm"><input class="w-full m-0" name="post_password" type="password" size="20" /><input class="btn cta m-0" type="submit" name="Submit" value="' . esc_attr__("Submit", 'wp-spinnr') . '" /></div>
    </form></div>';

        return $output;
    }
    add_filter('the_password_form', 'wp_spinnr_password_form');
endif;
