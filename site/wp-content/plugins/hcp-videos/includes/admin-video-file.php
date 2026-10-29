<?php
/**
 * "Video file" box on the Video edit screen: upload or replace the video,
 * set the poster, and add captions.
 *
 * The browser converts the chosen file to a 1080p H.264 MP4 when needed
 * (assets/transcode-worker.js), uploads it straight to R2 with a presigned
 * URL, and saves the result to the post over AJAX as soon as each step
 * finishes, so nothing depends on pressing Update afterwards.
 *
 * Every upload gets a new timestamped key: CDN objects are cached as
 * immutable, so a replaced file must never reuse an old URL.
 */

defined( 'ABSPATH' ) || exit;

const HCP_VIDEOS_MEDIABUNNY_URL = 'https://cdn.jsdelivr.net/npm/mediabunny@1.59.1/dist/bundles/mediabunny.min.mjs';

add_action( 'add_meta_boxes_video', function () {
	add_meta_box( 'hcp-video-file', 'Video file', 'hcp_videos_file_box', 'video', 'normal', 'high' );
} );

function hcp_videos_file_box( WP_Post $post ): void {
	$src      = hcp_videos_src( $post->ID );
	$poster   = (string) get_post_meta( $post->ID, '_hcpvid_thumb', true );
	$captions = '' !== (string) get_post_meta( $post->ID, HCP_VIDEOS_CAPTIONS_META, true );
	$duration = hcp_videos_duration( $post->ID );
	?>
	<div id="hcp-vf" data-src="<?php echo esc_attr( $src ); ?>">
		<?php if ( ! hcp_videos_r2_configured() ) : ?>
			<div class="notice notice-error inline"><p>Uploads are not configured on this site (missing R2 credentials). The current video still plays.</p></div>
		<?php endif; ?>

		<div class="hcp-vf-preview">
			<div class="hcp-vf-stage">
				<video controls preload="metadata" playsinline <?php echo $src ? 'src="' . esc_url( $src ) . '"' : ''; ?> <?php echo $poster ? 'poster="' . esc_url( $poster ) . '"' : ''; ?>></video>
			</div>
			<p><button type="button" class="button hcp-vf-endcard-toggle">Preview end card</button></p>
			<p class="hcp-vf-current">
				<?php if ( $src ) : ?>
					Current file: <a href="<?php echo esc_url( $src ); ?>" target="_blank" rel="noopener"><?php echo esc_html( basename( wp_parse_url( $src, PHP_URL_PATH ) ) ); ?></a>
					<?php echo $duration ? '&middot; ' . esc_html( $duration ) : ''; ?>
				<?php else : ?>
					No video uploaded yet.
				<?php endif; ?>
			</p>
		</div>

		<div class="hcp-vf-controls">
			<p>
				<label class="button button-primary">
					<?php echo $src ? 'Replace video' : 'Upload video'; ?>
					<input type="file" accept="video/*" class="hcp-vf-video-input" hidden>
				</label>
				<span class="description">Any common video format. Files larger than 1080p are converted automatically (Chrome or Edge).</span>
			</p>
			<div class="hcp-vf-poster">
				<img class="hcp-vf-poster-img" alt="Current poster" <?php echo $poster ? 'src="' . esc_url( $poster ) . '"' : 'hidden'; ?>>
				<span class="hcp-vf-poster-none" <?php echo $poster ? 'hidden' : ''; ?>>No poster yet.</span>
			</div>
			<p>
				<strong>Poster:</strong>
				<button type="button" class="button hcp-vf-frame" disabled>Use current frame</button>
				<label class="button">
					Upload image
					<input type="file" accept="image/jpeg,image/png,image/webp" class="hcp-vf-poster-input" hidden>
				</label>
				<span class="description">After uploading a video, pause the preview on the frame you want and click "Use current frame". A Featured image, if set, is shown instead.</span>
			</p>
			<p>
				<strong>Captions:</strong>
				<span class="hcp-vf-captions-state"><?php echo $captions ? 'Added' : 'None'; ?></span>
				<label class="button">
					<?php echo $captions ? 'Replace .vtt' : 'Add .vtt'; ?>
					<input type="file" accept=".vtt,text/vtt" class="hcp-vf-captions-input" hidden>
				</label>
				<button type="button" class="button-link hcp-vf-captions-remove" <?php echo $captions ? '' : 'hidden'; ?>>Remove</button>
			</p>
		</div>

		<div class="hcp-vf-status" hidden>
			<p class="hcp-vf-message"></p>
			<progress max="100" value="0"></progress>
			<p class="hcp-vf-keep-open">Please keep this tab open until this finishes. You can use other tabs in the meantime.</p>
		</div>
	</div>
	<?php
}

