<?php
namespace TotalCMS\Component;

//---------------------------------------------------------------------------------
// IMAGE class
//---------------------------------------------------------------------------------
class Image extends Component
{
	// Thumb and Square need to be totalimage objects as well

	protected $image;
	protected $resize;
	protected $quality;
	protected $scale;
	protected $alt;
	protected $thumbs;
	public    $suffix;
	public    $images;

	public function __construct($slug,$options=array())
	{
    	$options = array_merge(array(
			'type'     => 'image',
			'ext'      => 'jpg',
			'resize'   => 'auto',
			'quality'  => 85,
			'scale'    => 1500,
			'suffix'   => false,
			'filename' => false,
			'altfile'  => false
    	), $options);

		parent::__construct($slug,$options);

		$this->suffix  = $options['suffix'];
		$this->resize  = $options['resize'];
		$this->quality = $options['quality'];
		$this->scale   = $options['scale'];

		$this->thumbs = array();
		$this->alt = new Alt($slug,array(
			'set'      => $this->set,
			'type'     => $this->type,
			'filename' => $options['altfile'] === false ? $this->filename : $options['altfile']
		));
	}

	public static function square($options=array()) {
		$options = array_merge(array(
	        'scale_sq' => 100
		), $options);

		return array(
			'suffix' => 'sq',
			'resize' => 'crop',
			'scale'  => $options['scale_sq']
		);
	}

	public static function thumb($options=array()) {
		$options = array_merge(array(
	        'resize'   => 'auto',
	        'scale_th' => 100
		), $options);

		return array(
			'suffix' => 'th',
			'resize' => $options['resize'],
			'scale'  => $options['scale_th']
		);
	}

	public function get_thumbs()
	{
		return $this->thumbs;
	}

	public function get_alt()
	{
		return $this->alt->get_contents();
	}

	public function get_alt_file()
	{
		return $this->alt->target_path();
	}

	public function update_alt($alt)
	{
		return $this->alt->save_content($alt);
	}

	public function add_thumb($options=array())
	{
    	$options = array_merge(array(
			'suffix'   => 'th',
			'type'     => $this->type,
			'ext'      => $this->ext,
			'resize'   => $this->resize,
			'quality'  => $this->quality,
			'set'      => $this->set,
			'scale'    => $this->scale,
    	), $options);

		// Set the filename to have the thumb suffix
		$options['filename'] = $this->filename.'-'.$options['suffix'];

		$thumb = new Image($this->slug,$options);
		array_push($this->thumbs,$thumb);
	}

	public function delete()
	{
		parent::delete();

		$this->alt->delete();

		foreach ($this->thumbs as $thumb) {
			$thumb->delete();
		}
		return true;
	}

	public function get_images()
	{
		$images = array($this->target_path());
		foreach ($this->thumbs as $thumb) {
			array_push($images, $thumb->target_path());
		}
		return $images;
	}

	public function backup($prefix=false)
	{
		$parent_prefix = parent::backup($prefix);
		foreach ($this->thumbs as $thumb) {
			$thumb->backup($parent_prefix);
		}
		return $parent_prefix;
	}

	public function save_content_to_cms($image,$options=array())
	{
    	$options = array_merge(array(
	        'alt' => '',
    	), $options);

		$this->make_dir($this->tmp_dir);

		// Move uploaded image to tmp_dir for processing
		$tmp_image = $this->tmp_dir.'/'.$this->target_file;

		if (php_sapi_name() === 'cli') {
			//  Running Local for testing
			$rc = copy($image['tmp_name'],$tmp_image);
		}
		else {
			$rc = move_uploaded_file($image['tmp_name'],$tmp_image);
		}

		if (!$rc) {
			$this->log_error("Could not save uploaded image to cms! ".$tmp_image);
			return false;
		}

		// Check if the GD extension is loaded and just use the uploaded image if its not
		if (!extension_loaded('gd')) {
			copy($tmp_image, $this->target_path());
			$this->log_error("The GD PHP extension must be installed. Not resizing image.");
			return false;
		}

		$this->resize_image($tmp_image);

		foreach ($this->thumbs as $thumb) {
			$thumb->resize_image($tmp_image);
		}

		if (file_exists($tmp_image)) { unlink($tmp_image); }

		// Save (default) alt file unless one is there
		if (!file_exists($this->get_alt_file())) {
			$this->alt->save_content($options['alt']);
		}
		return true;
	}

