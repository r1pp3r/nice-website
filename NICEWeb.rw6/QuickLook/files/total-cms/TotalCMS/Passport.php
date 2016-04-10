<?php
namespace TotalCMS;

//---------------------------------------------------------------------------------
// PASSPORT
//---------------------------------------------------------------------------------
class Passport
{

	private $cms_dir;
	private $logfile;
	private $pfile;
	private $ifile;
	private $efile;

	const INTERIM_ALLOWED = 'Past allowed timeframe for interim passport. Please verify that your server can properly communicate to the passport server.';
	const INTERIM_CREATE  = 'Unable to create passport interim.';
	const PASSPORT_CREATE = 'Unable to create passport.';
	const FAILURE         = 'Passport check failed. You will need to register a passport to use TotalCMS further.';
	const SUCCESS         = 'Passport check succeeded';
	const PASSPORT_URL    = 'https://passport.joeworkman.net/total-cms/';
	const PFILE           = 'passport.total';
	const IFILE           = 'interim.total';
	const EFILE           = 'passport.easy';
	const MAXP            = 1;
	const MAXI            = 5;
	const MAXC            = 3;
	const MAXMC           = 6;

	//-----------------------------------------------------------
	// Public Methods
	//-----------------------------------------------------------
	public function __construct($cms_dir=null,$logfile=null)
	{
		$site_root = preg_replace('/(.*)\/rw_common.+/','$1',__DIR__);
		$default_dir = "$site_root/cms-data";

		$this->cms_dir = isset($cms_dir) ? $cms_dir : $default_dir;
		$this->logfile = isset($logfile) ? $logfile : "$this->cms_dir/cms.log";
		$this->pfile = dirname(__DIR__).'/'.self::PFILE;
		$this->ifile = dirname(__DIR__).'/'.self::IFILE;
		$this->efile = dirname(dirname(__DIR__)).'/'.self::EFILE;
	}

	public function check($data=null)
	{
		if (file_exists($this->efile)) {
			if ($this->check_easy() === true){
				return true;
			}
			else {
		    	$this->log_message("Passport Check: Total CMS content must be removed from 'cms-data' if you want to use Easy CMS. Checking for valid Total CMS license...");
			}
		}
		return $this->check_total($data);
	}

	public function check_total_exists()
	{
		$this->check();
		return file_exists($this->pfile);
	}

    public function return_error($msg)
    {
    	$msg = trim(strip_tags($msg));
    	$this->log_message($msg);

    	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    		$post = json_encode($_POST);
	    	$json = json_encode(array(
	    		'code'    => 500,
	    		'message' => $msg,
	    		'post'    => $post
	    	));
	    	// $this->log_message("POST INFO: ".$post);

	    	header('HTTP/1.1 500 Internal Server Error');
    		header('Content-Type: application/json');
    		die($json);
    	}
    	die($msg);
    }

    public function return_success($msg)
    {
    	$msg = trim(strip_tags($msg));
    	$this->log_message($msg);
    	return true;
    }

	//-----------------------------------------------------------
	// Private Methods
	//-----------------------------------------------------------
	private function check_easy()
	{
		if ($this->total_count() > 0) return false;
		return true;
	}

	private function check_total($data=null)
	{
		$passport_age = $this->passport_age();
		if ($passport_age === false || $passport_age > self::MAXP) {
			$verify = isset($data) && $data->type === 'passport' ? $data : $this->verify();
			if ($verify === false) {
				$interim_age = $this->interim_age();
				if ($interim_age !== false && $interim_age > self::MAXI) {
					$this->return_error(self::INTERIM_ALLOWED);
				}
				if ($this->create_interim() === false) {
					$this->return_error(self::INTERIM_CREATE);
				}
			}
			elseif ($verify->status == true){
				$this->log_message($verify->info);
				$this->cancel_interim();
			}
			else {
				$this->log_message($verify->info);
				$this->return_error(self::FAILURE);
			}

			if ($this->create_passport() === false) {
				$this->return_error(self::PASSPORT_CREATE);
			}
			$this->return_success(self::SUCCESS);
		}
		$this->clear_easy_passport();
		return true;
	}

	private function count_element($type,$recursive=false)
	{
		$dir = "$this->cms_dir/$type";
		if (file_exists($dir)) {
			if ($recursive) {
				$fi = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
				return iterator_count($fi);
			}
			$fi = new \FilesystemIterator($dir, \FilesystemIterator::SKIP_DOTS);
			return iterator_count($fi);
		}
		return 0;
	}

	private function total_count()
	{
		$total = 0;

		$multi_types = array('feed','blog','gallery','depot');
		foreach ($multi_types as $multi_type) {
			$total += ($this->count_element($multi_type,true)/self::MAXMC);
		}

		$types = array('video','toggle','file');
		foreach ($types as $type) {
			$total += $this->count_element($type);
		}

		return $total;
	}

	private function verify()
	{
		$ch = curl_init();
		$domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
		curl_setopt($ch,CURLOPT_URL,self::PASSPORT_URL.$domain);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION,true);
		curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,5);
		$contents = curl_exec($ch);
		$httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
		if ($httpCode !== 200) {
	    	$this->log_message("Passport request error for $domain: (http code:".$httpCode.") ".curl_error($ch));
	    	$this->log_message("Passport request contents: ".$contents);
			return false;
		}
		curl_close($ch);
		$this->log_message('PASSPORT DEBUG: '.$contents);
		return json_decode($contents);
	}

	private function log_message($message)
	{
		$logline = date(DATE_RFC2822)." $message\n";
	    error_log($logline, 3, $this->logfile);
	}

	private function age($file)
	{
		$oneday = 60*60*24;
		if (file_exists($file)) {
			return (time()-filemtime($file))/$oneday;
		}
		return false;
	}

	private function passport_age()
	{
		return $this->age($this->pfile);
	}

	private function interim_age()
	{
		return $this->age($this->ifile);
	}

	private function create_passport()
	{
		return file_put_contents($this->pfile,date(DATE_RFC2822));
	}

	private function create_interim()
	{
    	$this->log_message("WARNING: Unable to contact Passport server. Generating provisional license.");
		return file_put_contents($this->ifile,date(DATE_RFC2822));
	}

	private function cancel_interim()
	{
		if (file_exists($this->ifile)) {
			return unlink($this->ifile);
		}
		return true;
	}

	private function clear_easy_passport()
	{
		if (file_exists($this->efile)) {
			return unlink($this->efile);
		}
		return true;
	}
}
