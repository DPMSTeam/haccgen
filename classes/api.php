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

/**
 * API handler class for HaccGen.
 *
 * Provides methods for communicating with the HaccGen service
 * and performing remote requests.
 *
 * @package     local_haccgen
 * @copyright   2026 Dynamicpixel Multimedia Solutions
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_haccgen_api {
    /** @var null|callable */
    protected static $progressreporter = null;

    /**
     * Sets a progress reporter callback.
     *
     * The callback will be invoked to report progress updates
     * during long-running operations.
     *
     * @param callable|null $cb Progress reporter callback or null to disable.
     * @return void
     */
    public static function set_progress_reporter(?callable $cb): void {
        self::$progressreporter = $cb;
    }

    /**
     * Subscription manager endpoint URL (configured in plugin settings).
     *
     * @return string
     */
    public static function get_subscription_url(): string {
        $subscriptionurl = get_config('local_haccgen', 'subscription_url');
        if (empty($subscriptionurl)) {
            throw new \moodle_exception('error', 'local_haccgen', '', 'Subscription URL is missing.');
        }
        return $subscriptionurl;
    }

    /**
     * Get API credentials from plugin configuration.
     *
     * @return array{0:string,1:string}
     */
    protected static function get_api_credentials(): array {
        $apikey = get_config('local_haccgen', 'apikey');
        $apisecret = get_config('local_haccgen', 'apisecret');

        if (empty($apikey) || empty($apisecret)) {
            throw new \moodle_exception('error', 'local_haccgen', '', 'API credentials are missing.');
        }

        return [$apikey, $apisecret];
    }

    /**
     * Generate subtopics only (single call – no polling).
     *
     * This now posts directly to subscription_url, including credentials.
     *
     * @param mixed $data Input data object.
     * @return mixed
     */
    public static function generate_subtopics_only($data) {
        global $USER, $CFG;

        // Provider with safe fallback.
        $provider = get_config('local_haccgen', 'provider_for_content_outline');
        if (empty($provider)) {
            $provider = 'gemini';
        }

        $subscriptionurl = self::get_subscription_url();
        [$apikey, $apisecret] = self::get_api_credentials();

        // Build base payload depending on whether PDF reference exists.
        if (!empty($data->pdf_reference_url)) {
            $payload = [
                'action' => 'generate_subtopics',
                'user_id' => $USER->id,
                'provider' => $provider,
                'course_data' => [
                    'title' => $data->coursename,
                    'audience' => $data->targetaudience,
                    'level' => $data->levelofunderstanding,
                    'tone' => $data->toneofnarrative,
                    'duration' => $data->courseduration,
                    'description' => $data->description ?? '',
                    'pdf_reference_url' => $data->pdf_reference_url,
                ],
            ];
        } else {
            $payload = [
                'action' => 'generate_subtopics',
                'user_id' => $USER->id,
                'provider' => $provider,
                'course_data' => [
                    'title' => $data->coursename,
                    'audience' => $data->targetaudience,
                    'level' => $data->levelofunderstanding,
                    'tone' => $data->toneofnarrative,
                    'duration' => $data->courseduration,
                    'description' => $data->description ?? '',
                ],
            ];
        }

        // Attach subscription credentials and plugin identifier.
        $payload['api_key'] = $apikey;
        $payload['api_secret'] = $apisecret;
        $payload['plugin_name'] = get_string('pluginname', 'local_haccgen');
        $payload['lms_url'] = $CFG->wwwroot;

        // Call the subscription manager, which validates and forwards.
        try {
            $response = self::post_json_via_curl($subscriptionurl, $payload);
        } catch (\Throwable $e) {
            throw new \moodle_exception('error', 'local_haccgen', '', 'API request failed: ' . $e->getMessage());
        }

        if (!is_array($response)) {
            throw new \moodle_exception('error', 'local_haccgen', '', 'API response is not an array');
        }

        // If subscription_manager returned an error/status structure, surface that clearly.
        if (
            isset($response['status']) &&
            $response['status'] !== 'ok' &&
            $response['status'] !== 'success' &&
            !isset($response['subtopics'])
        ) {
            $message = $response['message'] ?? 'Remote subscription or content service error';
            throw new \moodle_exception('error', 'local_haccgen', '', $message);
        }

        if (!isset($response['subtopics'])) {
            throw new \moodle_exception('error', 'local_haccgen', '', 'No subtopics returned in API response');
        }

        // Sanitization recursive closure.
        $sanitize = function ($value) use (&$sanitize) {
            if (is_string($value)) {
                return trim(strip_tags($value));
            }
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $value[$k] = $sanitize($v);
                }
                return $value;
            }
            if (is_object($value)) {
                foreach ($value as $k => $v) {
                    $value->$k = $sanitize($v);
                }
                return $value;
            }
            return $value;
        };

        return $sanitize($response);
    }

    /**
     * Get the subscription status from the subscription manager.
     *
     * @return array
     */
    public static function get_subscription_status(): array {
        global $CFG, $USER;

        $subscriptionurl = self::get_subscription_url();
        [$apikey, $apisecret] = self::get_api_credentials();

        $payload = [
            'action' => 'plugin_status',
            'api_key' => $apikey,
            'api_secret' => $apisecret,
            'plugin_name' => get_string('pluginname', 'local_haccgen'),
            'lms_url' => $CFG->wwwroot,
            'user_id' => $USER->id, // Optional.
        ];

        $response = self::post_json_via_curl($subscriptionurl, $payload);

        if (!is_array($response)) {
            throw new \moodle_exception(
                'error',
                'local_haccgen',
                '',
                'Invalid status response from subscription manager.'
            );
        }

        return $response;
    }

    /**
     * Generate full content for topics (long-running with polling).
     *
     * All calls go via subscription_url (subscription_manager).
     *
     * @param array $topics Topics payload.
     * @param mixed $data Course data object.
     * @return array
     */
    public static function generate_content_for_topics(array $topics, $data) {
        global $USER, $CFG;

        $providercontent = get_config('local_haccgen', 'provider_for_content') ?? 'gemini';

        if (empty($topics)) {
            throw new \moodle_exception('no_topics', 'local_haccgen', '', 'No topics provided.');
        }

        foreach ($topics as $t) {
            if (empty($t['title']) || !isset($t['learning_objectives'])) {
                throw new \moodle_exception(
                    'missing_topic_fields',
                    'local_haccgen',
                    '',
                    'Title or learning objectives missing from topic.'
                );
            }
        }

        if (!isset($data)) {
            throw new \moodle_exception('missing_course_data', 'local_haccgen', '', 'Course data is required.');
        }
        if (!isset($data->case_study_data)) {
            throw new \moodle_exception('missing_case_study', 'local_haccgen', '', 'Case study data is required.');
        }

        $subscriptionurl = self::get_subscription_url();
        [$apikey, $apisecret] = self::get_api_credentials();

        $userid = 'user_' . $USER->id;
        $provider = isset($data->provider) && $data->provider !== '' ? $data->provider : null;

        $subtopicspayload = [];
        foreach ($topics as $topic) {
            $subtopicspayload[] = [
                'id' => $topic['id'] ?? uniqid('topic_'),
                'title' => $topic['title'],
                'description' => $topic['description'] ?? '',
                'estimated_duration' => $topic['estimated_duration'] ?? 'Less than 15 minutes',
                'learning_objectives' => array_values(
                    array_filter(
                        array_map(
                            'trim',
                            (array) ($topic['learning_objectives'] ?? [])
                        )
                    )
                ),
                'case_study_connection' => $topic['case_study_connection'] ?? null,
                'include_quiz' => !empty($topic['has_quiz']),
                'quiz_count' => !empty($topic['has_quiz']) ? (int) ($topic['quiz_question_count'] ?? 3) : 0,
            ];
        }

        $coursedata = [
            'course_title' => $data->coursename,
            'level' => $data->levelofunderstanding,
            'tone' => $data->toneofnarrative,
            'audience' => $data->targetaudience,
            'duration' => $data->courseduration,
            'description' => $data->description ?? '',
            'learning_objectives' => array_values(
                array_filter(
                    array_map(
                        'trim',
                        (array) ($data->learning_objectives ?? [])
                    )
                )
            ),
            'case_study_data' => $data->case_study_data,
        ];

        if (!empty($data->pdf_reference_url)) {
            $coursedata['pdf_reference_url'] = $data->pdf_reference_url;
        }

        $payload = [
            'action' => 'initiate_content_generation',
            'user_id' => $userid,
            'subtopics' => $subtopicspayload,
            'course_data' => $coursedata,
        ];
        $payload['lms_url'] = $CFG->wwwroot;

        if ($provider) {
            $payload['provider'] = $providercontent;
        }

        // Attach subscription credentials and plugin identifier.
        $payload['api_key'] = $apikey;
        $payload['api_secret'] = $apisecret;
        $payload['plugin_name'] = get_string('pluginname', 'local_haccgen');

        // Use the long-running cURL helper, which now preserves credentials for polling.
        $response = self::post_json_via_curl_content($subscriptionurl, $payload);

        if (!is_array($response)) {
            throw new \moodle_exception(
                'invalid_response',
                'local_haccgen',
                '',
                'Empty or invalid response from content generation service.'
            );
        }

        // If subscription_manager returned an error/status structure without content payloads, surface that clearly.
        if (
            isset($response['status']) &&
            $response['status'] !== 'ok' &&
            $response['status'] !== 'success' &&
            !isset($response['results']) &&
            !isset($response['result']) &&
            !isset($response['content'])
        ) {
            $message = $response['message'] ?? 'Remote subscription or content service error';
            throw new \moodle_exception('error', 'local_haccgen', '', $message);
        }

        $results = [];
        if (isset($response['results']) && is_array($response['results'])) {
            $results = $response['results'];
        } else if (isset($response['result']['subtopics']) && is_array($response['result']['subtopics'])) {
            $results = $response['result']['subtopics'];
        } else if (isset($response['content']) && is_array($response['content'])) {
            foreach ($response['content'] as $c) {
                $topicid = $c['topic_id'] ?? ($c['id'] ?? null);
                $res = $c['result'] ?? [];
                $generated = $res['generated_content'] ?? ($c['generated_content'] ?? []);
                $quizdata = $res['quiz_data'] ?? ($c['quiz_data'] ?? null);
                $overridetitle = $res['topic_title'] ?? ($c['topic_title'] ?? null);
                $overridedescription = $c['topic_description'] ?? null;
                $overrideduration = $c['estimated_duration'] ?? ($res['estimated_duration'] ?? null);

                $results[] = [
                    'id' => $topicid,
                    'generated_content' => $generated,
                    'quiz_data' => $quizdata,
                    '_override' => [
                        'title' => $overridetitle,
                        'description' => $overridedescription,
                        'estimated_duration' => $overrideduration,
                    ],
                    '_quiz_included_flag' => isset($res['quiz_included'])
                        ? (bool) $res['quiz_included']
                        : (isset($c['quiz_included']) ? (bool) $c['quiz_included'] : null),
                    '_topic_los' => $c['topic_learning_objectives'] ?? null,
                ];
            }
        } else {
            throw new \moodle_exception(
                'invalid_response',
                'local_haccgen',
                '',
                'The content generation service returned an unexpected response.'
            );
        }

        $byid = [];
        $bytitle = [];
        $norm = static function ($str) {
            return strtolower(trim((string) $str));
        };

        foreach ($results as $r) {
            if (!empty($r['id'])) {
                $byid[$r['id']] = $r;
            }
            $maybetitle = $r['_override']['title'] ?? ($r['generated_content']['topic_title'] ?? null);
            if (!empty($maybetitle)) {
                $bytitle[$norm($maybetitle)] = $r;
            }
        }

        $enrichedtopics = [];
        foreach ($subtopicspayload as $stub) {
            $tid = $stub['id'];
            $origtitle = $stub['title'];

            $r = $byid[$tid] ?? $bytitle[$norm($origtitle)] ?? [];

            $generated = $r['generated_content'] ?? [];
            $quizdata = $r['quiz_data'] ?? null;
            $quizcount = is_array($quizdata['questions'] ?? null) ? count($quizdata['questions']) : 0;

            $allobjectives = [];
            if (
                !empty($generated['topic_learning_objectives']) &&
                is_array($generated['topic_learning_objectives'])
            ) {
                foreach ($generated['topic_learning_objectives'] as $obj) {
                    $obj = trim((string) $obj);
                    if ($obj !== '') {
                        $allobjectives[] = $obj;
                    }
                }
            } else if (!empty($r['_topic_los']) && is_array($r['_topic_los'])) {
                foreach ($r['_topic_los'] as $obj) {
                    $obj = trim((string) $obj);
                    if ($obj !== '') {
                        $allobjectives[] = $obj;
                    }
                }
            } else {
                $allobjectives = $stub['learning_objectives'] ?? [];
            }

            $subtopics = [];
            if (!empty($generated['content_sections']) && is_array($generated['content_sections'])) {
                foreach ($generated['content_sections'] as $section) {
                    $sectiontitle = $section['section_title'] ?? 'Untitled Section';
                    $sectioncontent = $section['content'] ?? '';
                    $examples = $section['examples'] ?? [];

                    $rawhtml = '<!-- SUBTOPIC: ' . s($sectiontitle) . ' -->';
                    $rawhtml .= $sectioncontent;

                    if (!empty($examples) && is_array($examples)) {
                        $rawhtml .= '<ul>';
                        foreach ($examples as $ex) {
                            $rawhtml .= '<li>' . s($ex) . '</li>';
                        }
                        $rawhtml .= '</ul>';
                    }

                    $enhancedhtml = trim(self::enhance_llm_html($rawhtml));

                    $subtopics[] = [
                        'title' => $sectiontitle,
                        'content' => $sectioncontent,
                        'examples' => $examples,
                        'content_html' => $enhancedhtml,
                        'learning_objectives' => $allobjectives,
                    ];
                }
            }

            $override = $r['_override'] ?? [];
            $finaltitle = !empty($override['title']) ? $override['title'] : $stub['title'];
            $finaldescription = array_key_exists('description', $override) && $override['description'] !== null
                ? $override['description']
                : ($stub['description'] ?? '');
            $finalduration = !empty($override['estimated_duration'])
                ? $override['estimated_duration']
                : ($stub['estimated_duration'] ?? '');
            $quizincludedflag = ($quizcount > 0) || (!empty($r['_quiz_included_flag']));

            $enrichedtopics[] = [
                'id' => $tid,
                'title' => $finaltitle,
                'description' => $finaldescription,
                'estimated_duration' => $finalduration,
                'subtopics' => $subtopics,
                'quiz_included' => $quizincludedflag,
                'quiz_data' => $quizdata,
            ];
        }

        return $enrichedtopics;
    }

    /**
     * Post-process LLM HTML to improve Moodle rendering.
     *
     * @param string $html Raw HTML from the service.
     * @return string Processed HTML.
     */
    private static function enhance_llm_html($html) {
        $codeblocks = [];

        $html = preg_replace_callback(
            '/<code\b[^>]*>.*?<\/code>/is',
            function ($m) use (&$codeblocks) {
                $key = "__CODEBLOCK_" . count($codeblocks) . "__";
                $codeblocks[$key] = $m[0];
                return $key;
            },
            $html
        );

        if (strpos($html, '<image_prompt') !== false) {
            $html = preg_replace('/<p>\s*Image:\s*Image \d+:[^<]+<\/p>/i', '', $html);
            $html = preg_replace('/Image:\s*Image \d+:[^\n]+[\r\n]?/i', '', $html);
        }

        $html = preg_replace('/<image_prompt\b[^>]*>.*?<\/image_prompt>/is', '', $html);
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*\s*(.+?):/m', '<br><strong>$1:</strong>', $html);
        $html = preg_replace('/\*\s*(.+?):/m', '<b>$1:</b>', $html);
        $html = preg_replace('/\*\s*(.+?):/m', '<div>$1:</div>', $html);
        $html = preg_replace('/&lt;code&gt;(.*?)&lt;\/code&gt;/i', '$1', $html);

        foreach ($codeblocks as $key => $original) {
            $html = str_replace($key, $original, $html);
        }

        return $html;
    }

    /**
     * Generic JSON POST (single request) – used against subscription_url.
     *
     * @param string $url Target URL.
     * @param array $data Payload.
     * @return array
     */
    private static function post_json_via_curl(string $url, array $data) {
        $jsonpayload = json_encode($data);

        if ($jsonpayload === false) {
            $jsonerror = json_last_error_msg();
            throw new \moodle_exception(
                'apierror',
                'local_haccgen',
                '',
                'Failed to encode JSON payload: ' . $jsonerror
            );
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonpayload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 180,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $start = microtime(true);
        $response = curl_exec($ch);
        $elapsedms = (int) round((microtime(true) - $start) * 1000);

        $errno = curl_errno($ch);
        $errstr = $errno ? curl_error($ch) : null;
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            self::capture_api_metrics([
                'url' => $url,
                'action' => $data['action'] ?? null,
                'payload' => $jsonpayload,
                'response_raw' => '',
                'http_code' => $status ?: null,
                'elapsed_ms' => $elapsedms,
                'user_id' => $data['user_id'] ?? null,
                'phase' => 'curl_error',
            ]);
            throw new \moodle_exception('apierror', 'local_haccgen', '', 'cURL error: ' . $errstr);
        }

        if ($status < 200 || $status >= 300) {
            self::capture_api_metrics([
                'url' => $url,
                'action' => $data['action'] ?? null,
                'payload' => $jsonpayload,
                'response_raw' => (string) $response,
                'http_code' => $status,
                'elapsed_ms' => $elapsedms,
                'user_id' => $data['user_id'] ?? null,
                'phase' => 'http_error',
            ]);
            throw new \moodle_exception('apierror', 'local_haccgen', '', 'API returned non-200 status: ' . $status);
        }

        $decoded = json_decode((string) $response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            self::capture_api_metrics([
                'url' => $url,
                'action' => $data['action'] ?? null,
                'payload' => $jsonpayload,
                'response_raw' => (string) $response,
                'http_code' => $status,
                'elapsed_ms' => $elapsedms,
                'user_id' => $data['user_id'] ?? null,
                'phase' => 'json_decode_error',
            ]);
            throw new \moodle_exception('apierror', 'local_haccgen', '', 'Invalid JSON response from API.');
        }

        self::capture_api_metrics([
            'url' => $url,
            'action' => $data['action'] ?? null,
            'payload' => $jsonpayload,
            'response_raw' => (string) $response,
            'http_code' => $status,
            'elapsed_ms' => $elapsedms,
            'user_id' => $data['user_id'] ?? null,
            'request_id' => $decoded['request_id'] ?? ($data['request_id'] ?? null),
            'phase' => $decoded['phase'] ?? ($decoded['status'] ?? null),
            'response_keys' => array_keys($decoded),
        ]);

        return $decoded;
    }

    /**
     * JSON POST with polling for long-running generation.
     *
     * Preserves auth fields on each poll.
     *
     * @param string $url Target URL.
     * @param array $data Payload.
     * @return array
     */
    private static function post_json_via_curl_content(string $url, array $data) {
        global $CFG;

        $pollintervalseconds = isset($CFG->haccgen_poll_interval) ? (int) $CFG->haccgen_poll_interval : 45;
        $maxwaitseconds = isset($CFG->haccgen_poll_max_wait) ? (int) $CFG->haccgen_poll_max_wait : (30 * 60);

        // Logging helper.
        $logfile = $CFG->dataroot . '/post_json_via_curl.log';
        $log = function (string $message) use ($logfile): void {
            $line = sprintf("[%s] [post_json_via_curl_content] %s%s", date('Y-m-d H:i:s'), $message, PHP_EOL);
            @file_put_contents($logfile, $line, FILE_APPEND | LOCK_EX);
        };

        // Extract auth fields to re-send on each poll.
        $auth = [];
        foreach (['api_key', 'api_secret', 'plugin_name', 'plugin_identifier', 'lms_url'] as $field) {
            if (isset($data[$field])) {
                $auth[$field] = $data[$field];
            }
        }

        $dopost = function (string $endpoint, array $payload) use ($log) {
            $jsonpayload = json_encode($payload);

            if ($jsonpayload === false) {
                $jsonerror = json_last_error_msg();
                $log("Failed to json_encode payload for POST {$endpoint}: {$jsonerror}");
                throw new \moodle_exception(
                    'apierror',
                    'local_haccgen',
                    '',
                    'Failed to encode JSON payload: ' . $jsonerror
                );
            }

            $log("POST {$endpoint} | payload length: " . strlen($jsonpayload));

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonpayload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 180,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_ENCODING => '',
                CURLOPT_USERAGENT => 'local_haccgen/1.0 (+moodle)',
            ]);

            $start = microtime(true);
            $response = curl_exec($ch);
            $elapsedms = (int) round((microtime(true) - $start) * 1000);

            $errno = curl_errno($ch);
            $errstr = $errno ? curl_error($ch) : null;
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno) {
                $log("cURL error while calling {$endpoint} | errno={$errno} message={$errstr} | elapsed={$elapsedms}ms");
                self::capture_api_metrics([
                    'url' => $endpoint,
                    'action' => $payload['action'] ?? null,
                    'payload' => $jsonpayload,
                    'response_raw' => '',
                    'http_code' => $status ?: null,
                    'elapsed_ms' => $elapsedms,
                    'user_id' => $payload['user_id'] ?? null,
                    'request_id' => $payload['request_id'] ?? null,
                    'phase' => 'curl_error',
                ]);
                throw new \moodle_exception('apierror', 'local_haccgen', '', 'cURL error: ' . $errstr);
            }

            $log("Response from {$endpoint} | HTTP {$status} | elapsed={$elapsedms}ms");

            if ($status < 200 || $status >= 300) {
                $snippet = is_string($response) ? substr($response, 0, 500) : '';
                $log("Non-2xx HTTP status from {$endpoint}: {$status} | snippet={$snippet}");
                self::capture_api_metrics([
                    'url' => $endpoint,
                    'action' => $payload['action'] ?? null,
                    'payload' => $jsonpayload,
                    'response_raw' => (string) $response,
                    'http_code' => $status,
                    'elapsed_ms' => $elapsedms,
                    'user_id' => $payload['user_id'] ?? null,
                    'request_id' => $payload['request_id'] ?? null,
                    'phase' => 'http_error',
                ]);
                throw new \moodle_exception('apierror', 'local_haccgen', '', 'API returned non-200 status: ' . $status);
            }

            $decoded = json_decode((string) $response, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                $jsonerrormsg = json_last_error_msg();
                $snippet = is_string($response) ? substr($response, 0, 500) : '';
                $log("Invalid JSON from {$endpoint}: {$jsonerrormsg} | snippet={$snippet}");
                self::capture_api_metrics([
                    'url' => $endpoint,
                    'action' => $payload['action'] ?? null,
                    'payload' => $jsonpayload,
                    'response_raw' => (string) $response,
                    'http_code' => $status,
                    'elapsed_ms' => $elapsedms,
                    'user_id' => $payload['user_id'] ?? null,
                    'request_id' => $payload['request_id'] ?? null,
                    'phase' => 'json_decode_error',
                ]);
                throw new \moodle_exception('apierror', 'local_haccgen', '', 'Invalid JSON response from API.');
            }

            self::capture_api_metrics([
                'url' => $endpoint,
                'action' => $payload['action'] ?? null,
                'payload' => $jsonpayload,
                'response_raw' => (string) $response,
                'http_code' => $status,
                'elapsed_ms' => $elapsedms,
                'user_id' => $payload['user_id'] ?? null,
                'request_id' => $decoded['request_id'] ?? ($payload['request_id'] ?? null),
                'phase' => $decoded['phase'] ?? ($decoded['status'] ?? null),
                'response_keys' => array_keys($decoded),
            ]);

            $log("Successful JSON response from {$endpoint}");
            return $decoded;
        };

        $dogetjson = function (string $downloadurl) use ($log) {
            $log("GET {$downloadurl} (download_url)");

            $ch = curl_init($downloadurl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPGET => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 180,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_ENCODING => '',
                CURLOPT_USERAGENT => 'local_haccgen/1.0 (+moodle)',
            ]);

            $start = microtime(true);
            $body = curl_exec($ch);
            $elapsedms = (int) round((microtime(true) - $start) * 1000);

            $errno = curl_errno($ch);
            $errstr = $errno ? curl_error($ch) : null;
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno) {
                $log("Download error from {$downloadurl} | errno={$errno} message={$errstr} | elapsed={$elapsedms}ms");
                self::capture_api_metrics([
                    'url' => $downloadurl,
                    'action' => 'download_generated_json',
                    'payload' => null,
                    'response_raw' => '',
                    'http_code' => $status ?: null,
                    'elapsed_ms' => $elapsedms,
                    'phase' => 'curl_error',
                ]);
                throw new \moodle_exception('apierror', 'local_haccgen', '', 'Download error: ' . $errstr);
            }

            $log("Download response from {$downloadurl} | HTTP {$status} | elapsed={$elapsedms}ms");

            if ($status < 200 || $status >= 300) {
                $snippet = is_string($body) ? substr($body, 0, 500) : '';
                $log("Download URL non-2xx status from {$downloadurl}: {$status} | snippet={$snippet}");
                self::capture_api_metrics([
                    'url' => $downloadurl,
                    'action' => 'download_generated_json',
                    'payload' => null,
                    'response_raw' => (string) $body,
                    'http_code' => $status,
                    'elapsed_ms' => $elapsedms,
                    'phase' => 'http_error',
                ]);
                throw new \moodle_exception(
                    'apierror',
                    'local_haccgen',
                    '',
                    'Download URL returned non-200 status: ' . $status
                );
            }

            $decoded = json_decode((string) $body, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                $jsonerrormsg = json_last_error_msg();
                $snippet = is_string($body) ? substr($body, 0, 500) : '';
                $log("Downloaded content not valid JSON from {$downloadurl}: {$jsonerrormsg} | snippet={$snippet}");
                self::capture_api_metrics([
                    'url' => $downloadurl,
                    'action' => 'download_generated_json',
                    'payload' => null,
                    'response_raw' => (string) $body,
                    'http_code' => $status,
                    'elapsed_ms' => $elapsedms,
                    'phase' => 'json_decode_error',
                ]);
                throw new \moodle_exception('apierror', 'local_haccgen', '', 'Downloaded content is not valid JSON.');
            }

            self::capture_api_metrics([
                'url' => $downloadurl,
                'action' => 'download_generated_json',
                'payload' => null,
                'response_raw' => (string) $body,
                'http_code' => $status,
                'elapsed_ms' => $elapsedms,
                'request_id' => $decoded['request_id'] ?? null,
                'phase' => $decoded['phase'] ?? ($decoded['status'] ?? null),
                'response_keys' => array_keys($decoded),
            ]);

            $log("Successful JSON download from {$downloadurl}");
            return $decoded;
        };

        // Main flow.
        $log("Initial content generation request to {$url}");
        $initial = $dopost($url, $data);

        $initialjson = json_encode($initial);
        $log("Initial decoded response: " . substr((string) $initialjson, 0, 800));

        if (!empty($initial['download_url']) || (!empty($initial['phase']) && $initial['phase'] === 'completed')) {
            $downloadurl = $initial['download_url'] ?? null;
            if (!$downloadurl) {
                $log('Initial response marked completed but missing download_url.');
                throw new \moodle_exception(
                    'apierror',
                    'local_haccgen',
                    '',
                    'Generation marked completed but no download_url provided.'
                );
            }
            $log("Initial response already completed. Downloading from {$downloadurl}");
            return $dogetjson($downloadurl);
        }

        if (empty($initial['request_id'])) {
            $log('Initial response has no request_id; returning initial payload directly.');
            return $initial;
        }

        $requestid = (string) $initial['request_id'];
        $log("Async request_id={$requestid} received; starting polling loop.");

        $deadline = time() + $maxwaitseconds;
        $firstpoll = true;

        while (true) {
            if (time() >= $deadline) {
                $log("Polling timed out for request_id={$requestid}.");
                throw new \moodle_exception(
                    'apierror',
                    'local_haccgen',
                    '',
                    'Timed out waiting for content generation to finish.'
                );
            }

            sleep($pollintervalseconds);

            $pollbody = $firstpoll
                ? ['action' => 'initiate_content_generation', 'request_id' => $requestid]
                : ['action' => 'get_generation_status', 'request_id' => $requestid];

            $firstpoll = false;

            // Preserve auth fields on every poll request.
            $payload = $auth + $pollbody;

            $log("Polling status for request_id={$requestid}.");
            $status = $dopost($url, $payload);

            $phase = $status['phase'] ?? null;
            $stat = $status['status'] ?? null;
            $ready = isset($status['content_ready']) ? (bool) $status['content_ready'] : false;

            $completed = ($phase === 'completed') || ($stat === 'completed') || $ready || !empty($status['download_url']);

            if (!empty($status['error']) || ($phase === 'failed') || ($stat === 'failed')) {
                $errmsg = $status['error'] ?? ($status['message'] ?? 'unknown error');
                $log("Generation failed for request_id={$requestid}: {$errmsg}");
                throw new \moodle_exception('apierror', 'local_haccgen', '', 'Generation failed: ' . $errmsg);
            }

            if ($completed) {
                $downloadurl = $status['download_url'] ?? null;
                if (!$downloadurl) {
                    $log("Generation completed for request_id={$requestid} but no download_url provided.");
                    throw new \moodle_exception(
                        'apierror',
                        'local_haccgen',
                        '',
                        'Generation completed but no download_url provided.'
                    );
                }

                $log("Generation completed for request_id={$requestid}. Downloading from {$downloadurl}");
                return $dogetjson($downloadurl);
            }
        }
    }

    /**
     * Capture API call metrics in the "new format" and emit a Moodle event.
     *
     * Safe: never throws (errors are swallowed).
     *
     * @param array $params Metrics payload.
     * @return void
     */
    private static function capture_api_metrics(array $params): void {
        global $USER;

        try {
            $raw = (string) ($params['response_raw'] ?? '');
            $bytes = strlen($raw);

            // Mb_strlen can fail if mbstring is missing; fall back safely.
            $mbchars = function_exists('mb_strlen') ? mb_strlen($raw, 'UTF-8') : $bytes;

            $words = self::count_words_from_raw($raw);

            // Approx token heuristic; keep simple and stable.
            $approxtokens = (int) ceil($bytes / 4.0);

            $payloadpreview = '';
            if (array_key_exists('payload', $params)) {
                $p = $params['payload'];
                if (is_string($p)) {
                    $payloadpreview = function_exists('mb_substr') ? mb_substr($p, 0, 400) : substr($p, 0, 400);
                } else {
                    $payloadpreview = json_encode($p, JSON_UNESCAPED_UNICODE);
                    $payloadpreview = function_exists('mb_substr')
                        ? mb_substr((string) $payloadpreview, 0, 400)
                        : substr((string) $payloadpreview, 0, 400);
                }
            }

            $responsepreview = function_exists('mb_substr') ? mb_substr($raw, 0, 600) : substr($raw, 0, 600);

            $relateduserid = null;
            if (isset($params['user_id']) && is_numeric($params['user_id'])) {
                $relateduserid = (int) $params['user_id'];
            } else if (isset($USER) && !empty($USER->id)) {
                // Optional fallback, only if you want to attribute.
                $relateduserid = (int) $USER->id;
            }

            $eventdata = [
                'url' => $params['url'] ?? null,
                'action' => $params['action'] ?? null,
                'http_code' => $params['http_code'] ?? null,
                'elapsed_ms' => $params['elapsed_ms'] ?? null,
                'request_bytes' => isset($params['payload'])
                    ? strlen(is_string($params['payload']) ? $params['payload'] : json_encode($params['payload']))
                    : null,
                'response_bytes' => $bytes,
                'response_chars' => $mbchars,
                'word_count' => $words,
                'token_estimate' => $approxtokens,
                'phase' => $params['phase'] ?? null,
                'request_id' => $params['request_id'] ?? null,
                'payload_preview' => $payloadpreview,
                'response_preview' => $responsepreview,
                'related_userid' => $relateduserid,
                'response_keys' => $params['response_keys'] ?? null,
            ];

            // Emit your Moodle event (recommended).
            // Ensure you have: classes/event/api_response_captured.php with create_from_metrics().
            if (class_exists('\local_haccgen\event\api_response_captured')) {
                $event = \local_haccgen\event\api_response_captured::create_from_metrics($eventdata);
                $event->trigger();
            } else {
                // Fallback: developer debugging only.
                debugging(
                    '[local_haccgen][api_metrics] ' . json_encode($eventdata, JSON_UNESCAPED_UNICODE),
                    DEBUG_DEVELOPER
                );
            }
        } catch (\Throwable $e) {
            // Never break the request due to metrics capture.
            debugging('[local_haccgen][api_metrics] capture failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Best-effort word counting from raw response.
     *
     * - If JSON: extract known text fields then count words.
     * - Else: strip tags and count words from plain text.
     *
     * @param string $raw Raw response.
     * @return int
     */
    private static function count_words_from_raw(string $raw): int {
        if ($raw === '') {
            return 0;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $text = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return self::unicode_word_count($text);
        }

        $pieces = [];

        // Shape A: top-level "results".
        if (isset($decoded['results']) && is_array($decoded['results'])) {
            foreach ($decoded['results'] as $item) {
                $gc = $item['generated_content'] ?? ($item['result']['generated_content'] ?? null);
                self::collect_text_from_generated_content($gc, $pieces);
                if (!empty($item['quiz_data'])) {
                    self::collect_text_from_quiz($item['quiz_data'], $pieces);
                }
            }
        }

        // Shape B: "result" => "subtopics".
        if (isset($decoded['result']['subtopics']) && is_array($decoded['result']['subtopics'])) {
            foreach ($decoded['result']['subtopics'] as $item) {
                $gc = $item['generated_content'] ?? ($item['result']['generated_content'] ?? null);
                self::collect_text_from_generated_content($gc, $pieces);
                if (!empty($item['quiz_data'])) {
                    self::collect_text_from_quiz($item['quiz_data'], $pieces);
                }
            }
        }

        // Shape C: "content" array.
        if (isset($decoded['content']) && is_array($decoded['content'])) {
            foreach ($decoded['content'] as $c) {
                $gc = $c['result']['generated_content'] ?? ($c['generated_content'] ?? null);
                self::collect_text_from_generated_content($gc, $pieces);

                if (!empty($c['result']['quiz_data'])) {
                    self::collect_text_from_quiz($c['result']['quiz_data'], $pieces);
                } else if (!empty($c['quiz_data'])) {
                    self::collect_text_from_quiz($c['quiz_data'], $pieces);
                }
            }
        }

        // Fallback: collect all strings.
        if (!$pieces) {
            $it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($decoded));
            foreach ($it as $v) {
                if (is_string($v)) {
                    $pieces[] = $v;
                }
            }
        }

        $text = html_entity_decode(strip_tags(implode(' ', $pieces)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return self::unicode_word_count($text);
    }

    /**
     * Collect text fields from generated content.
     *
     * @param mixed $gc Generated content.
     * @param array $out Output array (by reference).
     * @return void
     */
    private static function collect_text_from_generated_content($gc, array &$out): void {
        if (!is_array($gc)) {
            return;
        }

        if (!empty($gc['topic_title'])) {
            $out[] = (string) $gc['topic_title'];
        }
        if (!empty($gc['topic_description'])) {
            $out[] = (string) $gc['topic_description'];
        }

        if (!empty($gc['topic_learning_objectives']) && is_array($gc['topic_learning_objectives'])) {
            foreach ($gc['topic_learning_objectives'] as $lo) {
                if (is_string($lo)) {
                    $out[] = $lo;
                }
            }
        }

        if (!empty($gc['content_sections']) && is_array($gc['content_sections'])) {
            foreach ($gc['content_sections'] as $sec) {
                if (!is_array($sec)) {
                    continue;
                }
                if (!empty($sec['section_title'])) {
                    $out[] = (string) $sec['section_title'];
                }
                if (!empty($sec['content'])) {
                    $out[] = (string) $sec['content'];
                }
                if (!empty($sec['examples']) && is_array($sec['examples'])) {
                    foreach ($sec['examples'] as $ex) {
                        if (is_string($ex)) {
                            $out[] = $ex;
                        }
                    }
                }
            }
        }
    }

    /**
     * Collect text fields from quiz data.
     *
     * @param mixed $quiz Quiz data.
     * @param array $out Output array (by reference).
     * @return void
     */
    private static function collect_text_from_quiz($quiz, array &$out): void {
        if (!is_array($quiz)) {
            return;
        }

        if (!empty($quiz['intro'])) {
            $out[] = (string) $quiz['intro'];
        }

        if (!empty($quiz['questions']) && is_array($quiz['questions'])) {
            foreach ($quiz['questions'] as $q) {
                if (!is_array($q)) {
                    continue;
                }
                if (!empty($q['question'])) {
                    $out[] = (string) $q['question'];
                }
                if (!empty($q['explanation'])) {
                    $out[] = (string) $q['explanation'];
                }
                if (!empty($q['options']) && is_array($q['options'])) {
                    foreach ($q['options'] as $opt) {
                        if (is_string($opt)) {
                            $out[] = $opt;
                        }
                    }
                }
            }
        }
    }

    /**
     * Unicode/CJK-friendly "word" counting:
     *
     * - Counts runs of letters/digits.
     * - Counts individual CJK scripts.
     *
     * @param string $text Input text.
     * @return int
     */
    private static function unicode_word_count(string $text): int {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        // Normalize whitespace.
        $text = preg_replace('/\s+/u', ' ', $text);

        // Count runs of letters/digits and CJK characters.
        preg_match_all(
            '/\p{Han}|\p{Hiragana}|\p{Katakana}|\p{Hangul}|[\p{L}\p{N}]+/u',
            $text,
            $m
        );
        return isset($m[0]) ? count($m[0]) : 0;
    }
}
