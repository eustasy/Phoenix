/* Phoenix — client breakdown chart (admin Dashboard).
 * Inlined by PHP inside a <script> tag, prefixed with `var CLIENTS = {…};` —
 * client family => { version => peer count }, biggest family first. Draws one
 * horizontal bar per family, divided into its versions.
 *
 * Horizontal, because family names are words and would otherwise be rotated or
 * truncated; stacked, because the question is "how much of my swarm is
 * Transmission" first and "which Transmission" second. */
/* global CLIENTS, Chart */

function phClientChart() {
  var el = document.getElementById("clients-chart")
  if (!el || typeof Chart === "undefined") return

  var families = Object.keys(CLIENTS)
  if (!families.length) return

  // One dataset per stack position rather than per version: versions differ
  // between families, so stacking by name would leave most cells empty. Slot 0
  // is each family's most-used version, slot 1 the next, and so on.
  var depth = 0
  families.forEach(function (f) {
    depth = Math.max(depth, Object.keys(CLIENTS[f]).length)
  })

  // Shades of the action colour, darkest for the most-used version, so a bar
  // reads as one family at a glance and still separates into versions.
  var shades = ["#205ea6", "#4385be", "#66a0c8", "#a1cbe4", "#c6dde8"]
  var dark = document.documentElement.classList.contains("theme-dark")
  if (dark) shades = ["#abcfe2", "#7ab4d6", "#4385be", "#2d6ca8", "#1f4f80"]

  var datasets = []
  for (var slot = 0; slot < depth; slot++) {
    datasets.push({
      label: "v" + (slot + 1),
      backgroundColor: shades[Math.min(slot, shades.length - 1)],
      borderWidth: 0,
      data: families.map(function (f) {
        var versions = Object.keys(CLIENTS[f])
        return slot < versions.length ? CLIENTS[f][versions[slot]] : 0
      }),
      // The version name each slot holds, per family — read back by the
      // tooltip, since the dataset label is only a stack position.
      phNames: families.map(function (f) {
        var versions = Object.keys(CLIENTS[f])
        return slot < versions.length ? versions[slot] : null
      }),
    })
  }

  new Chart(el, {
    type: "bar",
    data: { labels: families, datasets: datasets },
    options: {
      indexAxis: "y",
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function (ctx) {
              var name = ctx.dataset.phNames[ctx.dataIndex]
              if (!ctx.raw) return null
              return (name ? name + ": " : "") + ctx.raw.toLocaleString() + " peers"
            },
          },
        },
      },
      scales: {
        x: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
        y: { stacked: true, grid: { display: false } },
      },
    },
  })
}

phClientChart()
