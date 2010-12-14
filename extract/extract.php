<?php
require_once dirname( __FILE__ ) . '/../pomo/entry.php';
require_once dirname( __FILE__ ) . '/../pomo/translations.php';

class StringExtractor {
	function __construct( $rules ) {
		
	}
	
	function extract( $code ) {
		$translations = new Translations;
		$translations->add_entry( array( 'singular' => 'baba' ) );
		return $translations;
	}
}

class ExtractTest extends PHPUnit_Framework_TestCase {
	
	function test_with_just_a_string() {
		$extractor = new StringExtractor( array( '__' => array( 'string' ) ) );
		$expected = new Translation_Entry( array( 'singular' => 'baba' ) );
		$result = $extractor->extract('<?php __("baba"); ?>');
		$this->assertEquals( $expected, $result->entries['baba'] );
	}
}