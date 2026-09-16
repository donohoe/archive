<?php

include './_archive/config.php';
include './_archive/main.php';

$files = new Files;

if (!isset($_GET['debug']) && $files->serveFile()) {
	exit;
}

include './_archive/page.php';
