<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External library helpers for local_haccgen.
 *
 * @package     local_haccgen
 * @copyright   2026 Dynamicpixel Multimedia Solutions
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Ensure composer autoloader for bundled libs is loaded.
 *
 * @return void
 * @package local_haccgen
 */
function local_haccgen_require_extlib_autoload(): void {
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $autoload = __DIR__ . '/../.extlib/vendor/autoload.php';
    if (is_readable($autoload)) {
        require_once($autoload);
        $loaded = true;
        return;
    }

    // If autoload missing, keep $loaded=false so caller can detect via class_exists().
}

/**
 * Parse a PDF and return extracted text.
 *
 * @param string $filepath Absolute file path to PDF
 * @return string Extracted text (trimmed). Empty string on failure
 * @package local_haccgen
 */
function local_haccgen_parse_pdf(string $filepath): string {
    local_haccgen_require_extlib_autoload();

    if (!class_exists(\Smalot\PdfParser\Parser::class)) {
        debugging(
            'smalot/pdfparser is missing. Expected .extlib/vendor/autoload.php',
            DEBUG_DEVELOPER
        );
        return '';
    }

    if (!is_readable($filepath)) {
        return '';
    }

    try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filepath);
        return trim((string)$pdf->getText());
    } catch (\Throwable $e) {
        debugging(
            'PDF parse failed: ' . $e->getMessage(),
            DEBUG_DEVELOPER
        );
        return '';
    }
}
