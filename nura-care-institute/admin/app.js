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
