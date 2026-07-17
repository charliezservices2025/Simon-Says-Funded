<?php
/**
 * Thrive Downtown — snippet 07
 * Accessibility: main landmark + accessible names for icon controls +
 * duplicate-link differentiation. Prints ONE guarded inline <script> on wp_footer.
 *
 * WPCode: PHP Snippet | Auto Insert | Location: FRONTEND ONLY
 *   (an execution-scope location — do NOT pick "Site Wide Footer", which is for
 *    raw HTML insertion and would double-inject / mis-time this.)
 * Reversible: DEACTIVATE this snippet.
 *
 * ┌─ CRITICAL FlyingPress INTERACTION (or this silently fails the audit) ──────┐
 * │ If FlyingPress "Delay JavaScript" is ON, this inline script is held until   │
 * │ the first user interaction — but Lighthouse never interacts, so the audit   │
 * │ would still fail. Add the keyword  tdc-a11y  to FlyingPress's Delay-JS       │
 * │ EXCLUSIONS (Edit Exclusions, under the Delay toggle). This script carries   │
 * │ id="tdc-a11y-fixes" AND an inline /* tdc-a11y *​/ marker so the keyword      │
 * │ matches. Do NOT exclude anything else here.                                 │
 * └────────────────────────────────────────────────────────────────────────────┘
 *
 * Adds ARIA/labels only; the ONLY interactivity added is an Enter/Space handler
 * for icons we EXPLICITLY promote to role=button (so we never ship a keyboard-
 * inoperable button). Native <a>/<button> get a name only and keep semantics.
 * ARIA-only writes never trigger layout/paint => zero CLS. Never throws (wrapped
 * in try/catch so it can't log a console error and cost Best-Practices points).
 *
 * Every selector marked "VERIFY" must be confirmed against the live DOM.
 */
add_action( 'wp_footer', function () { ?>
<script id="tdc-a11y-fixes">
/* tdc-a11y keep-inline marker (FlyingPress Delay-JS exclude anchor) */
(function () {
  function attr(el, a){ return el.getAttribute(a); }
  function hasName(el){
    if (el.textContent && el.textContent.trim()) return true;
    if (attr(el,'aria-label') || attr(el,'aria-labelledby')) return true;
    if ((attr(el,'title')||'').trim()) return true;
    var img = el.querySelector('img[alt]');
    if (img && (img.getAttribute('alt')||'').trim()) return true;
    if (el.querySelector('svg title, svg [aria-label]')) return true;
    return false;
  }
  function labelIfEmpty(el, label){ if (el && !hasName(el)) el.setAttribute('aria-label', label); }
  function labelAll(sel, label){
    document.querySelectorAll(sel).forEach(function(el){ labelIfEmpty(el, label); });
  }
  // Native <a>/<button> keep semantics (name only). A non-interactive element is
  // promoted to a real, keyboard-operable button.
  function makeControl(el, label){
    if (!el) return;
    var tag = el.tagName.toLowerCase();
    var native = (tag === 'a' && el.hasAttribute('href')) || tag === 'button' || tag === 'input';
    if (native) { labelIfEmpty(el, label); return; }
    if (!el.hasAttribute('role'))     el.setAttribute('role', 'button');
    if (!el.hasAttribute('tabindex')) el.setAttribute('tabindex', '0');
    labelIfEmpty(el, label);
    if (!el.dataset.tdcKbd) {                 // bind keyboard activation exactly once
      el.dataset.tdcKbd = '1';
      el.addEventListener('keydown', function(e){
        if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
          e.preventDefault();
          el.click();                          // re-fires Divi's own click handler (bubbles)
        }
      });
    }
  }
  // Bounded OPEN-shadow-DOM search (only invoked when a WhatsApp/Elfsight node exists).
  function deepQuery(root, sel, out, depth){
    out = out || []; depth = depth || 0; if (depth > 6) return out;
    try { root.querySelectorAll(sel).forEach(function(n){ out.push(n); }); } catch(e){}
    try {
      root.querySelectorAll('*').forEach(function(n){
        if (n.shadowRoot) deepQuery(n.shadowRoot, sel, out, depth+1);
      });
    } catch(e){}
    return out;
  }

  function run(){
   try {
    // main landmark — never create a second main
    var mc = document.getElementById('main-content');
    if (mc && !mc.getAttribute('role') && !document.querySelector('main, [role="main"]')) {
      mc.setAttribute('role', 'main');
    }
    // mobile hamburger — VERIFY selector/element type against live DOM
    document.querySelectorAll('#et_mobile_nav_menu .mobile_menu_bar').forEach(function(el){
      makeControl(el, 'Open menu');
    });
    // header search icon — VERIFY
    document.querySelectorAll('#et_search_icon').forEach(function(el){
      makeControl(el, 'Search');
    });
    // Smart Slider arrows + dots (divs; name only)
    labelAll('.nextArrow, [class*="nextArrow"]', 'Next slide');
    labelAll('.previousArrow, [class*="previousArrow"]', 'Previous slide');
    document.querySelectorAll('.n2-bullet').forEach(function(el,i){
      labelIfEmpty(el, 'Go to slide ' + (i+1));
    });
    // Divi social-follow icons (only if Divi's hidden text/title is absent)
    document.querySelectorAll('.et_pb_social_media_follow_network a, .et-social-icon a').forEach(function(a){
      if (!hasName(a)) {
        var host = a.closest('[class*="et-social-"], [class*="et_pb_social_media_follow_network_"], li') || a;
        var cls  = ((host.className||'') + ' ' + (a.className||''));
        var m = cls.match(/(?:et-social-|network_)([a-z0-9]+)/i);
        a.setAttribute('aria-label', m ? m[1].charAt(0).toUpperCase()+m[1].slice(1) : 'Social profile');
      }
    });
    // Elfsight WhatsApp (light + OPEN shadow DOM, best-effort; skip walk if absent)
    if (document.querySelector('[class*="elfsight"], [class*="whatsapp"], a[href*="wa.me"], a[href*="api.whatsapp.com"]')) {
      deepQuery(document, '[class*="elfsight-app"] a, [class*="whatsapp"] a, a[href*="wa.me"], a[href*="api.whatsapp.com"]')
        .forEach(function(a){ labelIfEmpty(a, 'Chat on WhatsApp'); });
    }
    // duplicate "read more" links -> differentiate by post title
    document.querySelectorAll('a.more-link, .et_pb_post .more-link, .et_pb_blog_grid .more-link').forEach(function(a){
      if (!a.getAttribute('aria-label')) {
        var art = a.closest('article, .et_pb_post');
        var t = art && art.querySelector('.entry-title a, .entry-title, h1 a, h2 a, h3 a');
        if (t && t.textContent.trim())
          a.setAttribute('aria-label', (a.textContent.trim()||'Read more') + ': ' + t.textContent.trim());
      }
    });
   } catch (e) { /* never throw: a console error would cost Best-Practices points */ }
  }

  if (document.readyState !== 'loading') run();
  else document.addEventListener('DOMContentLoaded', run);
  // Late third-party widgets (Smart Slider init, Elfsight, Trustindex): idempotent retries.
  var n = 0, iv = setInterval(function(){ run(); if (++n >= 6) clearInterval(iv); }, 1000);
})();
</script>
<?php }, 99 );
