<?php
namespace TotalCMS;

use TotalCMS\Component\Text;
use TotalCMS\Component\Alt;

//---------------------------------------------------------------------------------
// REPLACE class
//---------------------------------------------------------------------------------
class ReplaceText
{
	protected $ext;
	protected $type;

	const EXT = 'cms';

	function __construct($type='text',$ext=self::EXT)
	{
		$this->ext  = $ext;
		$this->type = $type;
	}

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

				$totaltext = new Text($slug,array(
					'type' => $this->type,
					'ext'  => $this->ext
				));

				$macro = "%$slug%";
				if (strpos($buffer,$macro) !== false) {
					$text   = str_replace("\n",'<br/>', $totaltext->get_contents());
					$buffer = str_replace($macro, $text, $buffer);
				}

				$macro = "%$slug -format%";
				if (strpos($buffer,$macro) !== false) {
					$buffer = str_replace($macro, $totaltext->get_contents(true), $buffer);
				}

				$macro = "%$slug -alt%";
				if (strpos($buffer,$macro) !== false) {
					$totalalt = new Alt($slug,array('type'=>'image'));
					$buffer = str_replace($macro, $totalalt->get_contents(), $buffer);
				}
			}
		}
		return $buffer;
	}
}
