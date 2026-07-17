<?php
/**
 * Thrive Downtown — snippet 05
 * Preload the homepage LCP hero image with high priority (front page only).
 * Use this ONLY if FlyingPress's "Preload critical images" does not already
 * cover the LCP element (it covers <img> LCP; it does NOT auto-preload a Divi
 * CSS background-image hero — that's the main case for this snippet).
 *
 * WPCode: PHP Snippet | Auto Insert | Location: Run Everywhere (Frontend)
 * Reversible: DEACTIVATE this snippet = fully revert.
 * SAFE: front-page only, escaped output, and it SELF-DISABLES while the
 *       placeholder URL is unedited (so it can never ship a 404 preload).
 *
 * ┌─ BEFORE USING ────────────────────────────────────────────────────────────┐
 * │ 1. Read the REAL LCP element + URL from PSI → "Largest Contentful Paint    │
 * │    element" (mobile report), and note whether it's an <img> or a CSS       │
 * │    background-image.                                                       │
 * │ 2. WebP Express "Varied image responses" mode: preload the ORIGINAL URL    │
 * │    exactly as it appears in HTML/CSS (e.g. .../hero.jpg). The server       │
 * │    returns WebP via content negotiation. Do NOT point preload at .webp.    │
 * │    (Only if you switched WebP Express to "CDN friendly"/URL-replacement     │
 * │    should the preload target the .webp URL.)                               │
 * │ 3. <img> LCP  -> keep the RESPONSIVE printf (imagesrcset/imagesizes must   │
 * │                  match the <img>'s real srcset/sizes, or you double-load). │
 * │    CSS-bg LCP -> use the SINGLE-URL printf variant (see bottom) and delete │
 * │                  the responsive one.                                       │
 * │ 4. Keep only ONE preload for the LCP image. If FlyingPress already         │
 * │    preloads the same <img>, do NOT also run this for that URL.             │
 * └───────────────────────────────────────────────────────────────────────────┘
 */
if ( ! function_exists( 'tdc_preload_lcp_hero' ) ) {
    function tdc_preload_lcp_hero() {
        if ( is_admin() || ! is_front_page() || is_paged() ) {
            return;
        }

        // REPLACE with the REAL hero URL(s) from PSI (§ above). Use the ORIGINAL
        // extension (.jpg/.png) — WebP Express Varied mode serves WebP for it.
        $hero_mobile  = 'https://thrivedowntown.com/wp-content/uploads/REPLACE-hero-800.jpg';
        $hero_desktop = 'https://thrivedowntown.com/wp-content/uploads/REPLACE-hero-1600.jpg';

        // Safety: never emit a preload while the placeholder is unedited.
        if ( empty( $hero_mobile ) || false !== strpos( $hero_mobile, 'REPLACE-' ) ) {
            return;
        }

        // ---- RESPONSIVE preload: ONLY for an <img> LCP whose srcset you match ----
        printf(
            '<link rel="preload" as="image" href="%1$s" imagesrcset="%1$s 800w, %2$s 1600w" imagesizes="100vw" fetchpriority="high">' . "\n",
            esc_url( $hero_mobile ),
            esc_url( $hero_desktop )
        );

        // ---- CSS-BACKGROUND hero variant: DELETE the responsive printf above and
        // use this single-URL form instead (preload the one exact URL the CSS
        // background-image requests). If Divi serves a different bg per breakpoint,
        // emit two <link ... media="(max-width:980px)"> / media="(min-width:981px)">
        // preloads with the correct per-breakpoint URLs.
        //
        // printf(
        //     '<link rel="preload" as="image" href="%1$s" fetchpriority="high">' . "\n",
        //     esc_url( $hero_mobile )
        // );
    }
    // Priority 1 so it lands near the top of <head>, before stylesheets.
    add_action( 'wp_head', 'tdc_preload_lcp_hero', 1 );
}
