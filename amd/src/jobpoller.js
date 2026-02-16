/**
 * This file is part of Moodle - http://moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Poll the background job until it is done or failed.
 *
 * @module     local_haccgen/jobpoller
 * @copyright  2026 Dynamicpixel Multimedia Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    /**
     * Start polling a job id.
     * @param {Number} jobid
     */
    const init = function(jobid) {

        const spinner = document.getElementById('haccgen-spinner');
        if (spinner) {
            spinner.classList.remove('hidden');
        }

        const poll = function() {
            Ajax.call([{
                methodname : 'local_haccgen_check_job',
                args       : { jobid: jobid }
            }])[0].then(function(data) {

                if (data.status === 'done') {
                    // Give Moodle a moment to build session data then reload.
                    window.setTimeout(function() { window.location.reload(); }, 1500);

                } else if (data.status === 'failed') {
                    Notification.alert(
                        'Generation failed',
                        data.errormsg || '',
                        'Close'
                    );
                } else if (data.status === 'missing') {
                    location.reload();
                } else {
                    setTimeout(poll, 5000);
                }

            }).catch(Notification.exception);
        };

        poll();
    };

    return /** @alias module:local_haccgen/jobpoller */ { init: init };
});
