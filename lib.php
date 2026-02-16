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
 * Library functions for local_haccgen.
 *
 * @package local_haccgen
 * @copyright 2026 Dynamicpixel Multimedia Solutions
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');

/**
 * Plugin component name.
 *
 * @package local_haccgen
 */
const LOCAL_HACCGEN_COMPONENT = 'local_haccgen';

/**
 * File area for uploaded files.
 *
 * @package local_haccgen
 */
const LOCAL_HACCGEN_FILEAREA = 'uploads';

/**
 * Extends the course settings navigation to include an AI course management link.
 *
 * @param navigation_node $settingsnav The navigation node to extend
 * @param context $context The context of the course
 * @package local_haccgen
 */
function local_haccgen_extend_settings_navigation($settingsnav, $context) {
    global $PAGE;

    if (
        $context->contextlevel === CONTEXT_COURSE &&
        has_capability('local/haccgen:manage', $context) &&
        !empty($PAGE->course->id)
    ) {

        $courseid = $PAGE->course->id;
        $url = new moodle_url('/local/haccgen/manage.php', ['id' => $courseid]);
        $title = get_string('manageai', 'local_haccgen');

        $coursenode = $settingsnav->find(
            'courseadmin',
            navigation_node::TYPE_COURSE,
        );

        if ($coursenode) {
            $coursenode->add(
                $title,
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'haccgen_manage',
            );
        } else {
            $settingsnav->add(
                $title,
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'haccgen_manage',
            );
        }
    }
}

/**
 * Build a signed pluginfile URL for public access without login.
 *
 * @param stored_file $file
 * @param int|null $ttl
 * @param bool $forcedownload
 * @return string
 * @throws moodle_exception
 * @package local_haccgen
 */
function local_haccgen_build_signed_url(
    stored_file $file,
    ?int $ttl = null,
    bool $forcedownload = false
): string {
    $secret = (string)get_config(LOCAL_HACCGEN_COMPONENT, 'linksecret');
    if ($secret === '') {
        throw new moodle_exception(
            'linksecret_not_set',
            LOCAL_HACCGEN_COMPONENT,
        );
    }

    if ($ttl === null) {
        $ttl = (int)get_config(LOCAL_HACCGEN_COMPONENT, 'publiclinkttl');
    }
    if ($ttl <= 0) {
        $ttl = 3600;
    }

    $expires = time() + $ttl;

    $payload = implode('|', [
        $file->get_contextid(),
        LOCAL_HACCGEN_COMPONENT,
        LOCAL_HACCGEN_FILEAREA,
        $file->get_itemid(),
        $file->get_filepath(),
        $file->get_filename(),
        $expires,
    ]);

    $token = hash_hmac('sha256', $payload, $secret);

    $url = moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        LOCAL_HACCGEN_COMPONENT,
        LOCAL_HACCGEN_FILEAREA,
        $file->get_itemid(),
        $file->get_filepath(),
        $file->get_filename(),
        $forcedownload,
    );

    $url->param('expires', $expires);
    $url->param('token', $token);

    return $url->out(false);
}

/**
 * Serve files for local_haccgen.
 *
 * @param stdClass $course
 * @param stdClass|null $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 * @package local_haccgen
 */
function local_haccgen_pluginfile(
    $course,
    $cm,
    $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = []
) {
    if ($filearea !== LOCAL_HACCGEN_FILEAREA) {
        return false;
    }

    if (!in_array(
        $context->contextlevel,
        [CONTEXT_SYSTEM, CONTEXT_COURSE, CONTEXT_MODULE],
        true,
    )) {
        return false;
    }

    $itemid = (int)array_shift($args);
    $filename = array_pop($args);

    $filepath = '/';
    if (!empty($args)) {
        $filepath = '/' . implode('/', $args) . '/';
    }

    $fs = get_file_storage();
    $file = $fs->get_file(
        $context->id,
        LOCAL_HACCGEN_COMPONENT,
        LOCAL_HACCGEN_FILEAREA,
        $itemid,
        $filepath,
        $filename,
    );

    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    $token = optional_param('token', '', PARAM_ALPHANUM);
    $expires = optional_param('expires', 0, PARAM_INT);
    $secret = (string)get_config(LOCAL_HACCGEN_COMPONENT, 'linksecret');

    $tokengood = false;

    if ($secret !== '' && $token && $expires && time() < $expires) {
        $payload = implode('|', [
            $context->id,
            LOCAL_HACCGEN_COMPONENT,
            LOCAL_HACCGEN_FILEAREA,
            $itemid,
            $filepath,
            $filename,
            $expires,
        ]);

        $expected = hash_hmac('sha256', $payload, $secret);

        if (hash_equals($expected, $token)) {
            $tokengood = true;
        }
    }

    if ($tokengood) {
        $lifetime = max(0, $expires - time());
        $options['cacheability'] = 'public';
        send_stored_file($file, $lifetime, 0, $forcedownload, $options);
    }

    if ($context->contextlevel === CONTEXT_COURSE) {
        require_course_login($course);
    } else {
        require_login();
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}
