<?php

declare(strict_types=1);

////	view_toplist_html
// A ranked mini-table rendered as a card: rank, label with a proportional bar,
// and a value. Three columns, deliberately — these sit several to a row on the
// dashboard, where a full table would not fit and would not be read anyway.
//
// Generalised from the Geography page's top-countries panel, which was the first
// instance of this shape; the dashboard's mini-tables are the rest.
//
// $rows is a list of ['label' => string, 'value' => string (pre-formatted),
// 'bar' => int 0-100, 'href' => string|null, 'title' => string|null]. A row with
// an href links into the filtered listing it summarises, so a card is never a
// dead end. An optional 'title' becomes the hover tooltip: a card has room for
// one line, and the identifying detail behind a label goes here rather than
// crowding it out. Labels, values and titles are escaped here; hrefs are
// expected pre-escaped by the caller, which is what builds them.
//
// $accent colours the bars. $more is an optional ['label', 'href'] footer link.
// An empty $rows renders the card with $empty in place of the list, so the
// dashboard keeps its grid alignment rather than dropping a card.

/**
 * @param list<array{label: string, value: string, bar?: int, href?: string|null, title?: string|null}> $rows
 * @param array{label: string, href: string}|null $more
 */
function view_toplist_html(
    string $title,
    array $rows,
    string $accent = 'var(--color-action)',
    ?array $more = null,
    string $empty = 'Nothing to show yet.',
): string {
    $items = '';
    foreach ($rows as $i => $row) {
        $label = htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars($row['value'], ENT_QUOTES, 'UTF-8');
        $bar = max(0, min(100, $row['bar'] ?? 0));
        $href = $row['href'] ?? null;

        // Named apart from the card's own $title, which this loop would
        // otherwise overwrite on its first row.
        $row_title = (string) ($row['title'] ?? '');
        $attr = $row_title === ''
            ? ''
            : ' title="'.htmlspecialchars($row_title, ENT_QUOTES, 'UTF-8').'"';

        $name = $href !== null && $href !== ''
            ? '<a class="nm" href="'.$href.'"'.$attr.'>'.$label.'</a>'
            : '<span class="nm"'.$attr.'>'.$label.'</span>';

        $items .= '<div class="geo-rowi" style="--geo-c:'.htmlspecialchars($accent, ENT_QUOTES, 'UTF-8').'">'.
            '<span class="geo-rank">'.($i + 1).'</span>'.
            '<span class="geo-co">'.$name.
            '<span class="bar"><i style="width:'.$bar.'%"></i></span></span>'.
            '<span class="geo-val">'.$value.'</span>'.
            '</div>';
    }

    if ($items === '') {
        $items = '<p class="dim text-sm">'.htmlspecialchars($empty, ENT_QUOTES, 'UTF-8').'</p>';
    }

    $foot = $more !== null
        ? '<div class="ph-toplist-more"><a href="'.$more['href'].'">'.
            htmlspecialchars($more['label'], ENT_QUOTES, 'UTF-8').'</a></div>'
        : '';

    return '<div class="geo-toplist"><h3>'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</h3>'.
        $items.$foot.'</div>';
}
