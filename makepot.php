<?php
require_once 'not-gettexted.php';
require_once 'pot-ext-meta.php';
require_once 'extract/extract.php';

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

	var $rules = array(
		'_' => array('string'),
		'__' => array('string'),
		'_e' => array('string'),
		'_c' => array('string'),
		'_n' => array('singular', 'plural'),
		'_n_noop' => array('singular', 'plural'),
		'_nc' => array('singular', 'plural'),
		'__ngettext' => array('singular', 'plural'),
		'__ngettext_noop' => array('singular', 'plural'),
		'_x' => array('string', 'context'),
		'_ex' => array('string', 'context'),
		'_nx' => array('singular', 'plural', null, 'context'),
		'_nx_noop' => array('singular', 'plural', 'context'),
		'esc_attr__' => array('string'),
		'esc_html__' => array('string'),
		'esc_attr_e' => array('string'),
		'esc_html_e' => array('string'),
		'esc_attr_x' => array('string', 'context'),
		'esc_html_x' => array('string', 'context'),
		'comments_number_link' => array('string', 'singular', 'plural'),
	);

	var $ms_files = array( 'ms-.*', '.*/ms-.*', '.*/my-.*', 'wp-activate\.php', 'wp-signup\.php', 'wp-admin/network\.php', 'wp-admin/includes/ms\.php', 'wp-admin/network/.*\.php', 'wp-admin/includes/class-wp-ms.*' );

	var $meta = array(
		'default' => array(
			'from-code' => 'utf-8',
			'msgid-bugs-address' => 'wp-polyglots@lists.automattic.com',
			'language' => 'php',
			'add-comments' => 'translators',
			'comments' => "Copyright (C) 2010 {package-name}\nThis file is distributed under the same license as the {package-name} package.",
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
	
	function __construct($deprecated = true) {
		$this->extractor = new StringExtractor( $this->rules );
	}

	function realpath_missing($path) {
		return realpath(dirname($path)).DIRECTORY_SEPARATOR.basename($path);
	}

	function xgettext($project, $dir, $output_file, $placeholders = array(), $excludes = array(), $includes = array()) {
		$meta = array_merge( $this->meta['default'], $this->meta[$project] );
		$placeholders = array_merge( $meta, $placeholders );
		$meta['output'] = $this->realpath_missing( $output_file );
		$placeholder_keys = array_map( create_function( '$x', 'return "{".$x."}";' ), array_keys( $placeholders ) );
		$placeholder_values = array_values( $placeholders );
		foreach($meta as $key => $value) {
			$meta[$key] = str_replace($placeholder_keys, $placeholder_values, $value);
		}

		$originals = $this->extractor->extract_from_directory( $dir, $excludes, $includes );
		$pot = new PO;
		$pot->entries = $originals->entries;

		$pot->set_header( 'Project-Id-Version', $meta['package-name'].' '.$meta['package-version'] );
		$pot->set_header( 'Report-Msgid-Bugs-To', $meta['msgid-bugs-address'] );
		$pot->set_header( 'POT-Creation-Date', gmdate( 'Y-m-d H:i:s+00:00' ) );
		$pot->set_header( 'MIME-Version', '1.0' );
		$pot->set_header( 'Content-Type', 'text/plain; charset=UTF-8' );
		$pot->set_header( 'Content-Transfer-Encoding', '8bit' );
		$pot->set_header( 'PO-Revision-Date', '2010-MO-DA HO:MI+ZONE' );
		$pot->set_header( 'Last-Translator', 'FULL NAME <EMAIL@ADDRESS>' );
		$pot->set_header( 'Language-Team', 'LANGUAGE <LL@li.org>' );
		$pot->set_comment_before_headers( $meta['comments'] );
		$pot->export_to_file( $output_file );
		return true;
	}

	function wp_generic($dir, $args) {
		$defaults = array(
			'project' => 'wp-core',
			'output' => null,
			'default_output' => 'wordpress.pot',
			'includes' => array(),
			'excludes' => array_merge(
				array('wp-admin/includes/continents-cities\.php', 'wp-content/themes/twentyten/.*', 'wp-content/themes/twentyeleven/.*', ),
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
			$not_gettexted = new NotGettexted;
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
		return $this->xgettext('bp', $dir, $output, array(), array('bp-forums/bbpress/.*'));
	}

	function is_ms_file( $file_name ) {
		$is_ms_file = false;
		$prefix = substr( $file_name, 0, 2 ) === './'? '\./' : '';
		foreach( $this->ms_files as $ms_file )
			if ( preg_match( '|^'.$prefix.$ms_file.'$|', $file_name ) ) {
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
