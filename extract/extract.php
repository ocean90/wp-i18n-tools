<?php
require_once dirname( __FILE__ ) . '/../pomo/entry.php';
require_once dirname( __FILE__ ) . '/../pomo/translations.php';

require_once dirname( __FILE__ ) . '/class-function-extractor-php.php';
require_once dirname( __FILE__ ) . '/class-function-extractor-js.php';

/**
 * Responsible for extracting translatable strings from PHP source files
 * in the form of Translations instances
 */
class StringExtractor {

	public $rules = array(
		'php' => array(
			'__' => array( 'string' ),
			'_e' => array( 'string' ),
			'_n' => array( 'singular', 'plural' ),
		),
	);

	public $comment_prefix = 'translators:';

	public $extractors = array(
		'php' => 'Function_Extractor_PHP',
		'js'  => 'Function_Extractor_JS',
	);

	public function __construct( $rules = array() ) {
		if ( $rules ) {
			$this->set_rules( $rules );
		}
	}

	/**
	 * Sets rules for function calls.
	 *
	 * Includes back-compat for the old rules format.
	 *
	 * @param array $rules
	 */
	protected function set_rules( $rules ) {
		$supported_files = array_keys( $this->extractors );
		$rule_keys = array_keys( $rules );
		if ( count( $supported_files ) < count( $rule_keys ) ) { // TODO: Use array_diff()?
			$this->rules['php'] = $rules;
		} else {
			$this->rules = $rules;
		}
	}

	function extract_from_directory( $dir, $excludes = array(), $includes = array(), $prefix = '' ) {
		$old_cwd = getcwd();
		chdir( $dir );
		$translations = new Translations();

		$file_names = (array) scandir( '.' );
		foreach ( $file_names as $file_name ) {
			if ( '.' == $file_name || '..' == $file_name ) {
				continue;
			}

			$supported_files = array_keys( $this->extractors );
			if (
				preg_match( '/\.(' . implode( '|', $supported_files ) . ')$/', $file_name, $match ) &&
				$this->does_file_name_match( $prefix . $file_name, $excludes, $includes )
			) {
				// Get the extractor.
				$extractor_class = $this->extractors[ $match[1] ];
				$extractor = new $extractor_class( array(
					'functions_to_extract' => array_keys( $this->rules[ $match[1] ] ),
					'comment_prefix'       => $this->comment_prefix,
				) );
				$extractor->load_source_from_file( $file_name );
				$extracted_functions = $extractor->find_function_calls();

				// Get originals and merge with existing.
				$originals = $this->get_originals( $extracted_functions, $prefix . $file_name );
				$translations->merge_originals_with( $originals );
			}

			if ( is_dir( $file_name ) ) {
				$extracted = $this->extract_from_directory( $file_name, $excludes, $includes, $prefix . $file_name . '/' );
				$translations->merge_originals_with( $extracted );
			}
		}

		chdir( $old_cwd );

		return $translations;
	}

	function extract_from_file( $file_name, $prefix ) {
		$code = file_get_contents( $file_name );
		return $this->extract_from_code( $code, $prefix . $file_name );
	}

	function does_file_name_match( $path, $excludes, $includes ) {
		if ( $includes ) {
			$matched_any_include = false;
			foreach( $includes as $include ) {
				if ( preg_match( '#^'.$include.'$#', $path ) ) {
					$matched_any_include = true;
					break;
				}
			}
			if ( !$matched_any_include ) return false;
		}
		if ( $excludes ) {
			foreach( $excludes as $exclude ) {
				if ( preg_match( '#^'.$exclude.'$#', $path ) ) {
					return false;
				}
			}
		}
		return true;
	}

