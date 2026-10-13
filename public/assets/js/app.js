// Omni: toasts, the off-canvas panel, copy buttons and confirmations. No inline scripts (the CSP forbids them).
(function () {
  'use strict';

  // ---- Toasts: bottom left, closed with ✕ or after 10 seconds, with a bar counting down.
  var tones = { success: 'bg-emerald-600', error: 'bg-red-600', info: 'bg-slate-800', warning: 'bg-amber-600' };
  function showToast(message, type) {
    var container = document.getElementById('toast-container');
    if (!container || !message) return;
    var toast = document.createElement('div');
    toast.className = 'relative overflow-hidden rounded-lg px-4 pb-4 pt-3 text-sm text-white shadow-lg ' + (tones[type] || tones.info);
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    var row = document.createElement('div');
    row.className = 'flex items-start justify-between gap-3';
    var text = document.createElement('span');
    text.textContent = message;
    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'shrink-0 opacity-70 hover:opacity-100';
    close.setAttribute('aria-label', 'Close');
    close.textContent = '✕';
    close.addEventListener('click', function () { toast.remove(); });
    row.append(text, close);
    var track = document.createElement('div');
    track.className = 'absolute bottom-0 left-0 h-1 w-full bg-white/30';
    var bar = document.createElement('div');
    bar.className = 'h-full w-full bg-white/80';
    track.append(bar);
    toast.append(row, track);
    container.append(toast);
    requestAnimationFrame(function () {
      bar.style.transition = 'width 10s linear';
      bar.style.width = '0%';
    });
    setTimeout(function () { toast.remove(); }, 10000);
  }
  window.showToast = showToast;
  if (document.body.dataset.flashMessage) {
    showToast(document.body.dataset.flashMessage, document.body.dataset.flashType);
  }

  // ---- Off-canvas panels: [data-canvas-open="id"] opens, [data-canvas-close], the overlay or Escape closes.
  var overlay = document.querySelector('[data-canvas-overlay]');
  var opener = null;
  function openCanvas(id) {
    var panel = document.getElementById(id);
    if (!panel) return;
    opener = document.activeElement;
    if (overlay) overlay.classList.remove('hidden');
    panel.classList.remove('invisible');
    requestAnimationFrame(function () { panel.classList.remove('translate-x-full'); });
    document.body.classList.add('overflow-hidden');
    var first = panel.querySelector('input:not([type=hidden]), select, textarea') || panel.querySelector('button');
    if (first) setTimeout(function () { first.focus(); }, 50);
  }
  function closeCanvas() {
    if (overlay) overlay.classList.add('hidden');
    document.querySelectorAll('[data-canvas]').forEach(function (panel) {
      if (panel.classList.contains('translate-x-full')) return;
      panel.classList.add('translate-x-full');
      // Out of reach of the keyboard and screen readers once it has slid away.
      setTimeout(function () { panel.classList.add('invisible'); }, 300);
    });
    document.body.classList.remove('overflow-hidden');
    if (opener && opener.focus) opener.focus();
    opener = null;
  }
  document.querySelectorAll('[data-canvas-open]').forEach(function (button) {
    button.addEventListener('click', function () { openCanvas(button.dataset.canvasOpen); });
  });
  document.querySelectorAll('[data-canvas-close]').forEach(function (button) { button.addEventListener('click', closeCanvas); });
  if (overlay) overlay.addEventListener('click', closeCanvas);
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeCanvas();
  });

  // ---- Copy: [data-copy="x"] copies the text of [data-copy-source="x"].
  document.querySelectorAll('[data-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
      var source = document.querySelector('[data-copy-source="' + button.dataset.copy + '"]');
      if (!source || !navigator.clipboard) return;
      navigator.clipboard.writeText(source.textContent.trim()).then(function () {
        var label = button.textContent;
        button.textContent = 'Copied';
        setTimeout(function () { button.textContent = label; }, 1500);
      });
    });
  });

  // ---- Forms that ask first: <form data-confirm="Question?">.
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
  });
})();
