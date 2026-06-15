<?php

declare(strict_types=1);

////	view_admin_geography_html
// Render the admin Geography page: a choropleth world map (jsVectorMap) of the
// supplied per-country metrics, with a metric toggle, stepped legend, and
// top-countries panel. $metrics is the subset of metrics that have a usable
// source — 'peers' (active peers by country, live) and/or 'downloads'
// (completed downloads by country, from the events ledger) — each a
// country-code => count map. When $metrics is empty, a "geo isn't configured"
// state is shown instead. Marks the Geography nav active. Wrapped in the shared
// admin layout. Returns HTML string.
//
/**
 * @param PhoenixSettings $settings
 * @param array<string, array<string, int>> $metrics
 */
function view_admin_geography_html(array $settings, array $metrics, string $csrf_token): string
{
    require_once __DIR__.'/html.admin.layout.php';

    ////	Not configured / no data
    if ($metrics === []) {
        $body = '<div class="ph-empty">
			<span class="ph-ico" data-lucide="globe-2"></span>
			<p>Geographic data isn\'t available yet.</p>
			<p class="dim" style="max-width:52ch;margin:var(--space-2) auto 0">Enable the privacy-preserving events ledger and geo enrichment to populate this map: turn on <code>stats_enabled</code> and <code>stats_geo</code>, run <code>composer require geoip2/geoip2</code>, and point <code>stats_geo_database</code> at a GeoLite2 country <code>.mmdb</code>.</p>
		</div>';

        return view_admin_layout_html($settings, 'Geography', $body, 'geography', $csrf_token, 'Tracker', '', true);
    }

    ////	Presentation for each known metric (the controller decides which to
    // include based on what data exists).
    $presentation = [
        'peers' => [
            'short' => 'Active peers',
            'label' => 'Active peers by country',
            'listTitle' => 'Top countries — peers',
            'unit' => ' peers',
            'scope' => 'right now',
            'note' => 'Live peer counts, by the country of each peer\'s IP — resolved transiently and never stored.',
            'icon' => 'share-2',
            'accent' => '#205ea6',
            'bg' => 'var(--color-info-bg)',
            'scaleL' => ['#c6dde8', '#205ea6'],
            'scaleD' => ['#4385be', '#abcfe2'],
        ],
        'downloads' => [
            'short' => 'Completed downloads',
            'label' => 'Completed downloads by country',
            'listTitle' => 'Top countries — downloads',
            'unit' => ' downloads',
            'scope' => 'all-time',
            'note' => 'Completed downloads, by the coarse country code recorded in the events ledger.',
            'icon' => 'circle-check-big',
            'accent' => '#66800b',
            'bg' => 'var(--color-success-bg)',
            'scaleL' => ['#dde2b2', '#66800b'],
            'scaleD' => ['#879a39', '#bec97e'],
        ],
    ];

    // Merge presentation + values for the included metrics, in $metrics order.
    $geo = [];
    foreach ($metrics as $key => $values) {
        if (! isset($presentation[$key])) {
            continue;
        }
        $geo[$key] = $presentation[$key] + ['values' => $values];
    }
    $default = (string) array_key_first($geo);

    // Metric toggle (top bar). One segment per available metric.
    $toggle = '';
    foreach ($geo as $key => $m) {
        $on = $key === $default;
        $toggle .= '<button class="seg-btn'.($on ? ' is-on' : '').'" type="button" role="tab" aria-selected="'.($on ? 'true' : 'false').'" data-metric="'.htmlspecialchars($key, ENT_QUOTES, 'UTF-8').'" onclick="phGeoSet(\''.htmlspecialchars($key, ENT_QUOTES, 'UTF-8').'\')"><span class="ph-ico" data-lucide="'.$m['icon'].'"></span>'.htmlspecialchars($m['short']).'</button>';
    }
    $actions = '<div class="seg" role="tablist" aria-label="Map metric">'.$toggle.'</div>';

    $extra_head = '
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/css/jsvectormap.min.css">';
    $extra_srcs = [
        'https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/js/jsvectormap.min.js',
        'https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/maps/world.js',
    ];

    $body = '<div class="geo-wrap">
			<div class="geo-mapcard">
				<div class="geo-maphead">
					<div>
						<div class="geo-metric-label" id="geo-metric-label"></div>
						<div class="dim" id="geo-sub" style="font-size:var(--font-size-sm);margin-top:2px"></div>
					</div>
					<div class="geo-legend" id="geo-legend"></div>
				</div>
				<div id="geo-map" class="geo-map"></div>
				<p class="dim geo-foot">Country-level only &mdash; Phoenix never stores raw IP addresses. <span id="geo-note"></span></p>
			</div>
			<aside class="geo-side">
				<div class="ph-stat" id="geo-summary" style="--stat-bg:var(--color-info-bg);--stat-fg:var(--color-blue)">
					<div class="ph-stat-top"><div class="ph-stat-value" id="geo-total">0</div><div class="ph-stat-ico" id="geo-summary-ico"><span class="ph-ico" data-lucide="share-2"></span></div></div>
					<div class="ph-stat-label" id="geo-total-label"></div>
					<div class="ph-stat-sub"><b id="geo-countries">0</b> countries &middot; top: <b id="geo-topcountry">&mdash;</b></div>
				</div>
				<div class="geo-toplist">
					<h3 id="geo-list-title"></h3>
					<div id="geo-list"></div>
				</div>
			</aside>
		</div>';

    $geo_json = (string) json_encode($geo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $geo_js = <<<'JS'
        var COUNTRY = {US:'United States',DE:'Germany',GB:'United Kingdom',FR:'France',NL:'Netherlands',RU:'Russia',CA:'Canada',SE:'Sweden',JP:'Japan',PL:'Poland',BR:'Brazil',AU:'Australia',IT:'Italy',ES:'Spain',IN:'India',FI:'Finland',NO:'Norway',CZ:'Czechia',UA:'Ukraine',KR:'South Korea',CN:'China',MX:'Mexico',TR:'Turkey',CH:'Switzerland',AT:'Austria',BE:'Belgium',DK:'Denmark',PT:'Portugal',RO:'Romania',ZA:'South Africa'};
        var geoMap = null, geoMetric = GEO_DEFAULT, geoColors = {};
        function geoIsDark() { return document.documentElement.classList.contains('theme-dark'); }
        function geoScale(d) { return geoIsDark() ? d.scaleD : d.scaleL; }
        function lerpHex(a, b, t) {
          function p(h){ return [parseInt(h.slice(1,3),16),parseInt(h.slice(3,5),16),parseInt(h.slice(5,7),16)]; }
          var x = p(a), y = p(b);
          return '#' + [0,1,2].map(function(i){ return Math.round(x[i]+(y[i]-x[i])*t).toString(16).padStart(2,'0'); }).join('');
        }
        function geoEntries(d) {
          return Object.keys(d.values).map(function(k){ return [k, d.values[k]]; }).sort(function(a,b){ return b[1]-a[1]; });
        }
        function geoRenderPanel(d) {
          var entries = geoEntries(d);
          var total = entries.reduce(function(s,e){ return s + e[1]; }, 0);
          document.getElementById('geo-metric-label').textContent = d.label;
          document.getElementById('geo-sub').textContent = total.toLocaleString() + d.unit + ' · ' + entries.length + (entries.length === 1 ? ' country · ' : ' countries · ') + d.scope;
          document.getElementById('geo-note').textContent = d.note;
          document.getElementById('geo-total').textContent = total.toLocaleString();
          document.getElementById('geo-total-label').textContent = d.label.replace(' by country', '');
          document.getElementById('geo-list-title').textContent = d.listTitle;
          document.getElementById('geo-countries').textContent = entries.length;
          document.getElementById('geo-topcountry').textContent = entries.length ? (COUNTRY[entries[0][0]] || entries[0][0]) : '—';
          var ico = document.getElementById('geo-summary-ico');
          ico.innerHTML = '<span class="ph-ico" data-lucide="' + d.icon + '"></span>';
          document.getElementById('geo-summary').style.setProperty('--stat-fg', d.accent);
          document.getElementById('geo-summary').style.setProperty('--stat-bg', d.bg);
          var steps = 5, leg = '<span class="lo">low</span>', sc = geoScale(d);
          for (var i = 0; i < steps; i++) leg += '<span class="sw" style="background:' + lerpHex(sc[0], sc[1], i/(steps-1)) + '"></span>';
          leg += '<span class="hi">high</span>';
          document.getElementById('geo-legend').innerHTML = leg;
          var max = entries.length ? entries[0][1] : 1;
          var html = '';
          entries.slice(0, 7).forEach(function(e, i){
            html += '<div class="geo-rowi" style="--geo-c:' + d.accent + '">' +
              '<span class="geo-rank">' + (i+1) + '</span>' +
              '<span class="geo-co"><span class="nm">' + (COUNTRY[e[0]] || e[0]) + '</span><span class="bar"><i style="width:' + Math.round(e[1]/max*100) + '%"></i></span></span>' +
              '<span class="geo-val">' + e[1].toLocaleString() + '</span></div>';
          });
          document.getElementById('geo-list').innerHTML = html || '<p class="dim" style="font-size:var(--font-size-sm)">No data for this metric yet.</p>';
          phInitIcons();
        }
        function geoBuildMap(d) {
          if (geoMap) { try { geoMap.destroy(); } catch (e) {} geoMap = null; }
          var el = document.getElementById('geo-map');
          el.innerHTML = '';
          if (typeof jsVectorMap === 'undefined') return;
          var dark = geoIsDark();
          var sc = geoScale(d), keys = Object.keys(d.values);
          geoColors = {};
          if (keys.length) {
            var vals = keys.map(function (k) { return d.values[k]; });
            var max = Math.max.apply(null, vals), min = Math.min.apply(null, vals);
            keys.forEach(function (k) {
              var t = max > min ? (d.values[k] - min) / (max - min) : 1;
              t = 0.18 + 0.82 * Math.sqrt(t);
              geoColors[k] = lerpHex(sc[0], sc[1], t);
            });
          }
          geoMap = new jsVectorMap({
            selector: '#geo-map', map: 'world', zoomButtons: false, zoomOnScroll: false, backgroundColor: 'transparent',
            regionStyle: { initial: { fill: dark ? '#282726' : '#dad8ce', stroke: dark ? '#100f0f' : '#b3b1a8', strokeWidth: 0.3 }, hover: { fillOpacity: 0.85 } },
            onRegionTooltipShow: function (event, tooltip, code) {
              var v = d.values[code];
              tooltip.text((COUNTRY[code] || tooltip.text()) + (v != null ? ' — ' + v.toLocaleString() + d.unit : ' — no data'), true);
            }
          });
          geoApplyFills();
        }
        function geoApplyFills() {
          document.querySelectorAll('#geo-map svg path').forEach(function (p) {
            var c = geoColors[p.getAttribute('data-code')];
            if (c) { p.setAttribute('fill', c); p.style.fill = c; }
          });
        }
        function phGeoSet(metric) {
          if (!GEO[metric]) return;
          geoMetric = metric;
          document.querySelectorAll('.seg-btn').forEach(function (b) {
            var on = b.dataset.metric === metric;
            b.classList.toggle('is-on', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
          });
          geoRenderPanel(GEO[metric]);
          geoBuildMap(GEO[metric]);
        }
        phGeoSet(GEO_DEFAULT);
        new MutationObserver(function () { phGeoSet(geoMetric); })
          .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        JS;

    $inline_js = 'var GEO = '.$geo_json.";\nvar GEO_DEFAULT = ".json_encode($default).";\n".$geo_js;

    return view_admin_layout_html($settings, 'Geography', $body, 'geography', $csrf_token, 'Tracker', $actions, false, $extra_head, $inline_js, $extra_srcs);
}
