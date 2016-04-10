%[if preview]%
<?php if (!class_exists('totalpreview')) {

error_reporting(E_ALL);

// This cannot be done via central included library in RapidWeaver. It will not work in preview
class totalpreview
{
	protected $ext;
	protected $type;
	protected $baseurl;

	const NOTFOUND = 'Unable to locate the cms file with the id';
	const EXT = 'cms';

	function __construct($type='text',$ext=self::EXT)
	{
		$this->ext  = $ext;
		$this->type = $type;
		$this->baseurl = preg_replace('/\/\/$/','/','%baseURL%/'); // %baseURL% is replaced by the Stacks API
	}

	private function query($url) {
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,5);
		$results = curl_exec($ch);
		$httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
		curl_close($ch);
		return $httpCode == 404 ? false : $results;
	}

	public function format_text($text)
	{
		// Convert Breaks in the middle of a paragraph
		$text = str_replace("\r\n","\n",$text);
		$text = preg_replace('/([^\n])\n([a-zA-Z])/um',"$1<br/>$2", $text);
	    return Markdown($text);
	}

	public function get_contents($slug,$format=false)
	{
		$not_found = "<p>".self::NOTFOUND." <b>$slug</b>.</p>";

		$file = $this->baseurl."cms-data/$this->type/$slug.$this->ext";
		$results = $this->query($file);
		$contents = $results === false ? $not_found : $results;

	    return $format !== false ? $this->format_text($contents) : $contents;
	}

	//---------------------------------------------------------------------------------
	// Replace Methods
	//---------------------------------------------------------------------------------
	public function replace($buffer)
	{
		if ($this->type === 'text') return $this->totaltext_replace($buffer);
		return $buffer;
	}

	private function totaltext_replace($buffer)
	{
		// Find all of the macros defined on the page
		if (preg_match_all('/\W%(\w+)(\s-(format|alt))*%\W/', $buffer, $matches)) {
			$slugs = array_unique($matches[1]);

			foreach ($slugs as $slug) {
				$macro = "%$slug%";
				if (strpos($buffer,$macro) !== false) {
					$text   = str_replace("\n",'<br/>',$this->get_contents($slug));
					$buffer = str_replace($macro,$text,$buffer);
				}

				$macro = "%$slug -format%";
				if (strpos($buffer,$macro) !== false) {
					$buffer = str_replace($macro, $this->get_contents($slug,true), $buffer);
				}

				$macro = "%$slug -alt%";
				if (strpos($buffer,$macro) !== false) {
					$buffer = str_replace($macro, $this->get_alt($slug), $buffer);
				}
			}
		}
		return $buffer;
	}

	//---------------------------------------------------------------------------------
	// Alt Methods
	//---------------------------------------------------------------------------------
	public function get_alt($slug,$format=false)
	{
		$not_found = '';

		$file = $this->baseurl."cms-data/image/$slug.$this->ext";
		$results = $this->query($file);
		$contents = $results === false ? $not_found : $results;

	    return $format ? Markdown($contents) : $contents;
	}

	//---------------------------------------------------------------------------------
	// Toggle Methods
	//---------------------------------------------------------------------------------
	public function get_toggle_status($slug)
	{
		$file = $this->baseurl."cms-data/toggle/$slug.$this->ext";
		$results = $this->query($file);
		return $results === false ? false : true;
	}

	//---------------------------------------------------------------------------------
	// News Feed Methods
	//---------------------------------------------------------------------------------
	public function get_feed($slug)
	{
		$feed_url = $this->baseurl."rw_common/plugins/stacks/total-cms/totalapi.php?type=feed&slug=$slug";
		$results = $this->query($feed_url);

		if ($results === false) {
			return array();
		}
		$data = json_decode($results,true);
		return $data['data']['posts'];
	}

	//---------------------------------------------------------------------------------
	// Gallery Methods
	//---------------------------------------------------------------------------------
	public function get_gallery($slug)
	{
		$gallery_url = $this->baseurl."rw_common/plugins/stacks/total-cms/totalapi.php?type=gallery&slug=$slug";
		$results = $this->query($gallery_url);

		if ($results === false) {
			return array();
		}
		$data = json_decode($results,true);
		$images = array();
		foreach ($data['data']['images'] as $image) {
			if ($image['alt'] != '') {
				$image['alt'] = $image['alt'];
			}
			$images[] = $image;
		}
		return $images;
	}

	//---------------------------------------------------------------------------------
	// Video Methods
	//---------------------------------------------------------------------------------
	public function totalvideo_embed($slug,$options=array())
	{
    	$options = array_merge(array(
			'autoplay' => 0,
			'loop'     => 0,
			'ycolor'   => 'red',
			'ytheme'   => 'dark',
			'vcolor'   => '33aaff',
    	), $options);

		$contents = trim($this->get_contents($slug));

		if (strpos($contents,self::NOTFOUND) !== false) return $contents;

		if (strpos($contents,'youtu')  !== false) { //youtube.com or youtu.be
			$this->service = 'youtube';
			$embed = $this->youtube_embed($contents,$options);
		}
		elseif (strpos($contents,'vimeo') !== false) {
			$this->service = 'vimeo';
			$embed = $this->vimeo_embed($contents,$options);
		}
		return isset($embed) ? $embed : "<p>Unable to locate video ID from video url: '$contents'</p>";
	}

	private static function vimeo_embed($url,$options=array())
	{
		$options = array_merge(array(
			'autoplay' => 0,
			'loop'     => 0,
			'vcolor'   => '33aaff',
    	), $options);

		if (preg_match('/(\w+)$/', $url, $matches)) {
			$video_id = $matches[0];
			$query = http_build_query(array(
				'autoplay' => $options['autoplay'],
				'color'    => $options['vcolor'],
				'loop'     => $options['loop'],
				'api'      => 1,
				'badge'    => 0,
				'byline'   => 0,
				'portrait' => 0,
				'title'    => 0
			),'','&amp;');
			return "<iframe width='1280' height='720' src='https://player.vimeo.com/video/$video_id?$query' frameborder='0' webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>";
		}
		return false;
	}

	private static function youtube_embed($url,$options=array())
	{
    	$options = array_merge(array(
			'autoplay' => 0,
			'loop'     => 0,
			'ycolor'   => 'red',
			'ytheme'   => 'dark',
			'private'  => true
    	), $options);

		if (preg_match('/(\w+)$/', $url, $matches)) {
			$video_id = $matches[0];

			$query = array(
				'autoplay'       => $options['autoplay'],
				'loop'    		 => $options['loop'],
				'color'    		 => $options['ycolor'],
				'theme'    		 => $options['ytheme'],
				'origin'    	 => 'localhost', // or $_SERVER["SERVER_NAME"]
				'enablejsapi'    => 1,
				'rel'            => 0,
				'showinfo'       => 0
			);
			if ((strpos($url,'list')  !== false)) {
				// playlist
				$query['listType'] = 'playlist';
				$query['list'] = $video_id;
				$video_id = '';
			}
			$http_query = http_build_query($query,'','&amp;');
			$domain = $options['private'] === true ? 'www.youtube-nocookie.com' : 'www.youtube.com';
			return "<iframe width='1280' height='720' src='https://$domain/embed/$video_id?$http_query' frameborder='0' webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>";
		}
		return false;
	}

	//---------------------------------------------------------------------------------
	// Depot Methods
	//---------------------------------------------------------------------------------
	public function get_depot($slug)
	{
		$depot_url = $this->baseurl."rw_common/plugins/stacks/total-cms/totalapi.php?type=depot&slug=$slug";
		$results = $this->query($depot_url);

		if ($results === false) {
			return array();
		}
		$data = json_decode($results,true);
		return $data['data']['files'];
	}

}

} ?>
%[endif]%
