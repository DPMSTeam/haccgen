<?php
require_once(__DIR__ . '/../../config.php');
require __DIR__ . '/vendor/autoload.php';
require_once($CFG->dirroot . '/local/haccgen/lib.php');
require_once($CFG->dirroot . '/local/haccgen/settings.php');
require_once($CFG->dirroot . '/local/haccgen/lib/Parsedown.php');  // Adjust the path if needed
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/question/engine/bank.php');

$parsedown = new Parsedown();
try {
    $endpoint = local_haccgen_api::get_api_url();
    $statusmessage = null;
    $statusclass = null;
    $status_active = true;
} catch (moodle_exception $e) {
    $statusmessage = $e->getMessage();
    $statusclass = "alert alert-danger";
    $status_active = false;
}



$courseid = required_param('id', PARAM_INT);
$step = optional_param('step', 1, PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/haccgen:manage', $context);

$PAGE->set_url('/local/haccgen/manage.php', ['id' => $courseid, 'step' => $step]);
$PAGE->set_title(get_string('manageai', 'local_haccgen'));
$PAGE->set_heading($course->fullname);
$PAGE->requires->js('/lib/requirejs.php'); // ✅ load require()
$context = context_system::instance(); // Or context_module::instance($cmid)

$editorid = 'id_contenteditor'; // This must match the textarea ID in the template

// REQUIRED: editor options array
$editoroptions = [
    'maxfiles' => 0,
    'maxbytes' => 0,
    'trusttext' => true,
    'context' => $context,
];

// This attaches the TinyMCE or Atto editor to the textarea
$editor = editors_get_preferred_editor(FORMAT_HTML);
$editorinitjs = $editor->use_editor($editorid, $editoroptions);

// Create the actual <textarea> element
$textarea = html_writer::tag('textarea', '', [
    'id' => $editorid,
    'name' => 'contenteditor',
    'rows' => 10,
    'cols' => 60,
    'class' => 'form-control'
]);

ob_start();

// Initialize or convert session data to an object
if (!isset($SESSION->haccgen_data) || !is_object($SESSION->haccgen_data)) {
    if (isset($SESSION->haccgen_data) && is_array($SESSION->haccgen_data)) {
        // Convert existing array to object, taking the last entry if multiple
        $SESSION->haccgen_data = (object) array_pop($SESSION->haccgen_data);
    } else {
        $SESSION->haccgen_data = new stdClass();
    }
}

$hasdraft = $DB->record_exists('local_haccgen_content', [
    'courseid' => $courseid,
    'userid' => $USER->id,
]);
error_log("hasdraft  ".$hasdraft);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = optional_param('action', '', PARAM_TEXT);
    $topicsjsonreq = optional_param('topicsjson', '', PARAM_RAW);
    $quizjsonreq   = optional_param('quizjson',   '', PARAM_RAW);
    if ($action === 'back') {
        $prevstep = max(1, $step - 1); // Use max to prevent negative step
        ob_end_clean();
        redirect(new moodle_url('/local/haccgen/manage.php', ['id' => $courseid, 'step' => $prevstep]));
        exit;
    }

    $data = new stdClass();
    $generation_type = optional_param('generation_type', 'ai', PARAM_TEXT);
    if ($step == 1) {
        $data->generation_type = $generation_type;
        if ($generation_type === 'ai') {
            // Validate AI fields
            $data->coursename = optional_param('TOPICTITLE', '', PARAM_TEXT);
            if (trim($data->coursename) === '') {
                $errors['TOPICTITLE'] = get_string('error_required', 'local_haccgen');
            }
            $data->targetaudience = optional_param('targetaudience', '', PARAM_TEXT);
            if (empty($data->targetaudience)) {
                $errors['targetaudience'] = get_string('error_required', 'local_haccgen');
            }
            $data->description = optional_param('description', '', PARAM_TEXT);

            // Explicitly ignore all uploaded fields
            $data->coursename_uploaded = '';
            $data->targetaudience_uploaded = '';
            $data->description_uploaded = '';
            $data->pdf_file = '';
            $data->pdf_fileid = 0;
            $data->pdf_reference_url = '';

            //new fields for AI generation
            $data->customprompt = optional_param('customprompt', '', PARAM_TEXT);
            if ($is_draft) {
                $formdata['loaddrafturl'] = new moodle_url('/local/haccgen/old_draft.php', ['id' => $courseid]);
            }
        } else {
            // Explicitly unset AI fields to prevent validation
            unset($_POST['TOPICTITLE']);
            unset($_POST['targetaudience']);
            unset($_POST['description']);
            unset($_POST['customprompt']);

            // Validate uploaded fields
            $data->coursename = optional_param('TOPICTITLE_uploaded', '', PARAM_TEXT);
            if (trim($data->coursename) === '') {
                $errors['TOPICTITLE_uploaded'] = get_string('error_required', 'local_haccgen');
            }
            $data->targetaudience = optional_param('targetaudience_uploaded', '', PARAM_TEXT);
            if (empty($data->targetaudience)) {
                $errors['targetaudience_uploaded'] = get_string('error_required', 'local_haccgen');
            }
            $data->description = optional_param('description_uploaded', '', PARAM_TEXT);

            $data->customprompt = optional_param('customprompt', '', PARAM_TEXT);

            if (isset($_FILES['pdf_upload']) && $_FILES['pdf_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['pdf_upload'];
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);

                    if ($mime_type === 'application/pdf') {
                        $fs = get_file_storage();
                        $originalname = clean_filename($file['name']);
                        $ext = pathinfo($originalname, PATHINFO_EXTENSION);
                        $basename = pathinfo($originalname, PATHINFO_FILENAME);
                        $unique_filename = $basename . '_' . time() . '.' . $ext;

                        $data->pdf_file = $unique_filename;

                        // Values you will also pass to make_pluginfile_url:
                        $contextid = $context->id;
                        $component = 'local_haccgen';
                        $filearea  = 'uploads';
                        $itemid    = $courseid;
                        $filepath  = '/'; // or '/sub/dir/' (leading & trailing slash)
                        $filename  = $unique_filename; // same name you saved in $filerecord

// Create the file:
                        $filerecord = [
                            'contextid' => $contextid,
                              'component' => $component,
                            'filearea'  => $filearea,
                            'itemid'    => $itemid,
                             'filepath'  => $filepath,
                            'filename'  => $filename,
                            'timecreated' => time(),
                            'timemodified' => time(),
                         ];
                   $newfile = $fs->create_file_from_pathname($filerecord, $file['tmp_name']);

// Sign:
                   $expires = time() + 3600;
                   $secret  = (string)get_config('local_haccgen', 'linksecret'); // same on all nodes
                   $payload = implode('|', [$contextid, $component, $filearea, $itemid, $filepath, $filename, $expires]);
                    $token   = hash_hmac('sha256', $payload, $secret);

// URL:
                   $url = moodle_url::make_pluginfile_url($contextid, $component, $filearea, $itemid, $filepath, $filename);
                   $url->param('expires', $expires);
                   $url->param('token', $token);

                   $data->pdf_reference_url = $url->out(false);
                        $data->description = '';
                    } else {
                        $errors['pdf_upload'] = get_string('invalid_pdf', 'local_haccgen');
                    }
                }
            } else {
                $errors['pdf_upload'] = get_string('error_required', 'local_haccgen');
            }
            // Explicitly ignore all AI fields
            $data->TOPICTITLE_ai = '';
            $data->targetaudience_ai = '';
            $data->description_ai = '';
            $data->customprompt_ai = '';
        }

        $data->courseduration = 'Less than 15 minutes';
        $data->levelofunderstanding = 'Beginner';
        $data->toneofnarrative = 'Formal';
        if ($is_draft) {
            $formdata['loaddrafturl'] = new moodle_url('/local/haccgen/old_draft.php', ['id' => $courseid]);
        }
    } elseif ($step == 2) {
        $valid_levels = ['Beginner', 'Intermediate', 'Advanced'];
        $valid_durations = ['Less than 15 minutes', 'Less than 30 minutes', 'Less than 60 minutes', 'Less than 90 minutes', 'Less than 120 minutes'];
        $valid_tones = ['Formal', 'Conversational', 'Engaging'];
        $valid_languages = ['English', 'Hindi'];
        $min_topics = 2;
        $max_topics = 10;

        $data->levelofunderstanding = required_param('levelofunderstanding', PARAM_TEXT);
        if (!in_array($data->levelofunderstanding, $valid_levels)) {
            $errors['levelofunderstanding'] = get_string('please_select', 'local_haccgen');
        }

        $data->toneofnarrative = required_param('toneofnarrative', PARAM_TEXT);
        if (!in_array($data->toneofnarrative, $valid_tones)) {
            $errors['toneofnarrative'] = get_string('please_select', 'local_haccgen');
        }

        $data->courseduration = required_param('courseduration', PARAM_TEXT);
        if (!in_array($data->courseduration, $valid_durations)) {
            $errors['courseduration'] = get_string('please_select', 'local_haccgen');
        }

        // New field: Language selection
        $data->courselanguage = required_param('courselanguage', PARAM_TEXT);
        if (!in_array($data->courselanguage, $valid_languages)) {
            $errors['courselanguage'] = get_string('please_select', 'local_haccgen');
        }

        // New field: Number of topics (integer between 2–10)
        $data->numberoftopics = optional_param('numberoftopics', 5, PARAM_INT);
        if ($data->numberoftopics < $min_topics || $data->numberoftopics > $max_topics) {
            $errors['numberoftopics'] = get_string('invalid_topic_count', 'local_haccgen'); // You should define this string in your lang file
        }

        // Existing session data retrieval
        $data->coursename = $SESSION->haccgen_data->TOPICTITLE ?? '';
        $data->targetaudience = $SESSION->haccgen_data->targetaudience ?? '';
        $data->description = $SESSION->haccgen_data->description ?? '';
        $data->generation_type = $SESSION->haccgen_data->generation_type ?? 'ai';
        $data->pdf_file = $SESSION->haccgen_data->pdf_file ?? '';
        $data->pdf_fileid = $SESSION->haccgen_data->pdf_fileid ?? 0;
        $data->pdf_reference_url = $SESSION->haccgen_data->pdf_reference_url ?? '';
        $data->customprompt = $SESSION->haccgen_data->customprompt ?? '';
    } elseif ($step == 3) {
        $data->topic_order = optional_param('topic_order', '', PARAM_RAW);
        $data->coursename = $SESSION->haccgen_data->TOPICTITLE ?? '';
        $data->targetaudience = $SESSION->haccgen_data->targetaudience ?? '';
        $data->description = $SESSION->haccgen_data->description ?? '';
        $data->levelofunderstanding = $SESSION->haccgen_data->levelofunderstanding ?? 'Beginner';
        $data->toneofnarrative = $SESSION->haccgen_data->toneofnarrative ?? 'Formal';
        $data->courseduration = $SESSION->haccgen_data->courseduration ?? 'Less than 15 minutes';
        $data->generation_type = $SESSION->haccgen_data->generation_type ?? 'ai';
        $data->pdf_file = $SESSION->haccgen_data->pdf_file ?? '';
        $data->pdf_fileid = $SESSION->haccgen_data->pdf_fileid ?? 0;
        $data->customprompt = $SESSION->haccgen_data->customprompt ?? '';
        $data->courselanguage = $SESSION->haccgen_data->courselanguage ?? 'English';
        $data->pdf_reference_url = $SESSION->haccgen_data->pdf_reference_url ?? '';
        $data->numberoftopics = $SESSION->haccgen_data->numberoftopics ?? 5;
    } elseif ($step == 4) {
        $data->coursename = $SESSION->haccgen_data->TOPICTITLE ?? '';
        $data->targetaudience = $SESSION->haccgen_data->targetaudience ?? '';
        $data->description = $SESSION->haccgen_data->description ?? '';
        $data->levelofunderstanding = $SESSION->haccgen_data->levelofunderstanding ?? 'Beginner';
        $data->toneofnarrative = $SESSION->haccgen_data->toneofnarrative ?? 'Formal';
        $data->courseduration = $SESSION->haccgen_data->courseduration ?? 'Less than 15 minutes';
        $data->generation_type = $SESSION->haccgen_data->generation_type ?? 'ai';
        $data->pdf_file = $SESSION->haccgen_data->pdf_file ?? '';
        $data->pdf_fileid = $SESSION->haccgen_data->pdf_fileid ?? 0;
        $data->customprompt = $SESSION->haccgen_data->customprompt ?? '';
        $data->pdf_reference_url = $SESSION->haccgen_data->pdf_reference_url ?? '';
        $data->courselanguage = $SESSION->haccgen_data->courselanguage ?? 'English';
        $data->numberoftopics = $SESSION->haccgen_data->numberoftopics ?? 5;
    }

    if (empty($errors)) {
        $SESSION->haccgen_data->TOPICTITLE = $data->coursename ?? '';
        $SESSION->haccgen_data->targetaudience = $data->targetaudience ?? '';
        $SESSION->haccgen_data->description = $data->description ?? '';
        $SESSION->haccgen_data->levelofunderstanding = $data->levelofunderstanding ?? '';
        $SESSION->haccgen_data->toneofnarrative = $data->toneofnarrative ?? '';
        $SESSION->haccgen_data->courseduration = $data->courseduration ?? '';
        $SESSION->haccgen_data->generation_type = $data->generation_type ?? 'ai';
        $SESSION->haccgen_data->pdf_file = $data->pdf_file ?? '';
        $SESSION->haccgen_data->pdf_fileid = $data->pdf_fileid ?? 0;
        $SESSION->haccgen_data->courselanguage = $data->courselanguage ?? 'English';
        $SESSION->haccgen_data->numberoftopics = $data->numberoftopics ?? 5;
        $SESSION->haccgen_data->customprompt = $data->customprompt ?? '';
        $SESSION->haccgen_data->pdf_reference_url = $data->pdf_reference_url;
        $is_draft = optional_param('savedraft', 0, PARAM_BOOL);


        if ($step == 3 && $action === 'save' && !empty($data->topic_order)) {
            try {

                $topics = json_decode($data->topic_order, true, 512, JSON_THROW_ON_ERROR);

                $job = (object)[
                    'userid'       => $USER->id,
                    'courseid'     => $courseid,
                    'type'         => 'topiccontent',
                    'status'       => 'queued',
                    'progress'     => 0,
                    'message'      => null,
                    'inputjson'    => json_encode([
                        'topics'  => $topics,
                        'options' => [
                            'coursename'           => $SESSION->haccgen_data->TOPICTITLE ?? '',
                            'targetaudience'       => $SESSION->haccgen_data->targetaudience ?? '',
                            'description'          => $SESSION->haccgen_data->description ?? '',
                            'levelofunderstanding' => $SESSION->haccgen_data->levelofunderstanding ?? 'Beginner',
                            'toneofnarrative'      => $SESSION->haccgen_data->toneofnarrative ?? 'Formal',
                            'courseduration'       => $SESSION->haccgen_data->courseduration ?? 'Less than 15 minutes',
                            'numberoftopics'       => $SESSION->haccgen_data->numberoftopics ?? 5,
                            'case_study_data'      => $SESSION->haccgen_data->case_study_data ?? null,
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'timecreated'  => time(),
                    'timemodified' => time(),
                ];

                $job->id = $DB->insert_record('local_haccgen_job', $job);
                // error_log("AI Course Job {$job->id}: created with status 'queued'.");
                $task = new \local_haccgen\task\generate_topiccontent_task();
                // error_log("AI Course Job {$job->id}: queued for processing by adhoc task.");
                $task->set_custom_data(['jobid' => $job->id]);
                $task->set_userid($USER->id);
                $task->set_component('local_haccgen');
                \core\task\manager::queue_adhoc_task($task); // or task_manager::queue_adhoc_task($task);

                // Redirect to polling page (job.php). Do not touch $_SESSION here.
                redirect(new moodle_url('/local/haccgen/job.php', ['id' => $job->id]));
                exit; // be explicit

            } catch (Throwable $e) {
                $errors['general'] = get_string('invalidtopicorder', 'local_haccgen', $e->getMessage());
            }
        }  elseif ($step == 4 && $action === 'save') {

            // ---------------- Logger ----------------
            $logdir = $CFG->dataroot . '/local_haccgen';
            if (!is_dir($logdir)) { @mkdir($logdir, 0770, true); }
            $logfile = $logdir . '/save_' . date('Y-m-d') . '.log';
            $log = function (string $label, $data = null) use ($logfile, $USER, $courseid) {
                $payload = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (is_string($payload) && strlen($payload) > 8000) { $payload = substr($payload, 0, 8000) . '…'; }
                $line = sprintf("[%s] uid=%s course=%s %s: %s\n", date('c'), $USER->id ?? '0', $courseid ?? '0', $label, (string)$payload);
                @file_put_contents($logfile, $line, FILE_APPEND | LOCK_EX);
            };
            // ----------------------------------------
        
            // Params used in this step
            $is_draft       = optional_param('savedraft', 0, PARAM_BOOL);
            $topicsjsonreq  = optional_param('topicsjson', '', PARAM_RAW);   // legacy
            $quizjsonreq    = optional_param('quizjson',   '', PARAM_RAW);   // legacy
        
            // NEW: canonical payload (single JSON) + chunking support
            $payloadjson    = optional_param('payload', '', PARAM_RAW);
            $payload_parts  = optional_param('payload_parts', 0, PARAM_INT);
            if ($payloadjson === '' && $payload_parts > 0) {
                $buf = '';
                for ($i = 1; $i <= $payload_parts; $i++) {
                    $buf .= optional_param("payload_{$i}", '', PARAM_RAW);
                }
                $payloadjson = $buf;
            }
        
            $log('PARAM.savedraft', $is_draft);
            $log('PARAM.payload.raw', $payloadjson);
            $log('PARAM.topicsjson', $topicsjsonreq !== '' ? 'present' : '');  // don’t spam file with huge JSON
            $log('PARAM.quizjson',   $quizjsonreq   !== '' ? 'present' : '');
        
            $log('START', ['step' => $step ?? null, 'action' => $action ?? null, 'is_draft' => (int)$is_draft]);
        
            // ---------- Parse payload if present ----------
            $hasPayload = false;
            $payloadArr = null;
            if ($payloadjson !== '') {
                try {
                    $payloadArr = json_decode($payloadjson, true, 512, JSON_THROW_ON_ERROR);
                    $hasPayload = is_array($payloadArr) && !empty($payloadArr['topics']) && is_array($payloadArr['topics']);
                    $log('PAYLOAD.parsed', [
                        'ok' => true,
                        'topics_total' => $hasPayload ? count($payloadArr['topics']) : 0,
                        'meta' => $payloadArr['meta'] ?? null
                    ]);
                } catch (Throwable $e) {
                    $log('PAYLOAD.parse_error', $e->getMessage());
                    // continue with legacy fields below
                }
            }
        
// ---------- If SAVE DRAFT: stash latest into session and bounce ----------
if ($is_draft) {
    $SESSION->haccgen_data = $SESSION->haccgen_data ?? new stdClass();
    if ($hasPayload) {
        $topicsFlat = [];
        $quizMap    = [];

        foreach ($payloadArr['topics'] as $t) {
            $tTitle = (string)($t['title'] ?? '');
            foreach ((array)($t['subtopics'] ?? []) as $s) {
                $stTitle = (string)($s['title'] ?? '');
                $content = $s['content'] ?? [];
                $text    = is_array($content) ? ($content['text'] ?? '') : (string)$content;
                $itemid  = is_array($content) ? (int)($content['itemid'] ?? 0) : 0;
                $topicsFlat[$stTitle] = ['text' => $text, 'itemid' => $itemid];
            }
            $q = $t['quiz'] ?? ($t['quiz_data'] ?? null);
            if ($q && !empty($q['questions'])) {
                $qt = (string)($q['quiz_title'] ?? $tTitle);
                $quizMap[$qt] = [
                    'quiz_title'   => $qt,
                    'instructions' => (string)($q['instructions'] ?? ''),
                    'questions'    => array_values(array_map(function($qq, $i){
                        return [
                            'question_id'    => $qq['question_id'] ?? 'q'.($i+1),
                            'type'           => $qq['type'] ?? 'multiple_choice',
                            'difficulty'     => $qq['difficulty'] ?? 'easy',
                            'question'       => (string)($qq['question'] ?? ''),
                            'options'        => array_values(array_map('strval', (array)($qq['options'] ?? []))),
                            'correct_answer' => (string)($qq['correct_answer'] ?? ($qq['answer'] ?? '')),
                            'explanation'    => (string)($qq['explanation'] ?? '')
                        ];
                    }, (array)($q['questions'] ?? []), array_keys((array)($q['questions'] ?? [])))),
                ];
            }
        }

        $SESSION->haccgen_data->topicsjson = $topicsFlat;
        $SESSION->haccgen_data->quizjson   = $quizMap;
        $SESSION->haccgen_data->canonical_payload_json = $payloadjson;
        $SESSION->haccgen_data->canonical_payload      = $payloadArr;

        $log('DRAFT_BRANCH_FROM_PAYLOAD', [
            'topics_count'  => count($topicsFlat),
            'quizzes_count' => count($quizMap)
        ]);
    } else {
        if ($topicsjsonreq !== '') {
            $SESSION->haccgen_data->topicsjson = json_decode($topicsjsonreq, true) ?? [];
        }
        if ($quizjsonreq !== '') {
            $SESSION->haccgen_data->quizjson = json_decode($quizjsonreq, true) ?? [];
        }
        $SESSION->haccgen_data->canonical_payload_json = '';
        $SESSION->haccgen_data->canonical_payload      = null;

        $log('DRAFT_BRANCH_FROM_LEGACY', [
            'topics_count'  => count($SESSION->haccgen_data->topicsjson ?? []),
            'quizzes_count' => count($SESSION->haccgen_data->quizjson ?? [])
        ]);
    }

    \core\session\manager::write_close();
    redirect(new moodle_url('/local/haccgen/savedraft.php', ['id' => $courseid, 'step' => $step]));
}

        
            // ---------- Helpers (kept) ----------
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
            if (!function_exists('buildTopicsFromMixed')) {
                function buildTopicsFromMixed($flat, array $baseTopics): array {
                    if (is_string($flat)) {
                        try { $flat = json_decode($flat, true, 512, JSON_THROW_ON_ERROR); }
                        catch (Throwable $e) { $flat = []; }
                    }
                    if (!is_array($flat)) $flat = [];
        
                    $result = $baseTopics ?: [[ 'title' => 'Topic 1', 'subtopics' => [] ]];
                    if (!isset($result[0]['subtopics']) || !is_array($result[0]['subtopics'])) {
                        $result[0]['subtopics'] = [];
                    }
        
                    $index = [];
                    foreach ($result as $tIdx => $topic) {
                        foreach (($topic['subtopics'] ?? []) as $sIdx => $sub) {
                            $key = mb_strtolower(trim((string)($sub['title'] ?? '')));
                            if ($key !== '') $index[$key] = ['t' => $tIdx, 's' => $sIdx];
                        }
                    }
        
                    foreach ($flat as $rawTitle => $rawContent) {
                        $cleanTitle = mb_strtolower(trim((string)$rawTitle));
                        $newText    = is_array($rawContent) ? ($rawContent['text'] ?? '') : (string)$rawContent;
                        $newItemid  = is_array($rawContent) ? (int)($rawContent['itemid'] ?? 0) : 0;
        
                        if ($cleanTitle !== '' && isset($index[$cleanTitle])) {
                            $t = $index[$cleanTitle]['t']; $s = $index[$cleanTitle]['s'];
                            $finalText = trim($newText);
                            $result[$t]['subtopics'][$s]['content'] = ['text' => $finalText, 'itemid' => $newItemid];
                            $result[$t]['subtopics'][$s]['content_html'] = $finalText;
                        } else {
                            $result[0]['subtopics'][] = [
                                'title'        => (string)$rawTitle,
                                'content'      => ['text' => trim($newText), 'itemid' => $newItemid],
                                'examples'     => [],
                                'content_html' => trim($newText),
                            ];
                        }
                    }
                    return $result;
                }
            }
            $norm = static function ($s) { return mb_strtolower(trim((string)$s)); };
        
            // ---------- If payload is present: use it directly ----------
            if ($hasPayload) {
                // Normalise to match your creation loop expectations
                $topics = [];
                foreach ($payloadArr['topics'] as $t) {
                    $topic = [
                        'title'     => (string)($t['title'] ?? 'Untitled Topic'),
                        'subtopics' => []
                    ];
                    foreach ((array)($t['subtopics'] ?? []) as $s) {
                        $stTitle = (string)($s['title'] ?? 'Untitled Subtopic');
                        $content = $s['content'] ?? [];
                        if (!is_array($content)) $content = ['text' => (string)$content, 'itemid' => 0];
                        $topic['subtopics'][] = [
                            'title'   => $stTitle,
                            'content' => [
                                'text'   => (string)($content['text'] ?? ''),
                                'itemid' => (int)($content['itemid'] ?? 0)
                            ]
                        ];
                    }
                    if (!empty($t['quiz'])) {
                        $q = $t['quiz'];
                        $topic['quiz_included'] = 1;
                        $topic['quiz_data'] = [
                            'quiz_title'   => (string)($q['quiz_title'] ?? $topic['title']),
                            'instructions' => (string)($q['instructions'] ?? ''),
                            'questions'    => array_values(array_map(function($qq, $i){
                                return [
                                    'question_id'    => $qq['question_id'] ?? 'q'.($i+1),
                                    'type'           => $qq['type'] ?? 'multiple_choice',
                                    'difficulty'     => $qq['difficulty'] ?? 'easy',
                                    'question'       => (string)($qq['question'] ?? ''),
                                    'options'        => array_values(array_map('strval', (array)($qq['options'] ?? []))),
                                    'correct_answer' => (string)($qq['correct_answer'] ?? ($qq['answer'] ?? '')),
                                    'explanation'    => (string)($qq['explanation'] ?? '')
                                ];
                            }, (array)($q['questions'] ?? []), array_keys((array)($q['questions'] ?? []))))
                        ];
                    }
                    $topics[] = $topic;
                }
                $log('USING_PAYLOAD_TOPICS', ['topics_total' => count($topics)]);
        
                // ---------- Moodle objects ----------
                $newcourse = get_course($courseid);
                $module    = $DB->get_record('modules', ['name' => 'page'], '*', MUST_EXIST);
                $log('Fetched course and page module', ['page_module_id' => $module->id ?? null]);
        
                $existingsections = $DB->get_records('course_sections', ['course' => $courseid]);
                $sectionnumbers   = array_map(static function ($s){ return (int)$s->section; }, $existingsections);
                $sectionnumber    = !empty($sectionnumbers) ? max($sectionnumbers) + 1 : 1;
                $log('Computed starting section number', ['next_sectionnumber' => $sectionnumber, 'existing_sections' => count($existingsections)]);
        
                // ---------- Create sections, pages, quizzes (direct from payload) ----------
                foreach ($topics as $topic) {
                    $topicname = $topic['title'] ?? 'Untitled Topic';
                    $subtopics = $topic['subtopics'] ?? [];
                    if (empty($subtopics)) {
                        $log('ERROR topic has no subtopics', ['topic' => $topicname]);
                        throw new moodle_exception('nosubtopics', 'local_haccgen', '', 'No subtopics found for this topic.');
                    }
        
                    $log('Creating section for topic', [
                        'topic' => $topicname,
                        'sectionnumber' => $sectionnumber,
                        'subtopics' => count($subtopics),
                        'quiz_included' => !empty($topic['quiz_included'])
                    ]);
        
                    $section   = course_create_section($courseid, $sectionnumber);
                    $sectionid = is_object($section) ? $section->id : $section;
                    if ($sectionid) {
                        $DB->set_field('course_sections', 'name', $topicname, ['id' => $sectionid]);
                        $log('Section created/renamed', ['sectionid' => $sectionid]);
                    } else {
                        $log('WARN section creation returned empty id', ['sectionnumber' => $sectionnumber]);
                    }
        
                    // Pages for each subtopic
                    foreach ($subtopics as $sub) {
                        $subtopicname = $sub['title'] ?? 'Untitled Subtopic';
                        $editor = $sub['content'] ?? '';
                        if (!is_array($editor)) { $editor = ['text' => (string)$editor, 'itemid' => 0]; }
                        $draftid = (int)($editor['itemid'] ?? 0);
                        $html    = $editor['text'] ?? '';
        
                        $log('Creating page module', ['subtopic' => $subtopicname, 'draftid' => $draftid, 'html_len' => strlen((string)$html)]);
        
                        $page                = new stdClass();
                        $page->course        = $courseid;
                        $page->name          = $subtopicname;
                        $page->content       = '';
                        $page->contentformat = FORMAT_HTML;
                        $page->intro         = '';
                        $page->introformat   = FORMAT_HTML;
                        $page->timemodified  = time();
                        $page->id            = $DB->insert_record('page', $page);
        
                        $cm                 = new stdClass();
                        $cm->course         = $courseid;
                        $cm->module         = $module->id;
                        $cm->instance       = $page->id;
                        $cm->section        = $sectionnumber;
                        $cm->visible        = 1;
                        $cm->groupmode      = 0;
                        $cm->groupingid     = 0;
                        $cm->added          = time();
                        $cm->id             = add_course_module($cm);
                        course_add_cm_to_section($courseid, $cm->id, $sectionnumber);
        
                        $log('Page CM created and added to section', ['cmid' => $cm->id, 'pageid' => $page->id]);
        
                        $cmcontext  = context_module::instance($cm->id);
                        $editoropts = ['maxfiles' => 10, 'context' => $cmcontext, 'subdirs' => 0];
                        $html = file_save_draft_area_files($draftid, $cmcontext->id, 'mod_page', 'content', 0, $editoropts, $html);
        
                        $page->content = $html;
                        $DB->update_record('page', $page);
                        $log('Page content updated', ['pageid' => $page->id]);
                    }
        
                    // Quiz for topic (if any)
                    if (!empty($topic['quiz_included']) && !empty($topic['quiz_data'])) {
                        $quizdata     = $topic['quiz_data'];
        
                        $log('QUIZ.CREATE_BEGIN', [
                            'topic'            => $topicname,
                            'sectionnumber'    => $sectionnumber,
                            'quiz_title'       => $quizdata['quiz_title'] ?? 'Untitled Quiz',
                            'instructions_len' => strlen((string)($quizdata['instructions'] ?? '')),
                            'questions_count'  => count($quizdata['questions'] ?? [])
                        ]);
        
                        $quizmoduleid = $DB->get_field('modules', 'id', ['name' => 'quiz'], MUST_EXIST);
                        if (!empty($quizmoduleid)) {
                            $quizsettings = (object)[
                                'modulename'           => 'quiz',
                                'module'               => $quizmoduleid,
                                'course'               => $courseid,
                                'section'              => $sectionnumber,
                                'visible'              => 1,
                                'visibleold'           => 1,
                                'visibleoncoursepage'  => 1,
                                'name'                 => ($quizdata['quiz_title'] ?? 'Untitled Quiz'),
                                'intro'                => '<p>' . ($quizdata['instructions'] ?? '') . '</p>',
                                'introformat'          => FORMAT_HTML,
                                'preferredbehaviour'   => 'deferredfeedback',
                                'grade'                => 10,
                                'sumgrades'            => count($quizdata['questions'] ?? []),
                                'questionsperpage'     => 1,
                                'timeopen'             => 0,
                                'timeclose'            => 0,
                                'timelimit'            => 0,
                                'quizpassword'         => ''
                            ];
        
                            $log('QUIZ.SETTINGS', [
                                'name'               => $quizsettings->name,
                                'preferredbehaviour' => $quizsettings->preferredbehaviour,
                                'grade'              => $quizsettings->grade,
                                'sumgrades'          => $quizsettings->sumgrades,
                                'questionsperpage'   => $quizsettings->questionsperpage
                            ]);
        
                            $cmquiz = add_moduleinfo($quizsettings, $newcourse);
                            $cmid   = is_object($cmquiz) && isset($cmquiz->coursemodule) ? (int)$cmquiz->coursemodule : 0;
                            $quizid = is_object($cmquiz) && isset($cmquiz->instance)     ? (int)$cmquiz->instance     : 0;
        
                            $log('QUIZ.ADDED', ['cmid' => $cmid, 'quizid' => $quizid, 'ok' => (bool)$quizid]);
        
                            if ($quizid) {
                                // Review settings
                                $okUpdate = $DB->update_record('quiz', [
                                    'id'                      => $quizid,
                                    'reviewattempt'           => 69632,
                                    'reviewcorrectness'       => 4096,
                                    'reviewmaxmarks'          => 4096,
                                    'reviewmarks'             => 4096,
                                    'reviewspecificfeedback'  => 4096,
                                    'reviewgeneralfeedback'   => 4096,
                                    'reviewrightanswer'       => 4096,
                                    'reviewoverallfeedback'   => 4096
                                ]);
                                $log('QUIZ.REVIEW_SETTINGS_UPDATED', ['quizid' => $quizid, 'ok' => (bool)$okUpdate]);
        
                                // Context & default category
                                $realcm  = get_coursemodule_from_instance('quiz', $quizid, $courseid, false, MUST_EXIST);
                                $quiz    = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
                                $quizctx = context_module::instance($realcm->id);
                                $log('QUIZ.CONTEXT', ['cmid' => $realcm->id, 'contextid' => $quizctx->id]);
        
                                $catobj = question_make_default_categories([$quizctx]);
                                if (!empty($catobj->id)) {
                                    $catid = (int)$catobj->id;
                                    $log('QUIZ.CATEGORY_READY', ['catid' => $catid]);
        
                                    // Import MCQ questions
                                    $qSaved = 0; $qSkipped = 0;
                                    foreach ($quizdata['questions'] as $index => $qdata) {
                                        $qtypeName = $qdata['type'] ?? 'multiple_choice';
                                        if ($qtypeName !== 'multiple_choice') {
                                            $qSkipped++; $log('QUIZ.Q.SKIP_UNSUPPORTED', ['index' => $index, 'type' => $qtypeName]); continue;
                                        }
        
                                        $options = $qdata['options'] ?? [];
                                        $correctLetter = $qdata['correct_answer'] ?? '';
        
                                        $form = new stdClass();
                                        $form->category                 = $catid;
                                        $form->contextid                = $quizctx->id;
                                        $form->qtype                    = 'multichoice';
                                        $form->name                     = $qdata['question'];
                                        $form->questiontext             = ['text' => $qdata['question'], 'format' => FORMAT_HTML];
                                        $form->generalfeedback          = ['text' => $qdata['explanation'] ?? '', 'format' => FORMAT_HTML];
                                        $form->defaultmark              = 1;
                                        $form->penalty                  = 0.1;
                                        $form->single                   = 1;
                                        $form->shuffleanswers           = 1;
                                        $form->answernumbering          = 'abc';
                                        $form->correctfeedback          = ['text' => '', 'format' => FORMAT_HTML];
                                        $form->partiallycorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
                                        $form->incorrectfeedback        = ['text' => '', 'format' => FORMAT_HTML];
                                        $form->layout                   = 0;
                                        $form->showstandardinstruction  = 1;
                                        $form->shownumcorrect           = 1;
                                        $form->answer   = [];
                                        $form->fraction = [];
                                        $form->feedback = [];
        
                                        foreach ($options as $i => $optionText) {
                                            $letter    = chr(65 + $i);
                                            $isCorrect = strtoupper((string)$correctLetter) === $letter;
                                            $form->answer[]   = ['text' => $optionText, 'format' => FORMAT_HTML];
                                            $form->fraction[] = $isCorrect ? 1 : 0;
                                            $form->feedback[] = ['text' => $isCorrect ? 'Correct!' : 'Incorrect.', 'format' => FORMAT_HTML];
                                        }
        
                                        try {
                                            $qtype    = question_bank::get_qtype('multichoice');
                                            $question = $qtype->save_question((object)['category' => $catid, 'qtype' => 'multichoice'], $form);
                                            quiz_add_quiz_question($question->id, $quiz);
                                            $qSaved++; $log('QUIZ.Q.SAVED', ['index' => $index, 'questionid' => $question->id]);
                                        } catch (Exception $e) {
                                            $qSkipped++; $log('QUIZ.Q.ERROR_SAVE', ['index' => $index, 'message' => $e->getMessage()]);
                                        }
                                    }
                                    $log('QUIZ.CREATE_SUMMARY', ['quizid' => $quizid, 'saved' => $qSaved, 'skipped' => $qSkipped]);
                                } else {
                                    $log('QUIZ.ERROR_NO_CATEGORY', ['contextid' => $quizctx->id]);
                                }
                            } else {
                                $log('QUIZ.ERROR_ADD_MODULEINFO_FAILED', ['topic' => $topicname]);
                            }
        
                            $log('QUIZ.CREATE_END', ['topic' => $topicname]);
                        }
                    }
        
                    $sectionnumber++;
                    $log('Finished topic section', ['next_sectionnumber' => $sectionnumber]);
                }
        
                // ---------- Wrap up ----------
                rebuild_course_cache($courseid);
                unset($SESSION->haccgen_data);
                $log('Rebuilt course cache; redirecting');
                redirect(new moodle_url('/course/view.php', ['id' => $courseid]), get_string('content_generated', 'local_haccgen'));
                exit;
            }
        
            // ---------- Legacy path (no payload) ----------
            // (Your existing legacy code stays as-is from here down)
            // ---------- topicsjson ----------
            if ($topicsjsonreq !== '') {
                $log('topicsjsonreq(raw)', ['bytes' => strlen($topicsjsonreq)]);
                $subtopiccontents = json_decode($topicsjsonreq, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $log('topicsjsonreq(JSON_ERROR)', json_last_error_msg());
                    throw new moodle_exception('invalidjson', 'local_haccgen', '', 'topicsjson: ' . json_last_error_msg());
                }
                $log('topicsjson(parsed)', ['count' => is_array($subtopiccontents) ? count($subtopiccontents) : 0]);
            } else {
                $subtopiccontents = $SESSION->haccgen_data->topicsjson ?? [];
                $log('topicsjson(fallback_from_session)', ['count' => is_array($subtopiccontents) ? count($subtopiccontents) : 0]);
            }
            $SESSION->haccgen_data->topicsjson = $subtopiccontents;
            $log('SESSION.topicsjson.set', ['subtopics_count' => is_array($subtopiccontents) ? count($subtopiccontents) : 0]);
        
            // ---------- quizjson ----------
            if ($quizjsonreq !== '') {
                $log('quizjsonreq(raw)', ['bytes' => strlen($quizjsonreq)]);
                $quizcontents = json_decode($quizjsonreq, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $log('quizjsonreq(JSON_ERROR)', json_last_error_msg());
                    throw new moodle_exception('invalidjson','local_haccgen','', 'quizjson: ' . json_last_error_msg());
                }
                $cleanQuiz = [];
                foreach ($quizcontents as $rawKey => $payload) {
                    $title = $payload['quiz_title'] ?? preg_replace('/^quiz:\s*/i', '', trim($rawKey));
                    $payload['quiz_title'] = $title;
                    $cleanQuiz[ normalise_title($title) ] = $payload;
                }
                $quizcontents = $cleanQuiz;
                $log('quizjson(parsed_and_normalized)', ['count' => count($quizcontents), 'keys' => array_keys($quizcontents)]);
            } else {
                $quizcontents = $SESSION->haccgen_data->quizjson ?? [];
                $log('quizjson(fallback_from_session)', ['count' => is_array($quizcontents) ? count($quizcontents) : 0]);
            }
            $SESSION->haccgen_data->quizjson = $quizcontents;
        
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new moodle_exception('invalidjson', 'local_haccgen', '', 'JSON decode error: ' . json_last_error_msg());
            }
        
            // ---------- Moodle objects ----------
            $newcourse = get_course($courseid);
            $module    = $DB->get_record('modules', ['name' => 'page'], '*', MUST_EXIST);
            $log('Fetched course and page module', ['page_module_id' => $module->id ?? null]);
        
            $existingsections = $DB->get_records('course_sections', ['course' => $courseid]);
            $sectionnumbers   = array_map(static function ($s){ return (int)$s->section; }, $existingsections);
            $sectionnumber    = !empty($sectionnumbers) ? max($sectionnumbers) + 1 : 1;
            $log('Computed starting section number', ['next_sectionnumber' => $sectionnumber, 'existing_sections' => count($existingsections)]);
        
            // ---------- Build topics structure from base + flat ----------
            $baseTopics = json_decode(json_encode($SESSION->haccgen_data->topics ?? []), true);
            if (!is_array($baseTopics) || empty($baseTopics)) {
                $baseTopics = [[ 'title' => $newcourse->fullname ?: 'Topic 1', 'subtopics' => [] ]];
            }
            $topics = buildTopicsFromMixed($subtopiccontents, $baseTopics);
            $log('Initial topics built (pre WYWL injection)', ['topics' => count($topics)]);
        
            // ---------- (legacy) WYWL & quiz mapping & creation ----------
            // (keep your existing legacy code from here down unchanged)
            // ... [KEEP THE REST OF YOUR ORIGINAL LEGACY CODE BLOCK UNCHANGED] ...
        }
        
         elseif ($step < 3) {
            $nextstep = $step + 1;
            ob_end_clean();
            redirect(new moodle_url('/local/haccgen/manage.php', ['id' => $courseid, 'step' => $nextstep]));
            exit;
        }
    }
}

$step_states = [
    'is_step1_active' => $step >= 1,
    'is_step2_active' => $step >= 2,
    'is_step3_active' => $step >= 3,
    'is_step4_active' => $step >= 4,

    'is_connector1_active' => $step >= 2,
    'is_connector2_active' => $step >= 3,
    'is_connector3_active' => $step >= 4,

];

$formdata = [
    'action' => new moodle_url('/local/haccgen/manage.php', ['id' => $courseid, 'step' => $step]),
    'cancelurl' => new moodle_url('/course/view.php', ['id' => $courseid]),
    'courseid' => $courseid,
    'errors' => $errors,
    'TOPICTITLE' => $SESSION->haccgen_data->TOPICTITLE ?? '',
    'targetaudience' => $SESSION->haccgen_data->targetaudience ?? '',
    'audiencetags' => !empty($SESSION->haccgen_data->targetaudience) ? explode(',', $SESSION->haccgen_data->targetaudience) : [],
    'language' => isset($_POST['language']) ? clean_param($_POST['language'], PARAM_TEXT) : ($SESSION->haccgen_data->language ?? 'english'),
    'levelofunderstanding' => isset($_POST['levelofunderstanding']) ? clean_param($_POST['levelofunderstanding'], PARAM_TEXT) : ($SESSION->haccgen_data->levelofunderstanding ?? ''),
    'toneofnarrative' => isset($_POST['toneofnarrative']) ? clean_param($_POST['toneofnarrative'], PARAM_TEXT) : ($SESSION->haccgen_data->toneofnarrative ?? ''),
    'courseduration' => isset($_POST['courseduration']) ? clean_param($_POST['courseduration'], PARAM_TEXT) : ($SESSION->haccgen_data->courseduration ?? ''),

    'description' => $SESSION->haccgen_data->description ?? '',
    'generation_type' => $SESSION->haccgen_data->generation_type ?? 'ai',
    'pdfuploaded' => $SESSION->haccgen_data->pdf_file ?? '',
    'currentstep' => $step,
    'step_states' => $step_states,
    'has_levelofunderstanding' => !empty($SESSION->haccgen_data->levelofunderstanding),
    'has_toneofnarrative' => !empty($SESSION->haccgen_data->toneofnarrative),
    'has_courseduration' => !empty($SESSION->haccgen_data->courseduration),
    'is_level_beginner' => ($SESSION->haccgen_data->levelofunderstanding ?? '') === 'Beginner',
    'is_level_intermediate' => ($SESSION->haccgen_data->levelofunderstanding ?? '') === 'Intermediate',
    'is_level_advanced' => ($SESSION->haccgen_data->levelofunderstanding ?? '') === 'Advanced',
    'is_tone_formal' => ($SESSION->haccgen_data->toneofnarrative ?? '') === 'Formal',
    'is_tone_conversational' => ($SESSION->haccgen_data->toneofnarrative ?? '') === 'Conversational',
    'is_tone_engaging' => ($SESSION->haccgen_data->toneofnarrative ?? '') === 'Engaging',
    'is_duration_10minutes' => ($SESSION->haccgen_data->courseduration ?? '') === 'Less than 10 minutes',
    'is_duration_15minutes' => ($SESSION->haccgen_data->courseduration ?? '') === 'Less than 15 minutes',
    'is_duration_30minutes' => ($SESSION->haccgen_data->courseduration ?? '') === 'Less than 30 minutes',
    'courselanguage' => $SESSION->haccgen_data->courselanguage ?? 'English',
    'numberoftopics' => $SESSION->haccgen_data->numberoftopics ?? 5,
    'is_language_en' => ($SESSION->haccgen_data->courselanguage ?? '') === 'English',
    'is_language_hn' => ($SESSION->haccgen_data->courselanguage ?? '') === 'Hindi',
    'customprompt' => $SESSION->haccgen_data->customprompt ?? '',
    'contenteditor' => $textarea,


];

if ($step == 3) {
    $data = new stdClass();
    $data->coursename = $SESSION->haccgen_data->TOPICTITLE ?? '';
    $data->targetaudience = $SESSION->haccgen_data->targetaudience ?? '';
    $data->levelofunderstanding = $SESSION->haccgen_data->levelofunderstanding ?? 'Beginner';
    $data->toneofnarrative = $SESSION->haccgen_data->toneofnarrative ?? 'Conversational';
    $data->courseduration = $SESSION->haccgen_data->courseduration ?? 'Less than 30 minutes';
    $data->generation_type = $SESSION->haccgen_data->generation_type ?? 'ai';
    $data->pdf_fileid = $SESSION->haccgen_data->pdf_fileid ?? 0;
    $data->description = $SESSION->haccgen_data->description ?? '';
    $data->customprompt = $SESSION->haccgen_data->customprompt ?? '';
    $data->courselanguage = $SESSION->haccgen_data->courselanguage ?? 'English';
    $data->numberoftopics = $SESSION->haccgen_data->numberoftopics ?? 5;
    $data->pdf_reference_url = $SESSION->haccgen_data->pdf_reference_url ?? '';


    // Validate required fields
    $required = ['coursename', 'targetaudience', 'levelofunderstanding', 'toneofnarrative', 'courseduration', 'numberoftopics'];

    foreach ($required as $field) {
        if (empty($data->$field)) {
            $formdata['errors']['general'] = get_string('missingparam', 'local_haccgen', $field);
            $formdata['has_topics'] = false;
            $formdata['topics'] = [];
            break;
        }
    }
    // Validate allowed values
    $valid_levels = ['Beginner', 'Intermediate', 'Advanced'];
    $valid_tones = ['Conversational', 'Professional', 'Friendly', 'Technical', 'Formal', 'Engaging'];
    $valid_durations = ['Less than 15 minutes', 'Less than 30 minutes', 'Less than 60 minutes', 'Less than 90 minutes', 'Less than 120 minutes'];
    $valid_languages = ['English', 'Hindi'];
    if (!in_array($data->levelofunderstanding, $valid_levels)) {
        $formdata['errors']['general'] = get_string('invalidparam', 'local_haccgen', 'level of understanding');
    } elseif (!in_array($data->toneofnarrative, $valid_tones)) {
        $formdata['errors']['general'] = get_string('invalidparam', 'local_haccgen', 'tone of narrative');
    } elseif (!in_array($data->courseduration, $valid_durations)) {
        $formdata['errors']['general'] = get_string('invalidparam', 'local_haccgen', 'course duration');
    } elseif (!in_array($data->courselanguage, $valid_languages)) {
        $formdata['errors']['general'] = get_string('invalidparam', 'local_haccgen', 'language');
    } elseif ($data->numberoftopics < 2 || $data->numberoftopics > 10) {
        $formdata['errors']['general'] = get_string('invalidparam', 'local_haccgen', 'number of topics');
    }
    if (empty($formdata['errors'])) {
        try {
            // ✅ Single API call
            $result = local_haccgen_api::generate_subtopics_only($data);

            $subtopics = $result['subtopics'];
            $caseStudy = $result['case_study_data'] ?? null;
            $learningObjectives1 = $result['learning_objectives'] ?? [];

            // ✅ Store in session (AFTER actual data retrieved)
            $SESSION->haccgen_data->course_title = $data->coursename;
            $SESSION->haccgen_data->raw_subtopics = $subtopics;
            $SESSION->haccgen_data->courselanguage = $data->courselanguage;
            $SESSION->haccgen_data->numberoftopics = $data->numberoftopics;
            $SESSION->haccgen_data->learning_objectives1 = $learningObjectives1;
            // error_log('learning_objectives: ' . json_encode($learningObjectives1, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $SESSION->haccgen_data->case_study_data = is_array($caseStudy)
                ? $caseStudy
                : json_decode(json_encode($caseStudy), true); // ensure array format

            // ✅ Store it in local $data for later steps
            $data->case_study_data = $SESSION->haccgen_data->case_study_data;

            // ✅ Format topics
            $formdata['topics'] = array_map(function ($index, $subtopic) use ($subtopics) {
                $topicData = [
                    'id' => $subtopic['id'] ?? uniqid('topic_'),
                    'title' => $subtopic['title'] ?? 'Untitled',
                    'description' => $subtopic['description'] ?? '',
                    'estimated_duration' => $subtopic['estimated_duration'] ?? '',
                    'learning_objectives' => $subtopic['learning_objectives'] ?? [],
                ];

                return array_merge($topicData, [
                    'is_first' => $index === 0,
                    'is_last' => $index === count($subtopics) - 1,
                    '@index_plus_one' => $index + 1,
                    'encoded_topicdata' => rawurlencode(json_encode($topicData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                ]);
            }, array_keys($subtopics), $subtopics);

            $formdata['has_topics'] = true;
        } catch (moodle_exception $e) {
            // ✅ Directly show the custom message from api.php language strings
            $formdata['errors']['general'] = $e->getMessage();
            $formdata['has_topics'] = false;
            $formdata['topics'] = [];

            debugging('Subtopic generation failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        } catch (Exception $e) {
            // ✅ Catch any unexpected error
            $formdata['errors']['general'] = get_string('contentgenerationfailed', 'local_haccgen');
            $formdata['has_topics'] = false;
            $formdata['topics'] = [];

            debugging('Unexpected error during subtopic generation: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
} elseif ($step == 4) {


$loaddraft = optional_param('loaddraft', 0, PARAM_BOOL);
if ($loaddraft && !empty($SESSION->haccgen_draft_data)) {
    $SESSION->haccgen_data->topics   = $SESSION->haccgen_draft_data['topics']  ?? [];
    $SESSION->haccgen_data->quizjson = $SESSION->haccgen_draft_data['quizzes'] ?? [];
    unset($SESSION->haccgen_draft_data);
}


    $formdata['topics'] = $SESSION->haccgen_data->topics ?? [];
    // error_log('Topics data: ' . print_r($formdata['topics'], true));
    // initialize maps
    $subtopicContentMap = [];
    $quizContentMap = [];
    $learningObjectivesMap = [];

    // iterate by index (avoid foreach by reference issues)
    $topics = $formdata['topics'] ?? [];

    for ($t = 0; $t < count($topics); $t++) {
        $topic = $topics[$t]; // copy, not reference
        $processedSubtopics = [];
        $topicObjectives = []; // reset for THIS topic only

        $existingSubs = $topic['subtopics'] ?? [];

        foreach ($existingSubs as $s => $sub) {
            $displayTitle = $sub['title'] ?? "Untitled Subtopic";

            if (!empty($sub['learning_objectives'])) {
                $objectives = array_map('strval', $sub['learning_objectives']);
                $sub['json_learning_objectives'] = json_encode($objectives, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $learningObjectivesMap[$displayTitle] = $objectives;
                $topicObjectives = array_merge($topicObjectives, $objectives);
            }

            $contentHtml = $sub['content_html'] ?? ($sub['content']['text'] ?? '');
            if ($contentHtml === '') {
                $contentHtml = '<p>No content available.</p>';
            }
            $subtopicContentMap[$displayTitle] = $contentHtml;
            $sub['content_html'] = $sub['content_html'] ?? $contentHtml;
            if (empty($sub['content']['text'])) {
                $sub['content'] = ['text' => $contentHtml, 'itemid' => (int)($sub['content']['itemid'] ?? 0)];
            }
            $processedSubtopics[] = $sub;
            
        }

        // Create WYWL for THIS topic
        if (!empty($topicObjectives)) {
            $topicObjectives = array_values(array_unique($topicObjectives));
            $objectivesHtml = '<h4>Learning objectives</h4><ul><li>' .
                implode('</li><li>', $topicObjectives) .
                '</li></ul>';

            $wywlTitle = "Learning objectives - {$topic['title']}";
            $wywlSub = [
                'title' => $wywlTitle,
                'content_html' => $objectivesHtml,
                'content' => ['text' => $objectivesHtml, 'itemid' => 0],
                'json_learning_objectives' => json_encode($topicObjectives, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ];
            

            // Insert at first position
            array_unshift($processedSubtopics, $wywlSub);

            $subtopicContentMap[$wywlTitle] = $objectivesHtml;
        }

        // Assign processed subtopics back to THIS topic only
        $topic['subtopics'] = $processedSubtopics;
        $topics[$t] = $topic;
    }

    $formdata['topics'] = $topics;
    // Insert course-level WYWL as Topic 1
    $courseLevelObjectives = $SESSION->haccgen_data->learning_objectives1 ?? [];
    if (!empty($courseLevelObjectives)) {
        if (is_array($courseLevelObjectives)) {
            $courseLevelContent = '<ul>';
            foreach ($courseLevelObjectives as $obj) {
                $courseLevelContent .= '<li>' . htmlspecialchars($obj) . '</li>';
            }
            $courseLevelContent .= '</ul>';
        } else {
            $courseLevelContent = htmlspecialchars((string)$courseLevelObjectives);
        }
        // ✅ Render professional preview Mustache.
        $previewHtml = $OUTPUT->render_from_template('local_haccgen/preview_course', [
            'TOPICTITLE'           => $formdata['TOPICTITLE'] ?? '',
            'targetaudience'       => $formdata['targetaudience'] ?? '',
            'levelofunderstanding' => $formdata['levelofunderstanding'] ?? '',
            'toneofnarrative'      => $formdata['toneofnarrative'] ?? '',
            'courseduration'       => $formdata['courseduration'] ?? '',
            'pdfuploaded'          => $formdata['pdfuploaded'] ?? '',
            'audiencetags'         => $formdata['audiencetags'] ?? [],
            'objectives_html'      => $courseLevelContent,   // ✅ added here
        ]);

        $courseOverviewTopic = [
            'title' => 'About this course',
            'subtopics' => [
                [
                    'title' => 'About this course',
                    'content_html' => $previewHtml,
                ]
            ]
        ];

        // Also add to subtopic content map for frontend rendering
        $subtopicContentMap['About this course'] = $previewHtml;

        array_unshift($formdata['topics'], $courseOverviewTopic);
    }




    // build quiz map (unchanged)
    foreach ($formdata['topics'] as $topic) {
        if (!empty($topic['quiz_data']['quiz_title']) && !empty($topic['quiz_data']['questions'])) {
            $quizTitle = $topic['quiz_data']['quiz_title'];
            $questions = [];
            foreach ($topic['quiz_data']['questions'] as $q) {
                $questions[] = [
                    'question' => $q['question'] ?? '',
                    'options'  => $q['options'] ?? [],
                    'answer'   => $q['correct_answer'] ?? '',
                    'explanation' => $q['explanation'] ?? ''
                ];
            }
            $quizContentMap[$quizTitle] = [
                'instructions' => $topic['quiz_data']['instructions'] ?? '',
                'questions' => $questions
            ];
        }
    }

    // final JSON to pass to the template
    $formdata['subtopicContentJson'] = json_encode($subtopicContentMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    // error_log('subtopicContentJson: ' . $formdata['subtopicContentJson']);
    $formdata['quizContentJson'] = json_encode($quizContentMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $formdata['learningObjectiveJson'] = json_encode($learningObjectivesMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $formdata['topicsjson'] = json_encode($formdata['topics'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
//$formdata = [
// 'courseid' => $courseid,
// 'hasdraft' => $hasdraft,
// ... other data
//];
$formdata['courseid'] = $courseid;
$formdata['hasdraft'] = $hasdraft;

$formdata['TOPICTITLE_helpicon'] = [
    'text' => 'Enter the main title for your topic.',
    'component' => 'local_haccgen',
];

$formdata['targetaudience_helpicon'] = [
    'text' => 'Who this course is intended for (e.g., students, professionals).',
    'component' => 'local_haccgen',
];

$formdata['description_helpicon'] = [
    'text' => 'A brief summary of what this course covers.',
    'component' => 'local_haccgen',
];

$formdata['pdfupload_helpicon'] = [
    'text' => 'Upload a PDF file containing course material or content to extract topics from.',
    'component' => 'local_haccgen',
];

$formdata['levelofunderstanding_helpicon'] = [
    'text' => 'Select the learner’s proficiency level (e.g., Beginner, Intermediate, Advanced).',
    'component' => 'local_haccgen',
];

$formdata['toneofnarrative_helpicon'] = [
    'text' => 'Choose the tone you want the course to follow (e.g., Formal, Conversational, Engaging).',
    'component' => 'local_haccgen',
];

$formdata['courseduration_helpicon'] = [
    'text' => 'Specify how long the course should be (e.g., Less than 15, 30, or 60 minutes).',
    'component' => 'local_haccgen',
];
$formdata['statusmessage'] = $statusmessage;
$formdata['statusclasss'] = $statusclass;
$formdata['status_active'] = $status_active;

echo $OUTPUT->header();
if ($step == 1) {
    echo $OUTPUT->render_from_template('local_haccgen/ai_form', $formdata);
} elseif ($step == 2) {
    echo $OUTPUT->render_from_template('local_haccgen/step2_form', $formdata);
} elseif ($step == 3) {
    echo $OUTPUT->render_from_template('local_haccgen/step3_form', $formdata);
} elseif ($step == 4) {
    echo $OUTPUT->render_from_template('local_haccgen/step4_form', $formdata);
}
echo $OUTPUT->footer();

ob_end_flush();