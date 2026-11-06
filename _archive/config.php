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

// Open files from a folder listing in an in-page viewer (image/text/video
// preview, file info, prev/next). When false, links open the raw file.
define('ARCHIVE_VIEWER', true);

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

// Thumbnail guardrails. GD decodes the whole source image into memory
// (~4-5 bytes per pixel), so very large images can exhaust memory_limit.
// Images above this pixel count are skipped (they show the generic icon).
define('ARCHIVE_THUMBNAIL_MAX_PIXELS', 24000000); // ~6000x4000

// Max thumbnails generated in a single request, so a big new folder can't
// time out the page. Any remaining thumbnails are generated on later loads.
define('ARCHIVE_THUMBNAIL_MAX_PER_REQUEST', 20);

// Sandbox applied to archived HTML/SVG served from this origin: scripts can
// still run, but in an opaque origin with no access to this site's cookies
// or storage. Keep in sync with the matching header in the root .htaccess.
define('ARCHIVE_SANDBOX_CSP', 'sandbox allow-scripts allow-forms allow-popups allow-modals');

// ?debug output is only available where the web server sets the environment
// variable ARCHIVE_DEBUG=1 (e.g. `SetEnv ARCHIVE_DEBUG 1` in the local
// Apache vhost). This file stays identical across environments; production
// simply never sets the variable. Request data (IP, Host header) isn't used
// because clients and proxies control it.
function archive_debug_enabled() {
	if (!isset($_GET['debug'])) {
		return false;
	}
	// REDIRECT_ prefix: Apache renames env vars after an internal rewrite
	$flag = $_SERVER['ARCHIVE_DEBUG'] ?? $_SERVER['REDIRECT_ARCHIVE_DEBUG'] ?? getenv('ARCHIVE_DEBUG');
	return $flag === '1';
}
