<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WP_SPINNR
 */

?>
		</div><!-- #content -->
		<?php wp_spinnr_load_footer(); ?>

		<?php
		$is_excluded_page = is_page( array( 'log-in', 'register', 'forgot-password' ) ) || $GLOBALS['pagenow'] === 'wp-login.php';
		if (!is_user_logged_in() && !$is_excluded_page) {
		?>
		<div id="popup-overlay" class="hidden fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center cursor-pointer">
		    
			<div id="register-popup-content" class="relative bg-white p-8 shadow-2xl w-11/12 max-w-2xl text-center cursor-default overflow-y-auto max-h-full hidden">
				<span class="login-popup-close absolute top-2 right-4 text-gray-400 hover:text-gray-800 text-3xl font-bold cursor-pointer transition-colors">&times;</span>
			    <h3 class="">Register for Care Connect </h3>
			    <p class="">Join thousands of other HCPs providing better care together</p>
			    <?php echo do_shortcode('[formidable id=2 popupval="1" current_url="'. ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] .'"]'); ?>
			</div>   
			<div id="login-popup-content" class="relative bg-white p-8 shadow-2xl w-11/12 max-w-md text-center cursor-default hidden">
				<span class="login-popup-close absolute top-2 right-4 text-gray-400 hover:text-gray-800 text-3xl font-bold cursor-pointer transition-colors">&times;</span>
				<h3 class="mb-6 text-2xl font-semibold text-gray-800">Login to Your Account</h3>
				<div id="login-popup-message" class="mb-4 text-sm text-accent"></div>
				<?php
				$args = array(
					'redirect'       => ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
					'form_id'        => 'loginform-popup',
					'label_username' => __('Username or Email Address'),
					'label_password' => __('Password'),
					'label_remember' => __('Remember Me'),
					'label_log_in'   => __('Log In'),
					'remember'       => true,
				);
				wp_login_form($args);
				?>
			</div>
            
            <div id="racgp-popup-content" class="relative bg-white p-8 shadow-2xl w-11/12 max-w-md text-center cursor-default hidden">
                <span class="login-popup-close absolute top-2 right-4 text-gray-400 hover:text-gray-800 text-3xl font-bold cursor-pointer transition-colors">×</span>
                <h3 class="mb-4 text-2xl font-semibold text-gray-800">Enter your RACGP number to continue</h3>
                <p class="mb-6 text-sm text-gray-600 text-left">Your RACGP number is required to enable recording of CPD hours following completion of the activity.</p>
                <div id="racgp-popup-message" class="mb-4 text-sm text-accent"></div>

                <form id="racgp-details-form">
                    <div class="mb-4 text-left">
                        <label class="block text-sm font-medium text-gray-700 mb-1">RACGP number*</label>
                        <input type="text" id="racgp_number" name="racgp_number" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 focus:outline-none">
                    </div>
                    
                    <div class="mb-6 flex items-start text-left">
                        <input type="checkbox" id="racgp_consent" name="racgp_consent" required class="">
                        <label for="racgp_consent" class="text-sm text-gray-600" style="margin-left: 1rem;">I consent to my information being used to complete CPD reporting requirements with the RACGP*</label>
                    </div>

                    <button type="submit" class="button button-primary w-full bg-blue-600 text-white py-3 px-4 my-2 border-0 rounded-lg cursor-pointer text-base font-bold transition-colors hover:bg-blue-700 btn cta">Submit</button>
                </form>
            </div>
		</div>
		
	<script type="text/javascript">
        (function($) {
            $(document).ready(function() {
                const modal = $('#popup-overlay');
                const closeBtn = $('.login-popup-close');
                const form = $('#loginform-popup');
                const messageDiv = $('#login-popup-message');
                const loginContent = $('#login-popup-content');
                const registerContent = $('#register-popup-content');
                const racgpContent = $('#racgp-popup-content');
                const racgpForm = $('#racgp-details-form');
                const racgpMessageDiv = $('#racgp-popup-message');

                if (form.length) {
                    form.find('p > label').addClass('block text-left text-sm font-medium text-gray-700 mb-1');
                    form.find('input[type="text"], input[type="password"]').addClass('w-full p-3 mb-4 border border-gray-300 rounded-lg transition-colors focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none');
                    form.find('input[type="submit"]').addClass('w-full bg-blue-600 text-white py-3 px-4 my-2 border-0 rounded-lg cursor-pointer text-base font-bold transition-colors hover:bg-blue-700');                    
                    form.find('.login-remember').addClass('text-left mb-4');
                    form.find('.login-remember label').addClass('text-sm text-gray-600 font-normal');         
                    form.parent().find('#nav').addClass('text-sm');
                    form.parent().find('#nav a').addClass('text-blue-600 no-underline hover:underline');
					form.find('.login-submit .button').addClass('btn cta');
                }

                const handleModalClose = function() {
                    // If the user is on the RACGP screen and closes the modal, reload the page
                    if (!racgpContent.hasClass('hidden')) {
                        window.location.reload();
                    } else {
                        modal.fadeOut(300);
                    }
                };

                closeBtn.on('click', function() {
                    handleModalClose();
                });

                modal.on('click', function(event) {
                    if (event.target.id === 'popup-overlay') {
                        handleModalClose();
                    }
                });

                $(document).on('keydown', function(event) {
                    if (event.key === "Escape" && modal.is(':visible')) {
                        handleModalClose();
                    }
                });
                
                // --- AJAX Form Submission ---
                form.on('submit', function(e) {
                    e.preventDefault(); // Prevent default page refresh

                    messageDiv.text('Processing...').removeClass('text-error text-green').addClass('text-accent');

                    $.ajax({
                        type: 'POST',
                        url: login_popup_obj.ajax_url,
                        data: {
                            action: 'popup_login',
                            username: form.find('#user_login').val(),
                            password: form.find('#user_pass').val(),
                            rememberme: form.find('#rememberme').is(':checked') ? 'forever' : '',
                            page_id: '<?php echo get_the_ID(); ?>',
                            security: login_popup_obj.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                messageDiv.text(response.data.message).removeClass('text-error').addClass('text-green');
                                // Redirect after a short delay
                                if(response.data.message == "RACGP"){
                                    loginContent.addClass('hidden');
                                    racgpContent.removeClass('hidden');
                                } else { 
                                    if (window.location.pathname === "/fess-demonstration/" || window.location.pathname === "/fess-demonstration") {
                                        window.location.href = "/tools-and-videos#fess-children-nasal-spray";
                                        return; 
                                    }
                                    else {
                                        setTimeout(function() {
                                            window.location.reload();
                                        }, 1000);
                                    }
                                 }
                            } else {
                                messageDiv.text(response.data.message).removeClass('text-green').addClass('text-error');
                            }
                        },
                        error: function() {
                            messageDiv.text('An error occurred. Please try again.').addClass('text-error');
                        }
                    });
                });

                // RACGP Form Submission handler:
                racgpForm.on('submit', function(e) {
                    e.preventDefault();
                    racgpMessageDiv.text('Processing...').removeClass('text-error text-green').addClass('text-accent');
                    $.ajax({
                        type: 'POST',
                        url: racgp_ajax_vars.ajax_url,
                        data: {
                            action: 'save_racgp_data',
                            racgp_number: $('#racgp_number').val(),
                            security: racgp_ajax_vars.nonce
                        },
                        success: function(res) {
                            messageDiv.text('RACGP Updated Successfully.').removeClass('text-error').addClass('text-green');
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        },
                         error: function() {
                            racgpMessageDiv.text('An error occurred. Please try again.').addClass('text-error');
                        }
                    });
                });

				$('.logged_in_users_only').each(function () {
					const $section = $(this);
					//$section.addClass('logged_in_users_only');

					const overlay = $('<div class="login-overlay">' +
					'<span class="wpr bg-white p-base rounded-sm shadow-md"><a href="javascript:void(0);" class="m-0 btn cta shadow-md loginBtn">Login</a> or <a href="javascript:void(0);" class="m-0 btn cta shadow-md registerBtn">Register</a> to view&nbsp;  <i class="-rotate-45 fa-arrow-right fas transform ml-md text-accent"></i></span>' +
					'</div>');
					$section.append(overlay);
					
                	const clckbtn = overlay.find('.loginBtn');
                	const regbtn = overlay.find('.registerBtn');
					clckbtn.click(function(){
					    loginContent.removeClass('hidden');
					    registerContent.addClass('hidden');
                		modal.removeClass('hidden').hide().fadeIn(400);
					});
					regbtn.click(function(){
					    registerContent.removeClass('hidden');
					    loginContent.addClass('hidden');
                		modal.removeClass('hidden').hide().fadeIn(400);
					});
				});
            });
        })(jQuery);
    </script>

		<?php } ?>

	</div><!-- #page -->

	<?php wp_footer(); ?>

</body>
</html>