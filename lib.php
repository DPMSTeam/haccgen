<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/lib.php');

const LOCAL_haccgen_COMPONENT = 'local_haccgen';
const LOCAL_haccgen_FILEAREA  = 'uploads';

/**
 * Extends the course settings navigation to include an AI course management link.
 *
 * @param navigation_node $settingsnav The navigation node to extend.
 * @param context $context The context of the course.
 */
function local_haccgen_extend_settings_navigation($settingsnav, $context) {
    global $PAGE;

    if ($context->contextlevel == CONTEXT_COURSE &&
        has_capability('local/haccgen:manage', $context) &&
        !empty($PAGE->course->id)) {

        $courseid = $PAGE->course->id;
        $url   = new moodle_url('/local/haccgen/manage.php', ['id' => $courseid]);
        $title = get_string('manageai', 'local_haccgen');

        $coursenode = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE);
        if ($coursenode) {
            $coursenode->add($title, $url, navigation_node::TYPE_SETTING, null, 'haccgen_manage');
        } else {
            $settingsnav->add($title, $url, navigation_node::TYPE_SETTING, null, 'haccgen_manage');
        }
    }
}

/**
 * Build a signed pluginfile URL for public access without login (until expiry).
 *
 * @param stored_file $file
 * @param int|null $ttl  Seconds the link remains valid; if null, use plugin setting publiclinkttl (default 3600)
 * @param bool $forcedownload
 * @return string Absolute URL
 * @throws moodle_exception if linksecret is not configured
 */
function local_haccgen_build_signed_url(stored_file $file, ?int $ttl = null, bool $forcedownload = false): string {
    // Read secret from settings (or forced_plugin_settings). Must be non-empty.
    $secret = (string)get_config(LOCAL_haccgen_COMPONENT, 'linksecret');
    if ($secret === '') {
        throw new moodle_exception('linksecret_not_set', LOCAL_haccgen_COMPONENT);
    }

    // Resolve TTL: function arg > plugin setting > sane default (1h).
    if ($ttl === null) {
        $ttl = (int)get_config(LOCAL_haccgen_COMPONENT, 'publiclinkttl');
    }
    if ($ttl <= 0) {
        $ttl = 3600;
    }

    $expires = time() + $ttl;

    // Core fields — MUST match verification side exactly.
    $payload = implode('|', [
        $file->get_contextid(),
        LOCAL_haccgen_COMPONENT,
        LOCAL_haccgen_FILEAREA,
        $file->get_itemid(),
        $file->get_filepath(),   // keep leading & trailing slash as stored
        $file->get_filename(),
        $expires
    ]);
    $token = hash_hmac('sha256', $payload, $secret);

    $url = moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        LOCAL_haccgen_COMPONENT,
        LOCAL_haccgen_FILEAREA,
        $file->get_itemid(),
        $file->get_filepath(),
        $file->get_filename(),
        $forcedownload
    );
    $url->param('expires', $expires);
    $url->param('token', $token);

    return $url->out(false); // raw &, no HTML escaping
}

/**
 * Serve files for local_haccgen.
 *
 * Public when a valid token+expiry is present; otherwise falls back to Moodle auth.
 */
function local_haccgen_pluginfile($course, $cm, $context, $filearea, $args,
                                   $forcedownload, array $options = []) {

    if ($filearea !== LOCAL_haccgen_FILEAREA) { return false; }
    if ($context->contextlevel != CONTEXT_SYSTEM && $context->contextlevel != CONTEXT_COURSE) {
        return false;
    }

    // Parse /{itemid}/{subdir...}/{filename}
    $itemid   = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = '/';
    if (!empty($args)) { $filepath = '/' . implode('/', $args) . '/'; }

    $fs   = get_file_storage();
    $file = $fs->get_file($context->id, LOCAL_haccgen_COMPONENT, LOCAL_haccgen_FILEAREA, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) { send_file_not_found(); }

    // Token path (public).
    $token   = optional_param('token', '', PARAM_ALPHANUM); // sha256 hex
    $expires = optional_param('expires', 0, PARAM_INT);
    $secret  = (string)get_config(LOCAL_haccgen_COMPONENT, 'linksecret');

    $tokengood = false;
    $payload = null;
    $expected = null;

    if ($secret !== '' && $token && $expires && time() < (int)$expires) {
        $payload  = implode('|', [$context->id, LOCAL_haccgen_COMPONENT, LOCAL_haccgen_FILEAREA, $itemid, $filepath, $filename, $expires]);
        $expected = hash_hmac('sha256', $payload, $secret);
        if (hash_equals($expected, $token)) {
            $tokengood = true;
        }
    }

    // >>> TEMP DEBUG (remove in production)
    if (!$tokengood) {
        error_log('local_haccgen token FAIL: ' . json_encode([
            'ctx'       => $context->id,
            'itemid'    => $itemid,
            'filepath'  => $filepath,
            'filename'  => $filename,
            'exp'       => $expires,
            'now'       => time(),
            'has_token' => (bool)$token,
            'secretlen' => strlen($secret),
            'payload'   => $payload,
            'expected'  => $expected,
            'provided'  => $token,
        ]));
    }
    // <<<

    if ($tokengood) {
        $lifetime = max(0, $expires - time());
        $options['cacheability'] = 'public';
        send_stored_file($file, $lifetime, 0, $forcedownload, $options); // exits
    }

    // Private fallback (login/capability) if token missing/invalid/expired.
    if ($context->contextlevel == CONTEXT_COURSE) {
        require_course_login($course);
    } else {
        require_login();
    }

    send_stored_file($file, 0, 0, $forcedownload, $options); // exits
}
