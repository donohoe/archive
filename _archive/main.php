<?php

class Files {

	private string $base_name;
	private string $base_dir;
	private string $base_path;
	private string $current_path;
	private string $current_dir;
	private array  $approved_extensions;
	private array  $thumbnail_supported_extensions;
	private string $thumbnail_dir;
	private int    $thumbnail_width;

	public function __construct() {

		$parent_dir         = dirname(__DIR__);
		$this->base_name    = basename($parent_dir);
		$this->base_dir     = $this->base_name . DIRECTORY_SEPARATOR;
		$this->base_path    = realpath($parent_dir) . DIRECTORY_SEPARATOR;

		$this->current_path = $this->getPath();// . DIRECTORY_SEPARATOR;
		if (empty($this->current_path) || $this->current_path == '/') {
			$this->current_dir  = $this->base_dir;
			$this->current_path = $this->base_path;
		} else {
			if (is_file($this->current_path)) {
				$this->current_dir = dirname($this->current_path);
			} else {
				$this->current_dir = $this->base_dir . str_replace($this->base_path, '', $this->current_path);	
			}
		}
		
		$this->approved_extensions = ARCHIVE_APPROVED_EXTENSIONS;

		$this->thumbnail_supported_extensions = ['png', 'jpg', 'jpeg', 'gif'];
		$this->thumbnail_dir = '_archive/cache';
		$this->thumbnail_width = ARCHIVE_THUMBNAIL_WIDTH;
	}

	private function getPath(){
		$path = isset($_GET['p']) ? $_GET['p'] : './';

		if (!is_string($path)) {
			return false;
		}

		$resolved_path = $this->resolveActualPath($path);

		if ($resolved_path === false) {
			return false;
		}

		$real_path = realpath($resolved_path);

		if ($real_path && $this->is_within_base($real_path, $this->base_path)) {
			return $real_path;
		} else {
			return false;
		}
	}

	// Folder names are shown/linked in lowercase (see lowercaseDirSegments()),
	// but the filesystem itself may use mixed case (e.g. "Ad-Prototypes")

	private function resolveActualPath($path) {
		$path = trim(str_replace('\\', '/', $path), '/');
		$current = rtrim($this->base_path, DIRECTORY_SEPARATOR);

		if ($path === '' || $path === '.') {
			return $current;
		}

		foreach (explode('/', $path) as $segment) {
			if ($segment === '' || $segment === '.') {
				continue;
			}
			if ($segment === '..' || strpos($segment, '_') === 0 || strpos($segment, '.') === 0) {
				return false;
			}

			$candidate = $current . DIRECTORY_SEPARATOR . $segment;

			if (!file_exists($candidate)) {
				$match = false;
				foreach ((array) @scandir($current) as $entry) {
					if ($entry !== '.' && $entry !== '..' && strcasecmp($entry, $segment) === 0) {
						$match = $entry;
						break;
					}
				}
				if ($match === false) {
					return false;
				}
				$candidate = $current . DIRECTORY_SEPARATOR . $match;
			}

			$current = $candidate;
		}

		return $current;
	}

	private function is_within_base($path, $base) {
		return strpos($path, $base) === 0;
	}

	// Lowercases folder segments of a relative path for display/links, while
	// leaving a trailing filename segment untouched. $has_filename should be
	// true whenever the last segment names a file rather than a directory.

	private function lowercaseDirSegments($relative_path, $has_filename = false) {
		$trailing_slash = (substr($relative_path, -1) === '/');
		$segments = explode('/', rtrim($relative_path, '/'));

		$file_segment = ($has_filename && !empty($segments)) ? array_pop($segments) : null;
		$segments = array_map('strtolower', $segments);
		if ($file_segment !== null) {
			$segments[] = $file_segment;
		}

		return implode('/', $segments) . ($trailing_slash ? '/' : '');
	}

	private function getRelativePath($path) {
		return str_replace(dirname($this->base_path), '', $path);
	}

