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
  geoColors = {}
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
    total.toLocaleString() + d.unit + " · " + entries.length + (entries.length === 1 ? " country · " : " countries · ") + d.scope
  document.getElementById("geo-note").textContent = d.note
  document.getElementById("geo-total").textContent = total.toLocaleString()
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
  entries.slice(0, 7).forEach(function (e, i) {
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
      e[1].toLocaleString() +
      "</span></div>"
  })
  document.getElementById("geo-list").innerHTML = html || '<p class="dim text-sm">No data for this metric yet.</p>'
  phInitIcons()
}
function geoBuildMap(d) {
  if (geoMap) {
    try {
      geoMap.destroy()
    } catch {
      /* ignore */
    }
    geoMap = null
  }
  var el = document.getElementById("geo-map")
  el.innerHTML = ""
  if (typeof jsVectorMap === "undefined") return
  var dark = geoIsDark()
  var sc = geoScale(d),
    keys = Object.keys(d.values)
  geoColors = {}
  if (keys.length) {
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
  geoMap = new jsVectorMap({
    selector: "#geo-map",
    map: "world",
    zoomButtons: false,
    zoomOnScroll: false,
    backgroundColor: "transparent",
    regionStyle: {
      initial: { fill: dark ? "#282726" : "#dad8ce", stroke: dark ? "#100f0f" : "#b3b1a8", strokeWidth: 0.3 },
      hover: { fillOpacity: 0.85 },
    },
    onRegionTooltipShow: function (event, tooltip, code) {
      var v = d.values[code]
      tooltip.text((COUNTRY[code] || tooltip.text()) + (v != null ? " — " + v.toLocaleString() + d.unit : " — no data"), true)
    },
  })
  geoApplyFills()
}
function geoApplyFills() {
  document.querySelectorAll("#geo-map svg path").forEach(function (p) {
    var c = geoColors[p.getAttribute("data-code")]
    if (c) {
      p.setAttribute("fill", c)
      p.style.fill = c
    }
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
  geoRenderPanel(GEO[metric])
  geoBuildMap(GEO[metric])
}
document.querySelectorAll(".seg-btn[data-metric]").forEach(function (b) {
  b.addEventListener("click", function () {
    phGeoSet(b.dataset.metric)
  })
})
phGeoSet(GEO_DEFAULT)
new MutationObserver(function () {
  phGeoSet(geoMetric)
}).observe(document.documentElement, { attributes: true, attributeFilter: ["class"] })
