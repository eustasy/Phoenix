<?php

declare(strict_types=1);

////	view_head_html
// Shared document head for every Phoenix HTML surface: doctype, <html> with a
// default light theme, meta, the flame favicon, the Inter web font, the bundled
// design-system + Phoenix stylesheets, and the inline theme-init snippet that
// applies the persisted theme before first paint (avoiding a flash). Returns
// everything up to and including </head>; the caller opens <body> and its own
// page wrapper. $extra_head is trusted HTML (per-page <style>/<link>), injected
// verbatim. $title is plain text and is escaped here.

function view_head_html(string $title, string $extra_head = ''): string
{
    return '<!DOCTYPE html>
<html lang="en" class="theme-light">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</title>
	<link rel="icon" href="/assets/phoenix-mark.svg">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="/assets/ds.css">
	<link rel="stylesheet" href="/assets/phoenix.css">'.$extra_head.'
	<script>(function(){var t="light";try{t=localStorage.getItem("phoenix-theme")||"light";}catch(e){}document.documentElement.className="theme-"+(t==="dark"?"dark":"light");})();</script>
</head>';
}
