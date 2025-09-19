<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$jobid = required_param('id', PARAM_INT);

global $DB, $USER;

$job = $DB->get_record('local_haccgen_job', ['id' => $jobid], '*', MUST_EXIST);
require_capability('local/haccgen:manage', \context_course::instance($job->courseid));
if ($job->userid != $USER->id && !is_siteadmin()) {
  throw new moodle_exception('nopermissions', 'error');
}

$PAGE->set_url(new moodle_url('/local/haccgen/job.php', ['id' => $jobid]));
$PAGE->set_title(get_string('generatingcourse', 'local_haccgen'));
$PAGE->set_heading(get_string('generatingcourse', 'local_haccgen'));

echo $OUTPUT->header();
?>
<style>
  .aic-progress-wrap {
    margin-top: 1.5rem;
    padding: 1rem;
    border-radius: 10px;
    background: #f9fafc;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  }

  .aic-progress-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
    margin-bottom: .75rem;
  }

  .aic-status {
    font-weight: 600;
    text-transform: capitalize;
  }

  /* Status badge colors */
  .badge {
    display: inline-block;
    padding: 0.35em 0.6em;
    font-size: 0.85rem;
    font-weight: 600;
    border-radius: 0.35rem;
    color: #fff;
  }

  .badge-queued {
    background: #6c757d;
  }

  .badge-running {
    background: #007bff;
  }

  .badge-success {
    background: #28a745;
  }

  .badge-error {
    background: #dc3545;
  }

  .progress {
    height: 28px;
    border-radius: 8px;
    overflow: hidden;
  }

  .progress-bar {
    background: linear-gradient(45deg,
        rgba(255, 255, 255, 0.25) 25%,
        transparent 25%,
        transparent 50%,
        rgba(255, 255, 255, 0.25) 50%,
        rgba(255, 255, 255, 0.25) 75%,
        transparent 75%,
        transparent);
    background-size: 1.5rem 1.5rem;
    background-color: #007bff;
    font-weight: 600;
    color: #fff;
    transition: width 0.4s ease;
    animation: progress-stripes 1s linear infinite;
  }

  @keyframes progress-stripes {
    from {
      background-position: 1.5rem 0;
    }

    to {
      background-position: 0 0;
    }
  }

  .progress-bar.glow {
    box-shadow: 0 0 10px rgba(0, 200, 255, 0.7),
      0 0 20px rgba(0, 200, 255, 0.5);
  }

  .aic-progress-extra {
    margin-top: .75rem;
    display: flex;
    justify-content: flex-start;
    font-size: 0.95rem;
    color: #333;
  }

  #aic-rotating {
    margin-top: 1rem;
    font-style: italic;
    font-size: 1rem;
    color: #555;
    transition: opacity 0.6s ease;
  }

  #aic-msg {
    background: #f0f4f8;
    padding: .75rem;
    border-radius: 6px;
    margin-top: 1rem;
    font-family: monospace;
    font-size: 0.9rem;
    white-space: pre-wrap;
  }
</style>

<div id="jobstatus" data-jobid="<?php echo (int)$jobid; ?>">
  <div class="aic-progress-wrap">
    <div class="aic-progress-label">
      <div>Status: <span id="aic-status" class="aic-status badge badge-queued">queued</span></div>
      <div><span id="aic-percent">0</span>%</div>
    </div>
    <div class="progress" role="progressbar" aria-label="Job progress" aria-valuemin="0" aria-valuemax="100">
      <div id="aic-bar" class="progress-bar" style="width:0%">0%</div>
    </div>

    <!-- Elapsed only -->
    <div class="aic-progress-extra">
      <div>⏱ Elapsed: <span id="aic-timer">0:00</span></div>
    </div>

    <!-- Rotating messages -->
    <div id="aic-rotating">Getting things ready...</div>

    <pre id="aic-msg"></pre>
  </div>

  <form id="continueform" method="post" action="<?php echo (new moodle_url('/local/haccgen/consume_job.php'))->out(false); ?>" style="display:none;">
    <input type="hidden" name="jobid" value="<?php echo (int)$jobid; ?>">
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
    <button id="continuebtn" class="btn btn-primary mt-3">Continue</button>
  </form>
</div>

