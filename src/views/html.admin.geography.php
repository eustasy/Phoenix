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
			<p class="dim geo-empty-note">Enable the privacy-preserving events ledger and geo enrichment to populate this map: turn on <code>stats_enabled</code> and <code>stats_geo</code>, run <code>composer require geoip2/geoip2</code>, and point <code>stats_geo_database</code> at a GeoLite2 country <code>.mmdb</code>.</p>
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
        $toggle .= '<button class="seg-btn'.($on ? ' is-on' : '').'" type="button" role="tab" aria-selected="'.($on ? 'true' : 'false').'" data-metric="'.htmlspecialchars($key, ENT_QUOTES, 'UTF-8').'"><span class="ph-ico" data-lucide="'.$m['icon'].'"></span>'.htmlspecialchars($m['short']).'</button>';
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
						<div class="dim geo-sub" id="geo-sub"></div>
					</div>
					<div class="geo-legend" id="geo-legend"></div>
				</div>
				<div id="geo-map" class="geo-map"></div>
				<p class="dim geo-foot">Country-level only &mdash; Phoenix never stores raw IP addresses. <span id="geo-note"></span></p>
			</div>
			<aside class="geo-side">
				<div class="ph-stat ph-stat-blue" id="geo-summary">
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

    // The map logic lives in assets/_geography.js; it's read in and emitted
    // inline (prefixed with the PHP-computed GEO metrics + default key) so those
    // values are in scope — hence the "_" name marking it an inlined file.
    $inline_js = 'var GEO = '.$geo_json.";\nvar GEO_DEFAULT = ".json_encode($default).";\n"
        .(string) file_get_contents(__DIR__.'/../../public/assets/_geography.js');

    return view_admin_layout_html($settings, 'Geography', $body, 'geography', $csrf_token, 'Tracker', $actions, false, $extra_head, $inline_js, $extra_srcs);
}
