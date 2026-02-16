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
 * ISO Latin-1 encoding translations.
 *
 * Source: PostScript::ISOLatin1Encoding (Perl).
 *
 * @package     local_haccgen
 * @copyright   2026 Dynamicpixel Multimedia Solutions
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_haccgen',
        get_string('pluginname', 'local_haccgen')
    );

    $settings->add(new admin_setting_heading(
        'local_haccgen/apisettings',
        get_string('apisettings', 'local_haccgen'),
        ''
    ));

    $apikeyhtml =
        '<div style="background:#f1f4f8;padding:15px;border-radius:8px;' .
        'border:1px solid #dce3ea;margin-bottom:12px;">' .
        '<p style="font-size:14px;margin:0 0 12px;">' .
        'To use <strong>HACCGEN AI Services</strong>, you must generate your ' .
        '<strong>API Key</strong> and <strong>API Secret</strong> from the ' .
        'HACCGEN Dashboard. Watch the ' .
        '<a href="https://www.youtube.com/watch?v=2b8KzmHATJA" target="_blank" ' .
        'style="color:#0056d2;font-weight:600;text-decoration:underline;">' .
        'Installation &amp; Setup Tutorial</a>.' .
        '</p>' .
        '<div>' .
        '<a href="https://subscription.dynamicpixel.co.in/" target="_blank" ' .
        'style="display:inline-block;background:#0056d2;color:#fff!important;' .
        'padding:10px 18px;border-radius:5px;text-decoration:none;' .
        'font-weight:600;box-shadow:0px 2px 5px rgba(0,0,0,0.1);">' .
        '🔑 Get API Credentials</a>' .
        '</div>' .
        '</div>';

    $settings->add(new admin_setting_heading(
        'local_haccgen/getapikeyheading',
        '',
        $apikeyhtml
    ));

    require_once($CFG->dirroot . '/local/haccgen/classes/api.php');

    $usagehtml = '';

    try {
        $suburl = get_config('local_haccgen', 'subscription_url');
        $key = get_config('local_haccgen', 'apikey');
        $secret = get_config('local_haccgen', 'apisecret');

        if (empty($suburl) || empty($key) || empty($secret)) {
            $usagehtml =
                '<div style="text-align:center;color:#666;font-size:13px;' .
                'margin:10px 0;">' .
                'Enter Subscription URL, API Key and API Secret, then Save ' .
                'changes to view usage.' .
                '</div>';
        } else {
            $status = local_haccgen_api::get_subscription_status();

            $limit = (int)($status['usage_limit'] ?? 0);
            $used = (int)($status['usage_used'] ?? 0);

            if ($limit > 0) {
                $label = 'Words used – ' . number_format($used) .
                    ' / ' . number_format($limit);
            } else {
                $label = 'Words used – ' . number_format($used) .
                    ' (Unlimited)';
            }

            $usagehtml =
                '<div style="background:#e5e7eb;border:1px solid #dce3ea;' .
                'border-radius:8px;padding:14px 12px;margin:10px 0 16px;' .
                'text-align:center;font-size:14px;font-weight:600;' .
                'color:#1f2a37;">' .
                s($label) .
                '</div>';
        }
    } catch (Throwable $e) {
        $usagehtml =
            '<div style="color:#b00020;text-align:center;margin:10px 0;">' .
            'Unable to fetch usage. (' . s($e->getMessage()) . ')' .
            '</div>';
    }

    $settings->add(new admin_setting_heading(
        'local_haccgen/subscription_usage',
        '',
        $usagehtml
    ));

    $settings->add(new admin_setting_configtext(
        'local_haccgen/subscription_url',
        get_string('subscription_url', 'local_haccgen'),
        get_string('subscription_url_desc', 'local_haccgen'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_haccgen/apikey',
        get_string('apikey', 'local_haccgen'),
        get_string('apikey_desc', 'local_haccgen'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_haccgen/apisecret',
        get_string('apisecret', 'local_haccgen'),
        get_string('apisecret_desc', 'local_haccgen'),
        ''
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_haccgen/linksecret',
        get_string('linksecret', 'local_haccgen'),
        get_string('linksecret_desc', 'local_haccgen'),
        ''
    ));

    $settings->add(new admin_setting_configduration(
        'local_haccgen/publiclinkttl',
        get_string('publiclinkttl', 'local_haccgen'),
        get_string('publiclinkttl_desc', 'local_haccgen'),
        3600
    ));

    $ADMIN->add('localplugins', $settings);
}
