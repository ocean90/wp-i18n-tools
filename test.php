<?php

require_once dirname( __FILE__ ) . '/not-gettexted.php';
require_once dirname( __FILE__ ) . '/pot-ext-meta.php';
require_once dirname( __FILE__ ) . '/extract/extract.php';

$rules = array(
	'_'                    => array( 'string' ),
	'__'                   => array( 'string' ),
	'_e'                   => array( 'string' ),
	'_c'                   => array( 'string' ),
	'_n'                   => array( 'singular', 'plural' ),
	'_n_noop'              => array( 'singular', 'plural' ),
	'_nc'                  => array( 'singular', 'plural' ),
	'__ngettext'           => array( 'singular', 'plural' ),
	'__ngettext_noop'      => array( 'singular', 'plural' ),
	'_x'                   => array( 'string', 'context' ),
	'_ex'                  => array( 'string', 'context' ),
	'_nx'                  => array( 'singular', 'plural', null, 'context' ),
	'_nx_noop'             => array( 'singular', 'plural', 'context' ),
	'_n_js'                => array( 'singular', 'plural' ),
	'_nx_js'               => array( 'singular', 'plural', 'context' ),
	'esc_attr__'           => array( 'string' ),
	'esc_html__'           => array( 'string' ),
	'esc_attr_e'           => array( 'string' ),
	'esc_html_e'           => array( 'string' ),
	'esc_attr_x'           => array( 'string', 'context' ),
	'esc_html_x'           => array( 'string', 'context' ),
	'comments_number_link' => array( 'string', 'singular', 'plural' ),
);

$extractor = new StringExtractor( $rules );

$originals = $extractor->extract_from_directory( dirname( __FILE__ ) . '/extract/test/data' );

$pot = new PO();
$pot->entries = $originals->entries;

$pot->set_header( 'POT-Creation-Date', gmdate( 'Y-m-d H:i:s+00:00' ) );
$pot->set_header( 'MIME-Version', '1.0' );
$pot->set_header( 'Content-Type', 'text/plain; charset=UTF-8' );
$pot->set_header( 'Content-Transfer-Encoding', '8bit' );
$pot->set_header( 'PO-Revision-Date', date( 'Y' ) . '-MO-DA HO:MI+ZONE' );
$pot->set_header( 'Last-Translator', 'FULL NAME <EMAIL@ADDRESS>' );
$pot->set_header( 'Language-Team', 'LANGUAGE <LL@li.org>' );
$pot->export_to_file( dirname( __FILE__ ) . '/' . time() . '.pot' );
