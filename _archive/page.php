<?php

$response = $files->get();

$html_dir   = array();
$html_files = array();

// Escape for use in HTML text and attribute values
function archive_esc($value) {
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
// Mirrors the <meta> CSP below; frame-ancestors only works as a header
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self'; media-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");

if (archive_debug_enabled()) {
	header('Content-Type: text/html; charset=utf-8');
	print "<pre>";
	print archive_esc(print_r($response, true));
	print "</pre>";
	exit;
}

?>
<!doctype html>
<html lang="en-US" >
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
	<meta http-equiv="content-type" content="text/html;charset=utf-8" />
	<meta name="viewport" content="width=device-width,initial-scale=1" />
	<meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self'; media-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self';" />
	<?php if (defined('ARCHIVE_NOINDEX') && ARCHIVE_NOINDEX): ?>
	<meta name="robots" content="noindex, nofollow" />
	<?php endif; ?>
	<title>Archive</title>
	<link rel='stylesheet' href='/<?= archive_esc($response['base_dir']) ?>_archive/styles.css?ver=1.2.3' media='all' />
	<?php if (defined('ARCHIVE_VIEWER') && ARCHIVE_VIEWER && isset($response['dir'])): ?>
	<script src='/<?= archive_esc($response['base_dir']) ?>_archive/viewer.js?ver=1.2.3' defer></script>
	<?php endif; ?>
</head>
<body>
<div id="page">
	<header>
	<?php
		$html_nav = array();

		if (isset($response['navigation'])) {
			foreach($response['navigation'] as $item) {
				$html_nav[] = implode("", array(
					"<a href=\"/", htmlspecialchars($item['0']), "\">", htmlspecialchars($item['1']), "</a>"
				));
			}
		} else {
			$html_nav[] = 'nothing';
		}
	?>
		<div id="navigation">
			<h1><a href="/archive/">Archive</a></h1>
			<span>Index of</span>&nbsp;<a href="/" class="basic"><?php echo htmlspecialchars($_SERVER['SERVER_NAME']); ?></a> / <?php print implode(" /&nbsp;", $html_nav); ?>
		</div>
	</header>

	<div id="content">
		<?php

		if (isset($response['dir'])) {

			foreach($response['dir']['dirs'] as $dir) {
				$custom_class = trim(str_replace(" ", "-", preg_replace("/[^a-zA-Z0-9 ]/", "", $dir['1'])));
				$dir_href = archive_esc($dir['0']);
				$html_dir[] = implode("", array(
					"<li>",
						"<a href=\"/archive/{$dir_href}\" class=\"focusable\">",
							"<span class=\"thumbnail dir-$custom_class\"></span>",
							"<span class=\"label line\">{$dir['1']}</span>",
						"</a>",
					"</li>",
				));
			}

			foreach($response['dir']['files'] as $file) {
				$thumbnail = (!empty($file['3'])) ? archive_esc($file['3']) : false;
				$file_ext  = archive_esc($file['2']);
				$file_href = archive_esc($file['0']);
				if ($thumbnail) {
					$thumbnail_html = "<img src=\"{$thumbnail}\" style=\"max-height:240px;\">";
				} else {
					$thumbnail_html = "<svg aria-hidden=\"true\"><use xlink:href=\"#icon-file\"></use></svg><span class=\"icon-label {$file_ext}\">{$file_ext}</span>";
				}

				$file_data = implode(" ", array(
					'data-name="'     . archive_esc($file['6']) . '"',
					'data-ext="'      . $file_ext . '"',
					'data-size="'     . (int) $file['4'] . '"',
					'data-modified="' . archive_esc($file['5']) . '"',
				));

				$html_files[] = implode("", array(
					"<li>",
						"<a href=\"{$file_href}\" class=\"focusable\" {$file_data}>",
							"<div class=\"thumbnail\" style=\"max-height:240px\">",
								"<div class=\"inner\">",
									$thumbnail_html,
								"</div>",
							"</div>",
							"<span class=\"label line\">{$file['1']}</span>",
						"</a>",
					"</li>",
				));
			}

			if (!empty($html_dir)) {
				$tmp = implode("", $html_dir);
				print "<ul id=\"dirs\">{$tmp}</ul>";
			}

			if (!empty($html_files)) {
				$tmp = implode("", $html_files);
				print "<ul id=\"files\">{$tmp}</ul>";
			}

		} else if (isset($response['file'])) {
			$file_info = $files->renderFile( $response['file'] );
			if (!empty($file_info)) {
				$f = $response['file'];
				print implode("", array(
					"<div id=\"info\">",
						"<p>",
							"<span class=\"filesize\">", archive_esc($f["size_kb"]), " KB.</span> ",
							"<span>Modified: ", archive_esc($f["modified"]), "</span> ",
							"<a href=\"", archive_esc($f["link"]), "\" target=\"_blank\" rel=\"noopener\" class=\"icon-link\" >",
								"<img src=\"./_archive/icons/icon-link.svg\" alt=\"Open in New Tab\"/>",
							"</a>",
						"</p>",
					"</div>",
					"<div id=\"preview\">",
						$f['preview'],
					"</div>",
				));
			}
		} else {
			print "<p>Nope. No biscuit for you. Go <a href=\"./\">home</a>.</p>";
		}
?>
	</div>
	<footer>
		<p><a href="https://github.com/donohoe/archive" title="Archive">Source on Github</a></p>
	</footer>
	<div style="display: none;">
		<?php include './_archive/icons/icon-files.svg'; ?>
	</div>
</div>
</body>
</html>