<?php
require_once 'not-gettexted.php';
require_once 'pot-ext-meta.php';

if ( !defined( 'STDERR' ) ) {
	define( 'STDERR', fopen( 'php://stderr', 'w' ) );
}

class MakePOT {
	var $max_header_lines = 30;

	var $projects = array(
		'generic',
		'wp-core',
		'wp-ms',
		'wp-tz',
		'wp-plugin',
		'wp-theme',
		'bb',
		'mu',
		'bp',
	);

	var $keywords = array(
		'__', '_e', '_c',
		'__ngettext:1,2', '_n:1,2', '_nc:1,2',
		'__ngettext_noop:1,2', '_n_noop:1,2',
		'_x:1,2c', '_nx:1,2,4c', '_nx_noop:1,2,3c', '_ex:1,2c',
		'esc_attr__', 'esc_attr_e', 'esc_attr_x:1,2c',
		'esc_html__', 'esc_html_e', 'esc_html_x:1,2c',
	);
	
	var $ms_files = array('ms-*', '*/ms-*', '*/my-*', 'wp-activate.php', 'wp-signup.php', 'wp-admin/network.php');

	var $xgettext_options = array(
		'default' => array(
			'from-code' => 'utf-8',
			'msgid-bugs-address' => 'wp-polyglots@lists.automattic.com',
			'language' => 'php',
			'add-comments' => 'translators',
			'year' => '', // to be set in constructor
		),
		'generic' => array(),
		'wp-core' => array(
			'description' => 'Translation of WordPress {version}',
			'copyright-holder' => 'WordPress',
			'package-name' => 'WordPress',
			'package-version' => '{version}',
		),
		'wp-ms' => array(
			'description' => 'Translation of multisite strings in WordPress {version}',
			'copyright-holder' => 'WordPress',
			'package-name' => 'WordPress',
			'package-version' => '{version}',
		),		
		'wp-tz' => array(
			'description' => 'Translation of timezone strings in WordPress {version}',
			'copyright-holder' => 'WordPress',
			'package-name' => 'WordPress',
			'package-version' => '{version}',
		),		
		'bb' => array(
			'description' => 'Translation of bbPress',
			'copyright-holder' => 'bbPress',
			'package-name' => 'bbPress',
		),
		'wp-plugin' => array(
			'description' => 'Translation of the WordPress plugin {name} {version} by {author}',
			'msgid-bugs-address' => 'http://wordpress.org/tag/{slug}',
			'copyright-holder' => '{author}',
			'package-name' => '{name}',
			'package-version' => '{version}',
		),
		'wp-theme' => array(
			'description' => 'Translation of the WordPress theme {name} {version} by {author}',
			'msgid-bugs-address' => 'http://wordpress.org/tag/{slug}',
			'copyright-holder' => '{author}',
			'package-name' => '{name}',
			'package-version' => '{version}',
		),
		'bp' => array(
			'description' => 'Translation of BuddyPress',
			'copyright-holder' => 'BuddyPress',
			'package-name' => 'BuddyPress',
		),
	);
	
	var $manual_options = array(
		'copyright-holder' => "THE PACKAGE'S COPYRIGHT HOLDER",
		'package-name' => 'PACKAGE',
		'package-version' => 'VERSION',
		'year' => 'YEAR',
		'description' => 'SOME DESCRIPTIVE TITLE',
	);

	function MakePOT($deprecated = true) {
		$this->xgettext_options['default']['year'] = date('Y');
	}

	function realpath_missing($path) {
		return realpath(dirname($path)).DIRECTORY_SEPARATOR.basename($path);
	}

