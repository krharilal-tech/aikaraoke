(function ($) {
  'use strict';

  const YOUTUBE_URL_PATTERN = /^https?:\/\/(www\.|m\.|music\.)?(youtube\.com\/(watch\?v=|shorts\/)|youtu\.be\/)[\w-]{6,}/i;

  const $form = $('#generateForm');
  const $url = $('#youtubeUrl');
  const $error = $('#urlError');
  const $btn = $('#generateBtn');

  function isValidYoutubeUrl(value) {
    return YOUTUBE_URL_PATTERN.test(value.trim());
  }

  function showError(message) {
    $error.text(message).css('display', 'block');
    $url.addClass('is-invalid');
  }

  function clearError() {
    $error.css('display', 'none');
    $url.removeClass('is-invalid');
  }

  const PENDING_URL_KEY = 'aikaraoke_pending_url';
  const PENDING_KEEP_VOCALS_KEY = 'aikaraoke_pending_keep_vocals';
  const PENDING_LANGUAGE_KEY = 'aikaraoke_pending_language';

  $url.on('input', clearError);

  // A user who wasn't signed in gets bounced to /login (see the submit
  // handler's 401 branch below), which throws away whatever they'd typed —
  // stashing it client-side and restoring it here means they don't have to
  // re-paste the URL after signing in.
  (function restorePendingUrl() {
    const pendingUrl = sessionStorage.getItem(PENDING_URL_KEY);

    if (!pendingUrl) {
      return;
    }

    sessionStorage.removeItem(PENDING_URL_KEY);

    $url.val(pendingUrl);

    if (sessionStorage.getItem(PENDING_KEEP_VOCALS_KEY) === '1') {
      $('#keepVocals').prop('checked', true);
    }
    sessionStorage.removeItem(PENDING_KEEP_VOCALS_KEY);

    const pendingLanguage = sessionStorage.getItem(PENDING_LANGUAGE_KEY);

    if (pendingLanguage) {
      $('#language').val(pendingLanguage);
    }
    sessionStorage.removeItem(PENDING_LANGUAGE_KEY);

    if (window.AK.isAuthenticated) {
      $form.trigger('submit');
    }
  })();

  $form.on('submit', function (event) {
    event.preventDefault();

    const url = $url.val() || '';

    if (!isValidYoutubeUrl(url)) {
      showError('Please enter a valid YouTube video URL (youtube.com or youtu.be).');
      return;
    }

    clearError();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Starting...');

    $.ajax({
      url: window.AK.baseUrl + '/jobs',
      method: 'POST',
      data: {
        youtube_url: url,
        keep_vocals: $('#keepVocals').is(':checked') ? 1 : 0,
        language: $('#language').val(),
        _csrf: window.AK.csrfToken,
      },
      dataType: 'json',
    })
      .done(function (response) {
        if (response && response.success && response.job_id) {
          window.location.href = window.AK.baseUrl + '/jobs/' + response.job_id;
          return;
        }

        showError((response && response.message) || 'Something went wrong. Please try again.');
        $btn.prop('disabled', false).html('<i class="bi bi-magic me-2"></i>Generate Karaoke');
      })
      .fail(function (xhr) {
        if (xhr.status === 401) {
          sessionStorage.setItem(PENDING_URL_KEY, url);
          sessionStorage.setItem(PENDING_KEEP_VOCALS_KEY, $('#keepVocals').is(':checked') ? '1' : '0');
          sessionStorage.setItem(PENDING_LANGUAGE_KEY, $('#language').val());
          window.location.href = window.AK.baseUrl + '/login?next=%2F';
          return;
        }

        if (xhr.status === 402) {
          window.location.href = window.AK.baseUrl + '/pricing';
          return;
        }

        const message = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not start the job. Please try again.';
        showError(message);
        $btn.prop('disabled', false).html('<i class="bi bi-magic me-2"></i>Generate Karaoke');
      });
  });
})(jQuery);
