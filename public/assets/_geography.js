/* Phoenix — Geography choropleth (admin Geography page).
 * Inlined by PHP inside a <script> tag, prefixed with `var GEO = {…}; var
 * GEO_DEFAULT = "…";` (the per-country metrics + default metric key). Renders the
 * jsVectorMap world map, the metric toggle, legend, and top-countries panel. */
/* global GEO, GEO_DEFAULT, jsVectorMap, phInitIcons */

var COUNTRY = {
  US: "United States",
  DE: "Germany",
  GB: "United Kingdom",
  FR: "France",
  NL: "Netherlands",
  RU: "Russia",
  CA: "Canada",
  SE: "Sweden",
  JP: "Japan",
  PL: "Poland",
  BR: "Brazil",
  AU: "Australia",
  IT: "Italy",
  ES: "Spain",
  IN: "India",
  FI: "Finland",
  NO: "Norway",
  CZ: "Czechia",
  UA: "Ukraine",
  KR: "South Korea",
  CN: "China",
  MX: "Mexico",
  TR: "Turkey",
  CH: "Switzerland",
  AT: "Austria",
  BE: "Belgium",
  DK: "Denmark",
  PT: "Portugal",
  RO: "Romania",
  ZA: "South Africa",
}
var geoMap = null,
  geoMetric = GEO_DEFAULT,
  geoColors = {},
  // The metric currently rendered, read by the tooltip so a recolour does not
  // need the map rebuilt just to refresh the closure.
  geoData = null,
  // Theme the regions were last painted for.
  geoPaintedDark = null,
  // No-data region fill and stroke for that theme. Every region is painted from
  // these, so a theme change is a recolour like any other — the map is built
  // once and never rebuilt, which is what kept breaking.
  geoBaseFill = "",
  geoBaseStroke = ""
