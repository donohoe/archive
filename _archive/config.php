<?php

// Toggle whether archive pages tell search engines not to index them
define('ARCHIVE_NOINDEX', true);

// Timezone used for "Modified" dates shown on file listings.
date_default_timezone_set('America/New_York');

// File extensions the archive will show and link to. Anything else on disk
// is invisible to the app, regardless of what's in a folder.
define('ARCHIVE_APPROVED_EXTENSIONS', [
	'txt', 'md', 'pdf',
	'jpg', 'jpeg', 'gif', 'png', 'svg',
	'css', 'js', 'html',
	'psd',
	'mp4', 'mov',
	'zip'
]);

// Width (in pixels) generated thumbnails are resized to. Height is scaled
// to match the source image's aspect ratio.
define('ARCHIVE_THUMBNAIL_WIDTH', 360);

// Content-Type sent when a file is served directly (see Files::serveFile()).
// Keep in sync with ARCHIVE_APPROVED_EXTENSIONS above.
define('ARCHIVE_MIME_TYPES', [
	'txt'  => 'text/plain',
	'md'   => 'text/markdown',
	'pdf'  => 'application/pdf',
	'jpg'  => 'image/jpeg',
	'jpeg' => 'image/jpeg',
	'gif'  => 'image/gif',
	'png'  => 'image/png',
	'svg'  => 'image/svg+xml',
	'css'  => 'text/css',
	'js'   => 'application/javascript',
	'html' => 'text/html',
	'psd'  => 'image/vnd.adobe.photoshop',
	'mp4'  => 'video/mp4',
	'mov'  => 'video/quicktime',
	'zip'  => 'application/zip',
]);