	function xgettext($project, $dir, $output_file, $placeholders = array(), $excludes = array(), $includes = array()) {

		$options = array_merge($this->xgettext_options['default'], $this->xgettext_options[$project]);
		$options['output'] = $this->realpath_missing($output_file);

		$placeholder_keys = array_map(create_function('$x', 'return "{".$x."}";'), array_keys($placeholders));
		$placeholder_values = array_values($placeholders);
		foreach($options as $key => $value) {
			$options[$key] = str_replace($placeholder_keys, $placeholder_values, $value);
		}
		$manual_options = array();
		foreach(array_keys($this->manual_options) as $key) {
			$manual_options[$key] = $options[$key];
			unset($options[$key]);
		}

		$long_options = array();
		foreach($this->keywords as $keyword)
			$long_options[] = "--keyword=$keyword";
		foreach($options as $key => $value)
			$long_options[] = is_string($value)? "--$key=$value" : "--$key";
		$long_options = array_map('escapeshellarg', $long_options);
		$xgettext_options_str = implode(" \\\n", $long_options);
		// change dirs, so that we have nice relative references 
		$old_dir = getcwd();
		chdir($dir);
		
		$excludes_str = implode("\n-and ", array_map(create_function('$x', 'return "! -path ".escapeshellarg("./".$x)." \\\\";'), $excludes));
		$includes_str = implode("\n-or ", array_map(create_function('$x', 'return "-path ".escapeshellarg("./".$x)." \\\\";'), $includes));
		if ($excludes_str) $excludes_str = "\n\t\t -and \\( " . $excludes_str . "\n\\) \\";
		if ($includes_str) $includes_str = "\n\t\t -and \\( " . $includes_str . "\n\\) \\";
		$cmd = "
	find . -name '*.php' \\$excludes_str$includes_str
	-print \\
	| sed -e 's,^\./,,' \\
	| sort \\
	| xargs xgettext \\
	$xgettext_options_str";
		system($cmd, $exit_code);
		chdir($old_dir);
		if (0 !== $exit_code) {
			error_log("xgettext exited with exit code $exit_code.");
			return false;
		}

		$old_first_lines = $first_lines = $this->get_first_lines( $options['output'], 30 );
		$first_lines = str_replace('CHARSET', 'utf-8', $first_lines);
		foreach($this->manual_options as $key => $pot_placeholder) {
			$first_lines = str_replace($pot_placeholder, $manual_options[$key], $first_lines);
		}
		$pot = file_get_contents( $options['output'] );
		$pot = str_replace($old_first_lines, $first_lines, $pot);
		if (!file_put_contents($options['output'], $pot)) {
			return false;
		}
		return true;
	}

	function wp_generic($dir, $args) {
		$defaults = array(
			'project' => 'wp-core',
			'output' => null,
			'default_output' => 'wordpress.pot',
			'includes' => array(),
			'excludes' => array_merge(
				array('wp-admin/includes/continents-cities.php', 'wp-content/themes/twentyten/*'),
				$this->ms_files
			),
			'extract_not_gettexted' => true,
			'not_gettexted_files_filter' => array( &$this, 'is_not_ms_file' ),
		);
		$args = array_merge( $defaults, $args );
		extract( $args );
		$placeholders = array();
		if ( $wp_version = $this->wp_version( $dir ) )
			$placeholders['version'] = $wp_version;
		else
			return false;
		$output = is_null( $output )? $default_output : $output;		
		$res = $this->xgettext( $project, $dir, $output, $placeholders, $excludes, $includes );
		if ( !$res ) return false;
		
		if ( $extract_not_gettexted ) {
			$old_dir = getcwd();
			$output = realpath( $output );
			chdir( $dir );
			$php_files = NotGettexted::list_php_files('.');
			$php_files = array_filter( $php_files, $not_gettexted_files_filter );
			$not_gettexted = & new NotGettexted;
			$res = $not_gettexted->command_extract( $output, $php_files );
			chdir( $old_dir );
			/* Adding non-gettexted strings can repeat some phrases */
			$output_shell = escapeshellarg( $output );
			system( "msguniq --use-first $output_shell -o $output_shell" );
		}
		return $res;
	}
	
	function wp_core($dir, $output) {
		return $this->wp_generic( $dir, array(
			'project' => 'wp-core', 'output' => $output,
		) );
	}
	
	function wp_ms($dir, $output) {
		if ( !is_file("$dir/wp-admin/ms-users.php") ) return false;
		$core_pot = tempnam( sys_get_temp_dir(), 'wordpress.pot');
		if ( false === $core_pot ) return false;
		$core_result = $this->wp_core( $dir, $core_pot );
		if ( !$core_result ) {
			unlink( $core_pot );
			return false;
		}
		$ms_result = $this->wp_generic( $dir, array(
			'project' => 'wp-ms', 'output' => $output,
			'includes' => $this->ms_files, 'excludes' => array(),
			'default_output' => 'wordpress-ms.pot',
			'extract_not_gettexted' => true, 'not_gettexted_files_filter' => array( &$this, 'is_ms_file' ),
		) );
		if ( !$ms_result ) {
			return false;
		}
		$common_pot = tempnam( sys_get_temp_dir(), 'common.pot' );
		if ( !$common_pot ) {
			unlink( $core_pot );
			return false;
		}
		$ms_pot = realpath( is_null( $output )? 'wordpress-ms.pot' : $output );
		system( "msgcat --more-than=1 --use-first $core_pot $ms_pot > $common_pot" );
		system( "msgcat -u --use-first $ms_pot $common_pot -o $ms_pot" );
		return true;
	}
	
