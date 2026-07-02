(function () {
  "use strict";

  // Footer year
  var yearEl = document.getElementById("year");
  if (yearEl) {
    yearEl.textContent = new Date().getFullYear();
  }

  // Respect reduced-motion: drop the hero video entirely (the CSS poster
  // background takes over) so no video bytes are fetched or played.
  var heroVideo = document.querySelector(".hero-bg");
  if (
    heroVideo &&
    window.matchMedia &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches
  ) {
    heroVideo.removeAttribute("autoplay");
    if (typeof heroVideo.pause === "function") heroVideo.pause();
    heroVideo.parentNode.removeChild(heroVideo);
  }

  // Mobile nav toggle
  var navToggle = document.getElementById("navToggle");
  var navLinks = document.getElementById("primaryNav");
  if (navToggle && navLinks) {
    navToggle.addEventListener("click", function () {
      var isOpen = navLinks.getAttribute("data-open") === "true";
      navLinks.setAttribute("data-open", String(!isOpen));
      navToggle.setAttribute("aria-expanded", String(!isOpen));
    });
    navLinks.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        navLinks.setAttribute("data-open", "false");
        navToggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  // Submenu (Service Areas) toggle: drives the dropdown on touch/mobile and
  // keyboard. Desktop also opens it on hover/focus via CSS.
  document.querySelectorAll(".submenu-toggle").forEach(function (toggle) {
    var menu = document.getElementById(toggle.getAttribute("aria-controls"));
    if (!menu) return;
    toggle.addEventListener("click", function (e) {
      e.preventDefault();
      var isOpen = menu.getAttribute("data-open") === "true";
      menu.setAttribute("data-open", String(!isOpen));
      toggle.setAttribute("aria-expanded", String(!isOpen));
    });
  });

  // FAQ accordions
  document.querySelectorAll(".faq-item").forEach(function (item) {
    var btn = item.querySelector(".faq-q");
    var panel = item.querySelector(".faq-a");
    var inner = panel ? panel.querySelector(".faq-a-inner") : null;
    if (!btn || !panel) return;

    btn.addEventListener("click", function () {
      var isOpen = item.getAttribute("data-open") === "true";
      item.setAttribute("data-open", String(!isOpen));
      btn.setAttribute("aria-expanded", String(!isOpen));
      panel.style.maxHeight = !isOpen ? inner.offsetHeight + "px" : "0px";
    });
  });

  // Enrollment form
  var form = document.getElementById("enrollForm");
  if (form) {
    var statusEl = document.getElementById("enrollStatus");
    var submitBtn = form.querySelector('button[type="submit"]');

    // Anti-spam human check: fetch a signed challenge from the server and
    // show the verification question. The server rejects submissions that
    // lack a valid, correctly answered, human-paced challenge.
    var checkRow = document.getElementById("humanCheckRow");
    var questionEl = document.getElementById("humanQuestion");
    var answerEl = document.getElementById("human_answer");

    function loadChallenge() {
      if (!checkRow || !questionEl || !answerEl) return;
      fetch("/php/challenge.php", { headers: { Accept: "application/json" } })
        .then(function (res) {
          return res.json();
        })
        .then(function (c) {
          if (!c || !c.sig || !c.q) throw new Error("bad challenge");
          document.getElementById("challengeTs").value = c.ts;
          document.getElementById("challengeNonce").value = c.nonce;
          document.getElementById("challengeSig").value = c.sig;
          questionEl.textContent = c.q;
          answerEl.value = "";
          answerEl.required = true;
          checkRow.hidden = false;
        })
        .catch(function () {
          statusEl.setAttribute("data-state", "error");
          statusEl.textContent =
            "Our spam check could not load. Please refresh the page, or call (916) 544-1256.";
        });
    }
    loadChallenge();

    // Non-JS fallback redirected back here after a server-side error.
    if (window.location.search.indexOf("error=1") !== -1) {
      statusEl.setAttribute("data-state", "error");
      statusEl.textContent =
        "Something went wrong sending your request. Please call us at (916) 544-1256.";
    }

    form.addEventListener("submit", function (e) {
      e.preventDefault();

      // Honeypot check
      var honeypot = form.querySelector('input[name="website"]');
      if (honeypot && honeypot.value) {
        return;
      }

      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      var data = new FormData(form);
      submitBtn.disabled = true;
      submitBtn.textContent = "Sending...";
      statusEl.removeAttribute("data-state");

      fetch("/php/enroll.php", {
        method: "POST",
        body: data,
        headers: { Accept: "application/json" },
      })
        .then(function (res) {
          return res.json().catch(function () {
            return { ok: res.ok };
          });
        })
        .then(function (json) {
          if (json && json.ok) {
            form.reset();
            statusEl.setAttribute("data-state", "success");
            statusEl.textContent =
              "Thanks! Your enrollment request has been sent. We will contact you within one business day.";
            window.location.href = "/thank-you/";
          } else {
            statusEl.setAttribute("data-state", "error");
            statusEl.textContent =
              (json && json.message) ||
              "Something went wrong sending your request. Please call us at (916) 544-1256.";
            // The server asked for a fresh spam-check question.
            if (json && json.code === "challenge") {
              loadChallenge();
            }
          }
        })
        .catch(function () {
          statusEl.setAttribute("data-state", "error");
          statusEl.textContent =
            "Something went wrong sending your request. Please call us at (916) 544-1256.";
        })
        .finally(function () {
          submitBtn.disabled = false;
          submitBtn.textContent = "Submit Enrollment Request";
        });
    });
  }
})();
