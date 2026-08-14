/*
 * perf-delay-third-party.js — hold non-critical third-party scripts until first interaction.
 *
 * WHY: Lighthouse measures Total Blocking Time only during page load. Third-party scripts that
 * run after the user's first interaction cost nothing in the score and — more importantly — do
 * not compete with the hero image or delay interactivity for a real visitor either.
 *
 * HOW: patches Node.prototype.appendChild / insertBefore to intercept dynamically injected
 * <script> elements whose src matches BLOCK, and replays them on the first user interaction or
 * after RELEASE_AFTER_LOAD_MS following the load event.
 *
 * ---------------------------------------------------------------------------------------------
 * READ BEFORE DEPLOYING
 *
 * 1. This catches DYNAMICALLY INJECTED scripts (how Shopify app embeds and pixels load). It does
 *    NOT catch <script src> tags present in the parsed HTML. Handle those by removing the app
 *    embed or editing the theme.
 *
 * 2. Deferring an analytics pixel can lose attribution for visitors who never interact. The
 *    load-timeout fallback exists to bound that, but if attribution is business-critical, LEAVE
 *    YOUR PIXELS OUT OF `BLOCK` and accept the TBT cost. A correct pixel beats 10 Lighthouse
 *    points.
 *
 * 3. NEVER add a consent/GDPR banner to BLOCK. Deferring consent logic can result in trackers
 *    firing before consent is recorded.
 *
 * 4. NEVER add Shopify's own trekkie / web-pixels-manager / checkout scripts to BLOCK.
 *
 * 5. Add ONE vendor at a time and verify that vendor still works before adding the next.
 *    A batch rollout makes it impossible to tell which vendor broke.
 *
 * ROLLBACK: remove the <script> tag from theme.liquid. No other change is needed — nothing here
 * mutates your theme's own code, and the patches are undone as soon as scripts are released.
 *
 * INSTALL: Assets > add this file, then in theme.liquid, as the FIRST script in <head>, BEFORE
 * {{ content_for_header }}:
 *     <script src="{{ 'perf-delay-third-party.js' | asset_url }}"></script>
 * It must be a normal blocking script (no defer) and it must run before app scripts are
 * injected, or there will be nothing left to intercept. It is ~1 KB and executes in under 1 ms.
 */
(function () {
  'use strict';

  /* ------------------------------------------------------------------ configuration ------ */

  /* Vendors to hold. Match on a distinctive part of the script URL.
     START EMPTY. Add one entry at a time, testing each. Examples are commented out. */
  var BLOCK = [
    // 'tidio.co',            // chat widget
    // 'judge.me',            // reviews
    // 'loox.io',             // reviews
    // 'static.klaviyo.com',  // email capture / popups
    // 'connect.facebook.net' // Meta pixel — see note 2 above
    // 'analytics.tiktok.com' // TikTok pixel — see note 2 above
  ];

  /* Never hold these, whatever else matches. Shopify platform + consent tooling. */
  var NEVER = [
    'cdn.shopify.com/s/trekkie',
    'web-pixels-manager',
    'shopifycloud',
    'checkout',
    'consent',
    'cookieyes',
    'onetrust'
  ];

  /* Release automatically this long after the load event, for sessions with no interaction.
     Lower = safer for analytics, higher = better TBT. 3-5s is a reasonable band. */
  var RELEASE_AFTER_LOAD_MS = 4000;

  /* ------------------------------------------------------------------------ guards ------- */

  /* Never interfere with checkout, cart, or account flows. */
  var path = window.location.pathname;
  if (/^\/(checkout|cart|account|challenge|\d+\/(checkouts|orders))/.test(path)) return;

  /* Never run inside the theme editor — it would make the preview misleading. */
  if (window.Shopify && window.Shopify.designMode) return;

  /* No-op if nothing is configured, so installing the file alone changes nothing. */
  if (!BLOCK.length) return;

  /* Respect an opt-out for debugging: append ?nodelay=1 to any URL. */
  if (window.location.search.indexOf('nodelay=1') !== -1) return;

  /* ------------------------------------------------------------------------- engine ------ */

  var held = [];          // [parentNode, scriptNode, referenceNode|null]
  var released = false;

  var origAppend = Node.prototype.appendChild;
  var origInsert = Node.prototype.insertBefore;

  function srcOf(node) {
    /* Read the attribute rather than the .src property: .src resolves relative URLs and can
       trigger a fetch on some engines when set. */
    try {
      return (node.getAttribute && node.getAttribute('src')) || '';
    } catch (e) {
      return '';
    }
  }

  function matches(src, list) {
    for (var i = 0; i < list.length; i++) {
      if (src.indexOf(list[i]) !== -1) return true;
    }
    return false;
  }

  function shouldHold(node) {
    if (released) return false;
    if (!node || node.nodeType !== 1 || node.tagName !== 'SCRIPT') return false;

    /* An explicit escape hatch for markup you control. */
    if (node.hasAttribute && node.hasAttribute('data-no-delay')) return false;

    var src = srcOf(node);
    if (!src) return false;                    /* never hold inline scripts — too risky */
    if (matches(src, NEVER)) return false;
    return matches(src, BLOCK);
  }

  Node.prototype.appendChild = function (node) {
    if (shouldHold(node)) {
      held.push([this, node, null]);
      return node;                             /* caller sees a normal return value */
    }
    return origAppend.apply(this, arguments);
  };

  Node.prototype.insertBefore = function (node, ref) {
    if (shouldHold(node)) {
      held.push([this, node, ref]);
      return node;
    }
    return origInsert.apply(this, arguments);
  };

  function release() {
    if (released) return;
    released = true;

    /* Restore the originals first, so replayed scripts that inject further scripts of their own
       are not intercepted (they would never be released a second time). */
    Node.prototype.appendChild = origAppend;
    Node.prototype.insertBefore = origInsert;

    for (var i = 0; i < held.length; i++) {
      var parent = held[i][0];
      var node = held[i][1];
      var ref = held[i][2];
      try {
        if (ref && ref.parentNode === parent) {
          origInsert.call(parent, node, ref);
        } else if (parent && parent.isConnected !== false) {
          origAppend.call(parent, node);
        } else {
          /* Original parent is gone from the document — fall back to head so the script
             still executes rather than being silently dropped. */
          origAppend.call(document.head || document.documentElement, node);
        }
      } catch (e) {
        try {
          origAppend.call(document.head || document.documentElement, node);
        } catch (e2) {
          /* Give up on this one script rather than breaking the loop for the rest. */
        }
      }
    }
    held = [];
  }

  var EVENTS = ['pointerdown', 'touchstart', 'keydown', 'wheel', 'scroll', 'mousemove'];
  for (var i = 0; i < EVENTS.length; i++) {
    window.addEventListener(EVENTS[i], release, { once: true, passive: true, capture: true });
  }

  /* Fallback for sessions with no interaction at all. */
  if (document.readyState === 'complete') {
    setTimeout(release, RELEASE_AFTER_LOAD_MS);
  } else {
    window.addEventListener('load', function () {
      setTimeout(release, RELEASE_AFTER_LOAD_MS);
    }, { once: true });
  }

  /* Never let a background tab or an early unload strand the queue. */
  window.addEventListener('pagehide', release, { once: true });
})();
