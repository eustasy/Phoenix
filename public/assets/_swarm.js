/* Phoenix — live swarm traffic (admin Traffic page, Active peers metric).
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

function phSwarmChart() {
  var el = document.getElementById("swarm-chart")
  if (!el || typeof Chart === "undefined" || !SWARM.length) return

  var dark = document.documentElement.classList.contains("theme-dark")
  var up = dark ? "#879a39" : "#66800b"
  var down = dark ? "#4385be" : "#205ea6"
  var grid = dark ? "rgba(255,252,240,0.08)" : "rgba(16,15,15,0.08)"
  var text = dark ? "#b7b5ac" : "#6f6e69"

  function unit(bytes) {
    var u = ["B", "KB", "MB", "GB", "TB", "PB"]
    var i = 0
    while (bytes >= 1024 && i < u.length - 1) {
      bytes /= 1024
      i++
    }
    return (bytes < 10 && i > 0 ? bytes.toFixed(1) : Math.round(bytes)) + " " + u[i]
  }

  new Chart(el, {
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

phSwarmChart()
