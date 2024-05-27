<?php

date_default_timezone_set('America/New_York');

class Files {

	public function __construct() {
		$this->path = $this->getPath();
		$this->real_base = realpath(__DIR__);
		$this->approved_extensions = [
			'txt', 'md', 'pdf',
			'jpg', 'jpeg', 'gif', 'png', 'svg',
			'css', 'js', 'html',
			'psd',
			'mp4', 'mov',
			'zip'
		];

		$this->thumbnail_supported_extensions = ['png', 'jpg', 'jpeg', 'gif'];
		$this->thumbnail_dir = '_media/cache';
		$this->thumbnail_width = 360;
	}

	private function getRelativePath($path) {
		return str_replace($this->real_base, '', $path);
	}

	private function getNavigation(){
		$relative_path = $this->getRelativePath($this->path);
		//str_replace($this->real_base, '', $this->path);
		$breadcrumbs = explode(DIRECTORY_SEPARATOR, trim($relative_path, DIRECTORY_SEPARATOR));
		$navigaton = array(
			[ '', 'Home']
		);
		$breadcrumb_path = '';
		foreach ($breadcrumbs as $crumb) {
			if ($crumb === '') continue;
			$breadcrumb_path .= DIRECTORY_SEPARATOR . $crumb;
			$display_path = ltrim($breadcrumb_path, DIRECTORY_SEPARATOR);
			$navigaton[] = [ $display_path, $crumb ];
		}

		return $navigaton;
	}

	private function viewFile(){
		$file_extension = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
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
			$response['size']     = filesize($this->path);
			$response['size_kb']  = number_format($response['size'] / 1024, 0);
			$response['filename'] = basename($this->path);
			$response['modified'] = date('M j, Y H:i', filemtime($this->path));
			$response['ext']      = $file_extension;
			$response['path']     = $this->getRelativePath($this->path);
			$response['link']     = "/archive" . $response['path'];
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
				$html = "<img src=\".{$attrs['path']}\"/>";
				break;
			case 'txt':
			case 'md':
			case 'js':
			case 'css':
				$html = "<iframe src=\".{$attrs['path']}\"></iframe>";
				break;
			case 'mp4':
				$html = "<video controls><source src=\".{$attrs['path']}\" type=\"video/mp4\" /></video>";
				break;
			default:
				// throw new Exception('Unsupported image type');
				$html = "<pre>Preview not available. Click <a href=\"/archive{$attrs['path']}\" target=\"_blank\">here</a> to open</pre>";
		}
		return $html;
	}

	private function viewDir(){

		$contents = scandir($this->path);
		$directories = [];
		$files = [];

		$response = array(
			'dirs'  => array(),
			'files' => array()
		);

		foreach ($contents as $item) {
			if ($item === '.' || $item === '..' || strpos($item, '_') === 0) {
				continue;
			}

			$full_path = $this->path . DIRECTORY_SEPARATOR . $item;
			if (is_dir($full_path)) {
				$directories[] = $item;
			} elseif (is_file($full_path)) {
				$files[] = $item;
			}
		}

		sort($directories, SORT_NATURAL | SORT_FLAG_CASE);
		sort($files, SORT_NATURAL | SORT_FLAG_CASE);

		foreach ($directories as $directory) {
			$relative_dir_path = str_replace($this->real_base, '', $this->path . DIRECTORY_SEPARATOR . $directory);
			$response['dirs'][] = [ $relative_dir_path, $directory ];
		}

		foreach ($files as $file) {
			$file_extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
			if (in_array($file_extension, $this->approved_extensions)) {

				$thumbnail_path = '';
				
				if (in_array($file_extension, $this->thumbnail_supported_extensions)) {
					$thumbnail_path = $this->getThumbnailPath($file);
					if (!file_exists($thumbnail_path) || filemtime($full_path) > filemtime($thumbnail_path)) {
						$this->createThumbnail($file, $thumbnail_path);
					}
				}

				$relative_file_path = str_replace($this->real_base, '', $this->path . DIRECTORY_SEPARATOR . $file);
				$response['files'][] = [ $relative_file_path, $file, $file_extension, $thumbnail_path ];
			}
		}

		return $response;
	}

	public function get() {
		$response = array();

		if ($this->path !== false) {

			$response['navigation'] = $this->getNavigation();
			if (is_file($this->path)) {
				$response['file'] = $this->viewFile();
			} elseif (is_dir($this->path)) {
				$response['dir'] = $this->viewDir();
			} else {
				$response['error'] = 'The path is invalid or does not exist';
			}

		} else {
			$response['error'] = 'No path parameter provided or invalid path';
		}

		return $response;
	}

	public function renderFile($file){
		return json_encode($file, JSON_PRETTY_PRINT);
	}

	private function getPath(){
		$path = isset($_GET['p']) ? $_GET['p'] : './';
		$real_base = realpath(__DIR__);
		$real_path = realpath($path ? $real_base . DIRECTORY_SEPARATOR . $path : $real_base);

		if ($real_path && $this->is_within_base($real_path, $real_base)) {
			return $real_path;
		} else {
			return false;
		}
	}

	private function is_within_base($path, $base) {
		return strpos($path, $base) === 0;
	}

	/* Images */

	private function createThumbnail($file_path, $thumb_path) {

		$file_path = $this->path . DIRECTORY_SEPARATOR . $file_path;

		// print_r($file_path); exit;

		$info = getimagesize($file_path);
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
		$file_path = $this->path . DIRECTORY_SEPARATOR . $file_path;
		$file_info = pathinfo($file_path);
		$file_name = $file_info['filename'];
		$file_extension = $file_info['extension'];
		return $this->thumbnail_dir . DIRECTORY_SEPARATOR . hash('crc32', $this->path) . '_' . $file_name . '.' . $file_extension;
	}
}
