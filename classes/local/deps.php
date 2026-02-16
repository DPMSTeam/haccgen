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
 * Dependency loader utilities for local_haccgen.
 *
 * @package     local_haccgen
 * @category    external
 * @copyright   2026 Dynamicpixel Multimedia Solutions
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_haccgen\local;

/**
 * Helper class for loading optional third-party dependencies.
 */
final class deps {

    /**
     * Loads the PDF parser vendor autoloader once.
     *
     * Ensures that the Composer autoloader from .extlib/vendor
     * is included only once during execution.
     *
     * @return void
     */
    public static function autoload_pdfparser(): void {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $autoload = __DIR__ . '/../../.extlib/vendor/autoload.php';

        if (file_exists($autoload)) {
            require_once($autoload);
        }

        $loaded = true;
    }
}
