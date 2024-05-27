<?php

$files = new Files;
$response = $files->get();

$html_nav = array();
$html_dir = array();
$html_files = array();
$html_file_info = array();

?>
<!doctype html>
<html lang="en-US" >
<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
	<meta http-equiv="content-type" content="text/html;charset=utf-8" />
	<meta name="viewport" content="width=device-width,user-scalable=no,initial-scale=1" />
	<title>Archive</title>
	<link rel='stylesheet' href='_archive/styles.css?ver=1.0.1' media='all' />
</head>
<body>
<div id="page">
	<header>
	<?php
		if (isset($response['navigation'])) {
			foreach($response['navigation'] as $item) {
				if (empty($item['0'])) {
					$item['1'] = 'archive';
					$html_nav[] = implode("", array(
						$_SERVER['SERVER_NAME'] . " / <a href=\"./\">{$item['1']}</a>"
					));
				} else {
					$html_nav[] = implode("", array(
						"<a href=\"?p={$item['0']}\">{$item['1']}</a>"
					));
				}
			}
		} else {
			$html_nav[] = 'nothing';
		}
	?>
		<div id="navigation">
			<h1>Archive</h1>
			<span>Index of</span>&nbsp;<?php print implode(" / ", $html_nav); ?>
		</div>
	</header>

	<div id="content">
		<?php

		if (isset($response['dir'])) {

			foreach($response['dir']['dirs'] as $dir) {
				$html_dir[] = implode("", array(
					"<li>",
						"<a href=\"?p={$dir['0']}\" class=\"focusable\">",
							"<span class=\"thumbnail\"></span>",
							"<span class=\"label line\">{$dir['1']}</span>",
						"</a>",
					"</li>",
				));
			}

			foreach($response['dir']['files'] as $file) {
				$thumbnail = (!empty($file['3'])) ? $file['3'] : false;

				if ($thumbnail) {
					$thumbnail_html = "<img src=\"{$thumbnail}\" style=\"max-height:240px;\">";
				} else {
					$thumbnail_html = "<svg aria-hidden=\"true\"><use xlink:href=\"#icon-file\"></use></svg><span class=\"icon-label {$file['2']}\">{$file['2']}</span>";
				}

				$html_files[] = implode("", array(
					"<li>",
						"<a href=\"?p={$file['0']}\" class=\"focusable\">",
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
							"<span class=\"filesize\">{$f["size_kb"]} KB.</span> ",
							"<span>Modified: {$f["modified"]}</span> ",
							"<a href=\"{$f["link"]}\" target=\"_blank\" class=\"icon-link\" >",
								"<img src=\"./_archive/icon-link.svg\" alt=\"Open in New Tab\"/>",
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
		<p><a href="https://github.com/donohoe/archive">Github</a></p>
	</footer>
	<div style="display: none;">
		<?php include './_archive/icon-file-symbol.svg'; ?>
	</div>
</div>
</body>
</html>

