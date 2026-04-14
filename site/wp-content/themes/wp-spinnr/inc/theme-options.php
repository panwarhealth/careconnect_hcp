<?php
/**
 * WP Spinnr Custom Header
 *
 * @package WP_Spinnr
 */

/**
 * Add postMessage support for site title and description for the Theme Customizer.
 *
 * @param WP_Customize_Manager $wp_customize Theme Customizer object.
 */
function themeslug_sanitize_checkbox( $checked ) {
    // Boolean check.
    return ( ( isset( $checked ) && true == $checked ) ? true : false );
}

function wp_spinnr_customize_register( $wp_customize ) {

    $wp_customize->add_section(
        'theme_options',
        array(
            'title' => __( 'Theme Options', 'wp-spinnr' ),
            'priority' => 20,
        )
    );

    // Add control for logo uploader
    $wp_customize->add_setting( 'wp_spinnr_logo', array(
        'sanitize_callback' => 'esc_url',
    ) );
    $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'wp_spinnr_logo', array(
        'label'    => __( 'Upload Logo (replaces text)', 'wp-spinnr' ),
        'section'  => 'theme_options',
        'settings' => 'wp_spinnr_logo',
    ) ) );

    // Add control for header type
    $wp_customize->add_setting( 'header_type', array(
        'default'   => 'header1',
        'type'       => 'theme_mod',
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'wp_filter_nohtml_kses',
    ) );
    $wp_customize->add_control( new WP_Customize_Control($wp_customize, 'header_type', array(
        'label' => __( 'Header Type', 'wp-spinnr' ),
        'section'    => 'theme_options',
        'settings'   => 'header_type',
        'type'    => 'select',
        'choices' => array(
            'header1' => 'Header 1',
            'header2' => 'Header 2',
            'header3' => 'Header 3',
            'header4' => 'Header 4',
            'header5' => 'Header 5',
        )
    ) ) );

    // Add Control for header colors
    $wp_customize->add_setting( 'header_class', array(
        'default' => 'bg-light',
        'sanitize_callback' => 'wp_filter_nohtml_kses',
    ) );
    $wp_customize->add_control( new WP_Customize_Control($wp_customize, 'header_class', array(
        'label' => __( 'Header Class', 'wp-spinnr' ),
        'section'    => 'theme_options',
        'settings'   => 'header_class',
        'type' => 'text'
    ) ) );

    // Add Control for header colors
    $wp_customize->add_setting( 'header_link_class', array(
        'default' => 'text-dark',
        'sanitize_callback' => 'wp_filter_nohtml_kses',
    ) );
    $wp_customize->add_control( new WP_Customize_Control($wp_customize, 'header_link_class', array(
        'label' => __( 'Header Link Class', 'wp-spinnr' ),
        'section'    => 'theme_options',
        'settings'   => 'header_link_class',
        'type' => 'text'
    ) ) );

    // Add control for logo uploader
    $wp_customize->add_setting( 'wp_spinnr_footer_logo', array(
        'sanitize_callback' => 'esc_url',
    ) );
    $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'wp_spinnr_footer_logo', array(
        'label'    => __( 'Upload Footer Logo (replaces text)', 'wp-spinnr' ),
        'section'  => 'theme_options',
        'settings' => 'wp_spinnr_footer_logo',
    ) ) );

    // Add control for footer type
    $wp_customize->add_setting( 'footer_type', array(
        'default'   => 'footer1',
        'type'       => 'theme_mod',
        'capability' => 'edit_theme_options',
        'sanitize_callback' => 'wp_filter_nohtml_kses',
    ) );
    $wp_customize->add_control( new WP_Customize_Control($wp_customize, 'footer_type', array(
        'label' => __( 'Footer Type', 'wp-spinnr' ),
        'section'    => 'theme_options',
        'settings'   => 'footer_type',
        'type'    => 'select',
        'choices' => array(
            'footer1' => 'Footer 1',
            'footer2' => 'Footer 2',
            'footer3' => 'Footer 3',
            'footer4' => 'Footer 4',
            'footer5' => 'Footer 5',
            'footer6' => 'Footer 6',
            'footer7' => 'Footer 7',
            'footer8' => 'Footer 8',
            'footer3cols' => '3 Columns',
            'footer4cols' => '4 Columns',
            'footer5cols' => '5 Columns',
        )
    ) ) );

    $wp_customize->add_setting( 'footer_class', array(
        'default' => 'bg-light',
        'sanitize_callback' => 'wp_filter_nohtml_kses',
    ) );
    $wp_customize->add_control( new WP_Customize_Control($wp_customize, 'footer_class', array(
        'label' => __( 'Footer Class', 'wp-spinnr' ),
        'section'    => 'theme_options',
        'settings'   => 'footer_class',
        'type' => 'text'
    ) ) );

    $wp_customize->add_setting( 'footer_link_class', array(
        'default' => 'text-dark',
        'sanitize_callback' => 'wp_filter_nohtml_kses',
    ) );
    $wp_customize->add_control( new WP_Customize_Control($wp_customize, 'footer_link_class', array(
        'label' => __( 'Footer Link Class', 'wp-spinnr' ),
        'section'    => 'theme_options',
        'settings'   => 'footer_link_class',
        'type' => 'text'
    ) ) );

}
add_action( 'customize_register', 'wp_spinnr_customize_register' );
