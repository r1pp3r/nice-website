<?php
include 'totalcms.php';

use TotalCMS\Component\File;
use TotalCMS\Component\Depot;

//-------------------------------------------
// GET Requests
//-------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	// The type must be specified
	if(!isset($_GET['type'])) exit;

	$mime_type = array(
		'zip'=>'application/zip',
		'pdf'=>'application/pdf',
		'rtf'=>'application/rtf',
		'eps'=>'application/postscript',
		'psd'=>'application/octet-stream',
		'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'mp3'=>'audio/mpeg',
		'mp4'=>'video/mp4',
		'ogg'=>'audio/ogg',
		'ogv'=>'video/ogg',
		'txt'=>'text/plain',
		'html'=>'text/html',
		'css'=>'text/css',
		'js'=>'text/javascript',
		'jpg'=>'image/jpeg',
		'png'=>'image/png',
		'gif'=>'image/gif'
	);

	switch ($_GET['type']) {

	    case 'depot':
			$totaldepot = new Depot($_GET['slug'],$_GET);

			$file = $_GET['filename'];
			$ext = pathinfo($file,\PATHINFO_EXTENSION);
			$mime = isset($mime_type[$ext]) ? $mime_type[$ext] : 'application/octet-stream';

			header("Content-disposition: attachment; filename=$file");
			header("Content-type: $mime");
			readfile($totaldepot->target_path());
	        break;

        case 'file':
    		$totalfile = new File($_GET['slug'],$_GET);

			$file = $_GET['slug'].'.'.$_GET['ext'];
			$ext  = $_GET['ext'];
			$mime = isset($mime_type[$ext]) ? $mime_type[$ext] : 'application/octet-stream';

			header("Content-disposition: attachment; filename=$file");
			header("Content-type: $mime");
			readfile($totalfile->target_path());
            break;
	}
}

exit();
?>
