<?php
require_once dirname( __FILE__ ) . '/../pomo/entry.php';
require_once dirname( __FILE__ ) . '/../pomo/translations.php';

class StringExtractor {
	
	var $rules = array();
	
	function __construct( $rules = array() ) {
		$this->rules = $rules;
		
	}
	
	function entry_from_call( $call, $file_name ) {
		$rule = isset( $this->rules[$call['name']] )? $this->rules[$call['name']] : null;
		if ( !$rule ) return null;
		$entry = new Translation_Entry;
		for( $i = 0; $i < count( $rule ); ++$i ) {
			if ( $rule[$i] && ( !isset( $call['args'][$i] ) || !$call['args'][$i] ) ) return false;
			switch( $rule[$i] ) {
			case 'string':
				$entry->singular = $call['args'][$i];
				break;
			case 'singular':
				$entry->singular = $call['args'][$i];
				$entry->is_plural = true;
				break;
			case 'plural':
				$entry->plural = $call['args'][$i];
				$entry->is_plural = true;
				break;
			case 'context':
				$entry->context = $call['args'][$i];
				break;
			}
		}
		$entry->references = array( $file_name . ':' . $call['line'] );
		return $entry;
	}
	
	function extract_entries( $code, $file_name ) {
		$translations = new Translations;
		$function_calls = $this->find_function_calls( array_keys( $this->rules ), $code );
		foreach( $function_calls as $call ) {
			$entry = $this->entry_from_call( $call, $file_name );
			if ( $entry ) $translations->add_entry( array( 'singular' => 'baba' ) );
		}
		return $translations;
	}
	
	/**
	 * Finds all function calls in $code and returns an array with an associative array for each function:
	 *	- name - name of the function
	 *	- args - array for the function arguments. Each string literal is represented by itself, other arguments are represented by null.
	 *  - line - line number
	 */
	function find_function_calls( $function_names, $code ) {
		$tokens = token_get_all( $code );
		$function_calls = array();
		$in_func = false;
		foreach( $tokens as $token ) {
			$id = $text = null;
			if ( is_array( $token ) ) list( $id, $text, $line ) = $token;
			if ( T_WHITESPACE == $id ) continue;
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
					$function_calls[] = array( 'name' => $func_name, 'args' => $args, 'line' => $func_line );
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
		return $function_calls;
	}
}

class ExtractTest extends PHPUnit_Framework_TestCase {
	
	function setUp() {
		$this->extractor = new StringExtractor;
		$this->extractor->rules = array(
			'__' => array('string'),
		);
	}
	
	function test_with_just_a_string() {
		$expected = new Translation_Entry( array( 'singular' => 'baba' ) );
		$result = $this->extractor->extract_entries('<?php __("baba"); ?>', 'baba.php' );
		$this->assertEquals( $expected, $result->entries['baba'] );
	}
		
	function test_entry_from_call_simple() {
		$entry = $this->extractor->entry_from_call( array( 'name' => '__', 'args' => array('baba'), 'line' => 1 ), 'baba.php' );
		$this->assertEquals( $entry, new Translation_Entry( array( 'singular' => 'baba', 'references' => array('baba.php:1' ) ) ) );
	}
	
	function test_entry_from_call_nonexisting_function() {
		$entry = $this->extractor->entry_from_call( array( 'name' => 'f', 'args' => array('baba'), 'line' => 1 ), 'baba.php' );
		$this->assertEquals( $entry, null );
	}
	
	function test_entry_from_call_too_few_args() {
		$entry = $this->extractor->entry_from_call( array( 'name' => '__', 'args' => array(), 'line' => 1 ), 'baba.php' );
		$this->assertEquals( $entry, null );
	}	

	function test_entry_from_call_non_expected_null_arg() {
		$this->extractor->rules = array( '_nx' => array( 'singular', 'plural', 'context' ) );
		$entry = $this->extractor->entry_from_call( array( 'name' => '_nx', 'args' => array('%s baba', null, 'noun'), 'line' => 1 ), 'baba.php' );
		$this->assertEquals( $entry, null );
	}
	
	function test_entry_from_call_more_args_should_be_ok() {
		$this->extractor->rules = array( '__' => array('string') );
		$entry = $this->extractor->entry_from_call( array( 'name' => '__', 'args' => array('baba', 5, 'pijo', null), 'line' => 1 ), 'baba.php' );
		$this->assertEquals( $entry, new Translation_Entry( array( 'singular' => 'baba', 'references' => array('baba.php:1' ) ) ) );
	}
	

