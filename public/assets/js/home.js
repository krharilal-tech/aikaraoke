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
  const PENDING_LYRICS_KEY = 'aikaraoke_pending_lyrics';

  // Kept in sync with JobController::MAX_LYRICS_LENGTH.
  const MAX_LYRICS_LENGTH = 10000;
  const MAX_LYRICS_FILE_BYTES = 200 * 1024;

  const $useLyrics = $('#useLyrics');
  const $lyricsPanel = $('#lyricsPanel');
  const $lyrics = $('#lyrics');
  const $lyricsError = $('#lyricsError');

  function showLyricsError(message) {
    $lyricsError.text(message).css('display', 'block');
    $lyrics.addClass('is-invalid');
  }

  function clearLyricsError() {
    $lyricsError.css('display', 'none');
    $lyrics.removeClass('is-invalid');
  }

  function updateLyricsCount() {
    $('#lyricsCount').text($lyrics.val().length.toLocaleString('en-IN') + ' / 10,000');
  }

  function setLyricsOpen(open) {
    $useLyrics.prop('checked', open).attr('aria-expanded', open ? 'true' : 'false');
    $lyricsPanel.toggleClass('d-none', !open);
  }

  // Empty when the option is off, so the job finds lyrics as before.
  function pastedLyrics() {
    return $useLyrics.is(':checked') ? $lyrics.val().trim() : '';
  }

  $useLyrics.on('change', function () {
    setLyricsOpen(this.checked);
    clearLyricsError();

    if (this.checked) {
      $lyrics.trigger('focus');
    }
  });

  $lyrics.on('input', function () {
    clearLyricsError();
    updateLyricsCount();
  });

  // Read in the browser, so the user can check and fix the text before
  // submitting. Nothing is uploaded until the form is sent.
  $('#lyricsFile').on('change', function () {
    const file = this.files && this.files[0];
    this.value = '';

    if (!file) {
      return;
    }

    if (!/\.(txt|lrc)$/i.test(file.name)) {
      showLyricsError('Please choose a .txt or .lrc file.');
      return;
    }

    if (file.size > MAX_LYRICS_FILE_BYTES) {
      showLyricsError('That file is too large for a lyrics file.');
      return;
    }

    const reader = new FileReader();

    reader.onload = function () {
      const text = String(reader.result || '').replace(/^﻿/, '').replace(/\r\n?/g, '\n');

      if (text.length > MAX_LYRICS_LENGTH) {
        showLyricsError('Those lyrics are longer than 10,000 characters.');
        return;
      }

      clearLyricsError();
      $lyrics.val(text);
      updateLyricsCount();
    };

    reader.onerror = function () {
      showLyricsError('Could not read that file. Please paste the lyrics instead.');
    };

    reader.readAsText(file, 'UTF-8');
  });

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

    const pendingLyrics = sessionStorage.getItem(PENDING_LYRICS_KEY);

    if (pendingLyrics) {
      setLyricsOpen(true);
      $lyrics.val(pendingLyrics);
      updateLyricsCount();
    }
    sessionStorage.removeItem(PENDING_LYRICS_KEY);

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

    const lyrics = pastedLyrics();

    if ($useLyrics.is(':checked') && !lyrics) {
      showLyricsError('Paste the lyrics or upload a file, or turn this option off.');
      return;
    }

    if (lyrics.length > MAX_LYRICS_LENGTH) {
      showLyricsError('Lyrics can be at most 10,000 characters.');
      return;
    }

    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Starting...');

    $.ajax({
      url: window.AK.baseUrl + '/jobs',
      method: 'POST',
      data: {
        youtube_url: url,
        keep_vocals: $('#keepVocals').is(':checked') ? 1 : 0,
        language: $('#language').val(),
        lyrics: lyrics,
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

          if (lyrics) {
            sessionStorage.setItem(PENDING_LYRICS_KEY, lyrics);
          }
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
