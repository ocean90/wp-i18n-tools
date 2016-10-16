<?php

/**
 * Responsible for extracting functions calls from source files.
 */
abstract class Function_Extractor {

	/**
	 * @var array
	 */
	protected $functions_to_extract = array();

	/**
	 * @var string
	 */
	protected $comment_prefix = '';

	/**
	 * @var string
	 */
	protected $source = '';

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
	abstract public function find_function_calls();
}
