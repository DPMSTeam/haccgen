<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) { // Only admins and only in admin tree.
    // Use your actual component id so the page sits under "Local plugins".
    $settings = new admin_settingpage('local_haccgen', get_string('pluginname', 'local_haccgen'));

    // (Optional) section heading.
    $settings->add(new admin_setting_heading(
        'local_haccgen/apisettings',
        get_string('apisettings', 'local_haccgen'),
        ''
    ));
    // Subscription URL field (text).
    $settings->add(new admin_setting_configtext(
        'local_haccgen/subscription_url',
        get_string('subscription_url', 'local_haccgen'),
        get_string('subscription_url_desc', 'local_haccgen'),
        '', // default value
        PARAM_URL
    ));
    // API Key field.
    // Tip: API keys often contain non-alphanumeric characters; PARAM_RAW_TRIMMED is safer than ALPHANUMEXT.
    $settings->add(new admin_setting_configtext(
        'local_haccgen/apikey',
        get_string('apikey', 'local_haccgen'),
        get_string('apikey_desc', 'local_haccgen'),
        '', // default value
        
    ));

    // API Secret field (password masked).
    $settings->add(new admin_setting_configpasswordunmask(
        'local_haccgen/apisecret',
        get_string('apisecret', 'local_haccgen'),
        get_string('apisecret_desc', 'local_haccgen'),
        ''
    ));

    // Public-link signing secret (password masked).
    $settings->add(new admin_setting_configpasswordunmask(
        'local_haccgen/linksecret',
        get_string('linksecret', 'local_haccgen'),
        get_string('linksecret_desc', 'local_haccgen'),
        '' // no default; admin must set
    ));

    // Public-link expiry (duration in seconds/minutes/hours via UI).
    $settings->add(new admin_setting_configduration(
        'local_haccgen/publiclinkttl',
        get_string('publiclinkttl', 'local_haccgen'),
        get_string('publiclinkttl_desc', 'local_haccgen'),
        3600 // 1 hour default
    ));
    // Provider field (dropdown).
    $settings->add(new admin_setting_configselect(
        'local_haccgen/provider_for_content_ouline',
        get_string('provider_for_content_outline', 'local_haccgen'),
        get_string('provider_desc', 'local_haccgen'),
        'gemini', // default
        [
            'gemini'  => 'Gemini',
            'openai'  => 'OpenAI',
            'anthropic' => 'Anthropic',
        ]
    ));
    $settings->add(new admin_setting_configselect(
        'local_haccgen/provider_for_content',
        get_string('provider_for_content', 'local_haccgen'),
        get_string('provider_desc', 'local_haccgen'),
        'gemini', // default
        [
            'gemini'  => 'Gemini',
            'openai'  => 'OpenAI',
            'anthropic' => 'Anthropic',
        ]
    ));

    // Add settings page to admin tree.
    $ADMIN->add('localplugins', $settings);
}
