<?php
namespace TotalCMS\Component;

//---------------------------------------------------------------------------------
// Component Interface
//---------------------------------------------------------------------------------
interface ComponentInterface
{
	public function delete();
	public function target_path();
	public function get_contents($format=false);
	public function log_message($message);
    public function log_error($msg);
    public function process_data();
    public function to_data($id=false);
    public function backup($prefix=false);
    public function save_content($contents,$options=array());
    public function save_content_to_cms($contents,$options=array());
}

//---------------------------------------------------------------------------------
// Component Abstract Class
//---------------------------------------------------------------------------------
abstract class Component implements ComponentInterface
{
	public $slug;
	protected $error;
	protected $filename;
	protected $ext;
	protected $site_root;
	protected $cms_dir;
	protected $tmp_dir;
	protected $target_dir;
	protected $target_file;
	protected $bkp_dir;
	protected $type;
	protected $contents;
	protected $set;
	protected $bkp_max;
	protected $logfile;
	protected $logsize;
	protected $easy;
	protected $not_found;

	const NOTFOUND = 'Unable to locate the cms file with the id';
	const EXT = 'cms';
	const JPG = 'jpg';
	const MAXFEED = 25;

	//-----------------------------------------------------------
	// Constructor
	//-----------------------------------------------------------
	public function __construct($slug,$options=array())
	{
		// Set this data up first so that the logfile could be used
		if (php_sapi_name() === 'cli') {
			//  Running Local for testing. cms-data will be inside Library folder
			$this->site_root = preg_replace('/(.*\/Library).+/','$1',__DIR__);
		}
		else {
			// Assuming the this is deployed at /rw_common/plugins/stacks/total-cms
			$this->site_root = preg_replace('/(.*)\/rw_common.+/','$1',__DIR__);
		}
		$this->cms_dir = "$this->site_root/cms-data";
		$this->logfile = $this->cms_dir.'/cms.log';
		$this->logsize = 500;

		// slug checks
		if(!isset($slug)) {
			$this->log_error("Must define slug for cms to function");
			return false;
		}
		if(preg_match('/[^\w-]/',$slug)) {
			$this->log_error("Invalid characters found in CMS ID: $slug (Alpha-Numberic Only: A-Z a-z 0-9). This may cause issues.");
		}

    	$options = array_merge(array(
			'type'     => 'text',
			'ext'      => self::EXT,
			'set' 	   => false,
			'filename' => false
    	), $options);

		set_error_handler(array($this, 'error_handler'));

		$this->slug     = $slug;
		$this->ext      = $options['ext'];
		$this->filename = $options['filename'] === false ? $slug : $options['filename'];
		$this->type     = $options['type'];
		$this->set      = $options['set'];
		$this->contents = false;

		$this->bkp_max  = 10;

		$this->easy = file_exists(__DIR__.'/../passport.easy');

		$this->tmp_dir = $this->cms_dir.'/tmp';
		$this->bkp_dir = $this->cms_dir."/backups/$this->type/$slug";

		if ($this->set === true) {
			$this->target_dir = $this->cms_dir."/$this->type/$slug";
		}
		else {
			$this->target_dir = $this->cms_dir."/$this->type";
			$this->set = false;
		}

		$this->target_file = "$this->filename.$this->ext";
		$this->not_found = "<p>".self::NOTFOUND." <b>$this->slug</b>.</p>";

    	$this->make_dir($this->cms_dir);
	}

	//-----------------------------------------------------------
	// Public Methods
	//-----------------------------------------------------------
	public function delete()
	{
		$this->log_message("Deleting cms data for $this->type/$this->slug/$this->target_file");
		$path = $this->target_path();
		if (file_exists($path)) return unlink($path);
		return true;
	}

	public function target_path()
	{
		return $this->target_dir.'/'.$this->target_file;
	}

	public function format_text($text)
	{
		// Convert Breaks in the middle of a paragraph
		$text = str_replace("\r\n","\n",$text);
		$text = preg_replace('/([^\n])\n([a-zA-Z])/um',"$1<br/>$2", $text);
		return \Michelf\Markdown::defaultTransform($text);
	}

	public function get_contents($format = false)
	{
		$contents = $this->contents;

		if (!$contents) {
			if (file_exists($this->target_path())) {
				// Get the contents if the file exists
				$this->contents = file_get_contents($this->target_path());
				$contents = $this->contents;
			}
			else {
				// Not Found
				$contents = $this->not_found;
			}
		}

	    return $format ? $this->format_text($contents) : $contents;
	}