	protected function resize_image($image_path)
	{
		$rotateVal = 0;

		if ($this->ext === self::JPG) {
			$exif = exif_read_data($image_path);
			if (array_key_exists('Orientation', $exif)) {
				switch($exif['Orientation']) {
				    case 8:
				        $rotateVal = -90;
				        break;
				    case 3:
				        $rotateVal = 180;
				        break;
				    case 6:
				        $rotateVal = 90;
				        break;
				}
			}
		}

		$imagine = new \Imagine\Gd\Imagine();
		$autorotate = new \Imagine\Filter\Basic\Rotate($rotateVal);
		$image = $rotateVal == 0 ? $imagine->open($image_path) : $autorotate->apply($imagine->open($image_path));

		$this->scale_image($image);

		if ($this->resize === 'crop') {
			$this->crop_image($image);
		}

		return $this->save_image($image);
	}

	protected function scale_image($image)
	{
		$size   = $image->getSize();
		$width  = $size->getWidth();
		$height = $size->getHeight();

		$mode = $this->resize;
		if ($mode === 'auto') {
			$mode = $height > $width ? 'portrait' : 'landscape';
		}
		elseif ($mode === 'crop') {
			$mode = $height > $width ? 'landscape' : 'portrait';
		}

		switch ($mode) {
			case 'portrait':
				if ($height > $this->scale) {
					return $image->resize($size->heighten($this->scale));
				}
				break;
			case 'landscape':
				if ($width > $this->scale) {
					return $image->resize($size->widen($this->scale));
				}
				break;
		}
		return false;
	}

	protected function crop_image($image)
	{
		$size   = $image->getSize();
		$width  = $size->getWidth();
		$height = $size->getHeight();

		$startX = 0;
		$startY = 0;

		$smallest = min($height,$width,$this->scale);

		if ($height > $width) { // portrait
			$startY = round(($height - $smallest)/2);
		}
		else { // landscape
			$startX = round(($width - $smallest)/2);
		}

		$point = new \Imagine\Image\Point($startX,$startY);
		$box   = new \Imagine\Image\Box($smallest,$smallest);
		return $image->crop($point,$box);
	}

	protected function save_image($image)
	{
		// Default jpeg compression
		$compression_level = array('jpeg_quality' => $this->quality);

		// png compression
		if ($this->ext === 'png') {
			// Scale quality from 0-100 to 0-9
			$scaleQuality = round(($this->quality/100) * 9);
			// Invert quality setting as 0 is best, not 9
			$invertScaleQuality = 9 - $scaleQuality;
			$compression_level = array('png_compression_level' => $invertScaleQuality);
		}

		// if (file_exists($this->target_path())) {
		// 	unlink($this->target_path());
		// }

		return $image->save($this->target_path(),$compression_level);
	}

	public function process_data()
	{
		$this->make_dir($this->target_dir);

		$images = array();
		$cms_dir = ltrim(str_replace($this->site_root,"",$this->target_dir),"/");

		$data  = array(
			'img'   => "$cms_dir/$this->target_file",
			'alt'   => $this->get_alt()
		);

		$this->add_thumb($this->thumb());
		$this->add_thumb($this->square());

		foreach ($this->thumbs as $thumb) {
			if (file_exists($thumb->target_path())) {
				$data['thumb'][$thumb->suffix] = "$cms_dir/$thumb->target_file";
			}
		}

		$images[] = $data;
		$this->images = $images;
		return $this->images;
	}
}
