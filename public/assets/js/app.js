/**
 * Global app bootstrap: wires the CSRF token onto every AJAX request so
 * individual page scripts don't have to repeat it.
 */
(function ($) {
  'use strict';

  const csrfToken = $('meta[name="csrf-token"]').attr('content');

  $.ajaxSetup({
    headers: { 'X-CSRF-Token': csrfToken },
  });

  window.AK = window.AK || {};
  window.AK.csrfToken = csrfToken;
  window.AK.baseUrl = $('meta[name="base-url"]').attr('content') || '';
  window.AK.isAuthenticated = $('meta[name="auth-status"]').attr('content') === '1';

  window.AK.flashAlert = function (container, type, message) {
    const $alert = $(
      '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert"></div>'
    )
      .text(message)
      .append(
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
      );
    $(container).prepend($alert);
  };
})(jQuery);
