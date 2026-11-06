<?php

include './_archive/config.php';
include './_archive/main.php';

$files = new Files;

if (!archive_debug_enabled() && $files->serveFile()) {
	exit;
}

include './_archive/page.php';
