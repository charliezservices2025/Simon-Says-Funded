(function () {
  "use strict";
  if (window.__nciChat) return;
  window.__nciChat = true;

  /* Widget styles are injected here so the site's critical CSS stays
     untouched and every page needs only this one script tag. */
  var CSS =
    '.nchat{position:fixed;right:18px;bottom:18px;z-index:1600;font-family:var(--font-body,"Public Sans",system-ui,sans-serif)}' +
    ".nchat [hidden]{display:none!important}" +
    ".nchat-launch{font:600 15px/1 inherit;font-family:inherit;background:var(--ink,#1b1e22);color:#fff;border:1px solid var(--ink,#1b1e22);border-radius:999px;padding:14px 22px;cursor:pointer;letter-spacing:.01em;box-shadow:0 8px 24px rgba(27,30,34,.28)}" +
    ".nchat-launch:hover{background:var(--slate,#46587a);border-color:var(--slate,#46587a)}" +
    ".nchat-launch:focus-visible{outline:3px solid var(--ember,#df5430);outline-offset:2px}" +
    ".nchat-bubble{position:absolute;right:0;bottom:60px;width:min(280px,78vw);background:#fff;color:var(--ink,#1b1e22);border:1px solid var(--line,#dfe2e7);border-left:3px solid var(--ember,#df5430);border-radius:10px;padding:12px 14px;font-size:14px;line-height:1.5;box-shadow:0 12px 32px rgba(27,30,34,.18);cursor:pointer}" +
    ".nchat-panel{position:fixed;right:18px;bottom:18px;width:min(370px,calc(100vw - 24px));height:min(540px,calc(100vh - 36px));height:min(540px,calc(100dvh - 36px));display:flex;flex-direction:column;background:#fff;border:1px solid var(--line-strong,#c6cbd3);border-radius:14px;overflow:hidden;box-shadow:0 18px 48px rgba(27,30,34,.3)}" +
    ".nchat-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:13px 16px;background:var(--ink,#1b1e22);color:#fff}" +
    '.nchat-head strong{display:block;font-family:var(--font-display,"Roboto Slab",serif);font-size:15.5px;line-height:1.3}' +
    ".nchat-head span{display:block;font-size:12px;color:#b9c4d8;line-height:1.3}" +
    ".nchat-close{background:transparent;border:1px solid rgba(255,255,255,.45);color:#fff;border-radius:8px;padding:6px 12px;font-size:13px;font-family:inherit;cursor:pointer}" +
    ".nchat-close:hover{background:rgba(255,255,255,.14)}" +
    ".nchat-log{flex:1;overflow-y:auto;padding:16px 14px;display:flex;flex-direction:column;gap:10px;background:var(--paper,#f5f6f8)}" +
    ".nchat-msg{max-width:88%;padding:10px 13px;border-radius:12px;font-size:14px;line-height:1.55;overflow-wrap:break-word}" +
    ".nchat-bot{align-self:flex-start;background:#fff;border:1px solid var(--line,#dfe2e7);border-bottom-left-radius:4px;color:var(--ink,#1b1e22)}" +
    ".nchat-user{align-self:flex-end;background:var(--slate,#46587a);color:#fff;border-bottom-right-radius:4px}" +
    ".nchat-bot a{color:var(--ember-text,#bf3f1d);font-weight:600}" +
    ".nchat-chips{display:flex;flex-wrap:wrap;gap:8px;padding:10px 14px;background:var(--paper,#f5f6f8);border-top:1px solid var(--line,#dfe2e7)}" +
    ".nchat-chips button{background:#fff;border:1px solid var(--line-strong,#c6cbd3);border-radius:999px;padding:7px 12px;font-size:12.5px;color:var(--slate,#46587a);font-family:inherit;cursor:pointer}" +
    ".nchat-chips button:hover{border-color:var(--slate,#46587a);color:var(--ink,#1b1e22)}" +
    ".nchat-form{display:flex;gap:8px;padding:12px 14px;background:#fff;border-top:1px solid var(--line,#dfe2e7)}" +
    ".nchat-form input{flex:1;min-width:0;border:1px solid var(--line-strong,#c6cbd3);border-radius:9px;padding:10px 12px;font-size:16px;font-family:inherit;color:var(--ink,#1b1e22)}" +
    ".nchat-form input:focus{outline:2px solid var(--slate,#46587a);outline-offset:1px}" +
    ".nchat-form button{background:var(--ember-dark,#b8421f);border:1px solid var(--ember-dark,#b8421f);color:#fff;border-radius:9px;padding:10px 16px;font-weight:700;font-size:14px;font-family:inherit;cursor:pointer}" +
    ".nchat-form button:hover{background:var(--ember,#df5430)}" +
    ".nchat-srlabel{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}" +
    "@media (max-width:620px){.nchat{right:12px;bottom:12px}.nchat-bubble{bottom:56px}.nchat-panel{right:8px;left:8px;bottom:8px;width:auto;height:min(480px,calc(100dvh - 16px))}}" +
    "@media print{.nchat{display:none}}";
  var styleEl = document.createElement("style");
  styleEl.textContent = CSS;
  document.head.appendChild(styleEl);

  var PHONE = "(916) 544-1256";
  var TEL = "tel:+19165441256";
  var EMAIL = "education@nuracareinstitute.com";

  /* ---------------- knowledge base ---------------- */
  var LINK = function (href, label) {
    return '<a href="' + href + '">' + label + "</a>";
  };

  var COURSE_LIST =
    "Here is everything we offer:<br>" +
    "&bull; Certified Nursing Assistant (CNA): $1,500<br>" +
    "&bull; Home Health Aide (HHA): $575<br>" +
    "&bull; AHA Basic Life Support (BLS): $82<br>" +
    "&bull; AHA Advanced Cardiovascular Life Support (ACLS): $210<br>" +
    "&bull; Restorative Nursing Assistant (RNA): $250<br>" +
    "&bull; CNA Continuing Education: 6 CEUs for $50<br>" +
    "See details on the " + LINK("/courses/", "courses page") + ".";

  var INTENTS = [
    {
      k: /\b(hi|hello|hey|good (morning|afternoon|evening)|howdy)\b/i,
      a: "Hello! I can help with courses, prices, prerequisites, mobile CPR classes, and booking. What would you like to know?"
    },
    {
      k: /\b(thank|thanks|appreciate)\b/i,
      a: "You are very welcome! Anything else I can help with?"
    },
    {
      k: /\b(bye|goodbye|see you|that.?s all)\b/i,
      a: "Take care! If anything else comes up, call us at " + PHONE + " or use the " + LINK("/enroll/", "enrollment form") + "."
    },
    {
      k: /\b(cna|nursing assistant)\b.*\b(class|cost|price|fee|tuition|how much|program|course|info)|certified nursing/i,
      a: "Our Certified Nursing Assistant program is $1,500 and combines classroom instruction with supervised clinical practice toward your California CNA certification. No prior healthcare experience is required; you will need a background check and health screening. Details: " + LINK("/courses/certified-nursing-assistant/", "CNA program page") + "."
    },
    {
      k: /\b(hha|home health aide|home care|in.?home)\b/i,
      a: "Home Health Aide training is $575. It is an advanced course for active CNAs: you must hold a current California CNA certification and a current BLS card before enrolling. Details: " + LINK("/courses/home-health-aide/", "HHA page") + "."
    },
    {
      k: /\b(rna|restorative)\b/i,
      a: "Restorative Nursing Assistant training is $250, for active CNAs who hold a current BLS card. It builds skills in range of motion, gait and transfer training, and rehabilitation support. Details: " + LINK("/courses/restorative-nursing-assistant/", "RNA page") + "."
    },
    {
      k: /\b(acls|advanced cardio)/i,
      a: "AHA Advanced Cardiovascular Life Support (ACLS) is $210. A current BLS Provider card is recommended before starting. Details: " + LINK("/courses/acls-certification/", "ACLS page") + "."
    },
    {
      k: /\b(bls|cpr|basic life support|aed)\b/i,
      a: "Our AHA Basic Life Support (BLS) course is $82 and covers high quality CPR, AED use, and team response. You get an AHA BLS Provider card, generally valid two years. We also bring BLS to your workplace anywhere in our service area. Details: " + LINK("/courses/bls-cpr-certification/", "BLS page") + "."
    },
    {
      k: /\b(ceu|continuing education|in.?service|renew|renewal|expir)/i,
      a: "For active CNAs we offer a continuing education session: 6 CEUs for $50, counting toward your California in-service renewal. The state form CDPH 278D is linked on the " + LINK("/courses/cna-inservice-hours/", "CEU page") + ". Bring it to your session and we will help you complete it."
    },
    {
      k: /\b(prerequisite|require|need.*(before|first)|qualif)/i,
      a: "Prerequisites at a glance:<br>&bull; CNA: no prior healthcare experience needed<br>&bull; HHA and RNA: active CNA certification plus a current BLS card<br>&bull; ACLS: current BLS recommended<br>&bull; BLS and CNA CEUs: open to anyone who needs them."
    },
    {
      k: /\b(price|prices|cost|how much|fee|tuition|pricing|rates)\b/i,
      a: COURSE_LIST
    },
    {
      k: /\b(course|courses|program|programs|classes|offer|services)\b/i,
      a: COURSE_LIST
    },
    {
      k: /\b(mobile|onsite|on.?site|travel|come to (us|me)|group|team|business|company|facility|sacramento|roseville|auburn|grass valley|yuba|napa|redding)\b/i,
      a: "Yes! We bring CPR, BLS, and ACLS training directly to teams and facilities across Northern California, including Sacramento, Roseville, Auburn, Grass Valley, Yuba City, Napa, and Redding. See " + LINK("/service-areas/", "our service areas") + " or call " + PHONE + " to schedule an onsite class."
    },
    {
      k: /\b(where|address|location|located|directions|campus|orangevale)\b/i,
      a: "Our campus is at 9198 Greenback Lane, Suite 108, Orangevale, CA 95662, easy to reach from Citrus Heights, Fair Oaks, Folsom, and Sacramento. Map and details: " + LINK("/about/", "about page") + "."
    },
    {
      k: /\b(hour|hours|open|close|when.*open|schedule.*office)\b/i,
      a: "Our admissions office is open Monday through Friday, 8:30am to 5:00pm PST. Call " + PHONE + " any time in those hours."
    },
    {
      k: /\b(phone|call|number|email|contact|reach)\b/i,
      a: "You can call us at " + PHONE + " or email " + LINK("mailto:" + EMAIL, EMAIL) + ". Office hours are Monday to Friday, 8:30am to 5:00pm PST."
    },
    {
      k: /\b(enroll|book|booking|sign ?up|register|reserve|start|apply)\b/i,
      a: "Booking is easy: fill out the " + LINK("/enroll/", "enrollment form") + " and our admissions team will follow up within one business day with schedule and start date. Submitting the form does not charge anything."
    },
    {
      k: /\b(zelle|pay|payment|deposit|money|paypal|card|cash)\b/i,
      a: "We accept Zelle among other payment options. Booking online never charges you anything; our admissions team confirms payment details with you directly when they schedule your class. Call " + PHONE + " with any payment questions."
    },
    {
      k: /\b(tynesha|instructor|teacher|who teaches|staff)\b/i,
      a: "Instruction is led by Tynesha Zacarias, our Lead Instructor, who works directly with students through classroom and hands-on clinical practice. More on the " + LINK("/about/", "about page") + "."
    },
    {
      k: /\b(job|career|employment|work|hire|salary)\b/i,
      a: "Our graduates work in hospitals, skilled nursing facilities, and home care across the Sacramento region. Completing a course does not guarantee employment or exam passage, but we will support you every step. Ask admissions about career pathways at " + PHONE + "."
    },
    {
      k: /\b(human|person|agent|someone|real|talk|speak)\b/i,
      a: "Of course. Call us at " + PHONE + " (Mon to Fri, 8:30am to 5:00pm PST) or email " + LINK("mailto:" + EMAIL, EMAIL) + " and a real person will help you."
    }
  ];

  var FALLBACK =
    "I am not sure about that one, sorry! I can help with courses, prices, prerequisites, our service areas, and booking. " +
    "For anything else, call " + PHONE + " or email " + LINK("mailto:" + EMAIL, EMAIL) + ".";

  var WELCOME =
    "Hi! I am the Nura Care Institute assistant. Ask me about courses, prices, prerequisites, or booking a class.";

  var CHIPS = [
    ["Courses and prices", "courses and prices"],
    ["Mobile CPR for my team", "mobile CPR for my team"],
    ["How do I enroll?", "how do I enroll"],
    ["Talk to a person", "talk to a person"]
  ];

  function answer(text) {
    for (var i = 0; i < INTENTS.length; i++) {
      if (INTENTS[i].k.test(text)) return INTENTS[i].a;
    }
    return FALLBACK;
  }

  /* ---------------- ding (WebAudio, no file) ---------------- */
  function ding() {
    try {
      var Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      var ctx = ding.ctx || (ding.ctx = new Ctx());
      if (ctx.state === "suspended") {
        ctx.resume().catch(function () {});
        if (ctx.state === "suspended") return;
      }
      [880, 1174.66].forEach(function (freq, idx) {
        var o = ctx.createOscillator();
        var g = ctx.createGain();
        o.type = "sine";
        o.frequency.value = freq;
        var t0 = ctx.currentTime + idx * 0.09;
        g.gain.setValueAtTime(0.0001, t0);
        g.gain.exponentialRampToValueAtTime(0.06, t0 + 0.02);
        g.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.5);
        o.connect(g).connect(ctx.destination);
        o.start(t0);
        o.stop(t0 + 0.55);
      });
    } catch (e) { /* sound is a nicety, never an error */ }
  }

  /* ---------------- widget ---------------- */
  var root = document.createElement("div");
  root.className = "nchat";
  root.innerHTML =
    '<button type="button" class="nchat-launch" aria-haspopup="dialog">Chat with us</button>' +
    '<div class="nchat-bubble" hidden></div>' +
    '<section class="nchat-panel" role="dialog" aria-label="Chat with Nura Care Institute" hidden>' +
    '  <header class="nchat-head">' +
    '    <div><strong>Nura Care Institute</strong><span>Virtual assistant</span></div>' +
    '    <button type="button" class="nchat-close">Close</button>' +
    "  </header>" +
    '  <div class="nchat-log" aria-live="polite"></div>' +
    '  <div class="nchat-chips"></div>' +
    '  <form class="nchat-form">' +
    '    <label class="nchat-srlabel" for="nchatInput">Type your question</label>' +
    '    <input id="nchatInput" type="text" autocomplete="off" placeholder="Type your question">' +
    '    <button type="submit">Send</button>' +
    "  </form>" +
    "</section>";
  document.body.appendChild(root);

  var launch = root.querySelector(".nchat-launch");
  var bubble = root.querySelector(".nchat-bubble");
  var panel = root.querySelector(".nchat-panel");
  var log = root.querySelector(".nchat-log");
  var chipsEl = root.querySelector(".nchat-chips");
  var form = root.querySelector(".nchat-form");
  var input = root.querySelector("#nchatInput");

  var history = [];
  try { history = JSON.parse(sessionStorage.getItem("nchat") || "[]"); } catch (e) {}

  function persist() {
    try { sessionStorage.setItem("nchat", JSON.stringify(history.slice(-40))); } catch (e) {}
  }

  function addMsg(who, html, save) {
    var el = document.createElement("div");
    el.className = "nchat-msg nchat-" + who;
    el.innerHTML = html;
    log.appendChild(el);
    log.scrollTop = log.scrollHeight;
    if (save !== false) {
      history.push([who, html]);
      persist();
    }
  }

  function renderChips() {
    chipsEl.innerHTML = "";
    CHIPS.forEach(function (c) {
      var b = document.createElement("button");
      b.type = "button";
      b.textContent = c[0];
      b.addEventListener("click", function () { ask(c[1]); });
      chipsEl.appendChild(b);
    });
  }

  function ask(text) {
    addMsg("user", text.replace(/&/g, "&amp;").replace(/</g, "&lt;"));
    setTimeout(function () {
      addMsg("bot", answer(text));
      ding();
    }, 350);
  }

  function openPanel() {
    bubble.hidden = true;
    panel.hidden = false;
    launch.hidden = true;
    if (!log.childElementCount) {
      if (history.length) {
        history.forEach(function (m) { addMsg(m[0], m[1], false); });
      } else {
        addMsg("bot", WELCOME);
      }
      renderChips();
    }
    ding();
    input.focus();
  }

  function closePanel() {
    panel.hidden = true;
    launch.hidden = false;
  }

  launch.addEventListener("click", openPanel);
  bubble.addEventListener("click", openPanel);
  root.querySelector(".nchat-close").addEventListener("click", closePanel);
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    var v = input.value.trim();
    if (!v) return;
    input.value = "";
    ask(v);
  });

  // Gentle welcome nudge, once per browser session.
  var nudged = false;
  try { nudged = sessionStorage.getItem("nchatNudge") === "1"; } catch (e) {}
  if (!nudged) {
    setTimeout(function () {
      if (!panel.hidden) return;
      bubble.textContent = WELCOME;
      bubble.hidden = false;
      ding();
      try { sessionStorage.setItem("nchatNudge", "1"); } catch (e) {}
      setTimeout(function () { bubble.hidden = true; }, 12000);
    }, 3500);
  }
})();
