<?php
/**
 * Thrive Downtown — snippet 06
 * Accessibility: guarantee <html> has a lang attribute (belt-and-suspenders).
 *
 * DO THIS FIRST (native, correct fix): WP Admin → Settings → General →
 *   Site Language → "English (Canada)". WordPress then emits <html lang="en-CA">.
 * This snippet is only a FALLBACK for the rare case a cache/optimizer strips it.
 *
 * WPCode: PHP Snippet | Auto Insert | Location: Run Everywhere (Frontend)
 * Reversible: DEACTIVATE this snippet.
 *
 * Safe: returns $output unchanged when a lang= token already exists (word-boundary
 * check, so it is NOT fooled by xml:lang=), never fatals.
 */
add_filter( 'language_attributes', function ( $output ) {
    if ( ! preg_match( '/(^|\s)lang=/i', (string) $output ) ) {
        $output = trim( 'lang="en-CA" ' . $output );
    }
    return $output;
}, 20 );
