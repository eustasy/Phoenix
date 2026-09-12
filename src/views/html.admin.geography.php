<?php

declare(strict_types=1);

////	view_admin_geography_html
// Render the admin Geography page: a choropleth world map (jsVectorMap) of ONE
// per-country metric, with links to the others, a stepped legend, and a
// top-countries panel.
//
// $metric names the metric being shown and $values is its country-code => number
// map; $available lists every metric the controller can offer, which is what the
// segmented control is built from. Only the selected metric is computed, because
// each reads the whole events ledger — a client-side toggle would mean paying
// for maps nobody asked to see.
//
// An empty $available is the "geo isn't configured" state. Marks the Geography
// nav active. Wrapped in the shared admin layout. Returns HTML string.
//
/**
 * @param PhoenixSettings $settings
 * @param array<string, int> $values
 * @param list<string> $available
 */
function view_admin_geography_html(array $settings, string $metric, array $values, array $available, string $csrf_token): string
{
    require_once __DIR__.'/html.admin.layout.php';
    require_once __DIR__.'/../functions/cdn.assets.php';

    ////	Not configured / no data
    if ($available === [] || $metric === '') {
        $body = '<div class="ph-empty">
			<span class="ph-ico" data-lucide="globe-2"></span>
			<p>Geographic data isn\'t available yet.</p>
			<p class="dim geo-empty-note">Enable the privacy-preserving events ledger and geo enrichment to populate this map: turn on <code>stats_enabled</code> and <code>stats_geo</code>, run <code>composer require maxmind-db/reader</code>, and point <code>stats_geo_database</code> at a GeoLite2 country <code>.mmdb</code>.</p>
		</div>';

        return view_admin_layout_html($settings, 'Geography', $body, 'geography', $csrf_token, 'Tracker', '', 'narrow');
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
            'icon' => 'share-2',
            'accent' => '#205ea6',
            'bg' => 'var(--color-info-bg)',
            'scaleL' => ['#c6dde8', '#205ea6'],
            // Same direction of travel as the light scale — pale at the low end,
            // saturated at the high end. Running dark the other way made a
            // high-value country the palest on the map, which reads inverted.
            'scaleD' => ['#abcfe2', '#4385be'],
        ],
        'bandwidth' => [
            'short' => 'Bandwidth',
            'label' => 'Bandwidth by country',
            'listTitle' => 'Top countries — bandwidth',
            'unit' => ' bytes',
            // Values are byte counts, so the panel and tooltip render them as
            // sizes rather than as 22098152264304.
            'format' => 'bytes',
            'scope' => 'all-time estimate',
            'icon' => 'arrow-up-down',
            'accent' => '#bc5215',
            'bg' => 'var(--color-warning-bg)',
            'scaleL' => ['#f1d3b3', '#bc5215'],
            'scaleD' => ['#e8b79b', '#c25d1e'],
        ],
        'downloads' => [
            'short' => 'Completed downloads',
            'label' => 'Completed downloads by country',
            'listTitle' => 'Top countries — downloads',
            'unit' => ' downloads',
            'scope' => 'all-time',
            'icon' => 'circle-check-big',
            'accent' => '#66800b',
            'bg' => 'var(--color-success-bg)',
            'scaleL' => ['#dde2b2', '#66800b'],
            'scaleD' => ['#bec97e', '#879a39'],
        ],
    ];

    if (! isset($presentation[$metric])) {
        $metric = (string) ($available[0] ?? '');
    }

    // Only the selected metric carries data; the script renders exactly this one.
    $geo = [$metric => $presentation[$metric] + ['values' => $values]];
    $default = $metric;

    // Metric toggle (top bar). Links, not buttons: each metric is a separate
    // request, so each is its own URL — shareable, and the back button works.
    $toggle = '';
    foreach ($available as $key) {
        if (! isset($presentation[$key])) {
            continue;
        }
        $on = $key === $metric;
        $toggle .= '<a class="seg-btn'.($on ? ' is-on' : '').'" role="tab" aria-selected="'.($on ? 'true' : 'false').
            '" href="?page=geography&amp;metric='.htmlspecialchars($key, ENT_QUOTES, 'UTF-8').'">'.
            '<span class="ph-ico" data-lucide="'.$presentation[$key]['icon'].'"></span>'.
            htmlspecialchars($presentation[$key]['short']).'</a>';
    }
    $actions = '<div class="seg" role="tablist" aria-label="Map metric">'.$toggle.'</div>';

    // Load the jsVectorMap stylesheet before phoenix.css so Phoenix's .jvm-*
    // overrides win by source order (no !important needed). Pinned and
    // integrity-checked like the scripts, since a stylesheet can move content
    // around the page just as readily.
    $map_css = cdn_assets()['jsvectormap_css'];
    $head_pre = '
	<link rel="stylesheet" href="'.htmlspecialchars($map_css['url'], ENT_QUOTES, 'UTF-8').'"'.
        ' integrity="'.htmlspecialchars($map_css['integrity'], ENT_QUOTES, 'UTF-8').'"'.
        ' crossorigin="anonymous">';
    $extra_srcs = [
        cdn_assets()['jsvectormap']['url'],
        cdn_assets()['jsvectormap_world']['url'],
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

    return view_admin_layout_html($settings, 'Geography', $body, 'geography', $csrf_token, 'Tracker', $actions, 'wide', '', $inline_js, $extra_srcs, $head_pre);
}
