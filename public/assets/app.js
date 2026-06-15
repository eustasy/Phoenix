/* Phoenix redesign — shared client helpers (mock interactions only). */

// Theme toggle (init snippet runs inline in <head> to avoid flash).
function phToggleTheme() {
  var dark = document.documentElement.classList.toggle('theme-dark');
  document.documentElement.classList.toggle('theme-light', !dark);
  try { localStorage.setItem('phoenix-theme', dark ? 'dark' : 'light'); } catch (e) {}
}

// Click-to-copy an info hash. Falls back silently without clipboard API.
function phCopyHash(btn) {
  var value = btn.getAttribute('data-hash') || '';
  var done = function () {
    btn.classList.add('is-copied');
    var ico = btn.querySelector('.ph-ico');
    if (ico) ico.setAttribute('data-lucide', 'check');
    if (window.lucide) lucide.createIcons({ attrs: { class: 'ph-ico' } });
    setTimeout(function () {
      btn.classList.remove('is-copied');
      if (ico) ico.setAttribute('data-lucide', 'copy');
      if (window.lucide) lucide.createIcons({ attrs: { class: 'ph-ico' } });
    }, 1200);
  };
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(value).then(done, done);
  } else { done(); }
}

// Live row filter over a table body. Matches text content of flagged columns.
function phFilterTable(input, tableSel, countSel) {
  var table = document.querySelector(tableSel);
  if (!table) return;
  var q = input.value.trim().toLowerCase();
  var rows = table.tBodies[0] ? table.tBodies[0].rows : [];
  var shown = 0;
  for (var i = 0; i < rows.length; i++) {
    var hit = !q || rows[i].textContent.toLowerCase().indexOf(q) !== -1;
    rows[i].hidden = !hit;
    if (hit) shown++;
  }
  if (countSel) {
    var c = document.querySelector(countSel);
    if (c) c.textContent = shown + (shown === 1 ? ' torrent' : ' torrents');
  }
  var empty = table.parentNode.querySelector('.ph-empty');
  if (empty) empty.hidden = shown !== 0;
}

// Sortable columns. Add class="ph-sort" data-type="num|text" to <th>.
function phMakeSortable(tableSel) {
  var table = document.querySelector(tableSel);
  if (!table) return;
  var ths = table.tHead ? table.tHead.rows[0].cells : [];
  for (var i = 0; i < ths.length; i++) {
    (function (th, idx) {
      if (!th.classList.contains('ph-sort')) return;
      th.addEventListener('click', function () {
        var asc = th.getAttribute('aria-sort') !== 'ascending';
        for (var j = 0; j < ths.length; j++) ths[j].removeAttribute('aria-sort');
        th.setAttribute('aria-sort', asc ? 'ascending' : 'descending');
        var type = th.getAttribute('data-type') || 'text';
        var body = table.tBodies[0];
        var rows = Array.prototype.slice.call(body.rows);
        rows.sort(function (a, b) {
          var av = cellVal(a.cells[idx], type), bv = cellVal(b.cells[idx], type);
          if (av < bv) return asc ? -1 : 1;
          if (av > bv) return asc ? 1 : -1;
          return 0;
        });
        rows.forEach(function (r) { body.appendChild(r); });
      });
    })(ths[i], i);
  }
  function cellVal(cell, type) {
    if (!cell) return type === 'num' ? -Infinity : '';
    var raw = cell.getAttribute('data-sort');
    if (raw === null) raw = cell.textContent;
    raw = raw.trim();
    if (type === 'num') { var n = parseFloat(raw.replace(/[^0-9.\-]/g, '')); return isNaN(n) ? -Infinity : n; }
    return raw.toLowerCase();
  }
}

function phInitIcons() {
  if (window.lucide) lucide.createIcons({ attrs: { class: 'ph-ico' } });
}
