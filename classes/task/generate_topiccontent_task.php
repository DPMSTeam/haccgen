<?php
namespace local_haccgen\task;
defined('MOODLE_INTERNAL') || die();

class generate_topiccontent_task extends \core\task\adhoc_task {
    public function get_component() { return 'local_haccgen'; }

    public function execute() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/local/haccgen/lib.php');
        $vendor = $CFG->dirroot . '/local/haccgen/vendor/autoload.php';
        if (file_exists($vendor)) { require_once($vendor); }

        $data = (object)$this->get_custom_data();
        $job  = $DB->get_record('local_haccgen_job', ['id' => $data->jobid], '*', MUST_EXIST);

        $job->status = 'running'; $job->progress = 10; $job->timemodified = time();
        $DB->update_record('local_haccgen_job', $job);

        try {
            $payload = json_decode($job->inputjson, true, 512, JSON_THROW_ON_ERROR);
            $topics  = $payload['topics']  ?? [];
            $options = (object)($payload['options'] ?? []);

            // NEW: register progress reporter (closure captures $job->id safely)
            \local_haccgen_api::set_progress_reporter(function(int $pct, string $msg, int $completed, int $total) use ($DB, $job) {
                // Refresh + update only minimal fields
                $jr = $DB->get_record('local_haccgen_job', ['id' => $job->id], '*', MUST_EXIST);
                $jr->progress = max(10, min(99, $pct)); // keep <100 while running
                // Store tiny JSON so job_status.php can expose counts
                $jr->message  = json_encode([
                    'completed_topics' => $completed,
                    'total_topics'     => $total,
                    'text'             => $msg,
                ], JSON_UNESCAPED_SLASHES);
                $jr->timemodified = time();
                $DB->update_record('local_haccgen_job', $jr);
            });

            $result = \local_haccgen_api::generate_content_for_topics($topics, $options);
            \local_haccgen_api::set_progress_reporter(null);

            $job->status = 'success';
            $job->progress = 100;
            $job->resultjson = json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $job->message = 'Topic content ready';
            $job->timemodified = time();
            $DB->update_record('local_haccgen_job', $job);

        } catch (\Throwable $e) {
            \local_haccgen_api::set_progress_reporter(null);
            $job->status = 'error';
            $job->progress = max(10, (int)$job->progress);
            $job->message = $e->getMessage();
            $job->timemodified = time();
            $DB->update_record('local_haccgen_job', $job);
            throw $e;
        }
    }
}