	function test_entry_from_call_context() {
		$this->extractor->rules = array( '_x' => array( 'string', 'context' ) );
		$entry = $this->extractor->entry_from_call( array( 'name' => '_x', 'args' => array('baba', 'noun'), 'line' => 1 ), 'baba.php' );
		$this->assertEquals( $entry, new Translation_Entry( array( 'singular' => 'baba', 'references' => array('baba.php:1' ), 'context' => 'noun' ) ) );
	}

	function test_entry_from_call_plural() {
		$this->extractor->rules = array( '_n' => array( 'singular', 'plural' ) );
		$entry = $this->extractor->entry_from_call( array( 'name' => '_n', 'args' => array('%s baba', '%s babas'), 'line' => 1 ), 'baba.php' );
		$this->assertEquals( $entry, new Translation_Entry( array( 'singular' => '%s baba', 'plural' => '%s babas', 'references' => array('baba.php:1' ), ) ) );
	}

	function test_entry_from_call_plural_and_context() {
		$this->extractor->rules = array( '_nx' => array( 'singular', 'plural', 'context' ) );
		$entry = $this->extractor->entry_from_call( array( 'name' => '_nx', 'args' => array('%s baba', '%s babas', 'noun'), 'line' => 1 ), 'baba.php' );
		$this->assertEquals( $entry, new Translation_Entry( array( 'singular' => '%s baba', 'plural' => '%s babas', 'references' => array('baba.php:1' ), 'context' => 'noun' ) ) );
	}
	
	function test_find_function_calls_one_arg_literal() {
		$this->assertEquals( array( array( 'name' => '__', 'args' => array( 'baba' ), 'line' => 1 ) ), $this->extractor->find_function_calls( array('__'), '<?php __("baba"); ?>' ) );
	}
	
	function test_find_function_calls_one_arg_non_literal() {
		$this->assertEquals( array( array( 'name' => '__', 'args' => array( null ), 'line' => 1 ) ), $this->extractor->find_function_calls( array('__'), '<?php __("baba" . "dudu"); ?>' ) );
	}
	
	function test_find_function_calls_shouldnt_be_mistaken_by_a_class() {
		$this->assertEquals( array(), $this->extractor->find_function_calls( array('__'), '<?php class __ { }; ("dyado");' ) );
	}
	
	function test_find_function_calls_2_args_bad_literal() {
		$this->assertEquals( array( array( 'name' => 'f', 'args' => array( null, "baba" ), 'line' => 1 ) ), $this->extractor->find_function_calls( array('f'), '<?php f(5, "baba" ); ' ) );
	}
	
	function test_find_function_calls_2_args_bad_literal_bad() {
		$this->assertEquals( array( array( 'name' => 'f', 'args' => array( null, "baba", null ), 'line' => 1 ) ), $this->extractor->find_function_calls( array('f'), '<?php f(5, "baba", 5 ); ' ) );
	}
	
	function test_find_function_calls_1_arg_bad_concat() {
		$this->assertEquals( array( array( 'name' => 'f', 'args' => array( null ), 'line' => 1 ) ), $this->extractor->find_function_calls( array('f'), '<?php f( "baba" . "baba" ); ' ) );
	}
	
	function test_find_function_calls_1_arg_bad_function_call() {
		$this->assertEquals( array( array( 'name' => 'f', 'args' => array( null ), 'line' => 1 ) ), $this->extractor->find_function_calls( array('f'), '<?php f( g( "baba" ) ); ' ) );
	}
	
	function test_find_function_calls_2_arg_literal_bad() {
		$this->assertEquals( array( array( 'name' => 'f', 'args' => array( "baba", null ), 'line' => 1 ) ), $this->extractor->find_function_calls( array('f'), '<?php f( "baba", null ); ' ) );
	}
	
	function test_find_function_calls_2_arg_bad_with_parens_literal() {
		$this->assertEquals( array( array( 'name' => 'f', 'args' => array( null, "baba" ), 'line' => 1 ) ), $this->extractor->find_function_calls( array('f'), '<?php f( g( "dyado", "chicho", "lelya "), "baba" ); ' ) );
	}

}