	function wp_tz($dir, $output) {
		$continents_path = 'wp-admin/includes/continents-cities.php';
		if ( !file_exists( "$dir/$continents_path" ) ) return false;
		return $this->wp_generic( $dir, array(
			'project' => 'wp-tz', 'output' => $output,
			'includes' => array($continents_path), 'excludes' => array(),
			'default_output' => 'wordpress-continents-cities.pot',
			'extract_not_gettexted' => false,
		) );
	}
	
	function wp_version($dir) {
		$version_php = $dir.'/wp-includes/version.php';
		if ( !is_readable( $version_php ) ) return false;		
		return preg_match( '/\$wp_version\s*=\s*\'(.*?)\';/', file_get_contents( $version_php ), $matches )? $matches[1] : false;
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
		$output = is_null($output)? 'bbpress.pot' : $output;
		return $this->xgettext('bb', $dir, $output, $placeholders);

	}

	function get_first_lines($filename, $lines = 30) {
		$extf = fopen($filename, 'r');
		if (!$extf) return false;
		$first_lines = '';
		foreach(range(1, $lines) as $x) {
			$line = fgets($extf);
			if (feof($extf)) break;
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
		$placeholders['name'] = $this->get_addon_header('Plugin Name', $source);
		$placeholders['slug'] = $slug;

		$output = is_null($output)? "$slug.pot" : $output;
		$res = $this->xgettext('wp-plugin', $dir, $output, $placeholders);
		if (!$res) return false;
	    $potextmeta = new PotExtMeta;
	    $res = $potextmeta->append($main_file, $output);
		/* Adding non-gettexted strings can repeat some phrases */
		$output_shell = escapeshellarg($output);
		system("msguniq $output_shell -o $output_shell");
		return $res;
	}

	function wp_theme($dir, $output, $slug = null) {
		$placeholders = array();
		// guess plugin slug
		if (is_null($slug)) {
			$slug = $this->guess_plugin_slug($dir);
		}
		$main_file = $dir.'/style.css';
		$source = $this->get_first_lines($main_file, $this->max_header_lines);

		$placeholders['version'] = $this->get_addon_header('Version', $source);
		$placeholders['author'] = $this->get_addon_header('Author', $source);
		$placeholders['name'] = $this->get_addon_header('Theme Name', $source);
		$placeholders['slug'] = $slug;

		$output = is_null($output)? "$slug.pot" : $output;
		$res = $this->xgettext('wp-theme', $dir, $output, $placeholders);
		if (!$res) return false;
	    $potextmeta = new PotExtMeta;
	    $res = $potextmeta->append($main_file, $output);
		/* Adding non-gettexted strings can repeat some phrases */
		$output_shell = escapeshellarg($output);
		system("msguniq $output_shell -o $output_shell");
		return $res;
	}
	
	function bp($dir, $output) {
		$output = is_null($output)? "buddypress.pot" : $output;
		return $this->xgettext('bp', $dir, $output, array(), array('bp-forums/bbpress/*'));
	}

	function is_ms_file( $file_name ) {
		$is_ms_file = false;
		$prefix = substr( $file_name, 0, 2 ) === './'? './' : '';
		foreach( $this->ms_files as $ms_file )
			if ( fnmatch( $prefix.$ms_file, $file_name ) ) {
				$is_ms_file = true;
				break;
			}
		return $is_ms_file;
	}
	
	function is_not_ms_file( $file_name ) {
		return !$this->is_ms_file( $file_name );
	}
}

// run the CLI only if the file
// wasn't included
$included_files = get_included_files();
if ($included_files[0] == __FILE__) {
	$makepot = new MakePOT;
	if ((3 == count($argv) || 4 == count($argv)) && in_array($method = str_replace('-', '_', $argv[1]), get_class_methods($makepot))) {
		$res = call_user_func(array(&$makepot, $method), realpath($argv[2]), isset($argv[3])? $argv[3] : null);
		if (false === $res) {
			fwrite(STDERR, "Couldn't generate POT file!\n");
		}
	} else {
		$usage  = "Usage: php makepot.php PROJECT DIRECTORY [OUTPUT]\n\n";
		$usage .= "Generate POT file from the files in DIRECTORY [OUTPUT]\n";
		$usage .= "Avaialbale projects: ".implode(', ', $makepot->projects)."\n";
		fwrite(STDERR, $usage);
		exit(1);
	}
}
