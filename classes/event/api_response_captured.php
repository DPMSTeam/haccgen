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
 * Event fired when an API response is captured for metrics/debugging.
 *
 * @package     local_haccgen
 * @copyright   2026 Dynamicpixel Multimedia Solutions
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_haccgen\event;

/**
 * Event class for API response capture.
 *
 * @package     local_haccgen
 * @copyright   2026 Dynamicpixel Multimedia Solutions
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_response_captured extends \core\event\base {

    /**
     * Initialise the event.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = null;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_api_response_captured', 'local_haccgen');
    }

    /**
     * Return a human-readable description of the event.
     *
     * @return string
     */
    public function get_description(): string {
        $o = $this->other ?? [];
        $bits = [
            'url' => $o['url'] ?? '',
            'action' => $o['action'] ?? '',
            'http_code' => $o['http_code'] ?? null,
            'elapsed_ms' => $o['elapsed_ms'] ?? null,
            'response_bytes' => $o['response_bytes'] ?? null,
            'word_count' => $o['word_count'] ?? null,
            'token_estimate' => $o['token_estimate'] ?? null,
            'phase' => $o['phase'] ?? null,
            'request_id' => $o['request_id'] ?? null,
        ];

        return 'API response captured: ' . json_encode($bits, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Return the URL related to the event.
     *
     * @return \moodle_url|null
     */
    public function get_url(): ?\moodle_url {
        return new \moodle_url('/');
    }

    /**
     * Factory to create the event from metrics payload.
     *
     * @param array $other Metrics payload.
     * @return self
     */
    public static function create_from_metrics(array $other): self {
        return self::create([
            'context' => \context_system::instance(),
            'relateduserid' => $other['related_userid'] ?? null,
            'other' => $other,
        ]);
    }
}