	private function getNavigation(){
		$relative_path = $this->getRelativePath($this->current_path);
		$breadcrumbs = explode(DIRECTORY_SEPARATOR, trim($relative_path, DIRECTORY_SEPARATOR));

		$navigaton = array();
		$is_current_file = is_file($this->current_path);
		$crumb_count = count(array_filter($breadcrumbs, fn($crumb) => $crumb !== ''));

		$path = '';
		$index = 0;
		foreach ($breadcrumbs as $crumb) {
			if ($crumb === '') continue;
			$index++;
			$path .= DIRECTORY_SEPARATOR . $crumb;
			$dir = ltrim($path, DIRECTORY_SEPARATOR);
			$dir = rtrim($dir, '/') . '/';
			$is_last = ($index === $crumb_count);
			$dir = $this->lowercaseDirSegments($dir, $is_current_file && $is_last);
			$navigaton[] = [ $dir, $crumb ];
		}

		return $navigaton;
	}

	private function viewFile(){
		$file_extension = strtolower(pathinfo($this->current_path, PATHINFO_EXTENSION));
		$response = array(
			'size'     => 0,
			'size_kb'  => 0,
			'filename' => '',
			'modified' => '',
			'ext'      => '',
			'path'     => '',
			'link'     => '',
			'preview'  => ''
		);

		if (in_array($file_extension, $this->approved_extensions)) {
			$response['size']     = filesize($this->current_path);
			$response['size_kb']  = number_format($response['size'] / 1024, 0);
			$response['filename'] = basename($this->current_path);
			$response['modified'] = date('M j, Y H:i', filemtime($this->current_path));
			$response['ext']      = $file_extension;
			$response['path']     = $this->lowercaseDirSegments($this->getRelativePath($this->current_path), true);
			$response['link']     = $response['path'];
			$response['preview']  = $this->getFilePreview($response);
		} else {
			$response['error'] = 'The file extension is not approved';
		}
		return $response;
	}

	private function getFilePreview($attrs) {
		$html = '';
		switch ($attrs['ext']) {
			case 'jpeg':
			case 'jpg':
			case 'png':
			case 'gif':
			case 'svg':
				$html = "<img src=\"{$attrs['path']}\"/>";
				break;
			case 'txt':
			case 'md':
			case 'js':
			case 'css':
				$html = "<iframe src=\"{$attrs['path']}\"></iframe>";
				break;
			case 'mp4':
				$html = "<video controls><source src=\"{$attrs['path']}\" type=\"video/mp4\" /></video>";
				break;
			default:
				// throw new Exception('Unsupported image type');
				$html = "<pre>Preview not available. Click <a href=\"{$attrs['path']}\" target=\"_blank\">here</a> to open</pre>";
		}
		return $html;
	}

	private function viewDir(){

		$contents = scandir($this->current_path);
		$directories = [];
		$files = [];

		$response = array(
			'dirs'  => array(),
			'files' => array()
		);

		foreach ($contents as $item) {
			if ($item === '.' || $item === '..' || strpos($item, '_') === 0 || strpos($item, '.') === 0) {
				continue;
			}
			$full_path = $this->current_path . DIRECTORY_SEPARATOR . $item;
			if (is_dir($full_path)) {
				$directories[] = $item;
			} elseif (is_file($full_path)) {
				$files[] = $item;
			}
		}

		sort($directories, SORT_NATURAL | SORT_FLAG_CASE);
		sort($files, SORT_NATURAL | SORT_FLAG_CASE);

		foreach ($directories as $directory) {
			$relative_dir_path = str_replace($this->base_path, '', $this->current_path . DIRECTORY_SEPARATOR . $directory);
			$relative_dir_path = ltrim($relative_dir_path, '/');
			$relative_dir_path = rtrim($relative_dir_path, '/') . '/';
			$relative_dir_path = $this->lowercaseDirSegments($relative_dir_path, false);
			$response['dirs'][] = [ $relative_dir_path, htmlspecialchars($directory) ];
		}

		foreach ($files as $file) {
			$file_extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
			if (in_array($file_extension, $this->approved_extensions)) {

				$thumbnail_path = '';
				$file_full_path = $this->current_path . DIRECTORY_SEPARATOR . $file;

				if (in_array($file_extension, $this->thumbnail_supported_extensions)) {
					$thumbnail_path = $this->getThumbnailPath($file);
					if (!file_exists($thumbnail_path) || filemtime($file_full_path) > filemtime($thumbnail_path)) {
						try {
							$this->createThumbnail($file, $thumbnail_path);
						} catch (\Throwable $e) {
							$thumbnail_path = '';
						}
					}
				}

				$relative_file_path = str_replace( dirname($this->base_path), '', $this->current_path . DIRECTORY_SEPARATOR . $file );
				$relative_file_path = $this->lowercaseDirSegments($relative_file_path, true);

				$response['files'][] = [
					$relative_file_path, 
					htmlspecialchars($file), 
					$file_extension, 
					($thumbnail_path) ? '/' . $this->base_dir . $thumbnail_path : ''
				];
			}
		}

		return $response;
	}

