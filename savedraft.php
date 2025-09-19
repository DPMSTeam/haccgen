<?php
// local/haccgen/savedraft.php
defined('MOODLE_INTERNAL') || define('MOODLE_INTERNAL', true);

require_once(__DIR__ . '/../../config.php');

global $DB, $CFG, $USER, $SESSION;

// ---- Required context & capability ----
$courseid = required_param('id', PARAM_INT);
$step     = optional_param('step', 4, PARAM_INT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/haccgen:manage', $context);

// ---------------- Logger ----------------
$logdir = $CFG->dataroot . '/local_haccgen';
if (!is_dir($logdir)) { @mkdir($logdir, 0770, true); }
$logfile = $logdir . '/save_' . date('Y-m-d') . '.log';
$log = function (string $label, $data = null, bool $pretty = false) use ($logfile, $USER, $courseid) {
    if (!is_string($data)) {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $data  = $pretty ? json_encode($data, $flags | JSON_PRETTY_PRINT) : json_encode($data, $flags);
    }
    if (is_string($data) && strlen($data) > 16000) { $data = substr($data, 0, 16000) . '…'; }
    $line = sprintf("[%s] uid=%s course=%s %s: %s\n", date('c'), $USER->id ?? '0', $courseid ?? '0', $label, (string)$data);
    @file_put_contents($logfile, $line, FILE_APPEND | LOCK_EX);
};
// ----------------------------------------

$log('savedraft.START', ['step' => $step]);

// ---- Read canonical payload (prefer POST, else session) ----
$payloadraw   = optional_param('payload', '', PARAM_RAW);
$payloadparts = optional_param('payload_parts', 0, PARAM_INT);
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

// ---- Parse & validate ----
$payload = json_decode($payloadraw, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload) || empty($payload['topics']) || !is_array($payload['topics'])) {
    throw new moodle_exception('invalidjson', 'local_haccgen', '', 'payload: ' . json_last_error_msg());
}
$log('payload.parsed', ['topics_total' => count($payload['topics']), 'meta' => $payload['meta'] ?? null]);

// ---- Normalizers (no legacy guessing) ----
$normstr = static fn($v): string => trim(mb_convert_encoding((string)$v, 'UTF-8', 'UTF-8'));
$sanitize_subtopic = static function($in) use ($normstr) {
    $title   = $normstr($in['title'] ?? '');
    $content = $in['content'] ?? [];
    if (!is_array($content)) { $content = ['text' => (string)$content, 'itemid' => 0]; }
    return [
        'title'   => $title,
        'content' => [
            'text'   => (string)($content['text'] ?? ''),
            'itemid' => (int)($content['itemid'] ?? 0)
        ],
        'type'    => $normstr($in['type'] ?? 'page')
    ];
};
$sanitize_quiz = static function($in, string $fallbackTitle = '') use ($normstr) {
    if (!is_array($in)) { return null; }
    $title = $normstr($in['quiz_title'] ?? $fallbackTitle);
    $inst  = (string)($in['instructions'] ?? '');
    $qs    = is_array($in['questions'] ?? null) ? $in['questions'] : [];
    $outq  = [];
    foreach ($qs as $i => $q) {
        $opts = array_values(array_map(static fn($o) => (string)$o, (array)($q['options'] ?? [])));
        $outq[] = [
            'question_id'    => $q['question_id'] ?? ('q'.($i+1)),
            'type'           => $q['type'] ?? 'multiple_choice',
            'difficulty'     => $q['difficulty'] ?? 'easy',
            'question'       => (string)($q['question'] ?? ''),
            'options'        => $opts,
            'correct_answer' => (string)($q['correct_answer'] ?? ($q['answer'] ?? '')),
            'explanation'    => (string)($q['explanation'] ?? '')
        ];
    }
    if ($title === '' && empty($outq)) { return null; }
    return [
        'quiz_title'   => $title,
        'instructions' => $inst,
        'questions'    => $outq
    ];
};

// ---- Build structured topics & compact quiz map ----
$structuredTopics = [];
$quizByTitle      = []; // keyed by quiz_title

foreach ($payload['topics'] as $tidx => $t) {
    $title = $normstr($t['title'] ?? '') ?: ('Topic '.($tidx+1));

    $subs = [];
    foreach ((array)($t['subtopics'] ?? []) as $s) {
        $subs[] = $sanitize_subtopic($s);
    }

    $quizraw = $t['quiz_data'] ?? ($t['quiz'] ?? null);
    $quiz    = $sanitize_quiz($quizraw, $title);

    $row = ['title' => $title, 'subtopics' => $subs];
    if ($quiz) {
        if (($quiz['quiz_title'] ?? '') === '') { $quiz['quiz_title'] = $title; }
        $row['quiz_included'] = 1;
        $row['quiz_data']     = $quiz;
        $quizByTitle[$quiz['quiz_title']] = $quiz;
    }

    $structuredTopics[] = $row;
}

$log('payload.normalized.summary', [
    'topics' => count($structuredTopics),
    'topics_with_quiz' => count($quizByTitle)
]);

// ---- Persist draft (upsert by user+course+status or always insert new) ----
// Choose policy: update existing 'draft' row or create a new one.
// If you want multiple drafts per course/user, comment the "UPDATE" branch and always INSERT.
$conditions = ['courseid' => $courseid, 'userid' => $USER->id, 'status' => 'draft'];
$existing   = $DB->get_record('local_haccgen_content', $conditions, '*', IGNORE_MISSING);

$topicsjson_to_store = json_encode($structuredTopics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$quizjson_to_store   = json_encode($quizByTitle,      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$now = time();

if ($existing) {
    $existing->topicsjson   = $topicsjson_to_store;
    $existing->quizjson     = $quizjson_to_store;
    $existing->timemodified = $now;
    $DB->update_record('local_haccgen_content', $existing);
    $log('DB.UPDATE.draft', ['draftid' => $existing->id, 'topics_bytes' => strlen($topicsjson_to_store), 'quiz_bytes' => strlen($quizjson_to_store)]);
} else {
    $record = new stdClass();
    $record->courseid     = $courseid;
    $record->userid       = $USER->id;
    $record->batchid      = uniqid('draft_', true); // fits CHAR(40)
    $record->status       = 'draft';
    $record->topicsjson   = $topicsjson_to_store;
    $record->quizjson     = $quizjson_to_store;
    $record->timecreated  = $now;
    $record->timemodified = $now;

    $record->id = $DB->insert_record('local_haccgen_content', $record);
    $log('DB.INSERT.draft', ['draftid' => $record->id, 'topics_bytes' => strlen($topicsjson_to_store), 'quiz_bytes' => strlen($quizjson_to_store)]);
}

// ---- Keep editor session in a ready state (optional, good UX) ----
$flatForUI = [];
foreach ($structuredTopics as $t) {
    foreach ($t['subtopics'] as $s) {
        $flatForUI[$s['title']] = $s['content'];
    }
}
$SESSION->haccgen_data = (object)[
    'topics'     => $structuredTopics,  // structured, with quiz_data when present
    'quizjson'   => $quizByTitle,       // compact by quiz_title
    'topicsjson' => $flatForUI          // editor convenience
];

// ---- Redirect back to step 4 editor ----
redirect(
    new moodle_url('/course/view.php', ['id' => $courseid]),
    get_string('changessaved'),
    0
);
