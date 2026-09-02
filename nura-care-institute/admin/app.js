(function () {
  "use strict";
  var btn = document.getElementById("menuBtn");
  var nav = document.getElementById("sideNav");
  var scrim = document.getElementById("navScrim");
  if (!btn || !nav) return;

  function setOpen(open) {
    nav.classList.toggle("open", open);
    btn.setAttribute("aria-expanded", String(open));
    if (scrim) scrim.hidden = !open;
    document.body.style.overflow = open ? "hidden" : "";
  }
  btn.addEventListener("click", function () {
    setOpen(!nav.classList.contains("open"));
  });
  if (scrim) scrim.addEventListener("click", function () { setOpen(false); });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") setOpen(false);
  });
  nav.querySelectorAll("a").forEach(function (a) {
    a.addEventListener("click", function () { setOpen(false); });
  });
})();

/* Interactive analytics chart: crosshair + combined day tooltip (desktop,
   mobile, tablet, total). Works with mouse, touch, and keyboard focus. */
(function () {
  "use strict";
  var COLORS = { d: "#3f5c9e", m: "#df5430", t: "#5e8ad4" };
  var LABELS = { d: "Desktop", m: "Mobile", t: "Tablet" };

  function row(k, v) {
    return '<div class="cx-row"><span class="cx-sw" style="background:' + COLORS[k] +
      '"></span><span>' + LABELS[k] + "</span><b>" + v + "</b></div>";
  }

  function initChart(wrap) {
    var svg = wrap.querySelector(".cx-svg");
    var tip = wrap.querySelector(".cx-tip");
    var cross = wrap.querySelector(".cx-cross");
    if (!svg || !tip) return;
    var bands = svg.querySelectorAll(".cx-band");
    if (!bands.length) return;

    function hide() {
      tip.hidden = true;
      if (cross) cross.setAttribute("visibility", "hidden");
    }

    function position(band) {
      var br = band.getBoundingClientRect();
      var wr = wrap.getBoundingClientRect();
      var center = br.left + br.width / 2 - wr.left + wrap.scrollLeft;
      var tw = tip.offsetWidth;
      var left = center - tw / 2;
      var minL = wrap.scrollLeft + 4;
      var maxL = wrap.scrollLeft + wrap.clientWidth - tw - 4;
      if (left < minL) left = minL;
      if (maxL >= minL && left > maxL) left = maxL;
      tip.style.left = left + "px";
    }

    function show(band) {
      var d = +band.getAttribute("data-d");
      var m = +band.getAttribute("data-m");
      var t = +band.getAttribute("data-t");
      tip.innerHTML =
        '<div class="cx-tip-day">' + (band.getAttribute("data-label") || "") + "</div>" +
        row("d", d) + row("m", m) + row("t", t) +
        '<div class="cx-row cx-total"><span>Total</span><b>' + (d + m + t) + "</b></div>";
      tip.hidden = false;
      if (cross) {
        var cx = band.getAttribute("data-cx");
        cross.setAttribute("x1", cx);
        cross.setAttribute("x2", cx);
        cross.setAttribute("visibility", "visible");
      }
      position(band);
    }

    bands.forEach(function (band) {
      ["pointerenter", "pointermove", "pointerdown", "focus"].forEach(function (ev) {
        band.addEventListener(ev, function () { show(band); });
      });
      band.addEventListener("blur", hide);
    });
    wrap.addEventListener("pointerleave", hide);
  }

  document.querySelectorAll(".chart-wrap").forEach(initChart);
})();
