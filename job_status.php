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
 * Job status endpoint (JSON) for local_haccgen.
 *
 * @package     local_haccgen
 * @copyright   2026 Dynamicpixel Multimedia Solutions
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

global $DB, $USER;

$jobid = required_param('id', PARAM_INT);

$job = $DB->get_record('local_haccgen_job', ['id' => $jobid], '*', MUST_EXIST);
require_capability('local/haccgen:manage', \context_course::instance($job->courseid));

if ($job->userid != $USER->id && !is_siteadmin()) {
    throw new moodle_exception('nopermissions', 'error');
}

$completed = null;
$total = null;
$textmsg = '';

$msgraw = (string) ($job->message ?? '');
if ($msgraw !== '') {
    $maybe = json_decode($msgraw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($maybe)) {
        if (isset($maybe['completed_topics'])) {
            $completed = (int) $maybe['completed_topics'];
        }
        if (isset($maybe['total_topics'])) {
            $total = (int) $maybe['total_topics'];
        }
        if (!empty($maybe['text'])) {
            $textmsg = (string) $maybe['text'];
        }
    } else {
        $textmsg = $msgraw;
    }
}

$progress = (int) $job->progress;
if ($total && $total > 0 && $completed !== null) {
    $progress = (int) round(($completed / max(1, $total)) * 100);
    if ($job->status !== 'success') {
        $progress = min($progress, 99);
    }
}

header('Content-Type: application/json; charset=utf-8');

$result = null;
if ($job->status === 'success') {
    $result = json_decode((string) $job->resultjson, true);
}

echo json_encode([
    'status' => $job->status,
    'progress' => max(0, min(100, $progress)),
    'message' => $textmsg,
    'completed_topics' => $completed,
    'total_topics' => $total,
    'result' => $result,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

exit;
