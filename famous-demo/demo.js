(function () {
  var sections = {
    "Philosophy": "philosophy",
    "Teachings": "teachings",
    "The Path": "path",
    "Library": "library",
    "Gematria": "gematria",
    "Lineage": "lineage",
    "Reflections": "reflections",
    "Begin Your Study": "teachings",
    "Descend": "philosophy"
  };

  function label(el) {
    return (el.innerText || "").replace(/\s+/g, " ").trim();
  }

  function go(id) {
    var target = document.getElementById(id);
    if (target) target.scrollIntoView({ behavior: "auto", block: "start" });
    closeMenu();
  }

  function showNote() {
    var note = document.getElementById("demo-note");
    if (!note) {
      note = document.createElement("p");
      note.id = "demo-note";
      note.className = "demo-note";
      note.textContent = "This comparison demo does not sign in, send mail, or take payment, and it does not open the live members site.";
      document.body.appendChild(note);
    }
    note.hidden = false;
  }

  var menu = document.createElement("nav");
  menu.id = "demo-menu";
  menu.className = "demo-menu";
  menu.setAttribute("aria-label", "Sections");
  var close = document.createElement("button");
  close.type = "button";
  close.textContent = "Close";
  menu.appendChild(close);
  Object.keys(sections).forEach(function (name) {
    if (name === "Begin Your Study" || name === "Descend") return;
    var link = document.createElement("a");
    link.href = "#" + sections[name];
    link.textContent = name;
    menu.appendChild(link);
  });
  document.body.appendChild(menu);

  function closeMenu() {
    menu.classList.remove("is-open");
    var toggle = document.querySelector('[aria-label="Toggle menu"]');
    if (toggle) toggle.setAttribute("aria-expanded", "false");
  }

  close.addEventListener("click", closeMenu);
  menu.addEventListener("click", function (event) {
    if (event.target.closest("a")) closeMenu();
  });

  var toggle = document.querySelector('[aria-label="Toggle menu"]');
  if (toggle) {
    toggle.setAttribute("aria-expanded", "false");
    toggle.setAttribute("aria-controls", "demo-menu");
    toggle.addEventListener("click", function () {
      var open = menu.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
  }

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") closeMenu();
  });

  document.querySelectorAll("button").forEach(function (button) {
    var text = label(button);
    if (sections[text]) {
      button.addEventListener("click", function () { go(sections[text]); });
      return;
    }
    if (text.indexOf("Receive the Naught Degree") !== -1 || text === "Enter the Path" || text === "Request the Naught Degree") {
      button.addEventListener("click", function () { go("join"); });
      return;
    }
    if (text === "Sign In" || text.indexOf("Sign In") !== -1) {
      button.addEventListener("click", function (event) {
        event.preventDefault();
        showNote();
      });
    }
  });

  document.querySelectorAll("form").forEach(function (form) {
    form.addEventListener("submit", function (event) {
      event.preventDefault();
      showNote();
    });
  });

  document.querySelectorAll('a[href="#join"]').forEach(function (link) {
    link.addEventListener("click", function (event) {
      event.preventDefault();
      go("join");
    });
  });
})();