add_action( 'admin_enqueue_scripts', function ( string $hook ) {
	$screen = get_current_screen();
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! $screen || 'video' !== $screen->post_type ) {
		return;
	}

	$css = '#hcp-vf .hcp-vf-stage{position:relative;width:100%;max-width:560px;aspect-ratio:16/9;background:#000;border-radius:4px;overflow:hidden;}'
		. '#hcp-vf .hcp-vf-stage video{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;}'
		. '#hcp-vf .hcp-vf-poster img{display:block;width:240px;max-width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:4px;background:#000;}'
		. '#hcp-vf .hcp-vf-poster{margin:12px 0 4px;}'
		. '#hcp-vf .hcp-vf-poster-none{color:#646970;font-style:italic;}'
		. '#hcp-vf .hcp-vf-controls .button{margin-right:4px;}'
		. '#hcp-vf .hcp-vf-status{max-width:560px;padding:8px 12px;background:#f6f7f7;border-left:4px solid #2271b1;}'
		. '#hcp-vf .hcp-vf-status.is-error{border-left-color:#d63638;}'
		. '#hcp-vf .hcp-vf-status.is-done{border-left-color:#00a32a;}'
		. '#hcp-vf .hcp-vf-status progress{width:100%;}'
		. '#hcp-vf .hcp-vf-keep-open{margin:4px 0 0;color:#646970;font-style:italic;}';
	wp_register_style( 'hcp-videos-file', false, array(), '1' );
	wp_enqueue_style( 'hcp-videos-file' );
	wp_add_inline_style( 'hcp-videos-file', $css );

	hcp_videos_enqueue_player_script();
	wp_enqueue_script(
		'hcp-videos-file',
		plugins_url( 'assets/admin-video-file.js', HCP_VIDEOS_FILE ),
		array( 'hcp-videos-player' ),
		(string) filemtime( HCP_VIDEOS_DIR . 'assets/admin-video-file.js' ),
		true
	);
	wp_localize_script( 'hcp-videos-file', 'hcpVideoFile', array(
		'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
		'nonce'      => wp_create_nonce( 'hcp_videos_file' ),
		'postId'     => (int) get_the_ID(),
		'configured' => hcp_videos_r2_configured(),
		'workerUrl'  => add_query_arg( 'ver', (string) filemtime( HCP_VIDEOS_DIR . 'assets/transcode-worker.js' ), plugins_url( 'assets/transcode-worker.js', HCP_VIDEOS_FILE ) ),
		'libraryUrl' => HCP_VIDEOS_MEDIABUNNY_URL,
		'autoCard'   => get_the_ID() ? hcp_videos_endcard_auto( (int) get_the_ID() ) : null,
		'videoBase'  => home_url( '/video/' ),
	) );
} );

/**
 * Checks the nonce and edit rights for the posted video, or ends the request.
 */
function hcp_videos_file_request_post(): WP_Post {
	check_ajax_referer( 'hcp_videos_file', 'nonce' );
	$post = get_post( (int) ( $_POST['post_id'] ?? 0 ) );
	if ( ! $post || 'video' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
		wp_send_json_error( array( 'message' => 'You cannot edit this video.' ), 403 );
	}
	return $post;
}

/**
 * Presigned upload URL for a new video or poster file.
 */
add_action( 'wp_ajax_hcp_videos_upload_url', function () {
	$post = hcp_videos_file_request_post();
	if ( ! hcp_videos_r2_configured() ) {
		wp_send_json_error( array( 'message' => 'Uploads are not configured on this site.' ), 500 );
	}

	$kind  = sanitize_key( $_POST['kind'] ?? '' );
	$types = array(
		'video'  => array( 'mp4' => 'video/mp4' ),
		'poster' => array( 'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ),
	);
	$ext = sanitize_key( $_POST['ext'] ?? '' );
	if ( ! isset( $types[ $kind ][ $ext ] ) ) {
		wp_send_json_error( array( 'message' => 'Unsupported file type.' ), 400 );
	}

	$slug = $post->post_name ?: sanitize_title( $post->post_title ) ?: 'video-' . $post->ID;
	$file = ( 'video' === $kind ? $slug : 'poster' ) . '-' . gmdate( 'YmdHis' ) . '.' . $ext;
	$key  = 'videos/' . $slug . '/' . $file;

	wp_send_json_success( array(
		'uploadUrl'    => hcp_videos_r2_presign_put( $key ),
		'publicUrl'    => hcp_videos_r2_public_url( $key ),
		'contentType'  => $types[ $kind ][ $ext ],
		'cacheControl' => 'public, max-age=31536000, immutable',
	) );
} );

/**
 * Saves whatever the browser has just finished: video URL + duration, poster,
 * or captions.
 */
add_action( 'wp_ajax_hcp_videos_save_file', function () {
	$post = hcp_videos_file_request_post();

	$is_ours = function ( string $url ): bool {
		return 0 === strpos( $url, HCP_VIDEOS_R2_PUBLIC_BASE );
	};

	$src = esc_url_raw( wp_unslash( $_POST['src'] ?? '' ) );
	if ( '' !== $src ) {
		if ( ! $is_ours( $src ) ) {
			wp_send_json_error( array( 'message' => 'Unexpected video URL.' ), 400 );
		}
		update_post_meta( $post->ID, 'video_src', $src );
		$seconds = (int) round( (float) ( $_POST['duration'] ?? 0 ) );
		if ( $seconds > 0 ) {
			update_post_meta( $post->ID, '_hcpvid_duration', hcp_videos_format_duration( $seconds ) );
		}
	}

	$poster = esc_url_raw( wp_unslash( $_POST['poster'] ?? '' ) );
	if ( '' !== $poster ) {
		if ( ! $is_ours( $poster ) ) {
			wp_send_json_error( array( 'message' => 'Unexpected poster URL.' ), 400 );
		}
		update_post_meta( $post->ID, '_hcpvid_thumb', $poster );
	}

	if ( isset( $_POST['captions'] ) ) {
		$vtt = str_replace( "\r\n", "\n", (string) wp_unslash( $_POST['captions'] ) );
		if ( '' === $vtt ) {
			delete_post_meta( $post->ID, HCP_VIDEOS_CAPTIONS_META );
		} elseif ( hcp_videos_is_vtt( $vtt ) ) {
			update_post_meta( $post->ID, HCP_VIDEOS_CAPTIONS_META, wp_slash( $vtt ) );
		} else {
			wp_send_json_error( array( 'message' => 'That file is not a WebVTT (.vtt) caption file.' ), 400 );
		}
	}

	wp_send_json_success( array( 'duration' => hcp_videos_duration( $post->ID ) ) );
} );
