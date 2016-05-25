<?php
if (!ini_get('date.timezone')) date_default_timezone_set('Europe/London');
error_reporting(E_ALL);

require_once('vendor/autoload.php');
require_once('autoload.php');

use TotalCMS\Component\Alt;
use TotalCMS\Component\Blog;
use TotalCMS\Component\Component;
use TotalCMS\Component\Depot;
use TotalCMS\Component\Feed;
use TotalCMS\Component\File;
use TotalCMS\Component\Gallery;
use TotalCMS\Component\Image;
use TotalCMS\Component\Text;
use TotalCMS\Component\Toggle;
use TotalCMS\Component\Video;
use TotalCMS\ReplaceText;
