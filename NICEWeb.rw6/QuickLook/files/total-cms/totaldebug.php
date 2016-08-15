<!-- TotalCMS Debug info -->
<h3>TotalCMS Debug Info</h3>
<div style="color:red">
<?php

	// TotalCMS Image support check
	if (!extension_loaded('gd')) {
		echo "<p>You do not have the PHP gd extension enabled</p>";
	}

	// TotalCMS curl support check
	if (!extension_loaded('curl')) {
		echo "<p>curl extension is not enabled on this server.</p>";
    }

	// TotalCMS directory checks
	// Assuming the this is deployed at /rw_common/plugins/stacks/total-cms
	$site_root = preg_replace('/(.*)\/rw_common.+/','$1',__DIR__);
	$cms_dir = "$site_root/cms-data";
	if (!file_exists($cms_dir)) {
		if (!mkdir($cms_dir, 0775, true)) echo "<p>Failed to create TotalCMS directory: <site-root>/total-cms</p>";
	}
	else {
		if (!is_writable($cms_dir)){
			chmod($cms_dir,0775);
			if (!is_writable($cms_dir)) echo "<p>The CMS directory is not writable. Please fix the permissions on the directory: $cms_dir</p>";
		}
	}

	// TotalCMS lib dir
	$asset_dir = __DIR__;
	if (!is_writable($asset_dir)) {
		chmod($cms_dir,0775);
		if (!is_writable($asset_dir)) echo "<p>The TotalCMS lib directory is not writable. Please fix the permissions on the directory: $asset_dir</p>";
	}
?>
</div>

<?php
	echo '<p>PHP version: '. phpversion() .'</p>';
	echo '<p>HTTP_HOST: '. $_SERVER['HTTP_HOST'] .'</p>';
	echo '<p>SERVER_NAME: '. $_SERVER['SERVER_NAME'] .'</p>';

	$domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
	$ch = curl_init();
	curl_setopt($ch,CURLOPT_URL,'https://passport.joeworkman.net/total-cms/'.$domain);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION,true);
	curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,5);
	$results = curl_exec($ch);
	echo "<p>Passport Check: $results</p>";
?>

<pre><?php phpinfo(); ?></pre>
