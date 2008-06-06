<?php
class MakePOT {
	var $use_advanced_xgettext_args = true;
	var $max_header_lines = 30;

	var $projects = array(
		'generic',
		'wp',
		'wp-plugin',
		'bb',
		'mu',
	);

	var $keywords = array(
		'__', '_e', '_c', '__ngettext:1,2', '__n:1,2',
		'__ngettext_noop:1,2', 
	);

	var $xgettext_options = array(
		'default' => array(
			'from-code' => 'utf-8',
			'msgid-bugs-address' => 'wp-polyglots@lists.automattic.com', 
			'language' => 'php',
		),
		'generic' => array(),
		'wp' => array(
			'copyright-holder' => 'WordPress',
			'package-name' => 'WordPress',
			'package-version' => '{version}',
		),
		'bb' => array(
			'copyright-holder' => 'bbPress',
			'package-name' => 'bbPress',
			'package-version' => '{version}',
		),

		'wp-plugin' => array(
			'msgid-bugs-address' => 'http://wordpress.org/tag/{slug}',
			'copyright-holder' => '{author}',
			'package-name' => '{plugin-name}',
			'package-version' => '{version}',
		),
	);

	function MakePOT($use_advanced_xgettext_args = true) {
		$this->use_advanced_xgettext_args = $use_advanced_xgettext_args;
	}

	function realpath_missing($path) {
		return realpath(dirname($path)).DIRECTORY_SEPARATOR.basename($path);
	}

	function xgettext($project, $dir, $output_file, $placeholders = array()) {

		$options = array_merge($this->xgettext_options['default'], $this->xgettext_options[$project]);
		$options['output'] = $this->realpath_missing($output_file);

		$placeholder_keys = array_map(create_function('$x', 'return "{".$x."}";'), array_keys($placeholders));
		$placeholder_values = array_values($placeholders);
		foreach($options as $key => $value)
			$options[$key] = str_replace($placeholder_keys, $placeholder_values, $value);

		if (!$this->use_advanced_xgettext_args) {
			unset($options['package-name']);
			unset($options['package-version']);
		}

		$long_options = array();
		foreach($this->keywords as $keyword)
			$long_options[] = "--keyword=$keyword";
		foreach($options as $key => $value)
			$long_options[] = is_string($value)? "--$key=$value" : "--$key";
		$long_options = array_map('escapeshellarg', $long_options);
		$string_options = implode(" \\\n", $long_options);
		// change dirs, so that we have nice relative references 
		$old_dir = getcwd();
		chdir($dir);
		$cmd = "
	find . -name '*.php' -print \\
	| sed -e 's,^\./,,' \\
	| sort \\
	| xargs xgettext \\
	$string_options";
		system($cmd, $exit_code);
		chdir($old_dir);
		return $exit_code;
	}

	function wp($dir, $output) {
		$placeholders = array();
		if (preg_match('/\$wp_version\s*=\s*\'(.*?)\';/', file_get_contents($dir.'/wp-includes/version.php'), $matches)) {
			$placeholders['version'] = $matches[1];
		}
		$output = is_null($output)? 'wordpress.pot' : $output;
		return $this->xgettext('wp', $dir, $output, $placeholders);
	}

	function mu($dir, $output) {
		$placeholders = array();
		if (preg_match('/\$wpmu_version\s*=\s*\'(.*?)\';/', file_get_contents($dir.'/wp-includes/version.php'), $matches)) {
			$placeholders['version'] = $matches[1];
		}
		$output = is_null($output)? 'wordpress.pot' : $output;
		return $this->xgettext('wp', $dir, $output, $placeholders);
	}


	function bb($dir, $output) {
		$placeholders = array();
		if (preg_match('/case\s+\'version\'.*?return\s+\'(.*?)\';/s', file_get_contents($dir.'/bb-includes/functions.php'), $matches)) {
			$placeholders['version'] = $matches[1];
		}
		$output = is_null($output)? 'bbpress.pot' : $output;
		return $this->xgettext('bb', $dir, $output, $placeholders);

	}

	function get_first_lines($filename, $lines = 30) {
		$extf = fopen($filename, 'r');
		if (!$extf) return false;
		$first_lines = '';
		foreach(range(1, $lines) as $x) {
			if (feof($extf)) break;
			$line = fgets($extf);
			if (false === $line) {
				return false;
			}
			$first_lines .= $line;
		}
		return $first_lines;
	}


	function get_addon_header($header, &$source) {
		if (preg_match('|'.$header.':(.*)$|mi', $source, $matches))
			return trim($matches[1]);
		else
			return false;
	}

	function generic($dir, $output) {
		$output = is_null($output)? "generic.pot" : $output;
		return $this->xgettext('generic', $dir, $output, array());
	} 

	function guess_plugin_slug($dir) {
		if ('trunk' == basename($dir)) {
			$slug = basename(dirname($dir));
		} elseif (in_array(basename(dirname($dir)), array('branches', 'tags'))) {
			$slug = basename(dirname(dirname($dir)));
		} else {
			$slug = basename($dir);
		}
		return $slug;
	}

	function wp_plugin($dir, $output, $slug = null) {
		$placeholders = array();
		// guess plugin slug
		if (is_null($slug)) {
			$slug = $this->guess_plugin_slug($dir);
		}
		$main_file = $dir.'/'.$slug.'.php';
		$source = $this->get_first_lines($main_file, $this->max_header_lines);

		$placeholders['version'] = $this->get_addon_header('Version', $source);
		$placeholders['author'] = $this->get_addon_header('Author', $source);
		$placeholders['plugin-name'] = $this->get_addon_header('Plugin Name', $source);
		$placeholders['slug'] = $slug;

		$output = is_null($output)? "$slug.pot" : $output;
		return $this->xgettext('wp-plugin', $dir, $output, $placeholders);
	}


}

// run the CLI only if the file
// wasn't included
$included_files = get_included_files();
if ($included_files[0] == __FILE__) {
	$makepot = new MakePOT;
	if ((3 == count($argv) || 4 == count($argv)) && in_array($method = str_replace('-', '_', $argv[1]), get_class_methods($makepot))) {
		$res = call_user_func(array(&$makepot, $method), realpath($argv[2]), isset($argv[3])? $argv[3] : null);
		if (0 != $res) {
			fwrite(STDERR, "xgettext returned exit code $res!\n");
		}
	} else {
		$usage  = "Usage: php makepot.php PROJECT DIRECTORY [OUTPUT]\n\n";
		$usage .= "Generate POT file from the files in DIRECTORY [OUTPUT]\n";
		$usage .= "Avaialbale projects: ".implode(', ', $makepot->projects)."\n";
		fwrite(STDERR, $usage);
		exit(1);
	}
}



?>