	public function log_message($message)
	{
		if (file_exists($this->logfile)) {
			$size = filesize($this->logfile)/1024;
			if ($size > $this->logsize) unlink($this->logfile);
		}
		$logline = date(DATE_RFC2822)." : $message\n";
	    return error_log($logline, 3, $this->logfile);
	}

    public function last_error()
    {
    	return $this->error;
    }

    public function log_error($msg)
    {
    	$msg = trim(strip_tags($msg));
    	$this->log_message("ERROR:".$msg);
    	$this->error = $msg;

    	// if (isset($_POST)) {
    	// 	$this->log_message("DEBUG ERROR INFO: ".json_encode($_POST));
    	// }
    }

    public function process_data()
    {

    }

    public function to_data($id=false)
    {
    	$this->process_data($id);
    	return $this;
    }

    public function set_filename($basename)
    {
    	$this->filename = $basename;
		$this->target_file = "$this->filename.$this->ext";
		return $this->target_file;
    }

    public function backup($prefix=false)
    {
    	if ($this->easy || !file_exists($this->target_path())) return false;

		$this->make_dir($this->bkp_dir);

    	// Add datetime stamp to backup file
    	$prefix = $prefix === false ? date("Ymd-His") : $prefix;
		$bkp_file = $this->set ? $this->target_file : $prefix.'-'.$this->target_file;

    	if (!copy($this->target_path(),$this->bkp_dir.'/'.$bkp_file)) {
    		$this->log_error("Could not backup to cms. ".$bkp_file);
    		return false;
    	}
    	$this->trim_old_backups();
    	return $prefix;
    }

    public function save_content_to_cms($contents,$options=array())
    {
    	$options = array_merge(array(
	        'strip' => false,
    	), $options);

		if ($options['strip'] === true) {
			$this->contents = stripslashes(strip_tags(rawurldecode($contents)));
		}
		else {
			$this->contents = stripslashes(rawurldecode($contents));
		}
    	return file_put_contents($this->target_path(),$this->contents);
    }

    public function save_content($contents,$options=array())
    {
    	$passport = new \TotalCMS\Passport($this->cms_dir,$this->logfile);
		$passport->check();

		$this->log_message("Saving new content to $this->type/$this->slug/$this->target_file");

    	$this->make_dir($this->target_dir);

		if ($this->save_content_to_cms($contents,$options) === false){
    		$this->log_error("Could not write to cms file! ".$this->target_path());
			return false;
		}

    	$this->backup();
    	return $this->target_path();
    }

	//-----------------------------------------------------------
	// Protected Methods
	//-----------------------------------------------------------
	public function error_handler($errno,$errstr,$errfile,$errline)
	{
		$this->log_error("PHP ERROR [$errno] $errstr ($errfile line:$errline)");
		// Don't execute PHP internal error handler */
		return true;
	}

    protected function make_dir($dir)
    {
    	if (!file_exists($dir)) {
    		if (!mkdir($dir, 0775, true)) {
    			$this->log_error("Failed to create cms directory: $dir");
    			return false;
    		}
    	}
    	else {
    		if (!is_writable($dir)) {
    			chmod($dir,0775);
	    		if (!is_writable($dir)) {
	    			$this->log_error("The cms directory is not writable. Please fix the permissions on the directory: $dir");
	    			return false;
	    		}
    		}
    	}
    	return true;
    }

	protected function scandir_sort_filemtime($dir)
	{
	    $files = array();
	    $it = new \FilesystemIterator($dir,\FilesystemIterator::SKIP_DOTS);
	    foreach ($it as $file) {
	        if (is_dir($file->getPathname())) continue;
	        $files[$file->getPathname()] = filemtime($file->getPathname());
	    }
	    asort($files);
	    return array_keys($files);
	}

	protected function trim_old_backups()
	{
		// Image have 4 times backup count becuase of thumbnails and alt cms files
		$max_count = ($this->type === 'image' || $this->type === 'gallery') ? $this->bkp_max * 4 : $this->bkp_max;

		$del_count = 0;
		$files = $this->scandir_sort_filemtime($this->bkp_dir);
		$count = count($files);
		if ($count > $max_count) {
			// Find how many we need to delete and nuke them
			$del_count = $count - $max_count;
			for ($i=0; $i < $del_count; $i++) {
				unlink($files[$i]);
			}
		}
		return $del_count;
	}
}
