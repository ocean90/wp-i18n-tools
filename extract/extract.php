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
		if ( isset( $call['line'] ) && $call['line'] ) $entry->references = array( $file_name . ':' . $call['line'] );
		if ( isset( $call['comment'] ) && $call['comment'] ) $entry->extracted_comments = $call['comment'];
		return $entry;
	}
	
	function extract_entries( $code, $file_name ) {
		$translations = new Translations;
		$function_calls = $this->find_function_calls( array_keys( $this->rules ), $code );
		foreach( $function_calls as $call ) {
			$entry = $this->entry_from_call( $call, $file_name );
			if ( $entry ) $translations->add_entry( $entry );
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
		$previous_is_comment = false;
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
				$func_comment = $previous_is_comment? trim( $previous_is_comment ) : '';
				$func_comment = trim( preg_replace( '/^\/\*|\/\//', '', preg_replace( '|\*/$|', '', $func_comment ) ) );
				$just_got_into_func = true;
				continue;
			}
			$previous_is_comment = ( T_COMMENT == $id )? $text : false;
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
					$call = array( 'name' => $func_name, 'args' => $args, 'line' => $func_line );
					if ( $func_comment ) $call['comment'] = $func_comment;
					$function_calls[] = $call;
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