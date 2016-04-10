<?php
namespace TotalCMS\Component;

//---------------------------------------------------------------------------------
// VIDEO class
//---------------------------------------------------------------------------------
class Video extends Component
{
	private $service;
	private $video_id;
	private $embed;

	public function __construct($slug,$options=array())
	{
    	$options = array_merge(array(
			'type' => 'video',
    	), $options);

		parent::__construct($slug,$options);
		$this->embed = '';
	}

	public function get_video_embed($options=array())
	{
    	$options = array_merge(array(
			'autoplay' => 0,
			'loop'     => 0,
			'ycolor'   => 'red',
			'ytheme'   => 'dark',
			'vcolor'   => '33aaff',
    	), $options);

		$contents = trim($this->get_contents());

		if (strpos($contents,self::NOTFOUND) !== false) return $contents;

		if (strpos($contents,'youtu')  !== false) { //youtube.com or youtu.be
			$this->service = 'youtube';
			$embed = $this->youtube_embed($contents,$options);
		}
		elseif (strpos($contents,'vimeo') !== false) {
			$this->service = 'vimeo';
			$embed = $this->vimeo_embed($contents,$options);
		}

		if ($embed !== false) {
			$this->embed = $embed;
			return $this->embed;
		}
		$this->log_error("Unable to locate video ID from video url: '$contents'");
		return false;
	}

    public function save_content_to_cms($contents,$options=array())
    {
		$contents = trim(stripslashes(rawurldecode($contents)));

		if ($contents !== '' && strpos($contents,'youtu')  === false && strpos($contents,'vimeo')  === false) {
    		$this->log_error("Cannot save unsupported video content: ".$contents);
    		return false;
		}
		$this->contents = $contents;
    	return file_put_contents($this->target_path(),$this->contents);
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
				'origin'    	 => $_SERVER["HTTP_HOST"], // or $_SERVER["SERVER_NAME"]
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
}
