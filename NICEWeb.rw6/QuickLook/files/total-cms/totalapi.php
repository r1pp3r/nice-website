<?php
include 'totalcms.php';

use TotalCMS\Component\Blog;
use TotalCMS\Component\Depot;
use TotalCMS\Component\Feed;
use TotalCMS\Component\File;
use TotalCMS\Component\Gallery;
use TotalCMS\Component\Image;
use TotalCMS\Component\Text;
use TotalCMS\Component\Toggle;
use TotalCMS\Component\Video;

$data = '';

//-------------------------------------------
// POST Requests
//-------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	switch ($_POST['type']) {

	    case 'text':
	    	text_post($_POST);
	        break;

	    case 'image':
	    	image_post($_FILES['file'],$_POST);
	        break;

	    case 'gallery':
	    	gallery_post($_FILES['file'],$_POST);
	        break;

	    case 'video':
	    	video_post($_POST);
	        break;

	    case 'file':
	    	file_post($_FILES['file'],$_POST);
	        break;

	    case 'depot':
	    	depot_post($_FILES['file'],$_POST);
	        break;

	    case 'feed':
	    	$image = isset($_FILES['file']) ? $_FILES['file'] : false;
	    	feed_post($image,$_POST);
	        break;

	    case 'toggle':
	    	toggle_post($_POST);
	        break;

	    case 'passport':
	    	passport($_POST);
	        break;
	}
}

//-------------------------------------------
// GET Requests
//-------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

	switch ($_GET['type']) {

	    case 'text':
			$data = text_get($_GET['slug']);
	        break;

	    case 'toggle':
			$data = toggle_get($_GET['slug']);
	        break;

	    case 'gallery':
	    	$data = gallery_get($_GET['slug'],$_GET);
	        break;

	    case 'depot':
	    	$data = depot_get($_GET['slug'],$_GET);
	        break;

	    case 'feed':
	    	$data = feed_get($_GET['slug'],$_GET);
	        break;

	    case 'image':
	    	$data = image_get($_GET['slug'],$_GET);
	        break;
	}
}

//-------------------------------------------
// PUT Requests
//-------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {

	switch ($_GET['type']) {

	    case 'gallery':
	    	gallery_put($_GET['slug'],$_GET['alt'],$_GET);
	        break;

        case 'image':
	    	image_put($_GET['slug'],$_GET['alt'],$_GET);
            break;

        case 'feed':
	    	feed_put($_GET['slug'],$_GET['alt'],$_GET);
            break;
	}
}

//-------------------------------------------
// DELETE Requests
//-------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

	switch ($_GET['type']) {

	    case 'gallery':
	    	gallery_delete($_GET['slug'],$_GET);
	        break;

        case 'image':
	    	image_delete($_GET['slug'],$_GET);
            break;

        case 'file':
	    	file_delete($_GET['slug'],$_GET);
            break;

        case 'depot':
	    	depot_delete($_GET['slug'],$_GET);
            break;

        case 'feed':
	    	feed_delete($_GET['slug'],$_GET);
            break;
	}
}

return_success("Success!",$data);

//-------------------------------------------
// Return Data
//-------------------------------------------
function return_success($msg,$data)
{
	header('Content-Type: application/json');
	echo json_encode(array(
		'code' 	  => 200,
		'message' => trim(strip_tags($msg)),
		'data'    => $data
	));
	exit();
}
function return_error($msg,$object)
{
	$msg = trim(strip_tags($msg));
	$json = json_encode(array(
		'code'    => 500,
		'message' => $msg,
		'post'    => $_POST,
		'data'    => $object->to_data(),
	));
	header('HTTP/1.1 500 Internal Server Error');
	header('Content-Type: application/json');
	die($json);
}

//-------------------------------------------
// Passport API
//-------------------------------------------
function passport($options=array())
{
	$passport = new \TotalCMS\Passport();
	return $passport->check(json_decode(json_encode($options)));
}

