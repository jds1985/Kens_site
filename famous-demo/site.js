(function () {
  var config = window.FAMOUS_DEMO_CONFIG || {};
  var button = document.querySelector("[data-menu-button]");
  var panel = document.querySelector("[data-menu-panel]");
  var note = document.querySelector("[data-members-note]");
  var action = document.querySelector("[data-members-action]");

  function closeMenu() {
    if (!button || !panel) return;
    button.setAttribute("aria-expanded", "false");
    panel.hidden = true;
  }

  function openMenu() {
    button.setAttribute("aria-expanded", "true");
    panel.hidden = false;
  }

  if (button && panel) {
    button.addEventListener("click", function () {
      if (button.getAttribute("aria-expanded") === "true") closeMenu();
      else openMenu();
    });
    panel.addEventListener("click", function (event) {
      if (event.target.closest("a")) closeMenu();
    });
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && button.getAttribute("aria-expanded") === "true") {
        closeMenu();
        button.focus();
      }
    });
  }

  var url = String(config.membersUrl || "").trim();
  var allowed = false;
  try {
    var parsed = new URL(url);
    var host = parsed.hostname.toLowerCase();
    allowed = (parsed.protocol === "https:" || parsed.protocol === "http:")
      && host !== "qabbalah.institute"
      && host !== "www.qabbalah.institute"
      && !host.endsWith(".hostgator.com")
      && !host.endsWith("hostgator.com");
  } catch (error) {
    allowed = false;
  }

  if (allowed && action && note) {
    action.hidden = false;
    action.href = url;
    action.textContent = "Open the isolated members demo";
    note.hidden = true;
  }
})();
