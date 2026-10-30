/* Phoenix — client breakdown chart (admin Dashboard).
 * Inlined by PHP inside a <script> tag, prefixed with `var CLIENTS = […];` —
 * [[family, [[major, count], …]], …] from stats_client_chart(), biggest family
 * first and newest major first within each. Draws one horizontal bar per
 * family, divided into its versions. `var CLIENTS_UNIT = "…";` names what the
 * counts are, singular — "peer" for the live swarm, "completed download" for
 * the ledger — for the tooltip.
 *
 * Lists of pairs, not objects: an object would hand back integer-like keys
 * (a family named "53", majors "3" and "4") first and in ascending order,
 * whatever order PHP sorted them into.
 *
 * Horizontal, because family names are words and would otherwise be rotated or
 * truncated; stacked, because the question is "how much of my swarm is
 * Transmission" first and "which Transmission" second. */
/* global CLIENTS, CLIENTS_UNIT, Chart */

// Theme colours, read at paint time rather than captured once: the class on
// <html> flips under a live chart when the reader toggles the theme.
//
// The grid and tick colours have to be given. Chart.js defaults them to a dark
// grey that is invisible against a dark background, which is what hid this
// chart's vertical grid in dark mode while the other charts, which set them,
// were fine.
function phClientTheme() {
  var dark = document.documentElement.classList.contains("theme-dark")
  return {
    // Shades of the action colour, strongest for the newest version, so a bar
    // reads as one family at a glance and still separates into versions.
    shades: dark ? ["#abcfe2", "#7ab4d6", "#4385be", "#2d6ca8", "#1f4f80"] : ["#205ea6", "#4385be", "#66a0c8", "#a1cbe4", "#c6dde8"],
    grid: dark ? "rgba(255,252,240,0.14)" : "rgba(16,15,15,0.14)",
    text: dark ? "#b7b5ac" : "#6f6e69",
  }
}

function phClientChart() {
  var el = document.getElementById("clients-chart")
  if (!el || typeof Chart === "undefined") return

  if (!CLIENTS.length) return
  var families = CLIENTS.map(function (c) {
    return c[0]
  })

  // One dataset per stack position rather than per version: versions differ
  // between families, so stacking by name would leave most cells empty. Slot 0
  // is each family's newest version, slot 1 the next, and so on.
  var depth = 0
  CLIENTS.forEach(function (c) {
    depth = Math.max(depth, c[1].length)
  })

  var c = phClientTheme()
  var shades = c.shades

  var datasets = []
  for (var slot = 0; slot < depth; slot++) {
    datasets.push({
      label: "v" + (slot + 1),
      backgroundColor: shades[Math.min(slot, shades.length - 1)],
      borderWidth: 0,
      data: CLIENTS.map(function (c) {
        return slot < c[1].length ? c[1][slot][1] : 0
      }),
      // The version name each slot holds, per family — read back by the
      // tooltip, since the dataset label is only a stack position.
      phNames: CLIENTS.map(function (c) {
        return slot < c[1].length ? c[1][slot][0] : null
      }),
    })
  }

  return new Chart(el, {
    type: "bar",
    data: { labels: families, datasets: datasets },
    options: {
      indexAxis: "y",
      responsive: true,
      maintainAspectRatio: false,
      // Any point in a row's band opens that row's tooltip, not only the bar
      // itself. Counts run from 1 to hundreds of thousands on one axis, so most
      // bars are a few pixels wide, or less, and could not be hovered at all.
      interaction: { mode: "index", axis: "y", intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function (ctx) {
              var name = ctx.dataset.phNames[ctx.dataIndex]
              if (!ctx.raw) return null
              return (name ? name + ": " : "") + ctx.raw.toLocaleString() + " " + CLIENTS_UNIT + (ctx.raw === 1 ? "" : "s")
            },
          },
        },
      },
      scales: {
        x: {
          stacked: true,
          beginAtZero: true,
          grid: { color: c.grid },
          ticks: { precision: 0, color: c.text },
        },
        y: { stacked: true, grid: { display: false }, ticks: { color: c.text } },
      },
    },
  })
}

var phClientInstance = phClientChart()

// Repaint on theme change. Without this the chart keeps the palette it was
// built with, so a grid sized for one background all but vanishes on the other.
document.addEventListener("phoenix:theme", function () {
  if (!phClientInstance) return
  var c = phClientTheme()
  var o = phClientInstance.options
  o.scales.x.grid.color = c.grid
  o.scales.x.ticks.color = c.text
  o.scales.y.ticks.color = c.text
  phClientInstance.data.datasets.forEach(function (d, slot) {
    d.backgroundColor = c.shades[Math.min(slot, c.shades.length - 1)]
  })
  phClientInstance.update("none")
})
