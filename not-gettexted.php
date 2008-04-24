<?php
/**
 * Console application, which extracts or replaces strings for
 * translation, which cannot be gettexted
 *
 * @version $Id$
 * @package wordpress-i18n
 * @subpackage tools
 */
error_reporting(E_ALL);

require_once 'pomo/po.php';
require_once 'pomo/mo.php';

define('LOGGING', false);

define('STAGE_OUTSIDE', 0);
define('STAGE_START_COMMENT', 1);
define('STAGE_WHITESPACE_BEFORE', 2);
define('STAGE_STRING', 3);
define('STAGE_WHITESPACE_AFTER', 4);
define('STAGE_END_COMMENT', 4);

$commands = array('extract' => 'command_extract', 'replace' => 'command_replace' );

// see: http://php.net/tokenizer
if (!defined('T_ML_COMMENT'))
	    define('T_ML_COMMENT', T_COMMENT);
else
	    define('T_DOC_COMMENT', T_ML_COMMENT);

function logmsg() {
	$args = func_get_args();
	if (LOGGING) error_log(implode(' ', $args));
}

function stderr($msg, $nl=true) {
	fwrite(STDERR, $msg.($nl? "\n" : ""));
}

function cli_die($msg) {
	stderr($msg);
	exit(1);
}

function unchanged_token($token, $s='') {
	return is_array($token)? $token[1] : $token;
}

function ignore_token($token, $s='') {
	return '';
}

function make_string_aggregator($global_array_name) {
	$a = $global_array_name;
	$GLOBALS[$a] = array();
	return create_function('$string, $comment_id, $line_number', 'global $'.$a.'; $'.$a.'[] = array($string, $comment_id, $line_number);');
}

function make_mo_replacer($global_mo_name) {
	$m = $global_mo_name;
	return create_function('$token, $string', 'global $'.$m.'; return var_export($'.$m.'->translate($string), true);');
}

function walk_tokens(&$tokens, $string_action, $other_action, $register_action=null) {

	$current_comment_id = '';
	$current_string = '';
	$current_string_line = 0;

	$result = '';

	foreach($tokens as $token) {
		if (is_array($token)) {
			list($id, $text, $line) = $token;
			if (T_ML_COMMENT == $id && preg_match('|/\*\s*(/?WP_I18N_[a-z_]+)\s*\*/|i', $text, $matches)) {
				if (STAGE_OUTSIDE == $stage) {
					$stage = STAGE_START_COMMENT;
					$current_comment_id = $matches[1];
					logmsg('start comment', $current_comment_id);
					$result .= call_user_func($other_action, $token);
					continue;
				}
				if (STAGE_START_COMMENT <= $stage && $stage <= STAGE_WHITESPACE_AFTER && '/'.$current_comment_id == $matches[1]) {
					$stage = STAGE_END_COMMENT; 
					logmsg('end comment', $current_comment_id);
					$result .= call_user_func($other_action, $token);
					if (!is_null($register_action)) call_user_func($register_action, $current_string, $current_comment_id, $current_string_line);
					continue;
				}
			} else if (T_CONSTANT_ENCAPSED_STRING == $id) {
				if (STAGE_START_COMMENT <= $stage && $stage < STAGE_WHITESPACE_AFTER) {
					eval('$current_string='.$text.';');
					logmsg('string', $current_string);
					$current_string_line = $line;
					$result .= call_user_func($string_action, $token, $current_string);
					continue;
				}
			} else if (T_WHITESPACE == $id) {
				if (STAGE_START_COMMENT <= $stage && $stage < STAGE_STRING) {
					$stage = STAGE_WHITESPACE_BEFORE;
					logmsg('whitespace before');
					$result .= call_user_func($other_action, $token);
					continue;
				}
				if (STAGE_STRING < $stage && $stage < STAGE_END_COMMENT) {
					$stage = STAGE_WHITESPACE_AFTER;
					logmsg('whitespace after');
					$result .= call_user_func($other_action, $token);
					continue;
				}
			}
		}
		$result .= call_user_func($other_action, $token);
		$stage = STAGE_OUTSIDE;
		$current_comment_id = '';
		$current_string = '';
		$current_string_line = 0;
	}
	return $result;
}


function command_extract() {
	$args = func_get_args();
	$pot_filename = $args[0];
	$filenames = array_slice($args, 1);

	$global_name = '__entries_'.mt_rand(1, 1000);
	$GLOBALS[$global_name] = array();
	$aggregator = make_string_aggregator($global_name);

	foreach($filenames as $filename) {
		$tokens = token_get_all(file_get_contents($filename));
		walk_tokens(&$tokens, 'ignore_token', 'ignore_token', $aggregator);
	}

	$potf = '-' == $pot_filename? STDOUT : @fopen($pot_filename, 'a');
	if (false === $potf) {
		cli_die("Couldn't open pot file: $pot_filename");
	}

	foreach($GLOBALS[$global_name] as $item) {
		@list($string, $comment_id, $line_number) = $item;
		$ref_line_number = isset($line_number)? ":$line_number" : '';
		$args = array(
			'singular' => $string,
			'extracted_comments' => "Not gettexted string $comment_id",
			'references' => array("$filename$ref_line_number"),
		);
		$entry = new Translation_Entry($args);
		fwrite($potf, "\n".PO::export_entry($entry)."\n");
	}
	if ('-' != $pot_filename) fclose($potf);
}

function command_replace() {
	$args = func_get_args();
	$mo_filename = $args[0];
	$filenames = array_slice($args, 1);

	$global_name = '__mo_'.mt_rand(1, 1000);
	$GLOBALS[$global_name] = new MO();
	$replacer = make_mo_replacer($global_name);

	$res = $GLOBALS[$global_name]->import_from_file($mo_filename);
	if (false === $res) {
		cli_die("Couldn't read MO file '$mo_filename'!");
	}
	foreach($filenames as $filename) {
		$tokens = token_get_all(file_get_contents($filename));
		$new_file = walk_tokens(&$tokens, $replacer, 'unchanged_token');
		$f = fopen($filename, 'w');
		fwrite($f, $new_file);
		fclose($f);
	}
}

function usage() {
	stderr('php i18n-comments.php COMMAND OUTPUTFILE INPUTFILES');
	stderr('Extracts and replaces strings, which cannot be gettexted');
	stderr('Commands:');
	stderr('	extract POTFILE PHPFILES appends the strings to POTFILE');
	stderr('	replace MOFILE PHPFILES replaces strings in PHPFILES with translations from MOFILE');
}

function cli() {
	global $argv, $commands;
	if (count($argv) < 4 || !in_array($argv[1], array_keys($commands))) {
		usage();
		exit(1);
	}
	call_user_func_array($commands[$argv[1]], array_slice($argv, 2));
}

// run the CLI only if the file
// wasn't included
$included_files = get_included_files();
if ($included_files[0] == __FILE__) {
	cli();
}

?>