function geoIsDark() {
  return document.documentElement.classList.contains("theme-dark")
}
function geoScale(d) {
  return geoIsDark() ? d.scaleD : d.scaleL
}
function lerpHex(a, b, t) {
  function p(h) {
    return [parseInt(h.slice(1, 3), 16), parseInt(h.slice(3, 5), 16), parseInt(h.slice(5, 7), 16)]
  }
  var x = p(a),
    y = p(b)
  return (
    "#" +
    [0, 1, 2]
      .map(function (i) {
        return Math.round(x[i] + (y[i] - x[i]) * t)
          .toString(16)
          .padStart(2, "0")
      })
      .join("")
  )
}
// A metric's values may be counts or bytes; "20.1 TB" beats 22098152264304.
// $unit is appended only where there is room for it (the summary line), not on
// every row of the list — see geoValue.
function geoFormat(d, n) {
  if (d.format !== "bytes") return n.toLocaleString() + d.unit
  var u = ["B", "KB", "MB", "GB", "TB", "PB"]
  var i = 0
  while (n >= 1024 && i < u.length - 1) {
    n /= 1024
    i++
  }
  return (n < 10 && i > 0 ? n.toFixed(1) : Math.round(n)) + " " + u[i]
}
// The list's value column: the bare figure. "peers" and "downloads" are not
// units, and repeating either down a narrow column only costs width — the
// metric label above the list already says what is being counted. Bytes still
// need their size suffix to mean anything.
function geoValue(d, n) {
  return d.format === "bytes" ? geoFormat(d, n) : n.toLocaleString()
}
function geoEntries(d) {
  return Object.keys(d.values)
    .map(function (k) {
      return [k, d.values[k]]
    })
    .sort(function (a, b) {
      return b[1] - a[1]
    })
}
function geoRenderPanel(d) {
  var entries = geoEntries(d)
  var total = entries.reduce(function (s, e) {
    return s + e[1]
  }, 0)
  document.getElementById("geo-metric-label").textContent = d.label
  document.getElementById("geo-sub").textContent =
    geoFormat(d, total) + " · " + entries.length + (entries.length === 1 ? " country · " : " countries · ") + d.scope
  document.getElementById("geo-note").textContent = d.note
  document.getElementById("geo-total").textContent = d.format === "bytes" ? geoFormat(d, total) : total.toLocaleString()
  document.getElementById("geo-total-label").textContent = d.label.replace(" by country", "")
  document.getElementById("geo-list-title").textContent = d.listTitle
  document.getElementById("geo-countries").textContent = entries.length
  document.getElementById("geo-topcountry").textContent = entries.length ? COUNTRY[entries[0][0]] || entries[0][0] : "—"
  var ico = document.getElementById("geo-summary-ico")
  ico.innerHTML = '<span class="ph-ico" data-lucide="' + d.icon + '"></span>'
  document.getElementById("geo-summary").style.setProperty("--stat-fg", d.accent)
  document.getElementById("geo-summary").style.setProperty("--stat-bg", d.bg)
  var steps = 5,
    leg = '<span class="lo">low</span>',
    sc = geoScale(d)
  for (var i = 0; i < steps; i++) leg += '<span class="sw" style="background:' + lerpHex(sc[0], sc[1], i / (steps - 1)) + '"></span>'
  leg += '<span class="hi">high</span>'
  document.getElementById("geo-legend").innerHTML = leg
  var max = entries.length ? entries[0][1] : 1
  var html = ""
  // 12, not a handful: the panel runs the full height of the map beside it, and
  // a shorter list left it half empty.
  entries.slice(0, 12).forEach(function (e, i) {
    html +=
      '<div class="geo-rowi" style="--geo-c:' +
      d.accent +
      '">' +
      '<span class="geo-rank">' +
      (i + 1) +
      "</span>" +
      '<span class="geo-co"><span class="nm">' +
      (COUNTRY[e[0]] || e[0]) +
      '</span><span class="bar"><i style="width:' +
      Math.round((e[1] / max) * 100) +
      '%"></i></span></span>' +
      '<span class="geo-val">' +
      geoValue(d, e[1]) +
      "</span></div>"
  })
  document.getElementById("geo-list").innerHTML = html || '<p class="dim text-sm">No data for this metric yet.</p>'
  phInitIcons()
}
// Resolve the theme-dependent colours once per render, so every path below
// paints from the same pair.
function geoSetThemeColors() {
  var dark = geoIsDark()
  geoPaintedDark = dark
  geoBaseFill = dark ? "#282726" : "#dad8ce"
  geoBaseStroke = dark ? "#100f0f" : "#b3b1a8"
}
function geoComputeColors(d) {
  var sc = geoScale(d),
    keys = Object.keys(d.values)
  geoColors = {}
  if (!keys.length) return
  var vals = keys.map(function (k) {
    return d.values[k]
  })
  var max = Math.max.apply(null, vals),
    min = Math.min.apply(null, vals)
  keys.forEach(function (k) {
    var t = max > min ? (d.values[k] - min) / (max - min) : 1
    t = 0.18 + 0.82 * Math.sqrt(t)
    geoColors[k] = lerpHex(sc[0], sc[1], t)
  })
}
// Called once, on the first render. Everything after that is a recolour, so
// there is no destroy/reconstruct path to get wrong.
function geoBuildMap() {
  var el = document.getElementById("geo-map")
  el.innerHTML = ""
  if (typeof jsVectorMap === "undefined") return
  geoMap = new jsVectorMap({
    selector: "#geo-map",
    map: "world",
    zoomButtons: false,
    zoomOnScroll: false,
    backgroundColor: "transparent",
    regionStyle: {
      initial: { fill: geoBaseFill, stroke: geoBaseStroke, strokeWidth: 0.3 },
      hover: { fillOpacity: 0.85 },
    },
    // Hand the fills to the library rather than only painting paths after
    // construction: a series is part of the map's own model, so it survives the
    // settling pass that re-applies regionStyle.initial after first paint.
    series: { regions: [{ attribute: "fill", values: geoColors }] },
    // Reads the module-level metric, not a captured one, so switching metric
    // does not need the map rebuilt for the tooltip to stay correct.
    onRegionTooltipShow: function (event, tooltip, code) {
      var v = geoData ? geoData.values[code] : null
      tooltip.text((COUNTRY[code] || tooltip.text()) + (v != null ? " — " + geoFormat(geoData, v) : " — no data"), true)
    },
  })
  geoSchedulePaint()
}
// jsVectorMap re-applies regionStyle.initial once the map has laid out, which
// wipes both the series and any paint that ran before it. That pass lands a
// frame later on a first build and later still on a rebuild (a theme flip), so
// repaint over the next two frames and once more shortly after rather than
// betting on a single deferred call. Painting a region that is already the
// right colour costs nothing.
function geoSchedulePaint() {
  geoApplyFills()
  requestAnimationFrame(function () {
    geoApplyFills()
    requestAnimationFrame(geoApplyFills)
  })
  setTimeout(geoApplyFills, 150)
}
// Paints every region, not just the ones with a value: a region the new metric
// does not cover has to be reset to the base fill, or it keeps the colour the
// previous metric gave it and the two readings appear overlaid.
function geoApplyFills() {
  document.querySelectorAll("#geo-map svg path").forEach(function (p) {
    var c = geoColors[p.getAttribute("data-code")] || geoBaseFill
    if (!c) return
    p.setAttribute("fill", c)
    p.style.fill = c
    // Stroke is theme-dependent too, and is why a theme change used to need the
    // map rebuilt. Setting it here removes the last reason to.
    if (geoBaseStroke) p.style.stroke = geoBaseStroke
  })
}
function phGeoSet(metric) {
  if (!GEO[metric]) return
  geoMetric = metric
  document.querySelectorAll(".seg-btn").forEach(function (b) {
    var on = b.dataset.metric === metric
    b.classList.toggle("is-on", on)
    b.setAttribute("aria-selected", on ? "true" : "false")
  })
  geoData = GEO[metric]
  geoRenderPanel(geoData)
  geoSetThemeColors()
  geoComputeColors(geoData)

  // Built once, then only ever recoloured — metric change and theme change take
  // the same path. Destroying and reconstructing the map was the source of the
  // toggle and theme-switch breakage.
  if (!geoMap) {
    geoBuildMap()
    return
  }
  try {
    geoMap.series.regions[0].setValues(geoColors)
  } catch {
    /* older builds: fall through to painting the paths directly */
  }
  geoSchedulePaint()
}

document.querySelectorAll(".seg-btn[data-metric]").forEach(function (b) {
  b.addEventListener("click", function () {
    phGeoSet(b.dataset.metric)
  })
})
phGeoSet(GEO_DEFAULT)
// The theme toggle flips two classes on <html>; other code may touch them too.
// Only react when the theme actually differs from the one last painted, so an
// unrelated class change cannot trigger a needless repaint.
new MutationObserver(function () {
  if (geoPaintedDark === geoIsDark()) return
  phGeoSet(geoMetric)
}).observe(document.documentElement, { attributes: true, attributeFilter: ["class"] })
