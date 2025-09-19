<?php
defined('MOODLE_INTERNAL') || die();

class local_haccgen_api
{
      /** @var null|callable */
      protected static $progressreporter = null;

      public static function set_progress_reporter(?callable $cb): void {
          self::$progressreporter = $cb;
      }
    /**
     * Get subscription URL from settings.
     */
    protected static function get_subscription_url(): string
    {
        $subscriptionurl = get_config('local_haccgen', 'subscription_url');
        if (empty($subscriptionurl)) {
            throw new \moodle_exception('error', 'local_haccgen', '', 'Subscription URL is missing.');
        }
        return $subscriptionurl;
    }

  /** Simple class-local logger to file + error_log */
    private static function log($level, $message, array $ctx = []): void {
        global $CFG;
        $rec = [
            'ts'    => date('c'),
            'level' => strtoupper($level),
            'comp'  => 'local_haccgen',
            'msg'   => $message,
            'ctx'   => $ctx,
        ];
        $json = json_encode($rec, JSON_UNESCAPED_SLASHES);

        // mirror to PHP error_log for quick visibility
        error_log('[local_haccgen] ' . $json);

        // write to moodledata
        $dir = $CFG->dataroot . '/local_haccgen/logs';
        if (!is_dir($dir)) { @mkdir($dir, 0770, true); }
        $file = $dir . '/plugin.log';

        // tiny rotation (~5MB)
        if (file_exists($file) && filesize($file) > 5 * 1024 * 1024) {
            @rename($file, $file . '.' . date('Ymd_His'));
        }
        @file_put_contents($file, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public static function get_api_url() {
        global $CFG;
   
        $apikey    = get_config('local_haccgen', 'apikey');
        $apisecret = get_config('local_haccgen', 'apisecret');
        if (empty($apikey) || empty($apisecret)) {
            throw new \moodle_exception('error', 'local_haccgen', '', 'API credentials are missing.');
        }
        $subscriptionurl = self::get_subscription_url();
        $payload = [
            'api_key'     => $apikey,
            'api_secret'  => $apisecret,
            'action'      => 'plugin_status',
            'plugin_name' => 'AI Course',
        ];
        $masked = [
            'api_key'     => substr($apikey, 0, 4) . '***' . substr($apikey, -2),
            'api_secret'  => substr($apisecret, 0, 4) . '***' . substr($apisecret, -2),
            'action'      => 'plugin_status',
            'plugin_name' => 'AI Course',
        ];
        self::log('INFO', 'Calling subscription endpoint', [
            'url'     => $subscriptionurl,
            'payload' => $masked,
        ]);

        $curl = new \curl();
        $opts = [
            'CURLOPT_HTTPHEADER' => ['Content-Type: application/json'],
            'timeout'            => 60,
        ];
        $raw    = $curl->post($subscriptionurl, json_encode($payload), $opts);
        $info   = method_exists($curl, 'get_info') ? $curl->get_info() : [];
        $errstr = property_exists($curl, 'error') ? $curl->error : null;

        if ($raw === false || $raw === null) {
            self::log('ERROR', 'Subscription call failed (transport)', [
                'http_code'  => $info['http_code'] ?? null,
                'curl_error' => $errstr,
                'info'       => $info,
            ]);
            throw new \moodle_exception('subscription_call_failed', 'local_haccgen', '',
                'Failed to call subscription service (transport error)');
        }

        $preview = substr((string)$raw, 0, 600);
        $json = json_decode($raw, true);
        if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
            self::log('ERROR', 'Subscription call returned non-JSON', [
                'http_code'  => $info['http_code'] ?? null,
                'json_error' => json_last_error_msg(),
                'preview'    => $preview,
            ]);
            throw new \moodle_exception('subscription_call_failed', 'local_haccgen', '',
                'Subscription endpoint returned invalid JSON');
        }

        // Handle API Gateway shape {statusCode, body}
        if (isset($json['body'])) {
            $inner = is_string($json['body']) ? json_decode($json['body'], true) : $json['body'];
            if (!is_array($inner)) {
                self::log('ERROR', 'Subscription body is invalid JSON', [
                    'json_error'   => json_last_error_msg(),
                    'body_preview' => substr((string)$json['body'], 0, 600),
                ]);
                throw new \moodle_exception('subscription_call_failed', 'local_haccgen', '',
                    'Invalid JSON in subscription response body');
            }
            $json = $inner;
        }

        self::log('INFO', 'Subscription response received', [
            'http_code'   => $info['http_code'] ?? null,
            'keys'        => array_keys($json),
            'raw_preview' => $preview,
        ]);

        if (($json['status'] ?? null) !== 'active') {
            self::log('ERROR', 'Subscription inactive/invalid', ['response' => $json]);
            throw new \moodle_exception('error', 'local_haccgen', '', $json['message'] ?? 'Subscription inactive.');
        }
        if (empty($json['content_endpoint'])) {
            self::log('ERROR', 'No content_endpoint in response', ['response' => $json]);
            throw new \moodle_exception('error', 'local_haccgen', '', 'No content endpoint returned.');
        }

        self::log('INFO', 'Subscription OK', ['content_endpoint' => $json['content_endpoint']]);
        return $json['content_endpoint'];
    }
    
    /**
     * Step 3: Generate subtopics only (no content).
     */
    public static function generate_subtopics_only($data)
    {
        global $USER;
       $provider = get_config('local_haccgen', 'provider_for_content_outline') ?? 'gemini';

        // Get endpoint dynamically
        $apiurl = self::get_api_url();
        if (!empty($data->pdf_reference_url)) {
            $payload = [
                'action' => 'generate_subtopics',
                'user_id' =>  $USER->id,
                'provider' => 'gemini',
                'course_data' => [
                    'title'           => $data->coursename,
                    'audience'        => $data->targetaudience,
                    'level'           => $data->levelofunderstanding,
                    'tone'            => $data->toneofnarrative,
                    'duration'        => $data->courseduration,
                    'description'     => $data->description ?? '',
                    'pdf_reference_url' => $data->pdf_reference_url,
                ]
            ];
          
        } else {
            $payload = [
                'action' => 'generate_subtopics',
                'user_id' => 'user_' . $USER->id,
                'course_data' => [
                    'title'           => $data->coursename,
                    'audience'        => $data->targetaudience,
                    'level'           => $data->levelofunderstanding,
                    'tone'            => $data->toneofnarrative,
                    'duration'        => $data->courseduration,
                    'description'     => $data->description ?? '',
                ]
            ];
        }
        $response = self::post_json_via_curl($apiurl, $payload);
        error_log("data->pdf_reference_url  ".$data->pdf_reference_url);
        error_log("Payload sent to content endpoint: " . json_encode($payload, JSON_PRETTY_PRINT));
        error_log("Raw API response: " . json_encode($response, JSON_PRETTY_PRINT));

        if (!isset($response['subtopics'])) {
            throw new moodle_exception('error', 'local_haccgen', '', 'No subtopics returned.');
        }

        return $response;
    }

    /**
     * Step 4: Generate lesson content for each finalized topic/subtopic.
     */
    public static function generate_content_for_topics(array $topics, $data)
{
    global $USER, $CFG;
        $provider_content = get_config('local_haccgen', 'provider_for_content') ?? 'gemini';

        // --- write to the same log file as the adhoc task ---
        $logfile = $CFG->dataroot . '/haccgen_task.log';
    $log = function(string $level, string $msg, array $ctx = []) use ($logfile) {
        $row = [
            'ts'    => date('c'),
            'level' => strtoupper($level),
            'comp'  => 'local_haccgen',
            'msg'   => $msg,
            'ctx'   => $ctx,
        ];
        @file_put_contents($logfile, json_encode($row, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    };

    if (empty($topics)) {
        $log('ERROR', 'No topics provided');
        throw new \moodle_exception('no_topics', 'local_haccgen', '', 'No topics provided.');
    }
    foreach ($topics as $t) {
        if (empty($t['title']) || !isset($t['learning_objectives'])) {
            $log('ERROR', 'Missing topic fields', ['topic_preview' => array_intersect_key($t, array_flip(['id','title']))]);
            throw new \moodle_exception('missing_topic_fields', 'local_haccgen', '', 'Title or learning objectives missing from topic.');
        }
    }
    if (!isset($data)) {
        $log('ERROR', 'Course data not set');
        throw new \moodle_exception('missing_course_data', 'local_haccgen', '', 'Course data is required.');
    }
    if (!isset($data->case_study_data)) {
        $log('ERROR', 'Missing case_study_data in course data');
        throw new \moodle_exception('missing_case_study', 'local_haccgen', '', 'Case study data is required.');
    }

    $apiurl   = self::get_api_url();
    $userId   = 'user_' . $USER->id;
    $provider = isset($data->provider) && $data->provider !== '' ? $data->provider : null;

    $log('INFO', 'Preparing payload', [
        'user'      => $userId,
        'apiurl'    => $apiurl,
        'topics'    => count($topics),
        'provider'  => $provider_content,
    ]);

    $subtopicsPayload = [];
    foreach ($topics as $topic) {
        $subtopicsPayload[] = [
            'id'                   => $topic['id'] ?? uniqid('topic_'),
            'title'                => $topic['title'],
            'description'          => $topic['description'] ?? '',
            'estimated_duration'   => $topic['estimated_duration'] ?? 'Less than 15 minutes',
            'learning_objectives'  => array_values(array_filter(array_map('trim', (array)($topic['learning_objectives'] ?? [])))),
            'case_study_connection'=> $topic['case_study_connection'] ?? null,
            'include_quiz'         => !empty($topic['has_quiz']),
            'quiz_count'           => !empty($topic['has_quiz']) ? (int)($topic['quiz_question_count'] ?? 3) : 0,
        ];
    }

    $courseData = [
        'course_title'        => $data->coursename,
        'level'               => $data->levelofunderstanding,
        'tone'                => $data->toneofnarrative,
        'audience'            => $data->targetaudience,
        'duration'            => $data->courseduration,
        'description'         => $data->description ?? '',
        'learning_objectives' => array_values(array_filter(array_map('trim', (array)($data->learning_objectives ?? [])))),
        'case_study_data'     => $data->case_study_data,
    ];
    if (!empty($data->pdf_reference_url)) {
        $courseData['pdf_reference_url'] = $data->pdf_reference_url;
    }

    $payload = [
        'action'      => 'initiate_content_generation',
        'user_id'     => $userId,
        'subtopics'   => $subtopicsPayload,
        'course_data' => $courseData,
    ];
    if ($provider) { $payload['provider'] = $provider_content; }

    // quick breadcrumb line (same file)
    @file_put_contents($logfile,
        date('c') . ' ' . json_encode(['comp'=>'local_haccgen','note'=>'payload_keys','keys'=>array_keys($payload)], JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );

    $log('INFO', 'Calling content endpoint');
    $response = self::post_json_via_curl_content($apiurl, $payload);

    if (!$response) {
        $log('ERROR', 'Empty or invalid response from API', ['apiurl'=>$apiurl]);
        throw new \moodle_exception('invalid_response', 'local_haccgen', '', 'Empty response from content generation service.');
    }

    $log('INFO', 'Response received', ['top_level_keys' => array_keys($response)]);

    $results = [];
    if (isset($response['results']) && is_array($response['results'])) {
        $results = $response['results'];
    } elseif (isset($response['result']['subtopics']) && is_array($response['result']['subtopics'])) {
        $results = $response['result']['subtopics'];
    } elseif (isset($response['content']) && is_array($response['content'])) {
        $log('INFO', 'Using response.content collection', ['count' => count($response['content'])]);
        foreach ($response['content'] as $c) {
            $topicId   = $c['topic_id'] ?? ($c['id'] ?? null);
            $res       = $c['result'] ?? [];
            $generated = $res['generated_content'] ?? ($c['generated_content'] ?? []);
            $quizData  = $res['quiz_data'] ?? ($c['quiz_data'] ?? null);
            $overrideTitle       = $res['topic_title'] ?? ($c['topic_title'] ?? null);
            $overrideDescription = $c['topic_description'] ?? null;
            $overrideDuration    = $c['estimated_duration'] ?? ($res['estimated_duration'] ?? null);

            $results[] = [
                'id'                 => $topicId,
                'generated_content'  => $generated,
                'quiz_data'          => $quizData,
                '_override'          => [
                    'title'              => $overrideTitle,
                    'description'        => $overrideDescription,
                    'estimated_duration' => $overrideDuration,
                ],
                '_quiz_included_flag'=> isset($res['quiz_included']) ? (bool)$res['quiz_included'] : (isset($c['quiz_included']) ? (bool)$c['quiz_included'] : null),
                '_topic_los'         => $c['topic_learning_objectives'] ?? null,
            ];
        }
    } else {
        $log('ERROR', 'Unexpected response structure', ['preview' => substr(json_encode($response), 0, 500)]);
        throw new \moodle_exception('invalid_response', 'local_haccgen', '', 'The content generation service returned an unexpected response.');
    }

    $byId = []; $byTitle = [];
    $norm = static function ($str) { return strtolower(trim((string)$str)); };

    foreach ($results as $r) {
        if (!empty($r['id'])) { $byId[$r['id']] = $r; }
        $maybeTitle = $r['_override']['title'] ?? ($r['generated_content']['topic_title'] ?? null);
        if (!empty($maybeTitle)) { $byTitle[$norm($maybeTitle)] = $r; }
    }
    $log('INFO', 'Result maps built', ['byId' => count($byId), 'byTitle' => count($byTitle)]);

    $enriched_topics = [];
    foreach ($subtopicsPayload as $stub) {
        $tid       = $stub['id'];
        $origTitle = $stub['title'];
        $r = $byId[$tid] ?? $byTitle[$norm($origTitle)] ?? [];
        if (!$r) {
            $log('WARN', 'No generated result matched topic', ['id'=>$tid, 'title'=>$origTitle]);
        }

        $generated = $r['generated_content'] ?? [];
        $quiz_data = $r['quiz_data'] ?? null;
        $quiz_count = is_array($quiz_data['questions'] ?? null) ? count($quiz_data['questions']) : 0;

        $all_objectives = [];
        if (!empty($generated['topic_learning_objectives']) && is_array($generated['topic_learning_objectives'])) {
            foreach ($generated['topic_learning_objectives'] as $obj) {
                $obj = trim((string)$obj);
                if ($obj !== '') { $all_objectives[] = $obj; }
            }
        } elseif (!empty($r['_topic_los']) && is_array($r['_topic_los'])) {
            foreach ($r['_topic_los'] as $obj) {
                $obj = trim((string)$obj);
                if ($obj !== '') { $all_objectives[] = $obj; }
            }
        } else {
            $all_objectives = $stub['learning_objectives'] ?? [];
        }

        $subtopics = [];
        if (!empty($generated['content_sections']) && is_array($generated['content_sections'])) {
            foreach ($generated['content_sections'] as $section) {
                $section_title   = $section['section_title'] ?? 'Untitled Section';
                $section_content = $section['content'] ?? '';
                $examples        = $section['examples'] ?? [];

                $raw_html  = '<!-- SUBTOPIC: ' . s($section_title) . ' -->';
                $raw_html .= $section_content;

                if (!empty($examples) && is_array($examples)) {
                    $raw_html .= '<ul>';
                    foreach ($examples as $ex) { $raw_html .= '<li>' . s($ex) . '</li>'; }
                    $raw_html .= '</ul>';
                }

                $enhanced_html = trim(self::enhance_llm_html($raw_html));

                $subtopics[] = [
                    'title'               => $section_title,
                    'content'             => $section_content,
                    'examples'            => $examples,
                    'content_html'        => $enhanced_html,
                    'learning_objectives' => $all_objectives,
                ];
            }
        }

        $override = $r['_override'] ?? [];
        $final_title       = !empty($override['title']) ? $override['title'] : $stub['title'];
        $final_description = array_key_exists('description', $override) && $override['description'] !== null
                             ? $override['description'] : ($stub['description'] ?? '');
        $final_duration    = !empty($override['estimated_duration']) ? $override['estimated_duration'] : ($stub['estimated_duration'] ?? '');
        $quiz_included_flag = ($quiz_count > 0) || (!empty($r['_quiz_included_flag']));

        $enriched_topics[] = [
            'id'                 => $tid,
            'title'              => $final_title,
            'description'        => $final_description,
            'estimated_duration' => $final_duration,
            'subtopics'          => $subtopics,
            'quiz_included'      => $quiz_included_flag,
            'quiz_data'          => $quiz_data,
        ];
    }

    $log('INFO', 'Returning enriched topics', [
        'count'  => count($enriched_topics),
        'course' => $courseData['course_title'],
    ]);

    return $enriched_topics;
}

    
    
    /**
     * Enhance raw LLM HTML content:
     * - Convert **bold** to <strong>
     * - Label <image_prompt> blocks
     */
    private static function enhance_llm_html($html)
    {
        // Step 1: Convert **bold** to <strong>
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*\s*(.+?):/m', '<br><strong>$1:</strong>', $html);
    
        // Step 2: Remove standalone lines that begin with "Image:" if an <image_prompt> exists later
        if (strpos($html, '<image_prompt') !== false) {
            $html = preg_replace('/<p>\s*Image:\s*Image \d+:[^<]+<\/p>/i', '', $html);
            $html = preg_replace('/Image:\s*Image \d+:[^\n]+[\r\n]?/i', '', $html);
        }
    
        // Step 3: Completely remove <image_prompt> ... </image_prompt>
        $html = preg_replace('/<image_prompt\b[^>]*>.*?<\/image_prompt>/is', '', $html);
    
        return $html;
    }
    



    /**
     * cURL helper for all API requests.
     */
    private static function post_json_via_curl(string $url, array $data)
    {
        global $CFG;
    
        // ----- tiny file logger (JSON lines) -----
        $logfile = $CFG->dataroot . '/haccgen_task.log';
        $log = function (string $level, string $msg, array $ctx = []) use ($logfile) {
            $row = [
                'ts'    => date('c'),
                'level' => strtoupper($level),
                'comp'  => 'local_haccgen',
                'msg'   => $msg,
                'ctx'   => $ctx,
            ];
            @file_put_contents($logfile, json_encode($row, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
        };
    
        $jsonPayload = json_encode($data);
    
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 180,   // was 30 → allow slow generation
            CURLOPT_CONNECTTIMEOUT => 15,    // fail fast if cannot connect
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_SSL_VERIFYPEER => true,  // keep secure (set to false ONLY on dev with self-signed)
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
    
        $log('INFO', 'HTTP POST begin', [
            'url'          => $url,
            'payload_keys' => array_keys($data),
        ]);
    
        $start = microtime(true);
        $response = curl_exec($ch);
        $elapsedMs = (int) round((microtime(true) - $start) * 1000);
    
        $errno  = curl_errno($ch);
        $errstr = $errno ? curl_error($ch) : null;
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    
        // Short, safe preview for logs
        $preview = is_string($response) ? mb_substr($response, 0, 800) : '';
        $preview = preg_replace('/\s+/', ' ', $preview ?? '');
    
        if ($errno) {
            $log('ERROR', 'cURL transport error', [
                'url'        => $url,
                'errno'      => $errno,
                'error'      => $errstr,
                'elapsed_ms' => $elapsedMs,
            ]);
            throw new \moodle_exception('apierror', 'local_haccgen', '', 'cURL error: ' . $errstr);
        }
    
        if ($status < 200 || $status >= 300) {
            $log('ERROR', 'Non-200 HTTP from endpoint', [
                'url'        => $url,
                'http_code'  => $status,
                'elapsed_ms' => $elapsedMs,
                'preview'    => $preview,
            ]);
            throw new \moodle_exception('apierror', 'local_haccgen', '', 'API returned non-200 status: ' . $status);
        }
    
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $log('ERROR', 'Invalid JSON response', [
                'url'        => $url,
                'elapsed_ms' => $elapsedMs,
                'json_error' => json_last_error_msg(),
                'preview'    => $preview,
            ]);
            throw new \moodle_exception('apierror', 'local_haccgen', '', 'Invalid JSON response from API.');
        }
    
        $log('INFO', 'HTTP POST success', [
            'url'        => $url,
            'elapsed_ms' => $elapsedMs,
            'keys'       => array_keys($decoded),
        ]);
    
        return $decoded;
    }
    



    private static function post_json_via_curl_content(string $url, array $data)
{
    global $CFG;

    $logfile = $CFG->dataroot . '/haccgen_task.log';
    $log = function (string $level, string $msg, array $ctx = []) use ($logfile) {
        $row = [
            'ts'    => date('c'),
            'level' => strtoupper($level),
            'comp'  => 'local_haccgen',
            'msg'   => $msg,
            'ctx'   => $ctx,
        ];
        @file_put_contents($logfile, json_encode($row, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    };

    $pollIntervalSeconds = isset($CFG->haccgen_poll_interval) ? (int)$CFG->haccgen_poll_interval : 45;
    $maxWaitSeconds      = isset($CFG->haccgen_poll_max_wait) ? (int)$CFG->haccgen_poll_max_wait : (30 * 60); // 30 min cap

    // --- helpers ------------------------------------------------------------
    $do_post = function(string $endpoint, array $payload) use ($log) {
        $jsonPayload = json_encode($payload);
        $log('DEBUG', 'Payload JSON', ['payload' => $jsonPayload]);
    
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'local_haccgen/1.0 (+moodle)',
        ]);
    
        $start = microtime(true);
        $response = curl_exec($ch);
        $elapsedMs = (int) round((microtime(true) - $start) * 1000);
    
        $errno  = curl_errno($ch);
        $errstr = $errno ? curl_error($ch) : null;
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    
        $preview = is_string($response) ? preg_replace('/\s+/', ' ', mb_substr($response, 0, 800)) : '';
    
        if ($errno) {
            $log('ERROR', 'cURL transport error', [
                'url'        => $endpoint,
                'errno'      => $errno,
                'error'      => $errstr,
                'elapsed_ms' => $elapsedMs,
            ]);
            throw new \moodle_exception('apierror', 'local_haccgen', '', 'cURL error: ' . $errstr);
        }
        if ($status < 200 || $status >= 300) {
            $log('ERROR', 'Non-200 HTTP from endpoint', [
                'url'        => $endpoint,
                'http_code'  => $status,
                'elapsed_ms' => $elapsedMs,
                'preview'    => $preview,
            ]);
            throw new \moodle_exception('apierror', 'local_haccgen', '', 'API returned non-200 status: ' . $status);
        }
    
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $log('ERROR', 'Invalid JSON response', [
                'url'        => $endpoint,
                'elapsed_ms' => $elapsedMs,
                'json_error' => json_last_error_msg(),
                'preview'    => $preview,
            ]);
            throw new \moodle_exception('apierror', 'local_haccgen', '', 'Invalid JSON response from API.');
        }

        $log('LODA', 'Full response JSON', [
            'url'      => $endpoint,
            'elapsed_ms' => $elapsedMs,
            'response' => $decoded,
        ]);
    
        $log('LODA', 'HTTP POST success', [
            'url'        => $endpoint,
            'elapsed_ms' => $elapsedMs,
            'keys'       => array_keys($decoded),
        ]);
    
        return $decoded;
    };
    

    $do_get_json = function(string $downloadUrl) use ($log) {
        $log('INFO', 'Downloading generated JSON', ['download_url' => $downloadUrl]);

        $ch = curl_init($downloadUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 180,   
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'local_haccgen/1.0 (+moodle)',
        ]);

        $start = microtime(true);
        $body = curl_exec($ch);
        $elapsedMs = (int) round((microtime(true) - $start) * 1000);

        $errno  = curl_errno($ch);
        $errstr = $errno ? curl_error($ch) : null;
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $preview = is_string($body) ? preg_replace('/\s+/', ' ', mb_substr($body, 0, 800)) : '';

        if ($errno) {
            $log('ERROR', 'cURL transport error (download)', [
                'url'        => $downloadUrl,
                'errno'      => $errno,
                'error'      => $errstr,
                'elapsed_ms' => $elapsedMs,
            ]);
            throw new \moodle_exception('apierror', 'local_haccgen', '', 'Download error: ' . $errstr);
        }
        if ($status < 200 || $status >= 300) {
            $log('ERROR', 'Non-200 HTTP from download URL', [
                'url'        => $downloadUrl,
                'http_code'  => $status,
                'elapsed_ms' => $elapsedMs,
                'preview'    => $preview,
            ]);
            throw new \moodle_exception('apierror', 'local_haccgen', '', 'Download URL returned non-200 status: ' . $status);
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $log('ERROR', 'Invalid JSON body from download URL', [
                'url'        => $downloadUrl,
                'elapsed_ms' => $elapsedMs,
                'json_error' => json_last_error_msg(),
                'preview'    => $preview,
            ]);
            throw new \moodle_exception('apierror', 'local_haccgen', '', 'Downloaded content is not valid JSON.');
        }

        $log('INFO', 'Downloaded JSON parsed OK', [
            'elapsed_ms' => $elapsedMs,
            'top_keys'   => array_keys($decoded),
        ]);

        return $decoded;
    };

    // --- Stage 1: initial request ------------------------------------------
    $initial = $do_post($url, $data);

    if (!empty($initial['download_url']) || (!empty($initial['phase']) && $initial['phase'] === 'completed')) {
        $downloadUrl = $initial['download_url'] ?? null;
        if (!$downloadUrl) {
            throw new \moodle_exception('apierror', 'local_haccgen', '', 'Generation marked completed but no download_url provided.');
        }
        return $do_get_json($downloadUrl);
    }
    if (empty($initial['request_id'])) {
        return $initial;
    }

    $requestId = (string)$initial['request_id'];
    $log('INFO', 'Generation initiated', [
        'request_id' => $requestId,
        'status'     => $initial['status'] ?? null,
        'message'    => $initial['message'] ?? null,
        'topics'     => $initial['total_topics'] ?? null,
        'estimate'   => $initial['estimated_completion'] ?? null,
    ]);

    // --- Stage 2: poll/resume until completed ------------------------------
    $deadline = time() + $maxWaitSeconds;
    $firstPoll = true;

    while (true) {
        if (time() >= $deadline) {
            $log('ERROR', 'Polling timed out', [
                'request_id' => $requestId,
                'waited_s'   => $maxWaitSeconds,
            ]);
            throw new \moodle_exception('apierror', 'local_haccgen', '', 'Timed out waiting for content generation to finish.');
        }

        sleep($pollIntervalSeconds);
        $payload = $firstPoll
            ? ['action' => 'initiate_content_generation', 'request_id' => $requestId]
            : ['action' => 'get_generation_status',      'request_id' => $requestId];

        $firstPoll = false;

        $status = $do_post($url, $payload);

        $phase     = $status['phase']  ?? null;
        $stat      = $status['status'] ?? null;
        $ready     = isset($status['content_ready']) ? (bool)$status['content_ready'] : false;

        $completedTopics = isset($status['completed_topics']) ? (int)$status['completed_topics'] : null;
        $totalTopics     = isset($status['total_topics']) ? (int)$status['total_topics'] : null;

      
        if (self::$progressreporter && $totalTopics && $totalTopics > 0 && $completedTopics !== null) {
            $pct = (int) round(($completedTopics / max(1, $totalTopics)) * 100);
            $msg = "Generating content… {$completedTopics}/{$totalTopics} topics complete";
            try { (self::$progressreporter)($pct, $msg, $completedTopics, $totalTopics); } catch (\Throwable $__) {}
        }

        $completed = ($phase === 'completed') || ($stat === 'completed') || $ready || !empty($status['download_url']);

        if (!empty($status['error']) || (!empty($phase) && $phase === 'failed') || (!empty($stat) && $stat === 'failed')) {
            $log('ERROR', 'Generation failed', [
                'request_id' => $requestId,
                'status'     => $stat,
                'phase'      => $phase,
                'error'      => $status['error'] ?? ($status['message'] ?? 'unknown error'),
            ]);
            throw new \moodle_exception('apierror', 'local_haccgen', '', 'Generation failed: ' . ($status['error'] ?? ($status['message'] ?? 'unknown error')));
        }

        if ($completed) {
            $downloadUrl = $status['download_url'] ?? null;
            if (!$downloadUrl) {
                $log('ERROR', 'Completed but missing download_url', ['request_id' => $requestId, 'status_keys' => array_keys($status)]);
                throw new \moodle_exception('apierror', 'local_haccgen', '', 'Generation completed but no download_url provided.');
            }

            // --- Stage 3: fetch final JSON from presigned URL ----------------
            return $do_get_json($downloadUrl);
        }

        // keep looping
    }
}

}
