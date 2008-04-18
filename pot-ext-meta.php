<?php
/**
 * Console application, which adds metadata strings from
 * a WordPress extension to a POT file
 *
 * @version $Id$
 * @package wordpress-i18n
 * @subpackage tools
 */

error_reporting(E_ALL);

require 'pomo/po.php';


define('HEADERS_MAX_LINES', 20);

function usage() {
	stderr("Usage: php pot-ext-meta.php EXT POT");
	stderr("Adds metadata from a WordPress theme or plugin file EXT to POT file");
}

function stderr($msg, $nl=true) {
	fwrite(STDERR, $msg.($nl? "\n" : ""));
}

function cli_die($msg) {
	stderr($msg);
	exit(1);
}

$headers = array(
	'Plugin Name',
	'Theme Name',
	'Plugin URI',
	'Theme URI',
	'Description',
	'Author',
	'Author URI',
	'Tags',
);

if (count($argv) < 2 || count($argv) > 3) {
	usage();
	exit(1);
}

$ext_filename = $argv[1];
$pot_filename = (isset($argv[2]))? $argv[2] : '-';

$extf = @fopen($ext_filename, 'r');
if (false === $extf) {
	cli_die("Couldn't open extension file: $ext_filename!");
}

$potf = '-' == $pot_filename? STDOUT : @fopen($pot_filename, 'a');
if (false === $potf) {
	cli_die("Couldn't open pot file: $pot_filename!");
}

$first_lines	= '';
foreach(range(1, HEADERS_MAX_LINES) as $x) {
	if (feof($extf)) break;
	$line = fgets($extf);
	if (false === $line) {
		cli_die("Error reading line $x from $ext_filename!");
	}
	$first_lines .= $line;
}
foreach($headers as $header) {
	if (preg_match('|'.$header.':(.*)$|mi', $first_lines, $matches)) {
		$string = trim($matches[1]);
		if (empty($string)) continue;
		$args = array(
			'singular' => $string,
			'extracted_comments' => $header.' of an extension',
		);
		$entry = new Translation_Entry($args);
		fwrite($potf, "\n".PO::export_entry($entry)."\n");
	}
}
fclose($extf);
if ('-' != $pot_filename) fclose($potf);

?>
