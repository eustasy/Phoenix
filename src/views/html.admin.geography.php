<?php

declare(strict_types=1);

////	view_admin_geography_html
// Render the admin Geography page: a choropleth world map (jsVectorMap) with an
// Active-peers / Total-traffic toggle, a stepped legend, and a top-countries
// panel. This is the UI only — it is NOT yet wired to the tracker. It renders
// preview/sample figures (clearly banner-flagged) so the operator can see the
// finished surface; a future change will derive real per-country counts from
// the privacy-preserving events ledger (stats_enabled + stats_geo). Marks the
// Geography nav active. Wrapped in the shared admin layout. Returns HTML string.

/** @param PhoenixSettings $settings */
function view_admin_geography_html(array $settings, string $csrf_token): string
{
    require_once __DIR__.'/html.admin.layout.php';

    $extra_head = '
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/css/jsvectormap.min.css">';

    $extra_srcs = [
        'https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/js/jsvectormap.min.js',
        'https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/maps/world.js',
    ];

    // Metric toggle lives in the top bar.
    $actions = '<div class="seg" role="tablist" aria-label="Map metric">'.
        '<button class="seg-btn is-on" type="button" role="tab" aria-selected="true" data-metric="peers" onclick="phGeoSet(\'peers\')"><span class="ph-ico" data-lucide="share-2"></span>Active peers</button>'.
        '<button class="seg-btn" type="button" role="tab" aria-selected="false" data-metric="traffic" onclick="phGeoSet(\'traffic\')"><span class="ph-ico" data-lucide="arrow-up-down"></span>Total traffic</button>'.
        '</div>';

    $body = '<div class="alert alert-info" style="display:flex;gap:var(--space-2);align-items:flex-start"><span class="ph-ico" data-lucide="info" style="flex-shrink:0"></span><div><strong>Preview &mdash; sample data.</strong> This page is not wired to the tracker yet. Real per-country figures will be derived from the events ledger once <code>stats_enabled</code> and <code>stats_geo</code> are configured.</div></div>

		<div class="geo-wrap">
			<div class="geo-mapcard">
				<div class="geo-maphead">
					<div>
						<div class="geo-metric-label" id="geo-metric-label">Active peers by country</div>
						<div class="dim" id="geo-sub" style="font-size:var(--font-size-sm);margin-top:2px">2,841 peers &middot; 38 countries &middot; right now</div>
					</div>
					<div class="geo-legend" id="geo-legend"></div>
				</div>
				<div id="geo-map" class="geo-map"></div>
				<p class="dim geo-foot">Country-level only &mdash; derived from each peer\'s IP at announce time and never stored, per the privacy-preserving events ledger. <span id="geo-note">Live peer counts.</span></p>
			</div>
			<aside class="geo-side">
				<div class="ph-stat" id="geo-summary" style="--stat-bg:var(--color-info-bg);--stat-fg:var(--color-blue)">
					<div class="ph-stat-top"><div class="ph-stat-value" id="geo-total">2,841</div><div class="ph-stat-ico" id="geo-summary-ico"><span class="ph-ico" data-lucide="share-2"></span></div></div>
					<div class="ph-stat-label" id="geo-total-label">Active peers</div>
					<div class="ph-stat-sub"><b id="geo-countries">38</b> countries &middot; top: <b id="geo-topcountry">United States</b></div>
				</div>
				<div class="geo-toplist">
					<h3 id="geo-list-title">Top countries &mdash; peers</h3>
					<div id="geo-list"></div>
				</div>
			</aside>
		</div>';

    $inline_js = <<<'JS'
        var COUNTRY = {US:'United States',DE:'Germany',GB:'United Kingdom',FR:'France',NL:'Netherlands',RU:'Russia',CA:'Canada',SE:'Sweden',JP:'Japan',PL:'Poland',BR:'Brazil',AU:'Australia',IT:'Italy',ES:'Spain',IN:'India',FI:'Finland',NO:'Norway',CZ:'Czechia',UA:'Ukraine',KR:'South Korea'};
        function fmtMB(v){ return v >= 1000 ? (v/1000).toFixed(1).replace(/\.0$/,'') + ' GB' : v + ' MB'; }
        var GEO = {
          peers: {
            metricLabel: 'Active peers by country', sub: '2,841 peers · 38 countries · right now',
            note: 'Live peer counts.', total: '2,841', totalLabel: 'Active peers', listTitle: 'Top countries — peers',
            icon: 'share-2', accent: '#205ea6', bg: 'var(--color-info-bg)', scaleL: ['#c6dde8', '#205ea6'], scaleD: ['#4385be', '#abcfe2'], unitSuffix: ' peers',
            fmt: function (v) { return v.toLocaleString(); },
            values: {US:612,DE:401,GB:288,FR:219,NL:207,RU:156,CA:134,SE:112,JP:98,PL:91,BR:84,AU:73,IT:64,ES:57,IN:49,FI:38,NO:31,CZ:27,UA:24,KR:22}
          },
          traffic: {
            metricLabel: 'Total traffic by country', sub: '6.0 GB served · 38 countries · all-time',
            note: 'Cumulative bytes served.', total: '6.0 GB', totalLabel: 'Traffic served', listTitle: 'Top countries — traffic',
            icon: 'arrow-up-down', accent: '#bc5215', bg: 'var(--color-warning-bg)', scaleL: ['#fed3af', '#bc5215'], scaleD: ['#da702c', '#fcc192'], unitSuffix: '',
            fmt: function (v) { return fmtMB(v); },
            values: {US:1340,DE:920,NL:760,GB:610,FR:420,SE:360,RU:300,CA:250,JP:190,PL:160,BR:150,AU:120,IT:110,ES:95,IN:80,FI:60,NO:48,CZ:40,UA:33,KR:30}
          }
        };
        var geoMap = null, geoMetric = 'peers', geoColors = {};
        function geoIsDark() { return document.documentElement.classList.contains('theme-dark'); }
        function geoScale(d) { return geoIsDark() ? d.scaleD : d.scaleL; }
        function lerpHex(a, b, t) {
          function p(h){ return [parseInt(h.slice(1,3),16),parseInt(h.slice(3,5),16),parseInt(h.slice(5,7),16)]; }
          var x = p(a), y = p(b);
          return '#' + [0,1,2].map(function(i){ return Math.round(x[i]+(y[i]-x[i])*t).toString(16).padStart(2,'0'); }).join('');
        }
        function geoRenderPanel(d) {
          document.getElementById('geo-metric-label').textContent = d.metricLabel;
          document.getElementById('geo-sub').textContent = d.sub;
          document.getElementById('geo-note').textContent = d.note;
          document.getElementById('geo-total').textContent = d.total;
          document.getElementById('geo-total-label').textContent = d.totalLabel;
          document.getElementById('geo-list-title').textContent = d.listTitle;
          var ico = document.getElementById('geo-summary-ico');
          ico.innerHTML = '<span class="ph-ico" data-lucide="' + d.icon + '"></span>';
          document.getElementById('geo-summary').style.setProperty('--stat-fg', d.accent);
          document.getElementById('geo-summary').style.setProperty('--stat-bg', d.bg);
          var steps = 5, leg = '<span class="lo">low</span>', sc = geoScale(d);
          for (var i = 0; i < steps; i++) leg += '<span class="sw" style="background:' + lerpHex(sc[0], sc[1], i/(steps-1)) + '"></span>';
          leg += '<span class="hi">high</span>';
          document.getElementById('geo-legend').innerHTML = leg;
          var entries = Object.keys(d.values).map(function(k){ return [k, d.values[k]]; }).sort(function(a,b){ return b[1]-a[1]; });
          var max = entries[0][1];
          document.getElementById('geo-topcountry').textContent = COUNTRY[entries[0][0]] || entries[0][0];
          var html = '';
          entries.slice(0, 7).forEach(function(e, i){
            html += '<div class="geo-rowi" style="--geo-c:' + d.accent + '">' +
              '<span class="geo-rank">' + (i+1) + '</span>' +
              '<span class="geo-co"><span class="nm">' + (COUNTRY[e[0]] || e[0]) + '</span><span class="bar"><i style="width:' + Math.round(e[1]/max*100) + '%"></i></span></span>' +
              '<span class="geo-val">' + d.fmt(e[1]) + '</span></div>';
          });
          document.getElementById('geo-list').innerHTML = html;
          phInitIcons();
        }
        function geoApplyFills() {
          document.querySelectorAll('#geo-map svg path').forEach(function (p) {
            var c = geoColors[p.getAttribute('data-code')];
            if (c) { p.setAttribute('fill', c); p.style.fill = c; }
          });
        }
        function geoBuildMap(d) {
          if (geoMap) { try { geoMap.destroy(); } catch (e) {} geoMap = null; }
          var el = document.getElementById('geo-map');
          el.innerHTML = '';
          if (typeof jsVectorMap === 'undefined') return;
          var dark = geoIsDark();
          var sc = geoScale(d), keys = Object.keys(d.values);
          var max = Math.max.apply(null, keys.map(function (k) { return d.values[k]; }));
          var min = Math.min.apply(null, keys.map(function (k) { return d.values[k]; }));
          geoColors = {};
          keys.forEach(function (k) {
            var t = max > min ? (d.values[k] - min) / (max - min) : 1;
            t = 0.18 + 0.82 * Math.sqrt(t);
            geoColors[k] = lerpHex(sc[0], sc[1], t);
          });
          geoMap = new jsVectorMap({
            selector: '#geo-map', map: 'world', zoomButtons: false, zoomOnScroll: false, backgroundColor: 'transparent',
            regionStyle: { initial: { fill: dark ? '#282726' : '#dad8ce', stroke: dark ? '#100f0f' : '#b3b1a8', strokeWidth: 0.3 }, hover: { fillOpacity: 0.85 } },
            onRegionTooltipShow: function (event, tooltip, code) {
              var v = d.values[code];
              tooltip.text((COUNTRY[code] || tooltip.text()) + (v != null ? ' — ' + d.fmt(v) + d.unitSuffix : ' — no data'), true);
            }
          });
          geoApplyFills();
        }
        function phGeoSet(metric) {
          geoMetric = metric;
          document.querySelectorAll('.seg-btn').forEach(function (b) {
            var on = b.dataset.metric === metric;
            b.classList.toggle('is-on', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
          });
          var d = GEO[metric];
          geoRenderPanel(d);
          geoBuildMap(d);
        }
        phGeoSet('peers');
        new MutationObserver(function () { phGeoSet(geoMetric); })
          .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        JS;

    return view_admin_layout_html($settings, 'Geography', $body, 'geography', $csrf_token, 'Tracker', $actions, false, $extra_head, $inline_js, $extra_srcs);
}