<script>
  (function() {
    const jobid = document.getElementById('jobstatus').dataset.jobid;
    const statusEl = document.getElementById('aic-status');
    const percentEl = document.getElementById('aic-percent');
    const barEl = document.getElementById('aic-bar');
    const msgEl = document.getElementById('aic-msg');
    const formEl = document.getElementById('continueform');
    const continueBtn = document.getElementById('continuebtn');
    const timerEl = document.getElementById('aic-timer');
    const rotatingEl = document.getElementById('aic-rotating');
    const url = '<?php echo (new moodle_url("/local/haccgen/job_status.php"))->out(false); ?>?id=' + encodeURIComponent(jobid);

    const startTime = Date.now();

    function fmtTime(ms) {
      const sec = Math.floor(ms / 1000);
      const m = Math.floor(sec / 60);
      const s = sec % 60;
      return `${m}:${s.toString().padStart(2,'0')}`;
    }

    function setProgress(pct) {
      const n = Math.max(0, Math.min(100, parseInt(pct || 0, 10)));
      percentEl.textContent = n;
      barEl.style.width = n + '%';
      barEl.setAttribute('aria-valuenow', n);
      barEl.textContent = n + '%';

      if (n >= 80) {
        barEl.classList.add('glow');
      } else {
        barEl.classList.remove('glow');
      }
    }

    function setStatus(status) {
      status = (status || 'queued').toLowerCase();
      statusEl.textContent = status;
      statusEl.className = 'aic-status badge badge-' + (['queued', 'running', 'success', 'error'].includes(status) ? status : 'queued');
    }

    // Rotating sentences
    const rotatingTexts = [
      "🚀 Preparing your course structure...",
      "📚 Adding learning materials...",
      "🛠️ Organizing modules...",
      "🎶 Adding rhythm to learning flow...",
      "🗝️ Unlocking hidden insights...",
      "🧭 Setting the learning direction...",
      "🌱 Growing interactive elements...",
      "🎨 Personalizing learning journey...",
      "🔍 Checking consistency...",
      "🧩 Connecting all topics...",
      "🏗️ Assembling knowledge blocks...",
      "🎯 Aligning objectives...",
      "📝 Structuring content flow...",
      "📦 Packing your resources...",
      "⚡ Optimizing performance...",
      "🔧 Fine-tuning details...",
      "🗂️ Sorting topics into modules",
      "🌐 Polishing for final shine...",
      "✨ Finalizing details...",
      "⏳ Almost there, please wait..."
    ];
    let rotateIndex = 0;
    let rotateTimer = null;

    function rotateText() {
      rotatingEl.style.opacity = 0;
      setTimeout(() => {
        rotatingEl.textContent = rotatingTexts[rotateIndex];
        rotatingEl.style.opacity = 1;

        if (rotateIndex < rotatingTexts.length - 1) {
          rotateIndex++;
        } else {
          // Stop rotating once we reach the last sentence
          clearInterval(rotateTimer);
        }
      }, 600);
    }

    rotateTimer = setInterval(rotateText, 8000);
    rotateText();

    async function poll() {
      try {
        const res = await fetch(url, {
          credentials: 'same-origin'
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const j = await res.json();

        // Elapsed time
        const elapsed = Date.now() - startTime;
        timerEl.textContent = fmtTime(elapsed);

        // Progress
        if (typeof j.completed_topics === 'number' && typeof j.total_topics === 'number' && j.total_topics > 0) {
          setStatus('running');
          statusEl.textContent = `${j.completed_topics}/${j.total_topics} topics`;
          const pct = Math.round((j.completed_topics / Math.max(1, j.total_topics)) * 100);
          setProgress(pct);
        } else {
          setStatus(j.status || 'queued');
          setProgress(j.progress || 0);
        }

        if (j.message) {
          msgEl.textContent = j.message;
        }

        if (j.status === 'success') {
          setStatus('success');
          if (rotateTimer) clearInterval(rotateTimer);
          formEl.style.display = 'block';
          formEl.submit();
          return;
        }
        if (j.status === 'error') {
          setStatus('error');
          if (rotateTimer) clearInterval(rotateTimer);
          formEl.style.display = 'block';
          continueBtn.textContent = 'Continue';
          return;
        }
      } catch (e) {
        msgEl.textContent = 'Polling failed: ' + (e && e.message ? e.message : e);
      }
      setTimeout(poll, 1200);
    }

    poll();
  })();
</script>

<?php echo $OUTPUT->footer();
