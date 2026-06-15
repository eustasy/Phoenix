<?php

declare(strict_types=1);

////	view_stats_html
// Render tracker statistics for humans: a hero active-peer count with a
// seeder/leecher split bar, then cards for torrents-with-peers, completed
// downloads, and traffic served (human-readable, with the exact byte count
// beneath). The figures are the same aggregation the operator dashboard shows.
// Wrapped in the public page chrome (Stats nav active).
// Returns HTML string. Caller is responsible for setting Content-Type header.

/**
 * @param array<string, int> $stats
 * @param PhoenixSettings $settings
 */
function view_stats_html(array $stats, array $settings): string
{
    require_once __DIR__.'/html.public.layout.php';
    require_once __DIR__.'/../functions/format.bytes.php';

    $swarm = $stats['seeders'] + $stats['leechers'];
    $seed_pct = $swarm > 0 ? round($stats['seeders'] / $swarm * 100, 1) : 0;
    $leech_pct = $swarm > 0 ? 100 - $seed_pct : 0;

    $extra_head = '
	<style>
		.stats-hero { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-2xl); box-shadow: var(--shadow-sm); padding: var(--space-8); display: grid; grid-template-columns: auto 1fr; gap: var(--space-8); align-items: center; }
		.stats-hero-num { font-size: 4rem; line-height: 1; font-weight: 700; letter-spacing: var(--letter-spacing-tight); font-variant-numeric: tabular-nums; }
		.stats-hero-label { color: var(--color-text-secondary); margin-top: var(--space-2); }
		.breakdown { display: flex; gap: var(--space-6); }
		.breakdown .b-num { font-size: var(--font-size-2xl); font-weight: 700; font-variant-numeric: tabular-nums; }
		.breakdown .b-num.seed { color: var(--color-green); }
		.breakdown .b-num.leech { color: var(--color-orange); }
		.breakdown .b-label { font-size: var(--font-size-sm); color: var(--color-text-secondary); }
		.swarm-bar { height: 10px; border-radius: var(--radius-full); overflow: hidden; display: flex; background: var(--color-bg-tertiary); margin-top: var(--space-4); }
		.swarm-bar .seg-seed { background: var(--color-green); }
		.swarm-bar .seg-leech { background: var(--color-orange); }
		@media (max-width: 640px){ .stats-hero { grid-template-columns: 1fr; gap: var(--space-5); } }
	</style>';

    $body = '<div class="ph-page-title">
		<div>
			<h1>Tracker Stats</h1>
			<p>A live snapshot of everything this tracker is coordinating right now.</p>
		</div>
	</div>

	<div class="stats-hero">
		<div>
			<div class="stats-hero-num">'.number_format($stats['peers']).'</div>
			<div class="stats-hero-label">active peers</div>
		</div>
		<div>
			<div class="breakdown">
				<div class="b"><div class="b-num seed">'.number_format($stats['seeders']).'</div><div class="b-label">Seeders</div></div>
				<div class="b"><div class="b-num leech">'.number_format($stats['leechers']).'</div><div class="b-label">Leechers</div></div>
			</div>
			<div class="swarm-bar" role="img" aria-label="'.number_format($stats['seeders']).' seeders, '.number_format($stats['leechers']).' leechers">
				<div class="seg-seed" style="width:'.$seed_pct.'%"></div>
				<div class="seg-leech" style="width:'.$leech_pct.'%"></div>
			</div>
		</div>
	</div>

	<div class="ph-stat-grid" style="margin-top:var(--space-4)">
		<div class="ph-stat" style="--stat-fg:var(--color-purple)">
			<div class="ph-stat-top"><div class="ph-stat-value">'.number_format($stats['torrents']).'</div><div class="ph-stat-ico tint-purple"><span class="ph-ico" data-lucide="database"></span></div></div>
			<div class="ph-stat-label">Torrents with peers</div>
		</div>
		<div class="ph-stat" style="--stat-bg:var(--color-success-bg);--stat-fg:var(--color-green)">
			<div class="ph-stat-top"><div class="ph-stat-value">'.number_format($stats['downloads']).'</div><div class="ph-stat-ico"><span class="ph-ico" data-lucide="circle-check-big"></span></div></div>
			<div class="ph-stat-label">Completed downloads</div>
		</div>
		<div class="ph-stat" style="--stat-bg:var(--color-warning-bg);--stat-fg:var(--color-orange)">
			<div class="ph-stat-top"><div class="ph-stat-value">'.format_bytes($stats['traffic']).'</div><div class="ph-stat-ico"><span class="ph-ico" data-lucide="arrow-up-down"></span></div></div>
			<div class="ph-stat-label">Traffic served</div>
			<div class="ph-stat-sub mono">'.number_format($stats['traffic']).' bytes</div>
		</div>
	</div>

	<p class="dim" style="font-size:var(--font-size-sm);margin-top:var(--space-5)">Served by <code>scrape.php?stats</code>. Figures match the operator dashboard &mdash; one aggregation, shown twice.</p>';

    return view_public_layout_html('Tracker Stats — Phoenix', $body, 'stats', $settings['phoenix_version'], true, $extra_head);
}
