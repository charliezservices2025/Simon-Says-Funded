(function () {
  "use strict";

  // Footer year
  var yearEl = document.getElementById("year");
  if (yearEl) {
    yearEl.textContent = new Date().getFullYear();
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
            window.location.href = "/thank-you.html";
          } else {
            statusEl.setAttribute("data-state", "error");
            statusEl.textContent =
              (json && json.message) ||
              "Something went wrong sending your request. Please call us at (916) 544-1256.";
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
