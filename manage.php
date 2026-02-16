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
 * Short description of file purpose.
 *
 * @package     local_haccgen
 * @copyright   2026 Dynamicpixel Multimedia Solutions
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/.extlib/vendor/autoload.php');
require_once($CFG->dirroot . '/local/haccgen/lib.php');
require_once($CFG->dirroot . '/local/haccgen/settings.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/question/engine/bank.php');

try {
    $endpoint = local_haccgen_api::get_subscription_url();
    $statusmessage = null;
    $statusclass = null;
    $statusactive = true;
} catch (moodle_exception $e) {
    $statusmessage = $e->getMessage();
    $statusclass = 'alert alert-danger';
    $statusactive = false;
}

$globalscormtype = get_config('local_haccgen', 'scormtype');
$globalpassingscore = get_config('local_haccgen', 'passingscore');
$globalscormversion = get_config('local_haccgen', 'scormversion');

$isscorm = optional_param('make_scorm', 0, PARAM_BOOL);

$courseid = required_param('id', PARAM_INT);
$step = optional_param('step', 1, PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($courseid);
require_capability('local/haccgen:manage', $context);

$PAGE->set_url('/local/haccgen/manage.php', ['id' => $courseid, 'step' => $step]);
$PAGE->set_title(get_string('manageai', 'local_haccgen'));
$PAGE->set_heading($course->fullname);
$PAGE->requires->js('/lib/requirejs.php'); // Load RequireJS.

$editorid = 'id_contenteditor';

// Editor options array.
$editoroptions = [
    'maxfiles' => -1,
    'maxbytes' => 0,
    'trusttext' => true,
    'context' => $context,
];

// Attach the editor to the textarea.
$editor = editors_get_preferred_editor(FORMAT_HTML);
$editorinitjs = $editor->use_editor($editorid, $editoroptions);

// Create the actual <textarea> element.
$textarea = html_writer::tag('textarea', '', [
    'id' => $editorid,
    'name' => 'contenteditor',
    'rows' => 10,
    'cols' => 60,
    'class' => 'form-control',
]);

ob_start();

// Initialize or convert session data to an object.
if (!isset($SESSION->haccgen_data) || !is_object($SESSION->haccgen_data)) {
    if (isset($SESSION->haccgen_data) && is_array($SESSION->haccgen_data)) {
        // Convert existing array to object, taking the last entry if multiple.
        $SESSION->haccgen_data = (object) array_pop($SESSION->haccgen_data);
    } else {
        $SESSION->haccgen_data = new stdClass();
    }
}

$hasdraft = $DB->record_exists('local_haccgen_content', [
    'courseid' => $courseid,
    'userid' => $USER->id,
]);

$errors = [];

// Always define this early (it was used before definition in the original file).
$isdraft = optional_param('savedraft', 0, PARAM_BOOL);

if (!empty($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = optional_param('action', '', PARAM_TEXT);

    $topicsjsonreq = optional_param('topicsjson', '', PARAM_RAW);
    $quizjsonreq = optional_param('quizjson', '', PARAM_RAW);

    $activelang = optional_param('activelang', '', PARAM_RAW_TRIMMED);

    // Language: session is source of truth (only update session if POST has a real value).
    $postedlang = trim($activelang);
    if ($postedlang !== '') {
        $SESSION->haccgen_data->activelang = $postedlang;
    }

    // Always use session value from here on (never trust empty POST).
    $activelang = $SESSION->haccgen_data->activelang ?? 'English';

    if ($action === 'back') {
        $prevstep = max(1, $step - 1);
        ob_end_clean();
        redirect(new moodle_url('/local/haccgen/manage.php', ['id' => $courseid, 'step' => $prevstep]));
        exit;
    }

    $data = new stdClass();
    $generationtype = optional_param('generationtype', 'ai', PARAM_TEXT);

    if ($step == 1) {
        $data->generationtype = $generationtype;

        if ($generationtype === 'ai') {
            // Validate AI fields.
            $data->coursename = optional_param('TOPICTITLE', '', PARAM_TEXT);
            if (trim($data->coursename) === '') {
                $errors['TOPICTITLE'] = get_string('error_required', 'local_haccgen');
            }

            $data->targetaudience = optional_param('targetaudience', '', PARAM_TEXT);
            if (empty($data->targetaudience)) {
                $errors['targetaudience'] = get_string('error_required', 'local_haccgen');
            }

            $data->description = optional_param('description', '', PARAM_TEXT);

            // Explicitly ignore all uploaded fields.
            $data->coursename_uploaded = '';
            $data->targetaudience_uploaded = '';
            $data->description_uploaded = '';
            $data->pdf_file = '';
            $data->pdf_fileid = 0;
            $data->pdf_reference_url = '';

            // New fields for AI generation.
            $data->customprompt = optional_param('customprompt', '', PARAM_TEXT);
            $data->activelang = $activelang;

            if ($isdraft) {
                $formdata['loaddrafturl'] = new moodle_url('/local/haccgen/old_draft.php', ['id' => $courseid]);
            }
        } else {
            // Validate uploaded fields (do NOT unset $_POST; Moodle coding style discourages it).
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
            $data->activelang = $activelang;

            if (isset($_FILES['pdf_upload']) && $_FILES['pdf_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['pdf_upload'];

                if ($file['error'] === UPLOAD_ERR_OK) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimetype = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);

                    if ($mimetype === 'application/pdf') {
                        $fs = get_file_storage();

                        $originalname = clean_filename($file['name']);
                        $ext = pathinfo($originalname, PATHINFO_EXTENSION);
                        $basename = pathinfo($originalname, PATHINFO_FILENAME);
                        $uniquefilename = $basename . '_' . time() . '.' . $ext;

                        $data->pdf_file = $uniquefilename;

                        // Values you will also pass to make_pluginfile_url().
                        $contextid = $context->id;
                        $component = 'local_haccgen';
                        $filearea = 'uploads';
                        $itemid = $courseid;
                        $filepath = '/';
                        $filename = $uniquefilename;

                        // Create the file.
                        $filerecord = [
                            'contextid' => $contextid,
                            'component' => $component,
                            'filearea' => $filearea,
                            'itemid' => $itemid,
                            'filepath' => $filepath,
                            'filename' => $filename,
                            'timecreated' => time(),
                            'timemodified' => time(),
                        ];
                        $fs->create_file_from_pathname($filerecord, $file['tmp_name']);

                        // Sign URL.
                        $expires = time() + 3600;
                        $secret = (string) get_config('local_haccgen', 'linksecret');
                        $payload = implode('|', [$contextid, $component, $filearea, $itemid, $filepath, $filename, $expires]);
                        $token = hash_hmac('sha256', $payload, $secret);

                        // URL.
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

            // Explicitly ignore all AI fields.
            $data->TOPICTITLE_ai = '';
            $data->targetaudience_ai = '';
            $data->description_ai = '';
            $data->customprompt_ai = '';
        }

        $data->courseduration = 'Less than 15 minutes';
        $data->levelofunderstanding = 'Beginner';
        $data->toneofnarrative = 'Formal';

        if ($isdraft) {
            $formdata['loaddrafturl'] = new moodle_url('/local/haccgen/old_draft.php', ['id' => $courseid]);
        }
    } else if ($step == 2) {
        $validlevels = ['Beginner', 'Intermediate', 'Advanced'];
        $validdurations = [
            'Less than 15 minutes',
            'Less than 30 minutes',
            'Less than 60 minutes',
            'Less than 90 minutes',
            'Less than 120 minutes',
        ];
        $validtones = ['Formal', 'Conversational', 'Engaging'];
        $validlanguages = ['English', 'Hindi'];
        $mintopics = 2;
        $maxtopics = 10;

        $data->levelofunderstanding = required_param('levelofunderstanding', PARAM_TEXT);
        if (!in_array($data->levelofunderstanding, $validlevels)) {
            $errors['levelofunderstanding'] = get_string('please_select', 'local_haccgen');
        }

        $data->toneofnarrative = required_param('toneofnarrative', PARAM_TEXT);
        if (!in_array($data->toneofnarrative, $validtones)) {
            $errors['toneofnarrative'] = get_string('please_select', 'local_haccgen');
        }

        $data->courseduration = required_param('courseduration', PARAM_TEXT);
        if (!in_array($data->courseduration, $validdurations)) {
            $errors['courseduration'] = get_string('please_select', 'local_haccgen');
        }

        $data->courselanguage = required_param('courselanguage', PARAM_TEXT);
        if (!in_array($data->courselanguage, $validlanguages)) {
            $errors['courselanguage'] = get_string('please_select', 'local_haccgen');
        }

        $data->numberoftopics = optional_param('numberoftopics', 5, PARAM_INT);
        if ($data->numberoftopics < $mintopics || $data->numberoftopics > $maxtopics) {
            $errors['numberoftopics'] = get_string('invalid_topic_count', 'local_haccgen');
        }

        // Existing session data retrieval.
        $data->coursename = $SESSION->haccgen_data->TOPICTITLE ?? '';
        $data->targetaudience = $SESSION->haccgen_data->targetaudience ?? '';
        $data->description = $SESSION->haccgen_data->description ?? '';
        $data->generationtype = $SESSION->haccgen_data->generationtype ?? 'ai';
        $data->pdf_file = $SESSION->haccgen_data->pdf_file ?? '';
        $data->pdf_fileid = $SESSION->haccgen_data->pdf_fileid ?? 0;
        $data->pdf_reference_url = $SESSION->haccgen_data->pdf_reference_url ?? '';
        $data->customprompt = $SESSION->haccgen_data->customprompt ?? '';
        $data->activelang = $activelang;
    } else if ($step == 3) {
        $data->topic_order = optional_param('topic_order', '', PARAM_RAW);
        $data->coursename = $SESSION->haccgen_data->TOPICTITLE ?? '';
        $data->targetaudience = $SESSION->haccgen_data->targetaudience ?? '';
        $data->description = $SESSION->haccgen_data->description ?? '';
        $data->levelofunderstanding = $SESSION->haccgen_data->levelofunderstanding ?? 'Beginner';
        $data->toneofnarrative = $SESSION->haccgen_data->toneofnarrative ?? 'Formal';
        $data->courseduration = $SESSION->haccgen_data->courseduration ?? 'Less than 15 minutes';
        $data->generationtype = $SESSION->haccgen_data->generationtype ?? 'ai';
        $data->pdf_file = $SESSION->haccgen_data->pdf_file ?? '';
        $data->pdf_fileid = $SESSION->haccgen_data->pdf_fileid ?? 0;
        $data->customprompt = $SESSION->haccgen_data->customprompt ?? '';
        $data->courselanguage = $SESSION->haccgen_data->courselanguage ?? 'English';
        $data->pdf_reference_url = $SESSION->haccgen_data->pdf_reference_url ?? '';
        $data->numberoftopics = $SESSION->haccgen_data->numberoftopics ?? 5;
        $data->activelang = $activelang;
    } else if ($step == 4) {
        $data->coursename = $SESSION->haccgen_data->TOPICTITLE ?? '';
        $data->targetaudience = $SESSION->haccgen_data->targetaudience ?? '';
        $data->description = $SESSION->haccgen_data->description ?? '';
        $data->levelofunderstanding = $SESSION->haccgen_data->levelofunderstanding ?? 'Beginner';
        $data->toneofnarrative = $SESSION->haccgen_data->toneofnarrative ?? 'Formal';
        $data->courseduration = $SESSION->haccgen_data->courseduration ?? 'Less than 15 minutes';
        $data->generationtype = $SESSION->haccgen_data->generationtype ?? 'ai';
        $data->pdf_file = $SESSION->haccgen_data->pdf_file ?? '';
        $data->pdf_fileid = $SESSION->haccgen_data->pdf_fileid ?? 0;
        $data->customprompt = $SESSION->haccgen_data->customprompt ?? '';
        $data->pdf_reference_url = $SESSION->haccgen_data->pdf_reference_url ?? '';
        $data->courselanguage = $SESSION->haccgen_data->courselanguage ?? 'English';
        $data->numberoftopics = $SESSION->haccgen_data->numberoftopics ?? 5;
        $data->activelang = $activelang;
    }

    // Remove/guard undefined debug that referenced $lang/$labels before they exist.

    if (empty($errors)) {
        $SESSION->haccgen_data->TOPICTITLE = $data->coursename ?? '';
        $SESSION->haccgen_data->targetaudience = $data->targetaudience ?? '';
        $SESSION->haccgen_data->description = $data->description ?? '';
        $SESSION->haccgen_data->levelofunderstanding = $data->levelofunderstanding ?? '';
        $SESSION->haccgen_data->toneofnarrative = $data->toneofnarrative ?? '';
        $SESSION->haccgen_data->courseduration = $data->courseduration ?? '';
        $SESSION->haccgen_data->generationtype = $data->generationtype ?? 'ai';
        $SESSION->haccgen_data->pdf_file = $data->pdf_file ?? '';
        $SESSION->haccgen_data->pdf_fileid = $data->pdf_fileid ?? 0;
        $SESSION->haccgen_data->courselanguage = $data->courselanguage ?? 'English';
        $SESSION->haccgen_data->numberoftopics = $data->numberoftopics ?? 5;
        $SESSION->haccgen_data->customprompt = $data->customprompt ?? '';
        $SESSION->haccgen_data->pdf_reference_url = $data->pdf_reference_url ?? '';
        $SESSION->haccgen_data->activelang = $activelang;

        // Keep $isdraft updated from POST.
        $isdraft = optional_param('savedraft', 0, PARAM_BOOL);

        if ($step == 3 && $action === 'save' && !empty($data->topic_order)) {
            try {
                $topics = json_decode($data->topic_order, true, 512, JSON_THROW_ON_ERROR);

                $job = (object) [
                    'userid' => $USER->id,
                    'courseid' => $courseid,
                    'type' => 'topiccontent',
                    'status' => 'queued',
                    'progress' => 0,
                    'message' => null,
                    'inputjson' => json_encode(
                        [
                            'topics' => $topics,
                            'options' => [
                                'coursename' => $SESSION->haccgen_data->TOPICTITLE ?? '',
                                'targetaudience' => $SESSION->haccgen_data->targetaudience ?? '',
                                'description' => $SESSION->haccgen_data->description ?? '',
                                'levelofunderstanding' => $SESSION->haccgen_data->levelofunderstanding ?? 'Beginner',
                                'toneofnarrative' => $SESSION->haccgen_data->toneofnarrative ?? 'Formal',
                                'courseduration' => $SESSION->haccgen_data->courseduration ?? 'Less than 15 minutes',
                                'numberoftopics' => $SESSION->haccgen_data->numberoftopics ?? 5,
                                'case_study_data' => $SESSION->haccgen_data->case_study_data ?? null,
                            ],
                        ],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
                    'timecreated' => time(),
                    'timemodified' => time(),
                ];

                $job->id = $DB->insert_record('local_haccgen_job', $job);

                $task = new \local_haccgen\task\generate_topiccontent_task();
                $task->set_custom_data(['jobid' => $job->id]);
                $task->set_userid($USER->id);
                $task->set_component('local_haccgen');
                \core\task\manager::queue_adhoc_task($task);

                redirect(new moodle_url('/local/haccgen/job.php', ['id' => $job->id]));
                exit;
            } catch (Throwable $e) {
                $errors['general'] = get_string('invalidtopicorder', 'local_haccgen', $e->getMessage());
            }
        } else if ($step == 4 && ($action === 'save' || $isscorm)) {

            // Logger.
            $logdir = $CFG->dataroot . '/local_haccgen';
            if (!is_dir($logdir)) {
                @mkdir($logdir, 0770, true);
            }
            $logfile = $logdir . '/save_' . date('Y-m-d') . '.log';
            $log = function (string $label, $data = null) use ($logfile, $USER, $courseid) {
                $payload = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (is_string($payload) && strlen($payload) > 8000) {
                    $payload = substr($payload, 0, 8000) . '…';
                }
                $line = sprintf(
                    "[%s] uid=%s course=%s %s: %s\n",
                    date('c'),
                    $USER->id ?? '0',
                    $courseid ?? '0',
                    $label,
                    (string) $payload
                );
                @file_put_contents($logfile, $line, FILE_APPEND | LOCK_EX);
            };
            // ----------------------------------------

            // Params used in this step.
            $isdraft = optional_param('savedraft', 0, PARAM_BOOL);
            $topicsjsonreq = optional_param('topicsjson', '', PARAM_RAW); // Legacy.
            $quizjsonreq = optional_param('quizjson', '', PARAM_RAW); // Legacy.

            // Canonical payload (single JSON) + chunking support.
            $payloadjson = optional_param('payload', '', PARAM_RAW);
            $payloadparts = optional_param('payloadparts', 0, PARAM_INT);
            if ($payloadjson === '' && $payloadparts > 0) {
                $buf = '';
                for ($i = 1; $i <= $payloadparts; $i++) {
                    $buf .= optional_param("payload_{$i}", '', PARAM_RAW);
                }
                $payloadjson = $buf;
            }

            $log('PARAM.savedraft', $isdraft);
            $log('PARAM.payload.raw', $payloadjson);
            $log('PARAM.topicsjson', $topicsjsonreq !== '' ? 'present' : '');
            $log('PARAM.quizjson', $quizjsonreq !== '' ? 'present' : '');

            $log('START', ['step' => $step ?? null, 'action' => $action ?? null, 'isdraft' => (int) $isdraft]);

            // Parse payload if present.
            $haspayload = false;
            $payloadarr = null;
            if ($payloadjson !== '') {
                try {
                    $payloadarr = json_decode($payloadjson, true, 512, JSON_THROW_ON_ERROR);
                    $haspayload = is_array($payloadarr) && !empty($payloadarr['topics']) && is_array($payloadarr['topics']);
                    $log('PAYLOAD.parsed', [
                        'ok' => true,
                        'topics_total' => $haspayload ? count($payloadarr['topics']) : 0,
                        'meta' => $payloadarr['meta'] ?? null,
                    ]);
                } catch (Throwable $e) {
                    $log('PAYLOAD.parse_error', $e->getMessage());
                    // Continue with legacy fields below.
                }
            }

            // If SAVE DRAFT: stash latest into session and bounce.
            if ($isdraft) {
                $SESSION->haccgen_data = $SESSION->haccgen_data ?? new stdClass();
                if ($haspayload) {
                    $topicsflat = [];
                    $quizmap = [];

                    foreach ($payloadarr['topics'] as $t) {
                        $ttitle = (string) ($t['title'] ?? '');
                        foreach ((array) ($t['subtopics'] ?? []) as $s) {
                            $stitle = (string) ($s['title'] ?? '');
                            $content = $s['content'] ?? [];
                            $text = is_array($content) ? ($content['text'] ?? '') : (string) $content;
                            $itemid = is_array($content) ? (int) ($content['itemid'] ?? 0) : 0;
                            $topicsflat[$stitle] = ['text' => $text, 'itemid' => $itemid];
                        }
                        $q = $t['quiz'] ?? ($t['quiz_data'] ?? null);
                        if ($q && !empty($q['questions'])) {
                            $qt = (string) ($q['quiz_title'] ?? $ttitle);
                            $quizmap[$qt] = [
                                'quiz_title' => $qt,
                                'instructions' => (string) ($q['instructions'] ?? ''),
                                'questions' => array_values(array_map(function ($qq, $i) {
                                    return [
                                        'question_id' => $qq['question_id'] ?? 'q' . ($i + 1),
                                        'type' => $qq['type'] ?? 'multiple_choice',
                                        'difficulty' => $qq['difficulty'] ?? 'easy',
                                        'question' => (string) ($qq['question'] ?? ''),
                                        'options' => array_values(array_map('strval', (array) ($qq['options'] ?? []))),
                                        'correct_answer' => (string) ($qq['correct_answer'] ?? ($qq['answer'] ?? '')),
                                        'explanation' => (string) ($qq['explanation'] ?? ''),
                                    ];
                                }, (array) ($q['questions'] ?? []), array_keys((array) ($q['questions'] ?? [])))),
                            ];
                        }
                    }

                    $SESSION->haccgen_data->topicsjson = $topicsflat;
                    $SESSION->haccgen_data->quizjson = $quizmap;
                    $SESSION->haccgen_data->canonical_payload_json = $payloadjson;
                    $SESSION->haccgen_data->canonical_payload = $payloadarr;

                    $log('DRAFT_BRANCH_FROM_PAYLOAD', ['topics_count' => count($topicsflat), 'quizzes_count' => count($quizmap)]);
                } else {
                    if ($topicsjsonreq !== '') {
                        $SESSION->haccgen_data->topicsjson = json_decode($topicsjsonreq, true) ?? [];
                    }
                    if ($quizjsonreq !== '') {
                        $SESSION->haccgen_data->quizjson = json_decode($quizjsonreq, true) ?? [];
                    }
                    $SESSION->haccgen_data->canonical_payload_json = '';
                    $SESSION->haccgen_data->canonical_payload = null;

                    $log('DRAFT_BRANCH_FROM_LEGACY', [
                        'topics_count' => count($SESSION->haccgen_data->topicsjson ?? []),
                        'quizzes_count' => count($SESSION->haccgen_data->quizjson ?? []),
                    ]);
                }

                \core\session\manager::write_close();
                redirect(new moodle_url('/local/haccgen/savedraft.php', ['id' => $courseid, 'step' => $step]));
            }

            // SCORM branch.
            if ($isscorm) {
                require_once(__DIR__ . '/scorm_builder.php');

                $topicsforscorm = [];

                // Course-level counters.
                $totalslides = 0;
                $totalquizzes = 0;

                // Ensure TOPICTITLE is updated from payload if present.
                if ($haspayload && !empty($payloadarr['meta']['title'])) {
                    $SESSION->haccgen_data->TOPICTITLE = trim($payloadarr['meta']['title']);
                }

                if ($haspayload) {
                    foreach ($payloadarr['topics'] as $tindex => $t) {
                        $subtopics = (array) ($t['subtopics'] ?? []);
                        $slidecount = count($subtopics);
                        $topichasquiz = !empty($t['quiz']) && !empty($t['quiz']['questions']);

                        $totalslides += $slidecount;
                        if ($topichasquiz) {
                            $totalquizzes++;
                        }

                        $topic = [
                            'title' => (string) ($t['title'] ?? 'Untitled Topic'),
                            'subtopics' => [],
                            'slide_count' => $slidecount,
                            'has_quiz' => $topichasquiz,
                        ];

                        foreach ($subtopics as $sindex => $s) {
                            $content = (string) ($s['content']['text'] ?? $s['content'] ?? '');
                            $topic['subtopics'][] = [
                                'title' => (string) ($s['title'] ?? 'Untitled Subtopic'),
                                'content_html' => $content,
                            ];
                        }

                        if ($topichasquiz) {
                            $topic['quiz'] = $t['quiz'];
                        }

                        $topicsforscorm[] = $topic;
                    }
                } else {
                    // Legacy fallback.
                    $flat = $topicsjsonreq !== '' ? json_decode($topicsjsonreq, true) : [];
                    foreach ((array) $flat as $title => $d) {
                        $topicsforscorm[] = [
                            'title' => $title,
                            'subtopics' => [[
                                'title' => $title,
                                'content_html' => $d['text'] ?? '',
                            ]],
                            'slide_count' => 1,
                            'has_quiz' => false,
                        ];
                        $totalslides += 1;
                    }
                }

                $coursehasquiz = $totalquizzes > 0;

                $scormmeta = [
                    'total_slides' => $totalslides,
                    'total_quizzes' => $totalquizzes,
                    'has_quiz' => $coursehasquiz,
                ];

                $coursename =
                    trim($payloadarr['meta']['title'] ?? '') ?:
                    trim($payloadarr['title'] ?? '') ?:
                    trim($SESSION->haccgen_data->TOPICTITLE ?? '') ?:
                    trim($SESSION->haccgen_data->coursename ?? '') ?:
                    'Untitled Course';

                // Read settings from modal (keep your original POST keys).
                $scormtype = $_POST['scormtype'] ?? 'single';
                $scormversion = $_POST['scormversion'] ?? $globalscormversion;

                if ($scormtype === 'single') {
                    $completiontype = $_POST['completiontype'] ?? 'slides';
                    $requiredslides = (int) ($_POST['requiredslides'] ?? 0);
                    $passingscore = (int) ($_POST['passingscore'] ?? 0);
                    $quizmode = $_POST['quizmode'] ?? 'average';
                    $quizselection = $_POST['quizselection'] ?? '';

                    $passingmode = match ($completiontype) {
                        'slides' => 'slides',
                        'quiz' => 'quiz',
                        'slides_quiz' => 'both',
                        default => 'slides',
                    };

                    $packagepath = build_scorm_package_from_array(
                        $topicsforscorm,
                        $coursename,
                        $passingscore,
                        $scormmeta,
                        $passingmode,
                        $requiredslides,
                        $quizmode,
                        $quizselection,
                        $scormversion
                    );
                } else {
                    $requiredscos = (int) ($_POST['requiredscos'] ?? 0);
                    $completionquizmulti = !empty($_POST['completionquizmulti']);
                    $multiquizmode = $_POST['multiquizmode'] ?? 'average';
                    $multiquizselection = $_POST['multiquizselection'] ?? '';
                    $multipassingscore = (int) ($_POST['multipassingscore'] ?? 0);

                    $multiquizids = array_filter(array_map('trim', explode(',', $multiquizselection)));

                    $packagepath = build_multi_scorm_package_from_array(
                        $topicsforscorm,
                        $coursename,
                        $multipassingscore,
                        $scormmeta,
                        $requiredscos,
                        $completionquizmulti,
                        $multiquizmode,
                        $multiquizids
                    );
                }

                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="course_scorm.zip"');

                readfile($packagepath);
                unlink($packagepath);
                exit;
            }

            // Helpers.
            if (!function_exists('normalise_title')) {
                /**
                 * Normalise quiz titles for consistent matching.
                 *
                 * @param string $s Raw title.
                 * @return string Normalised title.
                 */
                function normalise_title(string $s): string {
                    $s = preg_replace('/^quiz:\s*/i', '', $s);
                    return mb_strtolower(trim($s));
                }
            }

            if (!function_exists('hacc_base_key')) {
                /**
                 * Build a base key used for loose matching.
                 *
                 * @param string $s Input string.
                 * @return string Normalised base key.
                 */
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

            if (!function_exists('buildtopicsfrommixed')) {
                /**
                 * Build topic/subtopic structure from flat content and base topics.
                 *
                 * This accepts either an array or a JSON string in $flat.
                 *
                 * @param mixed $flat Flat array or JSON string.
                 * @param array $basetopics Base topics structure.
                 * @return array Normalised topics array.
                 */
                function buildtopicsfrommixed($flat, array $basetopics): array {
                    if (is_string($flat)) {
                        try {
                            $flat = json_decode($flat, true, 512, JSON_THROW_ON_ERROR);
                        } catch (Throwable $e) {
                            $flat = [];
                        }
                    }
                    if (!is_array($flat)) {
                        $flat = [];
                    }

                    $result = $basetopics ?: [['title' => 'Topic 1', 'subtopics' => []]];
                    if (!isset($result[0]['subtopics']) || !is_array($result[0]['subtopics'])) {
                        $result[0]['subtopics'] = [];
                    }

                    $index = [];
                    foreach ($result as $tidx => $topic) {
                        foreach (($topic['subtopics'] ?? []) as $sidx => $sub) {
                            $key = mb_strtolower(trim((string) ($sub['title'] ?? '')));
                            if ($key !== '') {
                                $index[$key] = ['t' => $tidx, 's' => $sidx];
                            }
                        }
                    }

                    foreach ($flat as $rawtitle => $rawcontent) {
                        $cleantitle = mb_strtolower(trim((string) $rawtitle));
                        $newtext = is_array($rawcontent) ? ($rawcontent['text'] ?? '') : (string) $rawcontent;
                        $newitemid = is_array($rawcontent) ? (int) ($rawcontent['itemid'] ?? 0) : 0;

                        if ($cleantitle !== '' && isset($index[$cleantitle])) {
                            $t = $index[$cleantitle]['t'];
                            $s = $index[$cleantitle]['s'];
                            $finaltext = trim($newtext);

                            $result[$t]['subtopics'][$s]['content'] = ['text' => $finaltext, 'itemid' => $newitemid];
                            $result[$t]['subtopics'][$s]['content_html'] = $finaltext;
                        } else {
                            $result[0]['subtopics'][] = [
                                'title' => (string) $rawtitle,
                                'content' => ['text' => trim($newtext), 'itemid' => $newitemid],
                                'examples' => [],
                                'content_html' => trim($newtext),
                            ];
                        }
                    }

                    return $result;
                }
            }

            $norm = static function ($s) {
                return mb_strtolower(trim((string)$s));
            };

            // If payload is present, use it directly.
            if ($haspayload) {
                // Normalise to match your creation loop expectations.
                $topics = [];
                foreach ($payloadarr['topics'] as $t) {
                    $topic = [
                        'title'     => (string)($t['title'] ?? 'Untitled Topic'),
                        'subtopics' => [],
                    ];
                    foreach ((array)($t['subtopics'] ?? []) as $s) {
                        $stitle = (string)($s['title'] ?? 'Untitled Subtopic');
                        $content = $s['content'] ?? [];
                        if (!is_array($content)) {
                            $content = [
                                'text' => (string) $content,
                                'itemid' => 0,
                            ];
                        }
                        $topic['subtopics'][] = [
                            'title'   => $stitle,
                            'content' => [
                                'text'   => (string)($content['text'] ?? ''),
                                'itemid' => (int)($content['itemid'] ?? 0),
                            ],
                        ];
                    }
                    if (!empty($t['quiz'])) {
                        $q = $t['quiz'];
                        $topic['quiz_included'] = 1;
                        $topic['quiz_data'] = [
                            'quiz_title'   => (string)($q['quiz_title'] ?? $topic['title']),
                            'instructions' => (string)($q['instructions'] ?? ''),
                            'questions'    => array_values(array_map(function ($qq, $i) {
                                return [
                                    'question_id'    => $qq['question_id'] ?? 'q' . ($i + 1),
                                    'type'           => $qq['type'] ?? 'multiple_choice',
                                    'difficulty'     => $qq['difficulty'] ?? 'easy',
                                    'question'       => (string)($qq['question'] ?? ''),
                                    'options'        => array_values(array_map('strval', (array)($qq['options'] ?? []))),
                                    'correct_answer' => (string)($qq['correct_answer'] ?? ($qq['answer'] ?? '')),
                                    'explanation'    => (string)($qq['explanation'] ?? ''),
                                ];
                            }, (array)($q['questions'] ?? []), array_keys((array)($q['questions'] ?? [])))),
                        ];
                    }
                    $topics[] = $topic;
                }
                $log('USING_PAYLOAD_TOPICS', ['topics_total' => count($topics)]);

                // Moodle objects.
                $newcourse = get_course($courseid);
                $module    = $DB->get_record('modules', ['name' => 'page'], '*', MUST_EXIST);
                $log('Fetched course and page module', ['page_module_id' => $module->id ?? null]);

                $existingsections = $DB->get_records('course_sections', ['course' => $courseid]);
                $sectionnumbers   = array_map(static function ($s) {
                    return (int)$s->section;
                }, $existingsections);
                $sectionnumber    = !empty($sectionnumbers) ? max($sectionnumbers) + 1 : 1;
                $log('Computed starting section number', [
                    'next_sectionnumber' => $sectionnumber,
                    'existing_sections' => count($existingsections),
                ]);

                // Create sections, pages, and quizzes from the payload.
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
                        'quiz_included' => !empty($topic['quiz_included']),
                    ]);

                    $section   = course_create_section($courseid, $sectionnumber);
                    $sectionid = is_object($section) ? $section->id : $section;
                    if ($sectionid) {
                        $DB->set_field('course_sections', 'name', $topicname, ['id' => $sectionid]);
                        $log('Section created/renamed', ['sectionid' => $sectionid]);
                    } else {
                        $log('WARN section creation returned empty id', ['sectionnumber' => $sectionnumber]);
                    }

                    // Pages for each subtopic.
                    foreach ($subtopics as $sub) {
                        $subtopicname = $sub['title'] ?? 'Untitled Subtopic';
                        $editor = $sub['content'] ?? '';
                        if (!is_array($editor)) {
                            $editor = ['text' => (string)$editor, 'itemid' => 0];
                        }
                        $draftid = (int)($editor['itemid'] ?? 0);
                        $html    = $editor['text'] ?? '';

                        $log('Creating page module', [
                            'subtopic' => $subtopicname,
                            'draftid' => $draftid,
                            'html_len' => strlen((string) $html),
                        ]);

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

                        $log('Page CM created and added to section', [
                            'cmid' => $cm->id,
                            'pageid' => $page->id,
                        ]);

                        $cmcontext  = context_module::instance($cm->id);
                        $editoropts = [
                            'maxfiles' => 100,
                            'context' => $cmcontext,
                            'subdirs' => 0,
                        ];

                        $fs        = get_file_storage();
                        $userctx   = context_user::instance($USER->id);
                        $coursectx = context_course::instance($courseid);

                        // 1) Import any draftfile.php links directly into this page.
                        $draftpattern =
                            '~draftfile\.php/(\d+)/user/draft/(\d+)(/[^"\']*)?/([^"\'>\s]+)~i';

                        if (preg_match_all($draftpattern, (string) $html, $m, PREG_SET_ORDER)) {
                            foreach ($m as $hit) {
                                $srcctxid = (int) $hit[1];
                                $draftid  = (int) $hit[2];
                                $filepath = isset($hit[3]) && $hit[3] !== '' ? $hit[3] : '/';

                                if ($filepath[0] !== '/') {
                                    $filepath = '/' . $filepath;
                                }
                                if (substr($filepath, -1) !== '/') {
                                    $filepath .= '/';
                                }

                                $filename = $hit[4];
                                $src = $fs->get_file(
                                    $srcctxid,
                                    'user',
                                    'draft',
                                    $draftid,
                                    $filepath,
                                    $filename
                                );

                                if ($src) {
                                    if (!$fs->file_exists(
                                        $cmcontext->id,
                                        'mod_page',
                                        'content',
                                        0,
                                        '/',
                                        $filename
                                    )) {
                                        $fs->create_file_from_storedfile([
                                            'contextid' => $cmcontext->id,
                                            'component' => 'mod_page',
                                            'filearea' => 'content',
                                            'itemid' => 0,
                                            'filepath' => '/',
                                            'filename' => $filename,
                                        ], $src);
                                    }

                                    $full = '~(?<=["\'])https?://[^"\']*draftfile\.php/'
                                        . $srcctxid . '/user/draft/' . $draftid
                                        . preg_quote($filepath, '~')
                                        . preg_quote($filename, '~')
                                        . '(?:\?[^"\']*)?(?=["\'])~i';

                                    $html = preg_replace(
                                        $full,
                                        '@@PLUGINFILE@@/' . $filename,
                                        $html
                                    );

                                    $log('PAGE.DRAFT_IMPORT.OK', [
                                        'filename' => $filename,
                                        'from_ctx' => $srcctxid,
                                        'draftid' => $draftid,
                                    ]);
                                } else {
                                    $log('PAGE.DRAFT_IMPORT.MISS', [
                                        'ctx' => $srcctxid,
                                        'draftid' => $draftid,
                                        'filepath' => $filepath,
                                        'filename' => $filename,
                                    ]);
                                }
                            }
                        }

                        // 2) Import placeholders (optional improvement).
                        // Try to find a matching file in plugin stash local_haccgen/uploads (itemid = $courseid).
                        // If you emit data-image-filename="actual_name.png", we’ll use that first.
                        $sourcefiles = $fs->get_area_files(
                            $coursectx->id,
                            'local_haccgen',
                            'uploads',
                            $courseid,
                            'id',
                            false
                        );

                        $indexbyname = [];
                        foreach ($sourcefiles as $sf) {
                            $indexbyname[mb_strtolower($sf->get_filename())] = $sf;
                        }

                        // Normaliser for loose matching (your keys contain colons/underscores).
                        $normalise = function ($s) {
                            $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML401, 'UTF-8');
                            $s = mb_strtolower($s);
                            $s = str_replace([' ', ':', '/', '\\'], '_', $s);
                            $s = preg_replace('~[^a-z0-9_]+~', '_', $s);
                            $s = preg_replace('~_+~', '_', $s);
                            return trim($s, '_');
                        };

                        $placeholderpattern =
                            '~<div[^>]*class=["\']?[^"\']*image-placeholder[^"\']*["\']?[^>]*'
                            . 'data-image(?:-filename)?=["\']([^"\']+)["\'][^>]*>.*?</div>~is';

                        if (preg_match_all(
                            $placeholderpattern,
                            (string) $html,
                            $hits,
                            PREG_SET_ORDER
                        )) {
                            // Build a loose index of plugin-stash files by "stem".
                            $indexbystem = [];
                            foreach ($sourcefiles as $sf) {
                                $stem = preg_replace('~\.[^.]+$~', '', $sf->get_filename());
                                $indexbystem[$normalise($stem)] = $sf;
                            }

                            foreach ($hits as $ph) {
                                // Key could be a filename (best) or your "topic-section" key.
                                $key = $ph[1];
                                $src = null;

                                $cand = mb_strtolower($key);
                                if (isset($indexbyname[$cand])) {
                                    $src = $indexbyname[$cand];
                                }

                                if (!$src) {
                                    $nkey = $normalise(preg_replace('~\.[^.]+$~', '', $key));
                                    $src  = $indexbystem[$nkey] ?? null;
                                }

                                if (!$src) {
                                    foreach ($sourcefiles as $sf) {
                                        if (stripos($sf->get_filename(), $key) !== false) {
                                            $src = $sf;
                                            break;
                                        }
                                    }
                                }

                                if (!$src) {
                                    $log('PAGE.PLACEHOLDER.SOURCE_NOT_FOUND', ['key' => $key]);
                                    continue;
                                }

                                $filename = $src->get_filename();
                                if (!$fs->file_exists(
                                    $cmcontext->id,
                                    'mod_page',
                                    'content',
                                    0,
                                    '/',
                                    $filename
                                )) {
                                    $fs->create_file_from_storedfile([
                                        'contextid' => $cmcontext->id,
                                        'component' => 'mod_page',
                                        'filearea' => 'content',
                                        'itemid' => 0,
                                        'filepath' => '/',
                                        'filename' => $filename,
                                    ], $src);
                                }

                                // Swap placeholder to IMG.
                                $img = '<p><img src="@@PLUGINFILE@@/' . s($filename) . '" alt="" /></p>';
                                $html = str_replace($ph[0], $img, $html);

                                $log('PAGE.PLACEHOLDER.REPLACED', [
                                    'key' => $key,
                                    'filename' => $filename,
                                ]);
                            }
                        }

                        $html = preg_replace(
                            '~([?&])(token|expires)=[^"&\']+~i',
                            '$1',
                            (string) $html
                        );
                        $html = preg_replace('~[?&]+$~', '', (string) $html);

                        // Save rewritten HTML.
                        $page->content = $html;
                        $DB->update_record('page', $page);

                        $finalfiles = $fs->get_area_files(
                            $cmcontext->id,
                            'mod_page',
                            'content',
                            0,
                            'id',
                            false
                        );

                        $log('PAGE.FINAL_FILES', array_map(function ($f) {
                            return [
                                'filename' => $f->get_filename(),
                                'size' => $f->get_filesize(),
                            ];
                        }, $finalfiles));

                        $fs = get_file_storage();
                        $files = $fs->get_area_files(
                            $cmcontext->id,
                            'mod_page',
                            'content',
                            0,
                            'id',
                            false
                        );

                        if (!$files) {
                            $log('ERROR_NO_FILES_SAVED_FOR_PAGE', [
                                'cmid' => $cm->id,
                                'subtopic' => $subtopicname,
                            ]);
                        }

                        $log('Page content updated', ['pageid' => $page->id]);
                    }

                    // Quiz for topic (if any).
                    if (!empty($topic['quiz_included']) && !empty($topic['quiz_data'])) {
                        $quizdata     = $topic['quiz_data'];

                        $log('QUIZ.CREATE_BEGIN', [
                            'topic'            => $topicname,
                            'sectionnumber'    => $sectionnumber,
                            'quiz_title'       => $quizdata['quiz_title'] ?? 'Untitled Quiz',
                            'instructions_len' => strlen((string)($quizdata['instructions'] ?? '')),
                            'questions_count'  => count($quizdata['questions'] ?? []),
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
                                'quizpassword'         => '',
                            ];

                            $log('QUIZ.SETTINGS', [
                                'name'               => $quizsettings->name,
                                'preferredbehaviour' => $quizsettings->preferredbehaviour,
                                'grade'              => $quizsettings->grade,
                                'sumgrades'          => $quizsettings->sumgrades,
                                'questionsperpage'   => $quizsettings->questionsperpage,
                            ]);

                            $cmquiz = add_moduleinfo($quizsettings, $newcourse);
                            $cmid   = is_object($cmquiz) && isset($cmquiz->coursemodule) ? (int)$cmquiz->coursemodule : 0;
                            $quizid = is_object($cmquiz) && isset($cmquiz->instance) ? (int)$cmquiz->instance : 0;

                            $log('QUIZ.ADDED', ['cmid' => $cmid, 'quizid' => $quizid, 'ok' => (bool)$quizid]);

                            if ($quizid) {
                                // Review settings.
                                $okupdate = $DB->update_record('quiz', [
                                    'id'                      => $quizid,
                                    'reviewattempt'           => 69632,
                                    'reviewcorrectness'       => 4096,
                                    'reviewmaxmarks'          => 4096,
                                    'reviewmarks'             => 4096,
                                    'reviewspecificfeedback'  => 4096,
                                    'reviewgeneralfeedback'   => 4096,
                                    'reviewrightanswer'       => 4096,
                                    'reviewoverallfeedback'   => 4096,
                                ]);
                                $log('QUIZ.REVIEW_SETTINGS_UPDATED', ['quizid' => $quizid, 'ok' => (bool)$okupdate]);

                                // Context & default category.
                                // Context and default category.
                                $realcm = get_coursemodule_from_instance(
                                    'quiz',
                                    $quizid,
                                    $courseid,
                                    false,
                                    MUST_EXIST
                                );
                                $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
                                $quizctx = context_module::instance($realcm->id);

                                $log('QUIZ.CONTEXT', [
                                    'cmid' => $realcm->id,
                                    'contextid' => $quizctx->id,
                                ]);

                                $catobj = question_make_default_categories([$quizctx]);
                                if (!empty($catobj->id)) {
                                    $catid = (int) $catobj->id;
                                    $log('QUIZ.CATEGORY_READY', ['catid' => $catid]);

                                    // Import MCQ questions.
                                    $qsaved = 0;
                                    $qskipped = 0;

                                    foreach ($quizdata['questions'] as $index => $qdata) {
                                        $qtypename = $qdata['type'] ?? 'multiple_choice';
                                        if ($qtypename !== 'multiple_choice') {
                                            $qskipped++;
                                            $log('QUIZ.Q.SKIP_UNSUPPORTED', [
                                                'index' => $index,
                                                'type' => $qtypename,
                                            ]);
                                            continue;
                                        }

                                        $options = $qdata['options'] ?? [];
                                        $correctletter = $qdata['correct_answer'] ?? '';

                                        $form = new stdClass();
                                        $form->category = $catid;
                                        $form->contextid = $quizctx->id;
                                        $form->qtype = 'multichoice';
                                        $form->name = $qdata['question'];
                                        $form->questiontext = [
                                            'text' => $qdata['question'],
                                            'format' => FORMAT_HTML,
                                        ];
                                        $form->generalfeedback = [
                                            'text' => $qdata['explanation'] ?? '',
                                            'format' => FORMAT_HTML,
                                        ];
                                        $form->defaultmark = 1;
                                        $form->penalty = 0.1;
                                        $form->single = 1;
                                        $form->shuffleanswers = 1;
                                        $form->answernumbering = 'none';
                                        $form->correctfeedback = ['text' => '', 'format' => FORMAT_HTML];
                                        $form->partiallycorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
                                        $form->incorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
                                        $form->layout = 0;
                                        $form->showstandardinstruction = 1;
                                        $form->shownumcorrect = 1;
                                        $form->answer = [];
                                        $form->fraction = [];
                                        $form->feedback = [];

                                        foreach ($options as $i => $optiontext) {
                                            $letter = chr(65 + $i);
                                            $iscorrect = strtoupper((string) $correctletter) === $letter;

                                            $form->answer[] = ['text' => $optiontext, 'format' => FORMAT_HTML];
                                            $form->fraction[] = $iscorrect ? 1 : 0;
                                            $form->feedback[] = [
                                                'text' => $iscorrect ? get_string('answercorrect', 'local_haccgen')
                                                    : get_string('answerincorrect', 'local_haccgen'),
                                                'format' => FORMAT_HTML,
                                            ];
                                        }

                                        try {
                                            $qtype = question_bank::get_qtype('multichoice');
                                            $question = $qtype->save_question(
                                                (object) ['category' => $catid, 'qtype' => 'multichoice'],
                                                $form
                                            );
                                            quiz_add_quiz_question($question->id, $quiz);

                                            $qsaved++;
                                            $log('QUIZ.Q.SAVED', [
                                                'index' => $index,
                                                'questionid' => $question->id,
                                            ]);
                                        } catch (Exception $e) {
                                            $qskipped++;
                                            $log('QUIZ.Q.ERROR_SAVE', [
                                                'index' => $index,
                                                'message' => $e->getMessage(),
                                            ]);
                                        }
                                    }

                                    $log('QUIZ.CREATE_SUMMARY', [
                                        'quizid' => $quizid,
                                        'saved' => $qsaved,
                                        'skipped' => $qskipped,
                                    ]);
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

                rebuild_course_cache($courseid);
                unset($SESSION->haccgen_data);
                $log('Rebuilt course cache; redirecting');
                redirect(new moodle_url('/course/view.php', ['id' => $courseid]), get_string('content_generated', 'local_haccgen'));
                exit;
            }

            if ($topicsjsonreq !== '') {
                $log('topicsjsonreq(raw)', ['bytes' => strlen($topicsjsonreq)]);
                $subtopiccontents = json_decode($topicsjsonreq, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $log('topicsjsonreq(JSON_ERROR)', json_last_error_msg());
                    throw new moodle_exception(
                        'invalidjson',
                        'local_haccgen',
                        '',
                        'topicsjson: ' . json_last_error_msg()
                    );
                }
                $log('topicsjson(parsed)', ['count' => is_array($subtopiccontents) ? count($subtopiccontents) : 0]);
            } else {
                $subtopiccontents = $SESSION->haccgen_data->topicsjson ?? [];
                $log('topicsjson(fallback_from_session)', ['count' => is_array($subtopiccontents) ? count($subtopiccontents) : 0]);
            }
            $SESSION->haccgen_data->topicsjson = $subtopiccontents;
            $log('SESSION.topicsjson.set', [
                'subtopics_count' => is_array($subtopiccontents) ? count($subtopiccontents) : 0,
            ]);

            // Quizjson.
            if ($quizjsonreq !== '') {
                $log('quizjsonreq(raw)', ['bytes' => strlen($quizjsonreq)]);
                $quizcontents = json_decode($quizjsonreq, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $log('quizjsonreq(JSON_ERROR)', json_last_error_msg());
                    throw new moodle_exception(
                        'invalidjson',
                        'local_haccgen',
                        '',
                        'quizjson: ' . json_last_error_msg()
                    );
                }
                $cleanquiz = [];
                foreach ($quizcontents as $rawkey => $payload) {
                    $title = $payload['quiz_title'] ?? preg_replace('/^quiz:\s*/i', '', trim($rawkey));
                    $payload['quiz_title'] = $title;
                    $cleanquiz[normalise_title($title)] = $payload;
                }
                $quizcontents = $cleanquiz;
                $log('quizjson(parsed_and_normalized)', [
                    'count' => count($quizcontents),
                    'keys' => array_keys($quizcontents),
                ]);
            } else {
                $quizcontents = $SESSION->haccgen_data->quizjson ?? [];
                $log('quizjson(fallback_from_session)', ['count' => is_array($quizcontents) ? count($quizcontents) : 0]);
            }
            $SESSION->haccgen_data->quizjson = $quizcontents;

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new moodle_exception(
                    'invalidjson',
                    'local_haccgen',
                    '',
                    'JSON decode error: ' . json_last_error_msg()
                );
            }

            // Moodle objects.
            $newcourse = get_course($courseid);
            $module    = $DB->get_record('modules', ['name' => 'page'], '*', MUST_EXIST);
            $log('Fetched course and page module', ['page_module_id' => $module->id ?? null]);

            $existingsections = $DB->get_records('course_sections', ['course' => $courseid]);
            $sectionnumbers   = array_map(static function ($s) {
                return (int)$s->section;
            }, $existingsections);
            $sectionnumber    = !empty($sectionnumbers) ? max($sectionnumbers) + 1 : 1;
            $log('Computed starting section number', [
                'next_sectionnumber' => $sectionnumber,
                'existing_sections' => count($existingsections),
            ]);

            // Build topics structure from base + flat.
            $basetopics = json_decode(json_encode($SESSION->haccgen_data->topics ?? []), true);
            if (!is_array($basetopics) || empty($basetopics)) {
                $basetopics = [['title' => $newcourse->fullname ?: 'Topic 1', 'subtopics' => []]];
            }
            $topics = buildtopicsfrommixed($subtopiccontents, $basetopics);
            $log('Initial topics built (pre WYWL injection)', ['topics' => count($topics)]);


        } else if ($step < 3) {
            $nextstep = $step + 1;
            ob_end_clean();
            redirect(new moodle_url('/local/haccgen/manage.php', ['id' => $courseid, 'step' => $nextstep]));
            exit;
        }
    }
}

$stepstates = [
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
    'audiencetags' => !empty($SESSION->haccgen_data->targetaudience)
        ? explode(',', $SESSION->haccgen_data->targetaudience)
        : [],

    'language' => isset($_POST['language'])
        ? clean_param($_POST['language'], PARAM_TEXT)
        : ($SESSION->haccgen_data->language ?? 'english'),

    'levelofunderstanding' => isset($_POST['levelofunderstanding'])
        ? clean_param($_POST['levelofunderstanding'], PARAM_TEXT)
        : ($SESSION->haccgen_data->levelofunderstanding ?? ''),

    'toneofnarrative' => isset($_POST['toneofnarrative'])
        ? clean_param($_POST['toneofnarrative'], PARAM_TEXT)
        : ($SESSION->haccgen_data->toneofnarrative ?? ''),

    'courseduration' => isset($_POST['courseduration'])
        ? clean_param($_POST['courseduration'], PARAM_TEXT)
        : ($SESSION->haccgen_data->courseduration ?? ''),

    'description' => $SESSION->haccgen_data->description ?? '',
    'generationtype' => $SESSION->haccgen_data->generationtype ?? 'ai',
    'pdfuploaded' => $SESSION->haccgen_data->pdf_file ?? '',
    'currentstep' => $step,
    'stepstates' => $stepstates,
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
    $data->generationtype = $SESSION->haccgen_data->generationtype ?? 'ai';
    $data->pdf_fileid = $SESSION->haccgen_data->pdf_fileid ?? 0;
    $data->description = $SESSION->haccgen_data->description ?? '';
    $data->customprompt = $SESSION->haccgen_data->customprompt ?? '';
    $data->courselanguage = $SESSION->haccgen_data->courselanguage ?? 'English';
    $data->numberoftopics = $SESSION->haccgen_data->numberoftopics ?? 5;
    $data->pdf_reference_url = $SESSION->haccgen_data->pdf_reference_url ?? '';


    // Validate required fields.
    $required = ['coursename', 'targetaudience', 'levelofunderstanding', 'toneofnarrative', 'courseduration', 'numberoftopics'];

    foreach ($required as $field) {
        if (empty($data->$field)) {
            $formdata['errors']['general'] = get_string('missingparam', 'local_haccgen', $field);
            $formdata['has_topics'] = false;
            $formdata['topics'] = [];
            break;
        }
    }
    // Validate allowed values.
    $validlevels = ['Beginner', 'Intermediate', 'Advanced'];
    $validtones = ['Conversational', 'Professional', 'Friendly', 'Technical', 'Formal', 'Engaging'];
    $validdurations = [
        'Less than 15 minutes',
        'Less than 30 minutes',
        'Less than 60 minutes',
        'Less than 90 minutes',
        'Less than 120 minutes',
    ];
    $validlanguages = ['English', 'Hindi'];
    if (!in_array($data->levelofunderstanding, $validlevels)) {
        $formdata['errors']['general'] = get_string('invalidparam', 'local_haccgen', 'level of understanding');
    } else if (!in_array($data->toneofnarrative, $validtones)) {
        $formdata['errors']['general'] = get_string('invalidparam', 'local_haccgen', 'tone of narrative');
    } else if (!in_array($data->courseduration, $validdurations)) {
        $formdata['errors']['general'] = get_string('invalidparam', 'local_haccgen', 'course duration');
    } else if (!in_array($data->courselanguage, $validlanguages)) {
        $formdata['errors']['general'] = get_string('invalidparam', 'local_haccgen', 'language');
    } else if ($data->numberoftopics < 2 || $data->numberoftopics > 10) {
        $formdata['errors']['general'] = get_string('invalidparam', 'local_haccgen', 'number of topics');
    }
    if (empty($formdata['errors'])) {
        try {

            // Single API call.
            $result = local_haccgen_api::generate_subtopics_only($data);

            $subtopics = $result['subtopics'];
            $casestudy = $result['case_study_data'] ?? null;
            $learningobjectives1 = $result['learning_objectives'] ?? [];

            // Store in session (AFTER actual data retrieved).
            $SESSION->haccgen_data->course_title = $data->coursename;
            $SESSION->haccgen_data->raw_subtopics = $subtopics;
            $SESSION->haccgen_data->courselanguage = $data->courselanguage;
            $SESSION->haccgen_data->numberoftopics = $data->numberoftopics;
            $SESSION->haccgen_data->learning_objectives1 = $learningobjectives1;

            $SESSION->haccgen_data->case_study_data = is_array($casestudy)
                ? $casestudy
                : json_decode(json_encode($casestudy), true);

            // Store it in local $data for later steps.
            $data->case_study_data = $SESSION->haccgen_data->case_study_data;

            // Format topics.
            $formdata['topics'] = array_map(function ($index, $subtopic) use ($subtopics) {
                $topicdata = [
                    'id' => $subtopic['id'] ?? uniqid('topic_'),
                    'title' => $subtopic['title'] ?? 'Untitled',
                    'description' => $subtopic['description'] ?? '',
                    'estimated_duration' => $subtopic['estimated_duration'] ?? '',
                    'learning_objectives' => $subtopic['learning_objectives'] ?? [],
                ];

                return array_merge($topicdata, [
                    'is_first' => $index === 0,
                    'is_last' => $index === count($subtopics) - 1,
                    '@index_plus_one' => $index + 1,
                    'encoded_topicdata' => rawurlencode(json_encode(
                        $topicdata,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    )),
                ]);
            }, array_keys($subtopics), $subtopics);

            $formdata['has_topics'] = true;
        } catch (moodle_exception $e) {
            // Directly show the custom message from api.php language strings.
            $formdata['errors']['general'] = $e->getMessage();
            $formdata['has_topics'] = false;
            $formdata['topics'] = [];

            debugging('Subtopic generation failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        } catch (Exception $e) {
            // Catch any unexpected error.
            $formdata['errors']['general'] = get_string('contentgenerationfailed', 'local_haccgen');
            $formdata['has_topics'] = false;
            $formdata['topics'] = [];

            debugging('Unexpected error during subtopic generation: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
} else if ($step == 4) {


    $loaddraft = optional_param('loaddraft', 0, PARAM_BOOL);
    if ($loaddraft && !empty($SESSION->haccgen_draft_data)) {
        $SESSION->haccgen_data->topics = $SESSION->haccgen_draft_data['topics'] ?? [];
        $SESSION->haccgen_data->quizjson = $SESSION->haccgen_draft_data['quizzes'] ?? [];
        unset($SESSION->haccgen_draft_data);
    }


    $formdata['topics'] = $SESSION->haccgen_data->topics ?? [];
    $subtopiccontentmap = [];
    $quizcontentmap = [];
    $learningobjectivesmap = [];

    $topics = $formdata['topics'] ?? [];
    /**
     * Return translated UI labels based on selected language.
     *
     * Provides course preview labels such as "About this course"
     * and "Learning objectives" in supported languages.
     *
     * @param string $lang Selected language string.
     * @return array Associative array of translated labels.
     */
    function haccgen_i18n_labels(string $lang): array {

        $lang = trim($lang);

        // Default English.
        $labels = [
            'about' => 'About this course',
            'learning_objectives_heading' => 'Learning objectives',
            'learning_objectives_prefix' => 'Learning objectives - ',
        ];

        switch ($lang) {
            case 'हिन्दी (Hindi)':
                return [
                    'about' => 'इस पाठ्यक्रम के बारे में',
                    'learning_objectives_heading' => 'सीखने के उद्देश्य',
                    'learning_objectives_prefix' => 'सीखने के उद्देश्य - ',
                ];

            case 'తెలుగు (Telugu)':
                return [
                    'about' => 'ఈ కోర్సు గురించి',
                    'learning_objectives_heading' => 'అభ్యాస లక్ష్యాలు',
                    'learning_objectives_prefix' => 'అభ్యాస లక్ష్యాలు - ',
                ];

            case 'தமிழ் (Tamil)':
                return [
                    'about' => 'இந்த பாடநெறி பற்றி',
                    'learning_objectives_heading' => 'கற்றல் நோக்கங்கள்',
                    'learning_objectives_prefix' => 'கற்றல் நோக்கங்கள் - ',
                ];

            case 'ಕನ್ನಡ (Kannada)':
                return [
                    'about' => 'ಈ ಕೋರ್ಸ್ ಬಗ್ಗೆ',
                    'learning_objectives_heading' => 'ಕಲಿಕೆಯ ಉದ್ದೇಶಗಳು',
                    'learning_objectives_prefix' => 'ಕಲಿಕೆಯ ಉದ್ದೇಶಗಳು - ',
                ];

            case 'বাংলা (Bengali)':
                return [
                    'about' => 'এই কোর্স সম্পর্কে',
                    'learning_objectives_heading' => 'শেখার উদ্দেশ্য',
                    'learning_objectives_prefix' => 'শেখার উদ্দেশ্য - ',
                ];

            case 'English':
            default:
                return $labels;
        }
    }
    $lang = $SESSION->haccgen_data->activelang ?? 'English';

    $labels = haccgen_i18n_labels($lang);
    debugging("shagunDEBUG Selected languageyhabhi: " . $labels['about']);
    for ($t = 0; $t < count($topics); $t++) {
        $topic = $topics[$t];
        $processedsubtopics = [];
        $topicobjectives = [];
        $existingsubs = $topic['subtopics'] ?? [];

        foreach ($existingsubs as $s => $sub) {
            $displaytitle = $sub['title'] ?? "Untitled Subtopic";

            if (!empty($sub['learning_objectives'])) {
                $objectives = array_map('strval', $sub['learning_objectives']);
                $sub['json_learning_objectives'] = json_encode($objectives, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $learningobjectivesmap[$displaytitle] = $objectives;
                $topicobjectives = array_merge($topicobjectives, $objectives);
            }

            $contenthtml = $sub['content_html'] ?? ($sub['content']['text'] ?? '');
            if ($contenthtml === '') {
                $contenthtml = '<p>' . get_string('nocontentavailable', 'local_haccgen') . '</p>';
            }

            $subtopiccontentmap[$displaytitle] = $contenthtml;
            $sub['content_html'] = $sub['content_html'] ?? $contenthtml;
            if (empty($sub['content']['text'])) {
                $sub['content'] = ['text' => $contenthtml, 'itemid' => (int)($sub['content']['itemid'] ?? 0)];
            }
            $processedsubtopics[] = $sub;
        }

        if (!empty($topicobjectives)) {
            $topicobjectives = array_values(array_unique($topicobjectives));
            $objectiveshtml = '<h4>' . $labels['learning_objectives_heading'] . '</h4><ul><li>' .
                implode('</li><li>', $topicobjectives) .
                '</li></ul>';

            $wywltitle = $labels['learning_objectives_prefix'] . $topic['title'];
            $wywlsub = [
                'title' => $wywltitle,
                'content_html' => $objectiveshtml,
                'content' => ['text' => $objectiveshtml, 'itemid' => 0],
                'json_learning_objectives' => json_encode($topicobjectives, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];


            array_unshift($processedsubtopics, $wywlsub);

            $subtopiccontentmap[$wywltitle] = $objectiveshtml;
        }

        $topic['subtopics'] = $processedsubtopics;
        $topics[$t] = $topic;
    }
    $lang = $SESSION->haccgen_data->activelang ?? 'English';
    debugging("shagunDEBUG Selected languagekhali: " . $labels['about']);

    $formdata['topics'] = $topics;
    $courselevelobjectives = $SESSION->haccgen_data->learning_objectives1 ?? [];
    if (!empty($courselevelobjectives)) {
        if (is_array($courselevelobjectives)) {
            $courselevelcontent = '<ul>';
            foreach ($courselevelobjectives as $obj) {
                $courselevelcontent .= '<li>' . htmlspecialchars($obj) . '</li>';
            }
            $courselevelcontent .= '</ul>';
        } else {
            $courselevelcontent = htmlspecialchars((string)$courselevelobjectives);
        }

        $abouttitle = $labels['about'];
        debugging("ABOUT TITLE: " . $abouttitle);


        $previewcontext = [
            'TOPICTITLE'           => $formdata['TOPICTITLE'] ?? '',
            'targetaudience'       => $formdata['targetaudience'] ?? '',
            'levelofunderstanding' => $formdata['levelofunderstanding'] ?? '',
            'toneofnarrative'      => $formdata['toneofnarrative'] ?? '',
            'courseduration'       => $formdata['courseduration'] ?? '',
            'pdfuploaded'          => $formdata['pdfuploaded'] ?? '',
            'audiencetags'         => $formdata['audiencetags'] ?? [],
            'objectives_html'      => $courselevelcontent,
            'learning_objectives_heading' => $labels['learning_objectives_heading'] ?? 'Learning objectives',
        ];

        if (isset($log) && is_callable($log)) {
            $log('TPL.preview_course.context', $previewcontext);
        } else {
            debugging("TPL.preview_course.context" . json_encode($previewcontext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        // Render after logging.
        $previewhtml = $OUTPUT->render_from_template('local_haccgen/preview_course', $previewcontext);

        $courseoverviewtopic = [
            'title' => $abouttitle,
            'subtopics' => [
                [
                    'title' => $abouttitle,
                    'content_html' => $previewhtml,
                ],
            ],
        ];

        $subtopiccontentmap[$abouttitle] = $previewhtml;

        array_unshift($formdata['topics'], $courseoverviewtopic);
    }



    $quizcontentmap = [];
    $quizlist = [];
    $quizindex = 0;

    foreach ($formdata['topics'] as $topicindex => $topic) {

        // Handle both possible quiz structures.
        $quizdata1 = $topic['quiz_data'] ?? ($topic['quiz'] ?? null);

        if (!empty($quizdata1['quiz_title']) && !empty($quizdata1['questions'])) {
            $quiztitle = trim($quizdata1['quiz_title']);
            if ($quiztitle === '') {
                $quiztitle = "Quiz " . ($quizindex + 1);
            }

            // Build quiz content map.
            $questions = [];
            foreach ($quizdata1['questions'] as $q) {
                $questions[] = [
                    'question'    => $q['question'] ?? '',
                    'options'     => $q['options'] ?? [],
                    'answer'      => $q['correct_answer'] ?? '',
                    'explanation' => $q['explanation'] ?? '',
                ];
            }
            $quizcontentmap[$quiztitle] = [
                'instructions' => $quizdata1['instructions'] ?? '',
                'questions'    => $questions,
            ];

            $quizid = "topic{$topicindex}_quiz";
            $quizlist[] = [
                'id'    => $quizid,
                'title' => $quiztitle,
            ];

            $quizindex++;
        }
    }

    $totalslides  = 0;
    $totalquizzes = 0;

    foreach ($formdata['topics'] as $topic) {
        $subtopics = $topic['subtopics'] ?? [];
        $totalslides += count($subtopics);

        if (!empty($topic['quiz_data']['questions']) && count($topic['quiz_data']['questions']) > 0) {
            $totalquizzes++;
        } else if (!empty($topic['quiz']['questions']) && count($topic['quiz']['questions']) > 0) {
            $totalquizzes++;
        }
    }

    $coursehasquiz = $totalquizzes > 0;

    $SESSION->haccgen_data = $SESSION->haccgen_data ?? new stdClass();
    $SESSION->haccgen_data->scorm_meta = [
        'total_slides'  => $totalslides,
        'total_quizzes' => $totalquizzes,
        'has_quiz'      => $coursehasquiz,
        'quiz_list'     => $quizlist,
    ];

    $formdata['scorm_meta'] = $SESSION->haccgen_data->scorm_meta;

    // Flatten SCORM meta for direct Mustache access.
    if (!empty($SESSION->haccgen_data->scorm_meta)) {
        $meta = $SESSION->haccgen_data->scorm_meta;

        $formdata['total_slides'] = $meta['total_slides'] ?? 0;
        $formdata['total_quizzes'] = $meta['total_quizzes'] ?? 0;
        $formdata['has_quiz'] = !empty($meta['has_quiz']);
        $formdata['quiz_list'] = $meta['quiz_list'] ?? [];

        // Optional: JSON for debug / JS.
        $formdata['scorm_meta_json'] = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    // Final JSON to pass to the template.
    $formdata['subtopicContentJson'] = json_encode($subtopiccontentmap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $formdata['quizContentJson'] = json_encode($quizcontentmap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    debugging('quizContentJson: ' . $formdata['quizContentJson']);
    $formdata['learningObjectiveJson'] = json_encode($learningobjectivesmap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $formdata['topicsjson'] = json_encode($formdata['topics'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}


$formdata['courseid'] = $courseid;
$formdata['hasdraft'] = $hasdraft;

$formdata['TOPICTITLE_helpicon'] = [
    'text' => get_string('help_topictitle', 'local_haccgen'),
    'component' => 'local_haccgen',
];

$formdata['targetaudience_helpicon'] = [
    'text' => get_string('help_targetaudience', 'local_haccgen'),
    'component' => 'local_haccgen',
];

$formdata['description_helpicon'] = [
    'text' => get_string('help_description', 'local_haccgen'),
    'component' => 'local_haccgen',
];

$formdata['pdfupload_helpicon'] = [
    'text' => get_string('help_pdfupload', 'local_haccgen'),
    'component' => 'local_haccgen',
];

$formdata['levelofunderstanding_helpicon'] = [
    'text' => get_string('help_levelofunderstanding', 'local_haccgen'),
    'component' => 'local_haccgen',
];

$formdata['toneofnarrative_helpicon'] = [
    'text' => get_string('help_toneofnarrative', 'local_haccgen'),
    'component' => 'local_haccgen',
];

$formdata['courseduration_helpicon'] = [
    'text' => get_string('help_courseduration', 'local_haccgen'),
    'component' => 'local_haccgen',
];
$formdata['statusmessage'] = $statusmessage;
$formdata['statusclasss'] = $statusclass;
$formdata['statusactive'] = $statusactive;
$formdata['passingscore'] = $globalpassingscore;
$formdata['scormversion'] = $_POST['scormversion'] ?? $globalscormversion;
// Normalize possible values.
$scormtype1   = strtolower(trim($formdata['scormtype'] ?? 'single'));
$scormversion = strtoupper(trim($formdata['scormversion'] ?? 'SCORM_1.2'));
if ($globalscormtype == 'multi') {
    $formdata['scormtype_multi_selected'] = $globalscormtype === 'multi';
} else {
    $formdata['scormtype_single_selected'] = $globalscormtype === 'single';
}
// Version selection flags — handle both "2004" and "SCORM_2004".
$formdata['scormversion_12_selected'] =
    ($scormversion === 'SCORM_1.2' || $scormversion === '1.2');

$formdata['scormversion_2004_selected'] =
    ($scormversion === 'SCORM_2004' || $scormversion === '2004');

$formdata['scormtype'] = $globalscormtype;
$formdata['scorm_meta'] = $scormmeta;
$formdata['scorm_meta_json'] = json_encode($scormmeta);
$formdata['labels'] = $labels;
$formdata['activelang'] = $lang;


echo $OUTPUT->header();
if ($step == 1) {
    echo $OUTPUT->render_from_template('local_haccgen/ai_form', $formdata);
} else if ($step == 2) {
    echo $OUTPUT->render_from_template('local_haccgen/step2_form', $formdata);
} else if ($step == 3) {
    echo $OUTPUT->render_from_template('local_haccgen/step3_form', $formdata);
} else if ($step == 4) {
    echo $OUTPUT->render_from_template('local_haccgen/step4_form', $formdata);
}
echo $OUTPUT->footer();

ob_end_flush();
