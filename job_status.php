<?php
require_once(__DIR__ . '/../../config.php');

$jobid = required_param('id', PARAM_INT);

require_login();

global $DB, $USER;

$job = $DB->get_record('local_haccgen_job', ['id' => $jobid], '*', MUST_EXIST);
require_capability('local/haccgen:manage', \context_course::instance($job->courseid));

if ($job->userid != $USER->id && !is_siteadmin()) {
    throw new moodle_exception('nopermissions', 'error');
}

$completed = null;
$total     = null;
$textmsg   = '';

$msgraw = (string)($job->message ?? '');
if ($msgraw !== '') {
    $maybe = json_decode($msgraw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($maybe)) {
        if (isset($maybe['completed_topics'])) { $completed = (int)$maybe['completed_topics']; }
        if (isset($maybe['total_topics']))     { $total     = (int)$maybe['total_topics']; }
        if (!empty($maybe['text']))            { $textmsg   = (string)$maybe['text']; }
    } else {
        $textmsg = $msgraw;
    }
}


$progress = (int)$job->progress;
if ($total && $total > 0 && $completed !== null) {
    $progress = (int)round(($completed / max(1, $total)) * 100);
    if ($job->status !== 'success') {
        $progress = min($progress, 99);
    }
}

@header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status'           => $job->status,
    'progress'         => max(0, min(100, $progress)),
    'message'           => $textmsg,
    'completed_topics' => $completed,
    'total_topics'     => $total,
    'result'           => ($job->status === 'success') ? json_decode((string)$job->resultjson, true) : null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

exit;
