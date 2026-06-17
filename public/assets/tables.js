/* Phoenix — data-table enhancements (assets/tables.js).
 * Progressive enhancement for the index / torrents / peers tables: a live row
 * filter and click-to-sort columns. Tables work fully without this script.
 *
 * Wiring (no inline JS):
 *   - a search <input data-filter-table="#tbl" data-filter-count="#count"> drives
 *     phFilterTable over that table; data-filter-count is optional.
 *   - any table whose <thead> has a <th class="ph-sort"> is made sortable. */

// Live row filter over a table body. Matches text content of every column.
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

// Sortable columns. Pass a table element or selector; sorts <th class="ph-sort">
// by data-sort (or text), typed via data-type="num|text".
function phMakeSortable(tableRef) {
  var table = typeof tableRef === 'string' ? document.querySelector(tableRef) : tableRef;
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
    if (type === 'num') { var n = parseFloat(raw.replace(/[^0-9.-]/g, '')); return isNaN(n) ? -Infinity : n; }
    return raw.toLowerCase();
  }
}

// Auto-wire on load (scripts run at end of <body>, so the DOM is ready).
document.querySelectorAll('[data-filter-table]').forEach(function (input) {
  input.addEventListener('input', function () {
    phFilterTable(input, input.getAttribute('data-filter-table'), input.getAttribute('data-filter-count'));
  });
});

document.querySelectorAll('table').forEach(function (table) {
  if (table.tHead && table.tHead.querySelector('th.ph-sort')) phMakeSortable(table);
});
