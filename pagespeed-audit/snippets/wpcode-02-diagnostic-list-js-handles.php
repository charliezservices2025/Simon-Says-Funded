<?php
/**
 * Thrive Downtown — snippet 02  (TEMPORARY DIAGNOSTIC — REMOVE AFTER USE)
 * Lists every front-end JS handle actually printed, so you can get the REAL
 * script handles for snippet 03 (conditional JS trim) and snippet 07 (scope
 * Smart Slider) instead of guessing.
 *
 * WPCode: PHP Snippet | Auto Insert | Location: Run Everywhere (Frontend)
 *
 * HOW TO USE:
 *   1. Activate this snippet.
 *   2. Open the front-end in an INCOGNITO window (logged OUT) on a page that has
 *      NO slider / share buttons / form (e.g. a plain page). The guard hides the
 *      output from logged-in users, so you must be logged out.
 *   3. View Source and read the "<!-- ENQUEUED JS HANDLES ... -->" comment near
 *      the bottom of the HTML. Also check a blog post and the homepage.
 *   4. DEACTIVATE / DELETE this snippet. It is diagnostic only — do not leave it on.
 */
add_action( 'wp_print_footer_scripts', function () {
    if ( is_admin() || is_user_logged_in() ) {
        return;
    }
    global $wp_scripts;
    if ( empty( $wp_scripts ) || empty( $wp_scripts->done ) ) {
        return;
    }
    // ->done is the authoritative list of everything actually output,
    // including dependencies (jquery, etc.) that ->queue would miss.
    $handles = array_values( array_unique( (array) $wp_scripts->done ) );
    sort( $handles );
    echo "\n<!-- ENQUEUED JS HANDLES (" . count( $handles ) . "): "
        . esc_html( implode( ', ', $handles ) ) . " -->\n";
}, PHP_INT_MAX );
