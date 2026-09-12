/* Phoenix — live swarm bandwidth (admin Bandwidth page, Active peers metric).
 * Inlined by PHP inside a <script> tag, prefixed with `var SWARM = […];` — the
 * busiest peers, each { label, client, torrent, uploaded, downloaded }.
 *
 * Paired horizontal bars rather than one: the live counters carry both
 * directions, and the ratio is the interesting part — a peer pulling hundreds
 * of megabytes while serving nothing reads differently from one doing both.
 *
 * This replaces the time series under the Active peers metric, because these
 * counters have no history: they are cumulative-since-client-start and vanish
 * when a peer leaves, so there is nothing to plot over time. */
/* global SWARM, Chart */

// Theme colours, read at paint time rather than captured once: the class on
// <html> flips under a live chart when the reader toggles the theme.
function phSwarmTheme() {
  var dark = document.documentElement.classList.contains("theme-dark")
  return {
    up: dark ? "#879a39" : "#66800b",
    down: dark ? "#4385be" : "#205ea6",
    grid: dark ? "rgba(255,252,240,0.14)" : "rgba(16,15,15,0.14)",
    text: dark ? "#b7b5ac" : "#6f6e69",
  }
}

function phSwarmChart() {
  var el = document.getElementById("swarm-chart")
  if (!el || typeof Chart === "undefined" || !SWARM.length) return

  var c = phSwarmTheme()
  var up = c.up
  var down = c.down
  var grid = c.grid
  var text = c.text

  function unit(bytes) {
    var u = ["B", "KB", "MB", "GB", "TB", "PB"]
    var i = 0
    while (bytes >= 1024 && i < u.length - 1) {
      bytes /= 1024
      i++
    }
    return (bytes < 10 && i > 0 ? bytes.toFixed(1) : Math.round(bytes)) + " " + u[i]
  }

  return new Chart(el, {
    type: "bar",
    data: {
      labels: SWARM.map(function (p) {
        return p.label
      }),
      datasets: [
        {
          label: "Uploaded",
          data: SWARM.map(function (p) {
            return p.uploaded
          }),
          backgroundColor: up,
          borderWidth: 0,
        },
        {
          label: "Downloaded",
          data: SWARM.map(function (p) {
            return p.downloaded
          }),
          backgroundColor: down,
          borderWidth: 0,
        },
      ],
    },
    options: {
      indexAxis: "y",
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: "bottom", labels: { color: text, boxWidth: 12 } },
        tooltip: {
          callbacks: {
            title: function (ctx) {
              var p = SWARM[ctx[0].dataIndex]
              return p.client ? p.label + " · " + p.client : p.label
            },
            label: function (ctx) {
              return ctx.dataset.label + ": " + unit(ctx.raw)
            },
            afterBody: function (ctx) {
              var p = SWARM[ctx[0].dataIndex]
              return p.torrent ? p.torrent : ""
            },
          },
        },
      },
      scales: {
        x: {
          beginAtZero: true,
          grid: { color: grid },
          ticks: {
            color: text,
            callback: function (v) {
              return unit(v)
            },
          },
        },
        y: { grid: { display: false }, ticks: { color: text } },
      },
    },
  })
}

var phSwarmInstance = phSwarmChart()

// Repaint on theme change. Without this the chart keeps the palette it was
// built with, so a grid sized for one background all but vanishes on the other.
document.addEventListener("phoenix:theme", function () {
  if (!phSwarmInstance) return
  var c = phSwarmTheme()
  var o = phSwarmInstance.options
  o.scales.x.grid.color = c.grid
  o.scales.x.ticks.color = c.text
  o.scales.y.ticks.color = c.text
  o.plugins.legend.labels.color = c.text
  phSwarmInstance.data.datasets[0].backgroundColor = c.up
  phSwarmInstance.data.datasets[1].backgroundColor = c.down
  phSwarmInstance.update("none")
})
