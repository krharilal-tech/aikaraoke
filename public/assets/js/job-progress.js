(function ($) {
  'use strict';

  const $section = $('section[data-job-id]');
  const jobId = $section.data('job-id');

  if (!jobId) {
    return;
  }

  const POLL_INTERVAL_MS = 2000;
  const TERMINAL_STATES = ['completed', 'failed'];

  let pollTimer = null;

  function formatEta(seconds) {
    if (seconds === null || seconds === undefined || seconds < 0) {
      return '';
    }

    const m = Math.floor(seconds / 60);
    const s = Math.round(seconds % 60);

    return m > 0 ? `~${m}m ${s}s remaining` : `~${s}s remaining`;
  }

  function renderStages(currentState) {
    const states = $('#stageList .stage-item').map(function () {
      return $(this).data('state');
    }).get();

    const currentIndex = states.indexOf(currentState);
    const failed = currentState === 'failed';

    $('#stageList .stage-item').each(function (index) {
      const $item = $(this);
      const $icon = $item.find('.stage-dot i');
      $item.removeClass('active done failed');

      if (failed && index === currentIndex) {
        $item.addClass('failed');
        $icon.attr('class', 'bi bi-x-lg');
        return;
      }

      if (currentIndex === -1) {
        return;
      }

      if (index < currentIndex) {
        $item.addClass('done');
        $icon.attr('class', 'bi bi-check-lg');
      } else if (index === currentIndex) {
        $item.addClass('active');
        $icon.attr('class', 'bi bi-arrow-repeat');
      } else {
        $icon.attr('class', 'bi bi-circle');
      }
    });
  }

  function renderLogs(logs) {
    if (!Array.isArray(logs) || logs.length === 0) {
      return;
    }

    const $console = $('#logConsole');
    $console.text(logs.join('\n'));
    $console.scrollTop($console[0].scrollHeight);
  }

  let videoRendered = false;

  function renderVideo(video) {
    if (videoRendered) {
      return;
    }

    videoRendered = true;
    $('#karaokePreview').attr('src', video.stream_url);
    $('#downloadVideoBtn').attr('href', video.download_url);
  }

  function render(data) {
    if (data.title) {
      $('#jobTitle').text(data.title);
      document.title = data.title + ' — AI Karaoke Maker';
    }

    if (data.channel) {
      $('#jobMeta').text(data.channel + (data.duration_sec ? ' · ' + Math.round(data.duration_sec / 60) + ' min' : ''));
    }

    if (data.thumbnail_url) {
      $('#jobThumbnail').attr('src', data.thumbnail_url).removeClass('d-none');
    }

    $('#progressBar').css('width', (data.progress_percent || 0) + '%').attr('aria-valuenow', data.progress_percent || 0);
    $('#stageMessage').text(data.message || humanizeState(data.state));
    $('#etaText').text(formatEta(data.eta_seconds));

    renderStages(data.state);
    renderLogs(data.logs);

    $('#emailNoticeBox').toggleClass('d-none', TERMINAL_STATES.includes(data.state));
    $('#errorBox').toggleClass('d-none', !data.error_message).text(data.error_message || '');
    $('#completedBox').toggleClass('d-none', data.state !== 'completed');

    if (data.state === 'completed') {
      $('#expiredNotice').toggleClass('d-none', !data.expired);
      $('#videoPlayerBox').toggleClass('d-none', !!data.expired);

      if (data.video && !data.expired) {
        renderVideo(data.video);
      }
    }

    if (TERMINAL_STATES.includes(data.state)) {
      stopPolling();
    }
  }

  function humanizeState(state) {
    if (!state) {
      return 'Working…';
    }

    return state.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
  }

  function poll() {
    $.ajax({
      url: window.AK.baseUrl + '/api/jobs/' + jobId + '/status',
      method: 'GET',
      dataType: 'json',
    })
      .done(function (response) {
        if (response && response.success) {
          render(response);
        }
      })
      .fail(function () {
        // Transient network hiccup — the next tick will retry.
      });
  }

  function startPolling() {
    poll();
    pollTimer = window.setInterval(poll, POLL_INTERVAL_MS);
  }

  function stopPolling() {
    if (pollTimer !== null) {
      window.clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  startPolling();
})(jQuery);