	function entry_from_call( $call, $file_name ) {
		$file_ext = pathinfo( $file_name, PATHINFO_EXTENSION );
		if ( ! isset( $this->rules[ $file_ext ] ) ) {
			return null;
		}

		$rules = $this->rules[ $file_ext ];
		if ( ! isset( $rules[ $call['name'] ] ) ) {
			return null;
		}

		$rule = $rules[ $call['name'] ];

		$entry = new Translation_Entry;
		$multiple = array();
		$complete = false;
		for( $i = 0; $i < count( $rule ); ++$i ) {
			if ( $rule[$i] && ( !isset( $call['args'][$i] ) || !is_string( $call['args'][$i] ) || '' == $call['args'][$i] ) ) return false;
			switch( $rule[$i] ) {
			case 'string':
				if ( $complete ) {
					$multiple[] = $entry;
					$entry = new Translation_Entry;
					$complete = false;
				}
				$entry->singular = $call['args'][$i];
				$complete = true;
				break;
			case 'singular':
				if ( $complete ) {
					$multiple[] = $entry;
					$entry = new Translation_Entry;
					$complete = false;
				}
				$entry->singular = $call['args'][$i];
				$entry->is_plural = true;
				break;
			case 'plural':
				$entry->plural = $call['args'][$i];
				$entry->is_plural = true;
				$complete = true;
				break;
			case 'context':
				$entry->context = $call['args'][$i];
				foreach( $multiple as &$single_entry ) {
					$single_entry->context = $entry->context;
				}
				break;
			}
		}
		if ( isset( $call['line'] ) && $call['line'] ) {
			$references = array( $file_name . ':' . $call['line'] );
			$entry->references = $references;
			foreach( $multiple as &$single_entry ) {
				$single_entry->references = $references;
			}
		}
		if ( isset( $call['comment'] ) && $call['comment'] ) {
			$comments = rtrim( $call['comment'] ) . "\n";
			$entry->extracted_comments = $comments;
			foreach( $multiple as &$single_entry ) {
				$single_entry->extracted_comments = $comments;
			}
		}
		if ( $multiple && $entry ) {
			$multiple[] = $entry;
			return $multiple;
		}

		return $entry;
	}

	function extract_from_code( $code, $file_name ) {
		$file_ext = pathinfo( $file_name, PATHINFO_EXTENSION );

		if ( ! isset( $this->extractors[ $file_ext ] ) || ! $this->rules[ $file_ext ] ) {
			return null;
		}

		// Get the extractor.
		$extractor_class = $this->extractors[ $file_ext ];
		$extractor = new $extractor_class( array(
			'functions_to_extract' => array_keys( $this->rules[ $file_ext ] ),
			'comment_prefix'       => $this->comment_prefix,
		) );
		$extractor->set_source( $code );

		$extracted_functions = $extractor->find_function_calls();

		return $this->get_originals( $extracted_functions, $file_name );
	}

	/**
	 * Converts function arguments into originals.
	 *
	 * @param array  $function_calls Array of function calls.
	 * @param string $file_name      The file name of the function calls.
	 * @return Translations The originals.
	 */
	function get_originals( $function_calls, $file_name ) {
		$translations = new Translations();

		foreach ( $function_calls as $call ) {
			$entry = $this->entry_from_call( $call, $file_name );
			if ( is_array( $entry ) ) {
				foreach ( $entry as $single_entry ) {
					$translations->add_entry_or_merge( $single_entry );
				}
			} elseif ( $entry ) {
				$translations->add_entry_or_merge( $entry );
			}
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
		$latest_comment = false;
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
				$func_comment = $latest_comment? $latest_comment : '';

				$just_got_into_func = true;
				$latest_comment = false;
				continue;
			}
			if ( T_COMMENT == $id ) {
				$text = preg_replace( '%^\s+\*\s%m', '', $text );
				$text = str_replace( array( "\r\n", "\n" ), ' ', $text );;
				$text = trim( preg_replace( '%^(/\*|//)%', '', preg_replace( '%\*/$%', '', $text ) ) );
				if ( 0 === stripos( $text, $this->comment_prefix ) ) {
					$latest_comment = $text;
				}
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
