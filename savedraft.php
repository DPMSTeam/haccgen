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
 * Save AI-generated draft content for a course.
 *
 * @package     local_haccgen
 * @copyright   2026 Dynamicpixel Multimedia Solutions
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

global $DB, $CFG, $USER, $SESSION;

$courseid = required_param('id', PARAM_INT);
$step = optional_param('step', 4, PARAM_INT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/haccgen:manage', $context);

$logdir = $CFG->dataroot . '/local_haccgen';
if (!is_dir($logdir)) {
    @mkdir($logdir, 0770, true);
}

$logfile = $logdir . '/save_' . date('Y-m-d') . '.log';

$log = function (string $label, $data = null, bool $pretty = false) use ($logfile, $USER, $courseid) {
    if (!is_string($data)) {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $data = $pretty
            ? json_encode($data, $flags | JSON_PRETTY_PRINT)
            : json_encode($data, $flags);
    }

    if (is_string($data) && strlen($data) > 16000) {
        $data = substr($data, 0, 16000) . '…';
    }

    $line = sprintf(
        "[%s] uid=%s course=%s %s: %s\n",
        date('c'),
        $USER->id ?? '0',
        $courseid ?? '0',
        $label,
        (string)$data,
    );

    @file_put_contents($logfile, $line, FILE_APPEND | LOCK_EX);
};
// ----------------------------------------

$log('savedraft.START', ['step' => $step]);

$payloadraw = optional_param('payload', '', PARAM_RAW);
$payloadparts = optional_param('payloadparts', 0, PARAM_INT);

if ($payloadraw === '' && $payloadparts > 0) {
    $buf = '';
    for ($i = 1; $i <= $payloadparts; $i++) {
        $buf .= optional_param("payload_{$i}", '', PARAM_RAW);
    }
    $payloadraw = $buf;
}

$source = 'post';
if ($payloadraw === '' && !empty($SESSION->haccgen_data->canonical_payload_json)) {
    $payloadraw = $SESSION->haccgen_data->canonical_payload_json;
    $source = 'session';
}

$log('PARAM.payload.source', $source);
$log('PARAM.payload.present', $payloadraw !== '' ? 1 : 0);

if ($payloadraw === '') {
    throw new moodle_exception('invalidjson', 'local_haccgen', '', 'No canonical payload received');
}

$payload = json_decode($payloadraw, true);

if (
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($payload) ||
    empty($payload['topics']) ||
    !is_array($payload['topics'])
) {
    throw new moodle_exception(
        'invalidjson',
        'local_haccgen',
        '',
        'payload: ' . json_last_error_msg(),
    );
}

$log('payload.parsed', [
    'topics_total' => count($payload['topics']),
    'meta' => $payload['meta'] ?? null,
]);

$normstr = static fn($v): string =>
trim(mb_convert_encoding((string)$v, 'UTF-8', 'UTF-8'));

$sanitizesubtopic = static function ($in) use ($normstr) {
    $title = $normstr($in['title'] ?? '');
    $content = $in['content'] ?? [];

    if (!is_array($content)) {
        $content = ['text' => (string)$content, 'itemid' => 0];
    }

    return [
        'title' => $title,
        'content' => [
            'text' => (string)($content['text'] ?? ''),
            'itemid' => (int)($content['itemid'] ?? 0),
        ],
        'type' => $normstr($in['type'] ?? 'page'),
    ];
};

$sanitizequiz = static function ($in, string $faalbacktitle = '') use ($normstr) {
    if (!is_array($in)) {
        return null;
    }

    $title = $normstr($in['quiz_title'] ?? $faalbacktitle);
    $inst = (string)($in['instructions'] ?? '');
    $qs = is_array($in['questions'] ?? null) ? $in['questions'] : [];
    $outq = [];

    foreach ($qs as $i => $q) {
        $opts = array_values(
            array_map(
                static fn($o) => (string)$o,
                (array)($q['options'] ?? [])
            )
        );

        $outq[] = [
            'question_id' => $q['question_id'] ?? ('q' . ($i + 1)),
            'type' => $q['type'] ?? 'multiple_choice',
            'difficulty' => $q['difficulty'] ?? 'easy',
            'question' => (string)($q['question'] ?? ''),
            'options' => $opts,
            'correct_answer' => (string)($q['correct_answer'] ?? ($q['answer'] ?? '')),
            'explanation' => (string)($q['explanation'] ?? ''),
        ];
    }

    if ($title === '' && empty($outq)) {
        return null;
    }

    return [
        'quiz_title' => $title,
        'instructions' => $inst,
        'questions' => $outq,
    ];
};

// Build structured topics & compact quiz map.
$kbase = [];
$quizbytitle = [];

foreach ($payload['topics'] as $tidx => $t) {
    $title = $normstr($t['title'] ?? '') ?: ('Topic ' . ($tidx + 1));

    $subs = [];
    foreach ((array)($t['subtopics'] ?? []) as $s) {
        $subs[] = $sanitizesubtopic($s);
    }

    $quizraw = $t['quiz_data'] ?? ($t['quiz'] ?? null);
    $quiz = $sanitizequiz($quizraw, $title);

    $row = [
        'title' => $title,
        'subtopics' => $subs,
    ];

    if ($quiz) {
        if (($quiz['quiz_title'] ?? '') === '') {
            $quiz['quiz_title'] = $title;
        }

        $row['quiz_included'] = 1;
        $row['quiz_data'] = $quiz;
        $quizbytitle[$quiz['quiz_title']] = $quiz;
    }

    $kbase[] = $row;
}

$log('payload.normalized.summary', [
    'topics' => count($kbase),
    'topics_with_quiz' => count($quizbytitle),
]);

$topicsjsontostore = json_encode(
    $kbase,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

$quizjsontostore = json_encode(
    $quizbytitle,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

$now = time();

$record = new stdClass();
$record->courseid = $courseid;
$record->userid = $USER->id;
$record->batchid = uniqid('draft_', true);
$record->status = 'draft';
$record->topicsjson = $topicsjsontostore;
$record->quizjson = $quizjsontostore;
$record->timecreated = $now;
$record->timemodified = $now;

$record->id = $DB->insert_record('local_haccgen_content', $record);

$log('DB.INSERT.draft', [
    'draftid' => $record->id,
    'topics_bytes' => strlen($topicsjsontostore),
    'quiz_bytes' => strlen($quizjsontostore),
]);

// Keep editor session ready.
$flatforui = [];
foreach ($kbase as $t) {
    foreach ($t['subtopics'] as $s) {
        $flatforui[$s['title']] = $s['content'];
    }
}

$SESSION->haccgen_data = (object)[
    'topics' => $kbase,
    'quizjson' => $quizbytitle,
    'topicsjson' => $flatforui,
];

// Redirect.
redirect(
    new moodle_url('/course/view.php', ['id' => $courseid]),
    get_string('changessaved'),
    0
);
