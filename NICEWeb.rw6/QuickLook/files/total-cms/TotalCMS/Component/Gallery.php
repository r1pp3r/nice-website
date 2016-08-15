<?php
namespace TotalCMS\Component;

//---------------------------------------------------------------------------------
// GALLERY class
//---------------------------------------------------------------------------------
class Gallery extends Image
{
	private $cache;
	private $json_file;
	public  $index;

	public function __construct($slug,$options=array())
	{
    	$options = array_merge(array(
			'type'     => 'gallery',
			'ext'      => 'jpg',
			'resize'   => 'auto',
			'quality'  => 85,
			'scale'    => 1500,
			'scale_th' => 300,
			'scale_sq' => 300,
			'index'    => false,
			'filename' => false
    	), $options);

		$options['set'] = true;
		$options['ext'] = self::JPG; //hard coded for now to make life easier

		parent::__construct($slug,$options);

		$this->cache = new \TotalCMS\Cache($slug,$this->target_dir,$this);
		$this->index = $options['index'] === false ? intval($this->cache->data->next) : intval($options['index']);
		$this->set_filename($this->slug."-".$this->index);

		$this->add_thumb($this->thumb($options));
		$this->add_thumb($this->square($options));

		$this->json_file = "$this->target_dir/$this->slug.json";
		$this->alt = new Alt($slug,array(
			'set'      => $this->set,
			'type'     => $this->type,
			'filename' => $this->filename
		));
	}

	public function delete()
	{
		if (file_exists($this->target_dir)) {
			parent::delete();
			// rebuild db
			$this->cache->rebuild();
			// delete json cache
			$this->delete_json();
			$this->process_data();
		}
	}

	private function delete_json()
	{
		if (file_exists($this->json_file)) unlink($this->json_file);
	}

	public function update_alt($alt)
	{
		parent::update_alt($alt);
		// delete json cache
		$this->delete_json();
		$this->process_data();
	}

	public function save_content_to_cms($image,$options=array())
	{
    	$options = array_merge(array(
	        'alt' => '',
    	), $options);

		parent::save_content_to_cms($image,$options);

		// rebuild cache & json
		$this->cache->rebuild();
		$this->delete_json();
		$this->process_data();
	}

	public function rebuild_schema()
	{
		$active = array();
		$index = 1;
		$fi = new \FilesystemIterator($this->target_dir, \FilesystemIterator::SKIP_DOTS);
		foreach($fi as $entry) {
		    if (preg_match('/\-(\d+)\.\w+$/',$entry->getFilename(),$matches)) {
		        $active[] = intval($matches[1]);
		        if ($matches[1] > $index) $index = $matches[1];
		    }
		}
		$active = array_unique($active);
		asort($active);
		return json_encode(array('next'=>$index+1,'active'=>array_values($active)));
	}

	public function default_schema()
	{
		$this->log_message('default_schema');
		// return json_encode(array('next'=>0,'active'=>array()));
		return '{"next":1,"active":[]}';
	}

	public function process_data($index=false)
	{
		if ($index !== false) {
			return parent::process_data();
		}
		else {
			if (file_exists($this->json_file)) {
				$this->images = json_decode(file_get_contents($this->json_file),true);
			}
			else {
	    		$this->make_dir($this->target_dir);

				$images = array();
				$cms_dir = ltrim(str_replace($this->site_root,"",$this->target_dir),"/");

				foreach ($this->cache->data->active as $active_index) {
					$image = new Gallery($this->slug,array('index'=>$active_index));
					$image->add_thumb($this->thumb());
					$image->add_thumb($this->square());

					$data  = array(
						'index' => "$active_index",
						'img'   => "$cms_dir/$image->target_file",
						'alt'   => $image->get_alt()
					);
					foreach ($image->get_thumbs() as $thumb) {
						$data['thumb'][$thumb->suffix] = "$cms_dir/$thumb->target_file";
					}
					$images[] = $data;
				}
				$this->images = $images;
				file_put_contents($this->json_file,json_encode($images));
			}
		}
		return $this->images;
	}
}
