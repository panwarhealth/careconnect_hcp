<?php

/**
 * Function for encrypting data
 *
 * @package WP_SPINNR
 */

/**
 * Do not update or you may lose connection to SPINNR.
 */
if (!function_exists('wp_spinnr_encrypt')) :
    function wp_spinnr_encrypt($data)
    {

        $method = "aes-256-cbc";
        $iv_length = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($iv_length);

        $encypted = openssl_encrypt(json_encode($data), $method, SECURE_AUTH_SALT, OPENSSL_RAW_DATA, $iv);

        return strtr(base64_encode($iv . $encypted), '+/=', '-_.');
    }
endif;
