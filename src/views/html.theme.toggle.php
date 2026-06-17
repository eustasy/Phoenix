<?php

declare(strict_types=1);

////	view_theme_toggle_html
// The light/dark theme toggle button. phToggleTheme() (assets/app.js) flips the
// root class and persists the choice. The sun/moon glyphs and the two labels
// are swapped purely in CSS based on the active theme, so this markup is the
// same in both. $extra_class lets callers add positioning hooks (e.g.
// ph-theme-fixed on auth pages, or a margin via a CSS class).

function view_theme_toggle_html(string $light = 'Light', string $dark = 'Dark', string $extra_class = ''): string
{
    $class = 'ph-theme-toggle'.($extra_class !== '' ? ' '.$extra_class : '');

    return '<button class="'.$class.'" type="button" onclick="phToggleTheme()">
		<span class="ph-moon ph-ico" data-lucide="moon"></span>
		<span class="ph-sun ph-ico" data-lucide="sun"></span>
		<span class="ph-theme-light-label">'.htmlspecialchars($light, ENT_QUOTES, 'UTF-8').'</span>
		<span class="ph-theme-dark-label">'.htmlspecialchars($dark, ENT_QUOTES, 'UTF-8').'</span>
	</button>';
}
