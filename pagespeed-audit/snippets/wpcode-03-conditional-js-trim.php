<?php
/**
 * Thrive Downtown — snippet 03
 * Conditional front-end JS trimming: remove plugin scripts from routes that
 * don't use them (structurally shrinks the ~407 KiB "unused JS" + payload).
 *
 * WPCode: PHP Snippet | Auto Insert | Location: Run Everywhere (Frontend)
 * Reversible: DEACTIVATE this snippet to restore all scripts.
 *
 * BEFORE USING: run snippet 02 to get the REAL handle names for THIS install,
 * then replace every 'VERIFY' handle below. Every wp_dequeue_script() is a no-op
 * if the handle isn't enqueued, so a wrong/absent handle cannot fatal the page —
 * but a wrong handle also won't remove anything, so verify.
 *
 * NOTE: FlyingPress's own JS delay (Load when idle) already hides these from the
 * initial execution path. This snippet additionally REMOVES them from routes that
 * don't need them. Do both.
 */
add_action( 'wp_enqueue_scripts', 'tdc_conditional_js_trim', 100 );
function tdc_conditional_js_trim() {

    // Never touch admin, AJAX, REST, feeds, or the Divi Visual Builder.
    if ( is_admin() || wp_doing_ajax()
        || ( defined( 'REST_REQUEST' ) && REST_REQUEST )
        || is_feed() ) {
        return;
    }
    if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
        return;
    }

    global $post;
    $content = ( $post instanceof WP_Post ) ? (string) $post->post_content : '';

    // ---- Shared Counts: only where share buttons actually render ----
    $has_share = is_singular( 'post' )
              || ( '' !== $content && (
                     false !== strpos( $content, 'shared_counts' )
                  || false !== strpos( $content, 'shared-counts' )
                 ) );
    if ( ! $has_share ) {
        wp_dequeue_script( 'shared-counts' );        // VERIFY handle via snippet 02
    }

    // ---- Mail Mint front-end: only where a Mail Mint form/popup is placed ----
    $has_mailmint = ( '' !== $content && (
                        false !== strpos( $content, 'mailmint' )
                     || false !== strpos( $content, 'mrm' )
                    ) )
                 || has_shortcode( $content, 'mailmint_form' );
    if ( ! $has_mailmint ) {
        wp_dequeue_script( 'mailmint' );             // VERIFY handle via snippet 02
        wp_dequeue_script( 'mint-mail-public' );     // VERIFY handle via snippet 02
    }

    // ---- Smart Slider ----
    // CAUTION: post_content detection MISSES sliders placed via a Divi Theme
    // Builder template, a widget, PHP, or a Divi module that references the slider
    // by numeric ID. Keep enqueued unless you CONFIRMED no slider renders here.
    $has_slider = is_front_page()
               || ( '' !== $content && false !== strpos( $content, 'smartslider' ) )
               || has_shortcode( $content, 'smartslider3' );
    if ( ! $has_slider ) {
        wp_dequeue_script( 'smartslider-frontend' ); // VERIFY handle via snippet 02
        wp_dequeue_script( 'nextend-frontend' );     // VERIFY handle via snippet 02
    }

    // ---- OPTIONAL: Gravity Forms — left COMMENTED OUT (safe) by default.
    // GF 2.5+ already loads its JS only on pages containing a form, so a blanket
    // dequeue is redundant and can silently kill multi-page forms / conditional
    // logic / reCAPTCHA. Enable ONLY if snippet 02 proves GF JS appears on a
    // genuinely form-less page, and re-test a LIVE submit afterward.
    // if ( $post instanceof WP_Post
    //     && ! has_shortcode( $content, 'gravityform' )
    //     && ! has_block( 'gravityforms/form', $post ) ) {
    //     wp_dequeue_script( 'gform_gravityforms_theme' ); // VERIFY handle via snippet 02
    //     wp_dequeue_script( 'gform_gravityforms' );       // VERIFY handle via snippet 02
    // }
}