//-------------------------------------------
// Toggle API
//-------------------------------------------
function toggle_post($options=array())
{
	$toggle = new Toggle($options['slug']);
	return $toggle->save_content($options['state']);
}
function toggle_get($slug)
{
	$toggle = new Toggle($slug);
	return $toggle->status();
}

//-------------------------------------------
// Text API
//-------------------------------------------
function text_post($options=array())
{
	$totaltext = new Text($options['slug']);
	return $totaltext->save_content($options['text'],array(
		'strip' => (array_key_exists('strip', $options) && $options['strip'] === 'true')
	));
}
function text_get($slug)
{
	$totaltext = new Text($slug);
	return $totaltext->get_contents();
}

//-------------------------------------------
// Video API
//-------------------------------------------
function video_post($options=array())
{
	$totalvideo = new Video($options['slug']);
	return $totalvideo->save_content($options['video']);
}

//-------------------------------------------
// Image API
//-------------------------------------------
function image_post($image,$options=array())
{
    $totalimage = new Image($options['slug'],$options);
    if (isset($options['thumbs']) && $options['thumbs'] === '1') {
    	$totalimage->add_thumb($totalimage->thumb($options));
    	$totalimage->add_thumb($totalimage->square($options));
    }
    return $totalimage->save_content($image);
}
function image_put($slug,$alt,$options)
{
	$totalimage = new Image($slug,$options);
    return $totalimage->update_alt($alt);
}
function image_get($slug,$options)
{
	$totalimage = new Image($slug,$options);
    return $totalimage->to_data();
}
function image_delete($slug,$options)
{
	$totalimage = new Image($slug,$options);
	$totalimage->add_thumb($totalimage->thumb($options));
	$totalimage->add_thumb($totalimage->square($options));
    return $totalimage->delete();
}

//-------------------------------------------
// Gallery API
//-------------------------------------------
function gallery_post($image,$options=array())
{
    $totalgallery = new Gallery($options['slug'],$options);
    return $totalgallery->save_content($image);
}
function gallery_get($slug,$options=array())
{
	$totalgallery = new Gallery($slug,$options);
	$index = isset($options['index']) ? $options['index'] : false;
	return $totalgallery->to_data($index);
}
function gallery_put($slug,$alt,$options)
{
	$totalgallery = new Gallery($slug,$options);
    return $totalgallery->update_alt($alt);
}
function gallery_delete($slug,$options)
{
	$totalgallery = new Gallery($slug,$options);
    return $totalgallery->delete();
}

//-------------------------------------------
// Feed API
//-------------------------------------------
function feed_post($image,$options=array())
{
    $totalfeed = new Feed($options['slug'],$options);
    return $totalfeed->save_content($options['feed'],array(
    	'strip' => ($options['strip'] === 'true'),
    	'image' => $image,
    	'alt'   => $options['alt'],
    ));

}
function feed_get($slug,$options=array())
{
	$totalfeed = new Feed($slug,$options);
	$timestamp = isset($options['timestamp']) ? $options['timestamp'] : false;
	return $totalfeed->to_data($timestamp);
}
function feed_delete($slug,$options)
{
	$totalfeed = new Feed($slug,$options);
    return $totalfeed->delete();
}
function feed_put($slug,$alt,$options)
{
	$totalfeed = new Feed($slug,$options);
    return $totalfeed->update_alt($alt);
}

//-------------------------------------------
// File API
//-------------------------------------------
function file_post($file,$options=array())
{
    $totalfile = new File($options['slug'],$options);
    return $totalfile->save_content($file);
}
function file_delete($slug,$options)
{
	$totalfile = new File($slug,$options);
    return $totalfile->delete();
}

//-------------------------------------------
// Depot API
//-------------------------------------------
function depot_post($file,$options=array())
{
    $totaldepot = new Depot($options['slug'],array(
    	'filename' => $file['name']
    ));
    return $totaldepot->save_content($file);
}
function depot_get($slug)
{
	$totaldepot = new Depot($slug);
	return $totaldepot->to_data();
}
function depot_delete($slug,$options)
{
	$totaldepot = new Depot($slug,$options);
    return $totaldepot->delete();
}
