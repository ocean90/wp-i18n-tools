<?php
$projects = array(
	'wp',
	'wp-plugin',
);

$keywords = array(
	'__', '_e', '_c', '__ngettext:1,2',
	'__ngettext_noop:1,2', 
);

$xgettext_options = array(
	'default' => array(
		'from-code' => 'utf-8',
		'msgid-bugs-address' => 'wp-polyglots@lists.automattic.com', 
		'language' => 'php',
	),
	'wp' => array(
		'copyright-holder' => 'WordPress',
		'package-name' => 'WordPress',
	),
	'wp-plugin' => array(
		'msgid-bugs-address' => 'http://wordpress.org/tag/{slug}',
		'copyright-holder' => '{author}',
		'package-name' => '{plugin-name}',
		'package-version' => '{version}',
	),
);

function realpath_missing($path) {
	return realpath(dirname($path)).DIRECTORY_SEPARATOR.basename($path);
}

function xgettext($project, $dir, $output_file, $placeholders = array()) {
	global $keywords;
	global $xgettext_options;

	$options = array_merge($xgettext_options['default'], $xgettext_options[$project]);
	$options['output'] = realpath_missing($output_file);

	$placeholder_keys = array_map(create_function('$x', 'return "{".$x."}";'), array_keys($placeholders));
	$placeholder_values = array_values($placeholders);
	foreach($options as $key => $value)
		$options[$key] = str_replace($placeholder_keys, $placeholder_values, $value);


	$long_options = array();
	foreach($keywords as $keyword)
		$long_options[] = "--keyword=$keyword";
	foreach($options as $key => $value)
		$long_options[] = is_string($value)? "--$key=$value" : "--$key";
	$long_options = array_map('escapeshellarg', $long_options);
	$string_options = implode(" \\\n", $long_options);
	// change dirs, so that we have nice relative references 
	chdir($dir);
	$cmd = "
find . -name '*.php' -print \\
| sed -e 's,^\./,,' \\
| sort \\
| xargs xgettext \\
$string_options";
	system($cmd, $exit_code);
	return $exit_code;
}

function wp($dir, $output) {
	$placeholders = array();
	if (preg_match('/\$wp_version\s*=\s*\'(.*?)\';/', file_get_contents($dir.'/wp-includes/version.php'), $matches)) {
		$placeholders['version'] = $matches[1];
	}
	$output = is_null($output)? 'wordpress.pot' : $output;
	return xgettext('wp', $dir, $output, $placeholders);
}

function get_addon_header($header, &$source) {
	preg_match('|'.$header.':(.*)$|mi', $source, $matches);
	return trim($matches[1]);
}

function wp_plugin($dir, $output, $slug = null) {
	$placeholders = array();
	// guess plugin slug
	if (is_null($slug)) {
		if ('trunk' == basename($dir)) {
			$slug = basename(dirname($dir));
		} elseif (in_array(basename(dirname($dir)), array('branches', 'tags'))) {
			$slug = basename(dirname(dirname($dir)));
		} else {
			$slug = basename($dir);
		}
	}
	$main_file = $dir.'/'.$slug.'.php';
	$source = file_get_contents($main_file);

	$placeholders['version'] = get_addon_header('Version', $source);
	$placeholders['author'] = get_addon_header('Author', $source);
	$placeholders['plugin-name'] = get_addon_header('Plugin Name', $source);
	$placeholders['slug'] = $slug;

	$output = is_null($output)? "$slug.pot" : $output;
	return xgettext('wp-plugin', $dir, $output, $placeholders);
}

// run the CLI only if the file
// wasn't included
$included_files = get_included_files();
if ($included_files[0] == __FILE__) {
	if (3 == count($argv)) {
		call_user_func(str_replace('-', '_', $argv[1]), realpath($argv[2]), isset($argv[3])? $argv[3] : null);
	} else {
		$usage  = "Usage: php makepot.php PROJECT DIRECTORY [OUTPUT]\n\n";
		$usage .= "Generate POT file from the files in DIRECTORY [OUTPUT]\n";
		$usage .= "Avaialbale projects: ".implode(', ', $projects)."\n";
		fwrite(STDERR, $usage);
		exit(1);
	}
}



?>
