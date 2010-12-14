<?php
require_once dirname( __FILE__ ) . '/../pomo/entry.php';
require_once dirname( __FILE__ ) . '/../pomo/translations.php';

class StringExtractor {
	function __construct() {
		
	}
	
	function extract( $code ) {
		$translations = new Translations;
		$translations->add_entry( array( 'singular' => 'baba' ) );
		return $translations;
	}
	
	/**
	 * Finds all function calls in $code and returns an array with an associative array for each function:
	 *	- name - name of the function
	 *	- args - array for the function arguments. Each string literal is represented by itself, other arguments are represented by null.
	 *  - line - line number
	 */
	function find_functions( $function_names, $code ) {
		$tokens = token_get_all( $code );
		$functions = array();
		$in_func = false;
		foreach( $tokens as $token ) {
			$id = $text = null;
			if ( is_array( $token ) ) list( $id, $text, $line ) = $token;
			echo "* ".token_name($id)." $text\n";
			if ( T_STRING == $id && in_array( $text, $function_names ) && !$in_func ) {
				$in_func = true;
				$paren_level = -1;
				$args = array();
				$func_name = $text;
				$func_line = $line;
				$just_got_into_func = true;
				continue;
			}
			if ( !$in_func ) continue;
			if ( '(' == $token ) {
				$paren_level++;
				if ( 0 == $paren_level ) { // start of first argument
					$just_got_into_func = false;
					$current_argument = null;
					$current_argument_is_just_literal = true;
				}
				continue;
			}
			if ( $just_got_into_func ) {
				// there wasn't a opening paren just after the function name -- this means it is not a function
				$in_func = false;
				$just_got_into_func = false;
			}
			if ( ')' == $token ) {
				if ( 0 == $paren_level ) {
					$in_func = false;
					$args[] = $current_argument;
					$functions[] = array( 'name' => $func_name, 'args' => $args, 'line' => $func_line );
				}
				$paren_level--;
				continue;
			}
			if ( ',' == $token && 0 == $paren_level ) {
				$args[] = $current_argument;
				$current_argument = null;
				$current_argument_is_just_literal = true;
				continue;
			}
			if ( T_CONSTANT_ENCAPSED_STRING == $id && $current_argument_is_just_literal ) {
				// we can use eval safely, because we are sure $text is just a string literal
				eval('$current_argument = '.$text.';' );
				continue;
			}
			$current_argument_is_just_literal = false;
			$current_argument = null;
		}
		return $functions;
	}
}

class ExtractTest extends PHPUnit_Framework_TestCase {
	
	function setUp() {
		$this->extractor = new StringExtractor;
	}
	
	function test_with_just_a_string() {
		$expected = new Translation_Entry( array( 'singular' => 'baba' ) );
		$result = $this->extractor->extract('<?php __("baba"); ?>');
		$this->assertEquals( $expected, $result->entries['baba'] );
	}
	
	function test_find_functions_one_arg_literal() {
		$this->assertEquals( array( array( 'name' => '__', 'args' => array( 'baba' ), 'line' => 1 ) ), $this->extractor->find_functions( array('__'), '<?php __("baba"); ?>' ) );
	}
	
	function test_find_functions_one_arg_non_literal() {
		$this->assertEquals( array( array( 'name' => '__', 'args' => array( null ), 'line' => 1 ) ), $this->extractor->find_functions( array('__'), '<?php __("baba" . "dudu"); ?>' ) );
	}
	
	function test_find_functions_shouldnt_be_mistaken_by_a_class() {
		$this->assertEquals( array(), $this->extractor->find_functions( array('__'), '<?php class __ { }; ("dyado");' ) );
	}
	
}