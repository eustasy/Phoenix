/* Phoenix — traffic over time (admin Traffic page, and the dashboard summary).
 * Inlined by PHP inside a <script> tag, prefixed with `var TRAFFIC = […]; var
 * TRAFFIC_BUCKET = <seconds>;` — the bucketed series, oldest first.
 *
 * Empty buckets are omitted by the query rather than filled with zeroes, so the
 * x axis is time-based and spans the gaps itself: a quiet week should read as a
 * flat stretch, not as the line skipping forward. */
/* global TRAFFIC, TRAFFIC_BUCKET, Chart */

// Theme colours, read at paint time rather than captured once: the class on
// <html> flips under a live chart when the reader toggles the theme.
function phTrafficTheme() {
  var dark = document.documentElement.classList.contains("theme-dark")
  return {
    line: dark ? "#879a39" : "#66800b",
    grid: dark ? "rgba(255,252,240,0.14)" : "rgba(16,15,15,0.14)",
    text: dark ? "#b7b5ac" : "#6f6e69",
  }
}

function phTrafficChart(canvasId) {
  var el = document.getElementById(canvasId || "traffic-chart")
  if (!el || typeof Chart === "undefined" || !TRAFFIC.length) return

  var c = phTrafficTheme()
  var line = c.line
  var grid = c.grid
  var text = c.text

  // A bucket wider than a week is a month's worth; label accordingly.
  var monthly = TRAFFIC_BUCKET > 604800

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
    type: "line",
    data: {
      labels: TRAFFIC.map(function (p) {
        var d = new Date(p.time * 1000)
        return monthly
          ? d.toLocaleDateString(undefined, { year: "numeric", month: "short" })
          : d.toLocaleDateString(undefined, { month: "short", day: "numeric" })
      }),
      datasets: [
        {
          data: TRAFFIC.map(function (p) {
            return p.bytes
          }),
          borderColor: line,
          backgroundColor: line + "22",
          borderWidth: 2,
          pointRadius: 0,
          pointHitRadius: 12,
          fill: true,
          tension: 0.25,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: "index", intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function (ctx) {
              var p = TRAFFIC[ctx.dataIndex]
              return unit(p.bytes) + " · " + p.completions.toLocaleString() + " completed"
            },
          },
        },
      },
      scales: {
        x: { grid: { display: false }, ticks: { color: text, maxRotation: 0, autoSkipPadding: 24 } },
        y: {
          beginAtZero: true,
          grid: { color: grid },
          ticks: {
            color: text,
            callback: function (v) {
              return unit(v)
            },
          },
        },
      },
    },
  })
}

var phTrafficInstance = phTrafficChart()

// Repaint on theme change. Without this the chart keeps the palette it was
// built with, so a grid sized for one background all but vanishes on the other.
document.addEventListener("phoenix:theme", function () {
  if (!phTrafficInstance) return
  var c = phTrafficTheme()
  var o = phTrafficInstance.options
  o.scales.x.ticks.color = c.text
  o.scales.y.ticks.color = c.text
  o.scales.y.grid.color = c.grid
  phTrafficInstance.data.datasets.forEach(function (d) {
    d.borderColor = c.line
    d.backgroundColor = c.line + "22"
  })
  phTrafficInstance.update("none")
})
