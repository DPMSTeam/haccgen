<?php
require_once(__DIR__.'/../../config.php');
require_login();
require_sesskey();

$jobid = optional_param('jobid', 0, PARAM_INT);
if (!$jobid) { $jobid = optional_param('id', 0, PARAM_INT); }
if (!$jobid) { throw new moodle_exception('missingparam', 'error', '', 'jobid'); }

global $DB, $USER, $SESSION, $CFG;

$job = $DB->get_record('local_haccgen_job', ['id' => $jobid], '*', MUST_EXIST);
require_capability('local/haccgen:manage', \context_course::instance($job->courseid));
if ($job->userid != $USER->id && !is_siteadmin()) { throw new moodle_exception('nopermissions', 'error'); }
if ($job->status !== 'success') { throw new moodle_exception('processing', 'local_haccgen'); }

$resultraw = $job->resultjson ?? '';
$result    = $resultraw ? json_decode($resultraw, true) : [];
if ($resultraw && json_last_error() !== JSON_ERROR_NONE) {
    error_log("[local_haccgen][consume_job] JSON decode error for job {$job->id}: ".json_last_error_msg());
    $result = [];
}


function aic_is_list(array $a): bool {
    $i = 0; foreach ($a as $k => $_) { if ($k !== $i++) return false; } return true;
}

function aic_pick_topics($result, $fallbacktitle = 'Content'): array {
    if (is_array($result) && isset($result['topics']) && is_array($result['topics'])) {
        return $result['topics'];
    }
    if (is_array($result) && isset($result['data']['topics']) && is_array($result['data']['topics'])) {
        return $result['data']['topics'];
    }
    if (is_array($result) && aic_is_list($result) && isset($result[0]['title']) && isset($result[0]['subtopics'])) {
        return $result;
    }
    if (is_array($result) && isset($result['subtopics']) && is_array($result['subtopics'])) {
        return [[
            'title' => $fallbacktitle,
            'subtopics' => array_map(function($s){
                return is_array($s) ? $s : ['title' => 'Subtopic', 'content' => (string)$s];
            }, $result['subtopics'])
        ]];
    }
    return [];
}


$logdir = $CFG->dataroot . '/temp/haccgen';
if (!is_dir($logdir)) { @mkdir($logdir, 0777, true); }
file_put_contents(
    $logdir.'/consume_job_'.(int)$jobid.'.log',
    "=== ".date('c')." job {$job->id} ===\n".substr($resultraw ?: 'null', 0, 5000)."\n\n",
    FILE_APPEND | LOCK_EX
);


$SESSION->haccgen_data = $SESSION->haccgen_data ?? new stdClass();

if ($job->type === 'topiccontent') {
    $title  = $SESSION->haccgen_data->TOPICTITLE ?? 'Content';
    $topics = aic_pick_topics($result, $title);
    error_log("[local_haccgen][consume_job] job {$job->id} picked topics: ".count($topics));

    $SESSION->haccgen_data->topics  = $topics;
    $SESSION->haccgen_data2 = (object)['topics' => $topics];

    redirect(new moodle_url('/local/haccgen/manage.php', ['id' => $job->courseid, 'step' => 4]));
    exit;
}

if ($job->type === 'subtopics') {
    $SESSION->haccgen_data->raw_subtopics = $result['subtopics'] ?? [];
    redirect(new moodle_url('/local/haccgen/manage.php', ['id' => $job->courseid, 'step' => 3]));
    exit;
}

if ($job->type === 'buildcourse') {
    redirect(new moodle_url('/course/view.php', ['id' => $job->courseid]));
    exit;
}

throw new moodle_exception('unknownjobtype', 'local_haccgen');



/* ---- safe logging to dataroot ---- 
$logdir = $CFG->dataroot . '/temp/haccgen';
if (!is_dir($logdir)) { @mkdir($logdir, 0777, true); }
$filepath = $logdir . '/consume_job_' . (int)$jobid . '.log';
$snippet  = substr($resultraw ?: 'null', 0, 5000);
file_put_contents(
    $filepath,
    "=== ".date('c')." job {$job->id} type={$job->type} status={$job->status} ===\n{$snippet}\n\n",
    FILE_APPEND | LOCK_EX
);
*/