	public function get() {

		$response = array(
			'p'            => isset($_GET['p']) ? $_GET['p'] : '',
			'base_name'    => $this->base_name,
			'base_dir'     => $this->base_dir,
			'base_path'    => $this->base_path,
			'current_dir'  => $this->current_dir,
			'current_path' => $this->current_path,
			'debug'        => array()
		);

		if (is_file($response['p'])) {
			$response['debug'][] = 'file';
		}

		if ($this->current_path !== false) {

			$response['navigation'] = $this->getNavigation();

			if (is_file($this->current_path)) {
				$response['file'] = $this->viewFile();
			} elseif (is_dir($this->current_path)) {
				$response['dir'] = $this->viewDir();
			} else {
				$response['error'] = 'The path is invalid or does not exist';
			}

		} else {
			$response['error'] = 'No path parameter provided or invalid path';
		}

		// if ($this->current_path !== false) {

		// 	$response['navigation'] = $this->getNavigation();

		// 	if (is_file($this->current_path)) {
		// 		$response['file'] = $this->viewFile();
		// 	} elseif (is_dir($this->current_path)) {
		// 		$response['dir'] = $this->viewDir();
		// 	} else {
		// 		$response['error'] = 'The path is invalid or does not exist';
		// 	}

		// } else {
		// 	$response['error'] = 'No path parameter provided or invalid path';
		// }

		return $response;
	}

	public function renderFile($file){
		return json_encode($file, JSON_PRETTY_PRINT);
	}

	/* Images */

	private function createThumbnail($file_path, $thumb_path) {

		$file_path = $this->current_path . DIRECTORY_SEPARATOR . $file_path;

		// print_r($file_path); exit;

		$info = getimagesize($file_path);
		if ($info === false) {
			throw new Exception('Unable to read image');
		}
		$mime = $info['mime'];
	
		switch ($mime) {
			case 'image/jpeg':
				$image = @imagecreatefromjpeg($file_path);
				break;
			case 'image/png':
				$image = @imagecreatefrompng($file_path);
				break;
			case 'image/gif':
				$image = @imagecreatefromgif($file_path);
				break;
			default:
				throw new Exception('Unsupported image type');
		}

		if (!$image) {
			throw new Exception('Unable to read image');
		}

		$width = imagesx($image);
		$height = imagesy($image);
	
		$thumb_height = floor($height * ($this->thumbnail_width / $width));
		$thumbnail = imagecreatetruecolor($this->thumbnail_width, $thumb_height);
	
		if ($mime === 'image/png') {
		    // Enable alpha blending and save alpha settings to preserve transparency
			imagealphablending($thumbnail, false);
			imagesavealpha($thumbnail, true);
			$transparent = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
			imagefill($thumbnail, 0, 0, $transparent);
		}
		imagecopyresized($thumbnail, $image, 0, 0, 0, 0, $this->thumbnail_width, $thumb_height, $width, $height);
	
		switch ($mime) {
			case 'image/jpeg':
				imagejpeg($thumbnail, $thumb_path);
				break;
			case 'image/png':
				imagepng($thumbnail, $thumb_path);
				break;
			case 'image/gif':
				imagegif($thumbnail, $thumb_path);
				break;
		}
	
		imagedestroy($image);
		imagedestroy($thumbnail);
	}

	private function getThumbnailPath($file_path) {
		$file_path = $this->current_path . DIRECTORY_SEPARATOR . $file_path;
		$file_info = pathinfo($file_path);
		$file_name = $file_info['filename'];
		$file_extension = $file_info['extension'];
		return $this->thumbnail_dir . DIRECTORY_SEPARATOR . hash('crc32', $this->current_path) . '_' . $file_name . '.' . $file_extension;
	}
}
