<?php
/**
 * Console application, which adds textdomain argument
 * to all i18n function calls
 *
 * @author 
 * @version $Id$
 * @package wordpress-i18n
 */
error_reporting(E_ALL);


$inplace = false;
$modified_contents = '';

function usage() {
	$usage = "Usage: php add-textdomain.php <domain> <file>\n\nAdds the string <domain> as a last argument to all i18n function calls in <file>\nand prints the modified php file on standard output.\n";
	fwrite(STDERR, $usage);
	exit(1);
}

function process_token($token_text) {
	global $inplace;
	global $modified_contents;

	if ($inplace)
		$modified_contents .= $token_text;
	else
		echo $token_text;
}


if (!isset($argv[1]) || !isset($argv[2])) {
	usage();
}

if ('-i' == $argv[1]) {
	$inplace = true;
	if (!isset($argv[3])) usage();
	array_shift($argv);	
}

$funcs = array('__', '_e', '_c', '__ngettext');
$domain = addslashes($argv[1]);
$source_filename = $argv[2];


$source = file_get_contents($source_filename);
$tokens = token_get_all($source);

$in_func = false;
$args_started = false;
$parens_balance = 0;
$found_domain = false;

foreach($tokens as $token) {
	$string_success = false;
	if (is_array($token)) {
		list($id, $text) = $token;
		if (T_STRING == $id && in_array($text, $funcs)) {
			$in_func = true;
			$parens_balance = 0;
			$args_started = false;
			$found_domain = false;
		} elseif (T_CONSTANT_ENCAPSED_STRING == $id && ("'$domain'" == $text || "\"$domain\"" == $text)) {
			if ($in_func && $args_started) {
				$found_domain = true;
			}
		}
		$token = $text;
	} elseif ('(' == $token){
		$args_started = true;
		++$parens_balance;
	} elseif (')' == $token) {
		--$parens_balance;
		if ($in_func && 0 == $parens_balance) {
			$token = $found_domain? ')' : ", '$domain')";
			$in_func = false;
			$args_started = false;
			$found_domain = false;
		}
	}
	process_token($token);
}

if ($inplace) {
	$f = fopen($source_filename, 'w');
	fwrite($f, $modified_contents);
	fclose($f);
}

?>
