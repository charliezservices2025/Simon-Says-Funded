<?php
/**
 * Thrive Downtown — snippet 01
 * Remove WP emoji script, wp-embed, and jQuery Migrate (front-end only).
 *
 * WPCode: Add Snippet → Add Your Custom Code → PHP Snippet
 *   Insert Method: Auto Insert   |   Location: Run Everywhere (Frontend)
 * Reversible: DEACTIVATE this snippet to restore everything.
 * Risk: LOW. jQuery Migrate removal has a Divi caveat — see the note at the bottom.
 *
 * NOTE for WPCode: you may keep or delete the leading <?php — WPCode tolerates both.
 * NEVER add a closing ?> tag.
 */

/* --- Disable the emoji detection script + styles --- */
add_action( 'init', function () {
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' );
    remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
    remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

    add_filter( 'tiny_mce_plugins', function ( $plugins ) {
        return is_array( $plugins ) ? array_diff( $plugins, array( 'wpemoji' ) ) : array();
    } );
    add_filter( 'wp_resource_hints', function ( $urls, $relation_type ) {
        if ( 'dns-prefetch' === $relation_type ) {
            $urls = array_filter( $urls, function ( $u ) {
                $href = is_array( $u ) ? ( isset( $u['href'] ) ? $u['href'] : '' ) : $u;
                return false === strpos( (string) $href, 's.w.org' );
            } );
        }
        return $urls;
    }, 10, 2 );
}, 20 );

/* --- Drop wp-embed.min.js on the front-end --- */
/* Runs on wp_footer priority 10, before print_footer_scripts (priority 20). */
add_action( 'wp_footer', function () {
    if ( ! is_admin() ) {
        wp_dequeue_script( 'wp-embed' );
    }
} );

/* --- Remove jQuery Migrate but KEEP jQuery core --- */
/* $scripts is passed by reference; mutating it here persists. */
add_action( 'wp_default_scripts', function ( $scripts ) {
    if ( is_admin() ) {
        return;
    }
    if ( ! empty( $scripts->registered['jquery'] ) ) {
        $scripts->registered['jquery']->deps = array_diff(
            (array) $scripts->registered['jquery']->deps,
            array( 'jquery-migrate' )
        );
    }
} );

/**
 * jQuery Migrate caution (Divi-specific): modern Divi does not need Migrate, but
 * an older Divi build or a legacy plugin can rely on deprecated jQuery APIs.
 * After enabling, QA the mobile menu, Smart Slider, and a Gravity Forms submit
 * WITH the browser console open. If you see "$ is not a function" /
 * "jQuery.fn.X is deprecated" errors or broken behavior, DEACTIVATE this snippet
 * to restore Migrate. Fully reversible.
 */
