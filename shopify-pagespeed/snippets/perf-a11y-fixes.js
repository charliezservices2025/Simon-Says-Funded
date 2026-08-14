/*
 * perf-a11y-fixes.js — label iframes injected by third-party apps.
 *
 * Fixes the Lighthouse audit "<frame> or <iframe> elements do not have a title"
 * (Accessibility 94 -> 100).
 *
 * USE THIS ONLY FOR IFRAMES YOU DO NOT CONTROL. If the iframe is in your own theme, add a real
 * title attribute to the markup instead — a specific title ("Product demo video") is genuinely
 * useful to a screen reader user, whereas a generic one only satisfies the audit.
 *
 * INSTALL: Assets > add this file, then at the END of theme.liquid <body>:
 *     <script src="{{ 'perf-a11y-fixes.js' | asset_url }}" defer></script>
 *
 * Cost: runs after load, observes lazily, no measurable TBT impact.
 */
(function () {
  'use strict';

  /* Map known app iframe sources to meaningful labels. Extend this rather than relying on the
     generic fallback — a screen reader user gets nothing useful from "Embedded content". */
  var LABELS = [
    { match: 'youtube.com',   title: 'YouTube video player' },
    { match: 'youtu.be',      title: 'YouTube video player' },
    { match: 'player.vimeo',  title: 'Vimeo video player' },
    { match: 'tiktok.com',    title: 'TikTok video player' },
    { match: 'instagram.com', title: 'Instagram post' },
    { match: 'google.com/maps', title: 'Map' }
    // { match: 'your-chat-app.com', title: 'Customer support chat' },
  ];

  function labelFor(src) {
    for (var i = 0; i < LABELS.length; i++) {
      if (src.indexOf(LABELS[i].match) !== -1) return LABELS[i].title;
    }
    return null;
  }

  function fixIframes(root) {
    var frames = (root || document).querySelectorAll('iframe:not([title]), frame:not([title])');
    for (var i = 0; i < frames.length; i++) {
      var el = frames[i];
      var src = el.getAttribute('src') || '';
      var title = labelFor(src);

      if (!title) {
        /* Hide decorative/tracking iframes from assistive tech entirely rather than
           announcing a meaningless label. Pixel iframes have no user-facing content. */
        var w = el.clientWidth, h = el.clientHeight;
        if ((w <= 1 && h <= 1) || el.style.display === 'none') {
          el.setAttribute('aria-hidden', 'true');
          el.setAttribute('title', 'Empty');
          continue;
        }
        title = 'Embedded content';
      }
      el.setAttribute('title', title);
    }
  }

  function init() {
    fixIframes(document);

    /* App iframes are often injected long after load (chat widgets especially). */
    if (window.MutationObserver) {
      var observer = new MutationObserver(function (mutations) {
        for (var i = 0; i < mutations.length; i++) {
          if (mutations[i].addedNodes.length) { fixIframes(document); return; }
        }
      });
      observer.observe(document.documentElement, { childList: true, subtree: true });

      /* Stop observing after 30s — by then every widget has loaded, and a permanent
         subtree observer is a needless cost on long sessions. */
      setTimeout(function () { observer.disconnect(); }, 30000);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
