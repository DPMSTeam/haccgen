<?php
require_once(__DIR__ . '/../../config.php');

global $DB, $SESSION, $OUTPUT, $PAGE, $USER, $CFG;

$courseid        = required_param('id', PARAM_INT);
$tmpbatch = optional_param('batchid', '', PARAM_RAW_TRIMMED);
$selectedbatchid = preg_replace('/[^A-Za-z0-9._-]/', '', $tmpbatch);
$action          = optional_param('action', '', PARAM_ALPHA);
$userid          = (int)$USER->id;

$course  = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/haccgen:manage', $context);

$PAGE->set_url(new moodle_url('/local/haccgen/old_draft.php', ['id' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('loaddraft', 'local_haccgen', 'Load Previous Draft'));
$PAGE->set_heading(format_string($course->fullname));

// ---- helpers ----
if (!function_exists('normalise_title')) {
    function normalise_title(string $s): string {
        $s = preg_replace('/^quiz:\s*/i', '', $s);
        return mb_strtolower(trim($s));
    }
}
if (!function_exists('hacc_base_key')) {
    function hacc_base_key(string $s): string {
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML401, 'UTF-8');
        $s = preg_replace('/^quiz:\s*/i', '', $s);
        $s = mb_strtolower($s);
        $s = str_replace('&', ' and ', $s);
        $s = preg_replace('/[:\-–—|].*$/u', '', $s);
        $s = preg_replace('/[^a-z0-9 ]/u', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }
}
$normstr = static function($v): string {
    return trim(mb_convert_encoding((string)$v, 'UTF-8', 'UTF-8'));
};
$sanitize_subtopic = static function($in) use ($normstr) {
    $title = $normstr($in['title'] ?? '');
    $content = $in['content'] ?? [];
    if (!is_array($content)) { $content = ['text' => (string)$content, 'itemid' => 0]; }
    $text   = (string)($content['text'] ?? '');
    $itemid = (int)($content['itemid'] ?? 0);
    $type   = $normstr($in['type'] ?? 'page');
    return [
        'title'   => $title,
        'content' => ['text' => $text, 'itemid' => $itemid],
        'type'    => $type
    ];
};
$sanitize_quiz = static function($in, string $fallbackTitle = '') use ($normstr) {
    if (!is_array($in)) { return null; }
    $title = $normstr($in['quiz_title'] ?? $fallbackTitle);
    $inst  = (string)($in['instructions'] ?? '');
    $qs    = is_array($in['questions'] ?? null) ? $in['questions'] : [];
    $outq = [];
    foreach ($qs as $idx => $q) {
        $opts = is_array($q['options'] ?? null) ? array_values(array_map(static fn($o)=> (string)$o, $q['options'])) : [];
        $outq[] = [
            'question_id'    => $q['question_id'] ?? ('q'.($idx+1)),
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

// If a batch is targeted, ensure it belongs to this user & course (and is a draft)
if ($selectedbatchid !== '' && ($action === 'load' || $action === 'delete')) {
    $owner = $DB->get_record('local_haccgen_content', [
        'userid'  => $userid,
        'batchid' => $selectedbatchid,
        'status'  => 'draft'
    ], 'id,courseid', IGNORE_MISSING);

    if ($owner && (int)$owner->courseid !== (int)$courseid) {
        redirect(new moodle_url('/local/haccgen/old_draft.php', [
            'id'      => (int)$owner->courseid,
            'batchid' => $selectedbatchid,
            'action'  => $action,
            'sesskey' => sesskey(),
        ]));
    }
}

// ---- Delete draft (CSRF-safe) ----
if ($selectedbatchid !== '' && $action === 'delete') {
    require_sesskey();
    $DB->delete_records('local_haccgen_content', [
        'courseid' => $courseid,
        'userid'   => $userid,
        'batchid'  => $selectedbatchid,
        'status'   => 'draft',
    ]);
    redirect(new moodle_url('/local/haccgen/old_draft.php', ['id' => $courseid]),
        get_string('draftdeletedsuccess', 'local_haccgen', 'Draft deleted successfully.'), 2);
}

// ---- Load draft into session, then bounce to step 4 ----
if ($selectedbatchid !== '' && $action === 'load') {
    $row = $DB->get_record_sql(
        "SELECT *
           FROM {local_haccgen_content}
          WHERE courseid = :c AND userid = :u AND batchid = :b AND status = 'draft'
       ORDER BY timemodified DESC, timecreated DESC",
        ['c' => $courseid, 'u' => $userid, 'b' => $selectedbatchid],
        IGNORE_MULTIPLE
    );
    if (!$row) {
        redirect(new moodle_url('/local/haccgen/old_draft.php', ['id' => $courseid]),
            get_string('draftnotfound', 'local_haccgen', 'Draft not found.'), 2);
    }

    // Decode DB fields (tolerant)
    $topicsDecoded = [];
    $quizDecoded   = [];
    if (!empty($row->topicsjson)) {
        $tmp = json_decode($row->topicsjson, true);
        if (json_last_error() === JSON_ERROR_NONE) { $topicsDecoded = $tmp; }
    }
    if (!empty($row->quizjson)) {
        $tmp = json_decode($row->quizjson, true);
        if (json_last_error() === JSON_ERROR_NONE) { $quizDecoded = $tmp; }
    }

    $isStructured = is_array($topicsDecoded)
                 && isset($topicsDecoded[0])
                 && is_array($topicsDecoded[0])
                 && array_key_exists('title', $topicsDecoded[0])
                 && array_key_exists('subtopics', $topicsDecoded[0]);

    $topics      = [];   // structured topics for UI
    $quizMapOut  = [];   // compact map keyed by quiz_title
    $flatForUI   = [];   // flat map for editor convenience

    if ($isStructured) {
        // ---------- New structured draft (no guessing) ----------
        foreach ($topicsDecoded as $tidx => $t) {
            $title = $normstr($t['title'] ?? ('Topic '.($tidx+1)));
            $subtopics = [];
            if (is_array($t['subtopics'] ?? null)) {
                foreach ($t['subtopics'] as $s) {
                    $san = $sanitize_subtopic($s);
                    $subtopics[] = $san;
                    // flat map keyed by subtopic title (editor expects this)
                    $flatForUI[$san['title']] = $san['content'];
                }
            }

            $topicRow = [
                'title'     => $title,
                'subtopics' => $subtopics
            ];

            // quiz_data may be under quiz_data or quiz
            $quizraw = $t['quiz_data'] ?? ($t['quiz'] ?? null);
            $quiz    = $sanitize_quiz($quizraw, $title);
            if ($quiz) {
                if (($quiz['quiz_title'] ?? '') === '') { $quiz['quiz_title'] = $title; }
                $topicRow['quiz_included'] = 1;
                $topicRow['quiz_data']     = $quiz;
                $quizMapOut[$quiz['quiz_title']] = $quiz;
            } else {
                // If structured topics have no quiz_data but DB quizjson exists keyed by topic title, attach it.
                $qFromDb = $quizDecoded[$title] ?? null;
                if ($qFromDb) {
                    $quiz = $sanitize_quiz($qFromDb, $title);
                    if ($quiz) {
                        $topicRow['quiz_included'] = 1;
                        $topicRow['quiz_data']     = $quiz;
                        $quizMapOut[$quiz['quiz_title']] = $quiz;
                    }
                }
            }

            $topics[] = $topicRow;
        }

        // Keep "About this course" first if present
        $norm = fn($s) => mb_strtolower(trim((string)$s));
        $aboutIdx = null;
        foreach ($topics as $i => $t) {
            if ($norm($t['title'] ?? '') === $norm('About this course')) { $aboutIdx = $i; break; }
        }
        if ($aboutIdx !== null && $aboutIdx > 0) {
            $about = $topics[$aboutIdx];
            array_splice($topics, $aboutIdx, 1);
            array_unshift($topics, $about);
        }

    } else {
        // ---------- Legacy draft (flat) – keep your previous path ----------
        $topicsFlat = is_array($topicsDecoded) ? $topicsDecoded : [];
        $quizMap    = is_array($quizDecoded)   ? $quizDecoded   : [];

        // Build nested topics from "Parent > Subtopic" keys
        $topicsNested = [];
        foreach ($topicsFlat as $key => $val) {
            $clean = html_entity_decode((string)$key, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $clean = str_replace(['→','›','»','‣'], '>', $clean);
            $parent = $clean;
            $sub    = $clean;
            if (strpos($clean, '>') !== false) {
                [$parent, $sub] = array_map('trim', explode('>', $clean, 2));
            }
            if (!isset($topicsNested[$parent])) {
                $topicsNested[$parent] = ['title' => $parent, 'subtopics' => []];
            }
            // Normalise content format
            if (is_array($val)) {
                $text   = (string)($val['text'] ?? $val['content_html'] ?? $val['content'] ?? '');
                $itemid = (int)($val['itemid'] ?? 0);
            } else {
                $text   = (string)$val;
                $itemid = 0;
            }
            $san = $sanitize_subtopic(['title' => $sub, 'content' => ['text'=>$text,'itemid'=>$itemid], 'type'=>'page']);
            $topicsNested[$parent]['subtopics'][] = $san;
            $flatForUI[$san['title']] = $san['content'];
        }
        $topics = array_values($topicsNested);

        // Build quiz lookup (best-effort)
        $cleanQuiz = [];
        $quizByKey = [];
        if (is_array($quizMap)) {
            foreach ($quizMap as $rawKey => $payload) {
                if (!is_array($payload)) { continue; }
                if (empty($payload['quiz_title'])) {
                    $payload['quiz_title'] = preg_replace('/^quiz:\s*/i', '', trim((string)$rawKey));
                }
                $title = (string)$payload['quiz_title'];
                $kFull = normalise_title($title);
                $kBase = hacc_base_key($title);
                $cleanQuiz[$kFull] = $payload;
                $quizByKey[$kFull] = $payload;
                $quizByKey[$kBase] = $payload;
            }
        }

        // Map quizzes to topics by topic title or any subtopic belonging to that topic
        $subToTopic = [];
        foreach ($topics as $t) {
            $parent = (string)($t['title'] ?? '');
            foreach (($t['subtopics'] ?? []) as $s) {
                $st = (string)($s['title'] ?? '');
                if ($st !== '') { $subToTopic[hacc_base_key($st)] = $parent; }
            }
        }

        foreach ($topics as &$t) {
            $title  = $t['title'] ?? '';
            $tkFull = normalise_title($title);
            $tkBase = hacc_base_key($title);

            if (isset($quizByKey[$tkFull]) || isset($quizByKey[$tkBase])) {
                $t['quiz_included'] = 1;
                $t['quiz_data']     = $quizByKey[$tkFull] ?? $quizByKey[$tkBase];
            } else {
                foreach ($cleanQuiz as $payload) {
                    $qTitle = $payload['quiz_title'] ?? '';
                    $qBase  = hacc_base_key($qTitle);
                    if ($qBase && isset($subToTopic[$qBase]) && hacc_base_key($subToTopic[$qBase]) === $tkBase) {
                        $t['quiz_included'] = 1;
                        $t['quiz_data']     = $payload;
                        break;
                    }
                }
                if (empty($t['quiz_included'])) {
                    unset($t['quiz_included'], $t['quiz_data']);
                }
            }

            if (!empty($t['quiz_data']) && !empty($t['quiz_data']['quiz_title'])) {
                $quizMapOut[$t['quiz_data']['quiz_title']] = $t['quiz_data'];
            }
        }
        unset($t);

        // Keep "About this course" first (if present)
        $norm = fn($s) => mb_strtolower(trim((string)$s));
        $aboutIdx = null;
        foreach ($topics as $i => $t) {
            if ($norm($t['title'] ?? '') === $norm('About this course')) { $aboutIdx = $i; break; }
        }
        if ($aboutIdx !== null && $aboutIdx > 0) {
            $about = $topics[$aboutIdx];
            array_splice($topics, $aboutIdx, 1);
            array_unshift($topics, $about);
        }

        // Move "Learning objectives - X" to first subtopic (same logic you had)
        foreach ($topics as &$t) {
            $subs = $t['subtopics'] ?? [];
            $target = null;
            foreach ($subs as $i => $s) {
                $st = (string)($s['title'] ?? '');
                if (preg_match('/^Learning objectives\s*-\s*(.+)$/i', $st, $m)) {
                    if ($norm($m[1]) === $norm($t['title'] ?? '')) { $target = $i; break; }
                }
            }
            if ($target !== null && $target > 0) {
                $item = $subs[$target];
                array_splice($subs, $target, 1);
                array_unshift($subs, $item);
            }
            $t['subtopics'] = $subs;
        }
        unset($t);
    }

    // Save to session and redirect to step 4
    $SESSION->haccgen_data = (object)[
        'topics'     => $topics,          // structured (with quiz_data inline when present)
        'quizjson'   => $quizMapOut,      // compact map keyed by quiz_title
        'topicsjson' => $flatForUI        // flat for editor convenience (subtopic title => {text,itemid})
    ];
    $SESSION->haccgen_last_loaded_batchid = $selectedbatchid;

    redirect(new moodle_url('/local/haccgen/manage.php', [
        'id'       => $courseid,
        'step'     => 4,
        'loaddraft'=> 1
    ]));
}

// ---------- UI: list saved draft batches ----------
$sql = "SELECT batchid,
               MAX(timemodified) AS timemodified,
               COUNT(*) AS cnt
          FROM {local_haccgen_content}
         WHERE courseid = :c AND userid = :u AND status = 'draft'
      GROUP BY batchid
      ORDER BY timemodified DESC";
$params = ['c' => $courseid, 'u' => $userid];

$drafts = [];
try { $drafts = $DB->get_records_sql($sql, $params); } catch (Throwable $e) { $drafts = []; }

echo $OUTPUT->header();

if (empty($drafts)) {
    echo $OUTPUT->notification(get_string('nodraftsfound', 'local_haccgen', 'No draft content found for this course.'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->continue_button(new moodle_url('/local/haccgen/manage.php', ['id' => $courseid, 'step' => 4]));
    echo $OUTPUT->footer();
    exit;
}

$formurl = new moodle_url('/local/haccgen/old_draft.php');
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $formurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_tag('div', ['class' => 'form-group']);
echo html_writer::tag('label', get_string('selectdraft', 'local_haccgen', 'Select a Draft:'), ['for' => 'batchid']);
echo html_writer::start_tag('select', ['name' => 'batchid', 'class' => 'form-control', 'required' => 'required', 'id' => 'batchid']);
echo html_writer::tag('option', '-- ' . get_string('choose') . ' --', ['value' => '']);

foreach ($drafts as $draft) {
    $label = userdate($draft->timemodified) . " ({$draft->cnt})";
    $attrs = ['value' => $draft->batchid];
    if ($draft->batchid === $selectedbatchid) { $attrs['selected'] = 'selected'; }
    echo html_writer::tag('option', $label, $attrs);
}
echo html_writer::end_tag('select');
echo html_writer::end_tag('div');

echo html_writer::empty_tag('br');
echo html_writer::tag('button', get_string('loaddraftbtn', 'local_haccgen', 'Load Draft'),
    ['type' => 'submit', 'name' => 'action', 'value' => 'load', 'class' => 'btn btn-primary']);
echo ' ';
echo html_writer::tag('button', get_string('deletedraftbtn', 'local_haccgen', 'Delete Draft'),
    ['type' => 'submit', 'name' => 'action', 'value' => 'delete', 'class' => 'btn btn-danger',
     'onclick' => "return confirm('".get_string('areyousure')."');"]);

echo html_writer::end_tag('form');
echo $OUTPUT->footer();
