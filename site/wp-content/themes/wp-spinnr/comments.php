<?php

/**
 * The template for displaying comments
 *
 * This is the template that displays the area of the page that contains both the current comments
 * and the comment form.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WP_SPINNR
 */

/*
 * If the current post is protected by a password and
 * the visitor has not yet entered the password we will
 * return early without loading the comments.
 */
if (post_password_required()) {
    return;
}
?>

<div id="comments" class="comments-area mt-lg">

    <?php
    if (have_comments()) : ?>

        <h3 class="comments-title">Comments</h3><!-- .comments-title -->


        <?php if (get_comment_pages_count() > 1 && get_option('page_comments')) : ?>
            <nav id="comment-nav-above" class="navigation comment-navigation" role="navigation">
                <h2 class="screen-reader-text"><?php esc_html_e('Comment navigation', 'wp-spinnr'); ?></h2>
                <div class="nav-links">

                    <div class="nav-previous"><?php previous_comments_link(esc_html__('Older Comments', 'wp-spinnr')); ?></div>
                    <div class="nav-next"><?php next_comments_link(esc_html__('Newer Comments', 'wp-spinnr')); ?></div>

                </div><!-- .nav-links -->
            </nav><!-- #comment-nav-above -->
        <?php endif; ?>

        <ul class="comment-list list-none">
            <?php
            wp_list_comments(array('callback' => 'wp_spinnr_comment'));
            ?>
        </ul><!-- .comment-list -->

        <?php if (get_comment_pages_count() > 1 && get_option('page_comments')) : // Are there comments to navigate through? 
        ?>
            <nav id="comment-nav-below" class="navigation comment-navigation" role="navigation">
                <h2 class="screen-reader-text"><?php esc_html_e('Comment navigation', 'wp-spinnr'); ?></h2>
                <div class="nav-links">

                    <div class="nav-previous"><?php previous_comments_link(esc_html__('Older Comments', 'wp-spinnr')); ?></div>
                    <div class="nav-next"><?php next_comments_link(esc_html__('Newer Comments', 'wp-spinnr')); ?></div>

                </div><!-- .nav-links -->
            </nav><!-- #comment-nav-below -->
        <?php
        endif;

    endif;

    if (!comments_open() && get_comments_number() && post_type_supports(get_post_type(), 'comments')) : ?>
        <p class="no-comments"><?php esc_html_e('Comments are closed.', 'wp-spinnr'); ?></p>
    <?php
    endif; ?>

    <?php comment_form($args = array(
        'id_form'           => 'commentform',
        'id_submit'         => 'commentsubmit',
        'class_submit'      => 'btn cta',
        'title_reply'       => __('Leave a Reply', 'wp-spinnr'),
        'title_reply_to'    => __('Leave a Reply to %s', 'wp-spinnr'),
        'cancel_reply_link' => __('Cancel Reply', 'wp-spinnr'),
        'label_submit'      => __('Post Comment', 'wp-spinnr'),
        'comment_field' =>  '<p><textarea placeholder="Start typing..." id="comment" class="border p-base" name="comment" cols="45" rows="8" aria-required="true"></textarea></p>',
        'comment_notes_after' => '<p class="form-allowed-tags">' .
            __('You may use these <abbr title="HyperText Markup Language">HTML</abbr> tags and attributes:', 'wp-spinnr') .
            '</p><div class="alert alert-info">' . allowed_tags() . '</div>'
    ));

    ?>

</div><!-- #comments -->