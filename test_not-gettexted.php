<?php
/**
 * Tests for not-gettexted.php
 *
 * @version $Id$
 * @package wordpress-i18n
 * @subpackage tools
 */
error_reporting(E_ALL);
require_once('PHPUnit/Framework.php');
require_once('not-gettexted.php');

class Test_Not_Gettexted extends PHPUnit_Framework_TestCase {
	function test_make_string_aggregator() {
		global $baba;
		$f = make_string_aggregator('baba');
		call_user_func($f, 'x', 'y');
		$this->assertEquals(array(array('x', 'y')), $baba);
	}
	function test_make_string_replacer() {
		global $dict;
		$dict = array('a' => 'b');
		$f = make_string_replacer('dict');
		$this->assertEquals("'b'", $f(null, 'a'));
		$this->assertEquals("'c'", $f(null, 'c'));
	}
	function test_walk_catchall() {
		$code = '<?php $s = 8; /* WP_I18N_GUGU*/ 	"yes" /* /WP_I18N_UGU		*/?>';
		$tokens = token_get_all($code);	
		$this->assertEquals('', walk_tokens($tokens, 'ignore_token', 'ignore_token'));
		$this->assertEquals('"yes"', walk_tokens($tokens, 'unchanged_token', 'ignore_token'));
	}

	//TODO more tests

}
?>
