<?php
/**
 * Thrive Downtown — snippet 08  (OPTIONAL / secondary)
 * Scope Smart Slider assets to the homepage; drop them elsewhere.
 *
 * PRIMARY, SAFEST route is FlyingPress JS/CSS exclusions (see FlyingPress config
 * doc) + snippet 03. Use this PHP only as a belt-and-suspenders dequeue.
 *
 * WPCode: PHP Snippet | Auto Insert | Location: Run Everywhere (Frontend)
 * Reversible: DEACTIVATE this snippet.
 *
 * CAVEAT: Smart Slider 3 often enqueues its assets ON DEMAND at render time,
 * which can be LATER than wp_enqueue_scripts. If View-Source shows slider assets
 * still loading on non-home pages despite this snippet, the assets are enqueued
 * on-render — use the FlyingPress exclude route instead. This snippet only helps
 * when Smart Slider enqueues globally.
 *
 * BEFORE USING: confirm the REAL handle names via snippet 02 (View-Source, search
 * "smartslider" / "n2-"). Then TEST the homepage slider AND a subpage.
 */
add_action( 'wp_enqueue_scripts', function () {
    // Homepage keeps the slider; everywhere else drops its assets.
    // If sliders are ALSO used on specific other pages, widen this guard, e.g.:
    // if ( is_front_page() || is_page( array( 'about', 'services' ) ) ) { return; }
    if ( is_front_page() ) {
        return;
    }
    // VERIFY these handle names via snippet 02 — Smart Slider versions them.
    foreach ( array( 'smartslider-frontend', 'n2-runtime', 'n2-css', 'smartslider-frontend-css' ) as $handle ) {
        if ( wp_script_is( $handle, 'enqueued' ) ) {
            wp_dequeue_script( $handle );
        }
        if ( wp_style_is( $handle, 'enqueued' ) ) {
            wp_dequeue_style( $handle );
        }
    }
}, 100 );
