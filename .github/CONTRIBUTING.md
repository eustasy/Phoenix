# Contributing

## Setting Up

See the [Installation Guide](https://github.com/eustasy/phoenix#install-guide) for requirements and setup.

## Code Style
### PHP

 - Tabular indentation, spaces for spacing.
 - Four stroke headers to large sections.
 - Remove trailing whitespaces.
 - IE 11+ Compatibility.
 - `require_once` any other file
 - Don't close PHP tags on PHP only files

```php
<?php

////	Large Header
// Description of what this secion
// does and does not do.
// Especially any caveats.

$hello = array(
	'key'       => 1,
	'other_key' => 2,
	'final_key' => 3
);

require_once $settings['functions'].'function.super_function.php';
$result = super_function($hello, $world);

if ( $result ) {
	...
} else if (
	!$result &&
	$earlier_result
) {
	...
} else {
	...
}
```

### HTML
 - Include `alt` attribute for all images
 - Include `title` attribute for all links
 - Close all your tags properly

### CSS
 - Try to use classes instead of IDs unless things are absolutely unique
 - One selector per line
 - Care with fallbacks and browsers compatibilities
```css
.class {
    color: #fefe89;
    font-size: 1.1em;
}

.second-class,
.third-class {
    backgound-color: white;
}
```

## Git

Please use descriptive commit descriptions when possible.

### Make a new branch and push it to GitHub.
```bash
git checkout -b fix-issue-number
git push -u origin fix-issue-number
```

### Merge from master
```bash
git checkout fix-issue-number
git merge master
```
