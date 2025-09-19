<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$courseid   = required_param('id', PARAM_INT);
$topicsjson = required_param('topicsjson', PARAM_RAW);
$quizjson   = required_param('quizjson', PARAM_RAW);

$userid = $USER->id;
$status = 'draft';
$time   = time();

global $DB;

// Decode and validate input
$topics  = json_decode($topicsjson, true);
error_log('Raw topicsjson: ' . $topicsjson);

$quizzes = json_decode($quizjson, true);

// Handle JSON errors
if (json_last_error() !== JSON_ERROR_NONE) {
    throw new moodle_exception('invalidjson', 'local_haccgen', '', json_last_error_msg());
}

// Generate a unique batch ID for this save operation
$batchid = uniqid('batch_', true);

// Save subtopics
if (is_array($topics)) {
    foreach ($topics as $topictitle => $topiccontent) {
        $contentdata = '';

        if (is_array($topiccontent) && isset($topiccontent['text'])) {
            $contentdata = $topiccontent['text'];
        } elseif (is_string($topiccontent)) {
            $contentdata = $topiccontent;
        }

        $record = new stdClass();
        $record->courseid       = $courseid;
        $record->userid         = $userid;
        $record->cmid           = null; // No cmid for drafts
        $record->content_type   = 'subtopic';
        $record->content_title  = $topictitle;
        $record->content_data   = $contentdata;
        $record->status         = $status;
        $record->timecreated    = $time;
        $record->batchid        = $batchid;

        $DB->insert_record('local_haccgen_contentlog', $record);
    }
}

// Save quizzes
if (is_array($quizzes)) {
    foreach ($quizzes as $title => $quizdata) {
        $record = new stdClass();
        $record->courseid      = $courseid;
        $record->userid        = $userid;
        $record->cmid          = null;
        $record->content_type  = 'quiz';
        $record->content_title = $title;
        $record->content_data  = json_encode($quizdata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $record->status        = $status;
        $record->timecreated   = $time;
        $record->batchid       = $batchid;

        $DB->insert_record('local_haccgen_contentlog', $record);
    }
}

redirect(new moodle_url('/course/view.php', ['id' => $courseid]), get_string('draftsavedsuccess', 'local_haccgen'));
