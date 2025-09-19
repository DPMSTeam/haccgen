<?php
defined('MOODLE_INTERNAL') || die();

// Path to autoload
require_once(__DIR__ . '/../vendor/autoload.php');

function local_haccgen_parse_pdf($filepath)
{
    // Initialize the PDF parser
    $parser = new \Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile($filepath);

    // Extract the text from the PDF
    $text = $pdf->getText();

    return trim($text);
}
