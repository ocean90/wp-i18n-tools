<?php
require_once dirname( __FILE__ ) . '/vendor/jTokenizer/jtokenizer.php';

/**
 * Responsible for extracting functions calls from PHP source files.
 */
class Function_Extractor_PHP {

	/**
	 * @var array
	 */
	private $functions_to_extract = array();

	/**
	 * @var string
	 */
	private $comment_prefix = '';

	/**
	 * @var string
	 */
	private $source = '';

	/**
	 * Function_Extractor_PHP constructor.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		if ( isset( $args['functions_to_extract'] ) ) {
			$this->functions_to_extract = $args['functions_to_extract'];
		}

		if ( isset( $args['comment_prefix'] ) ) {
			$this->comment_prefix = $args['comment_prefix'];
		}
	}

	/**
	 *
	 * @param string $file Path to a JavaScript file.
	 */
	public function load_source_from_file( $file ) {
		$this->source = file_get_contents( $file );
	}

	/**
	 * @param string $source
	 */
	public function set_source( $source ) {
		$this->source = $source;
	}

	/**
	 * Finds function calls.
	 *
	 * @return array|bool
	 */
	public function find_function_calls() {
		if ( ! $this->source || ! $this->functions_to_extract ) {
			return false;
		}

		$tokens = token_get_all( $this->source );

		$function_calls = array();
		$latest_comment = false;
		$in_func = false;
		$paren_level = -1;
		$just_got_into_func = false;
		$current_argument = null;
		$current_argument_is_just_literal = false;
		$func_name = $func_line = $func_comment = '';

		foreach ( $tokens as $token ) {
			$id = $text = $line = null;

			if ( is_array( $token ) ) {
				list( $id, $text, $line ) = $token;
			}

			if ( T_WHITESPACE === $id ) {
				continue;
			}

			if ( T_STRING === $id && in_array( $text, $this->functions_to_extract ) && ! $in_func ) {
				$in_func = true;
				$paren_level = -1;
				$args = array();
				$func_name = $text;
				$func_line = $line;
				$func_comment = $latest_comment ? $latest_comment : '';

				$just_got_into_func = true;
				$latest_comment = false;
				continue;
			}

			if ( $this->comment_prefix && T_COMMENT === $id ) {
				$text = preg_replace( '%^\s+\*\s%m', '', $text );
				$text = str_replace( array( "\r\n", "\n" ), ' ', $text );;
				$text = trim( preg_replace( '%^(/\*|//)%', '', preg_replace( '%\*/$%', '', $text ) ) );
				if ( 0 === stripos( $text, $this->comment_prefix ) ) {
					$latest_comment = $text;
				}
			}

			if ( ! $in_func ) {
				continue;
			}

			if ( '(' === $token ) {
				$paren_level++;
				if ( 0 === $paren_level ) { // Start of first argument.
					$just_got_into_func = false;
					$current_argument = null;
					$current_argument_is_just_literal = true;
				}
				continue;
			}

			if ( $just_got_into_func ) {
				// There wasn't a opening paren just after the function name -- this means it is not a function.
				$in_func = false;
				$just_got_into_func = false;
			}

			if ( ')' === $token ) {
				if ( 0 === $paren_level ) {
					$in_func = false;
					$args[] = $current_argument;
					$call = array( 'name' => $func_name, 'args' => $args, 'line' => $func_line );
					if ( $func_comment ) {
						$call['comment'] = $func_comment;
					}
					$function_calls[] = $call;
				}
				$paren_level--;
				continue;
			}

			if ( ',' === $token && 0 === $paren_level ) {
				$args[] = $current_argument;
				$current_argument = null;
				$current_argument_is_just_literal = true;
				continue;
			}

			if ( T_CONSTANT_ENCAPSED_STRING == $id && $current_argument_is_just_literal ) {
				// We can use eval safely, because we are sure $text is just a string literal.
				eval( '$current_argument = ' . $text . ';' );
				continue;
			}

			$current_argument_is_just_literal = false;
			$current_argument = null;
		}

		return $function_calls;
	}
}
