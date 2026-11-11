// Reading options: text size and high contrast. Remembered in this browser only.
(function () {
  var root = document.documentElement, sizes = [13, 15, 17, 19, 21, 24, 28, 34], DEF = 1, i = DEF;
  var saved = null;
  try {
    var s = parseInt(localStorage.getItem('ismem_size2'), 10); if (s >= 0 && s < sizes.length) i = s;
    saved = localStorage.getItem('ismem_hc');
  } catch (e) {}
  // High contrast: the member's own choice wins; otherwise follow the device setting "more contrast".
  var pref = false;
  try { pref = window.matchMedia && window.matchMedia('(prefers-contrast: more)').matches; } catch (e) {}
  if (saved === '1' || (saved === null && pref)) root.classList.add('hc');
  function apply() { root.style.setProperty('--fs', sizes[i] + 'px'); try { localStorage.setItem('ismem_size2', i); } catch (e) {} }
  apply();
  var c = document.getElementById('t-contrast');
  function setc() { if (c) c.setAttribute('aria-pressed', root.classList.contains('hc') ? 'true' : 'false'); }
  setc();
  function on(id, fn) { var b = document.getElementById(id); if (b) b.addEventListener('click', fn); }
  on('t-bigger', function () { if (i < sizes.length - 1) { i++; apply(); } });
  on('t-smaller', function () { if (i > 0) { i--; apply(); } });
  on('t-reset', function () { i = DEF; apply(); });
  on('t-contrast', function () {
    root.classList.toggle('hc'); setc();
    try { localStorage.setItem('ismem_hc', root.classList.contains('hc') ? '1' : '0'); } catch (e) {}
  });
  // Ask "are you sure?" on buttons marked data-confirm (undo actions).
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest ? ev.target.closest('[data-confirm]') : null;
    if (b && !window.confirm(b.getAttribute('data-confirm'))) ev.preventDefault();
  });
  // If the page opened with an error message, put the screen reader / keyboard focus on it.
  var n = document.querySelector('.notice.err');
  if (n) { n.focus({ preventScroll: false }); }
  var slot = document.getElementById('pledge-given-name');
  var signed = document.getElementById('pledge_name');
  var alias = document.getElementById('pseudonym');
  if (slot && (signed || alias)) {
    var fillPledge = function () {
      var typed = signed && signed.value.replace(/\s+/g, ' ').trim();
      if (!typed && alias) typed = alias.value.replace(/\s+/g, ' ').trim();
      slot.textContent = typed || '(give your name)';
    };
    if (signed) signed.addEventListener('input', fillPledge);
    if (alias) alias.addEventListener('input', fillPledge);
    fillPledge();
  }
})();
