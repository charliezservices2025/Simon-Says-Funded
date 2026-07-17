<?php
/**
 * Thrive Downtown — snippet 04  (OPTIONAL, low ROI — a few KiB)
 * Drop WordPress core polyfills on the front-end if no block needs them.
 * Targets the "Legacy JavaScript ~11 KiB" diagnostic (mostly vendor bundles you
 * cannot strip; this removes only WP core's own polyfill).
 *
 * WPCode: PHP Snippet | Auto Insert | Location: Run Everywhere (Frontend)
 * Reversible: DEACTIVATE this snippet to restore.
 *
 * TEST after enabling: any page using WP blocks / interactive block UI (forms,
 * galleries, embeds). If a block UI breaks, deactivate this snippet.
 */
add_action( 'wp_enqueue_scripts', function () {
    if ( is_admin() ) {
        return;
    }
    wp_dequeue_script( 'wp-polyfill' );
    wp_dequeue_script( 'regenerator-runtime' );
}, 100 );
