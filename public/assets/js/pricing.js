(function ($) {
  'use strict';

  const $modal = $('#buyPackageModal');
  const $modalName = $('#buyPackageModalName');
  const $phone = $('#buyPackagePhone');
  const $error = $('#buyPackageError');
  const $confirmBtn = $('#buyPackageConfirmBtn');

  let selectedPackageId = null;
  const bsModal = $modal.length ? new bootstrap.Modal($modal[0]) : null;

  function showError(message) {
    $error.text(message).css('display', 'block');
  }

  function clearError() {
    $error.text('').css('display', 'none');
  }

  $('.buy-package-btn').on('click', function () {
    if (!window.AK.isAuthenticated) {
      window.location.href = window.AK.baseUrl + '/login?next=%2Fpricing';
      return;
    }

    selectedPackageId = $(this).data('package-id');
    $modalName.text($(this).data('package-name'));
    clearError();

    if (bsModal) {
      bsModal.show();
    }
  });

  $confirmBtn.on('click', function () {
    const phone = ($phone.val() || '').replace(/\D/g, '');

    if (phone.length !== 10) {
      showError('Please enter a valid 10-digit phone number.');
      return;
    }

    clearError();
    $confirmBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Please wait…');

    $.ajax({
      url: window.AK.baseUrl + '/payments/' + selectedPackageId + '/checkout',
      method: 'POST',
      data: { phone: phone },
      dataType: 'json',
    })
      .done(function (response) {
        if (!response || !response.success || !response.payment_session_id) {
          showError((response && response.message) || 'Could not start the payment. Please try again.');
          $confirmBtn.prop('disabled', false).text('Continue to Payment');
          return;
        }

        const cashfree = Cashfree({ mode: response.cashfree_mode });
        cashfree.checkout({
          paymentSessionId: response.payment_session_id,
          redirectTarget: '_self',
        });
      })
      .fail(function (xhr) {
        const message = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not start the payment. Please try again.';
        showError(message);
        $confirmBtn.prop('disabled', false).text('Continue to Payment');
      });
  });
})(jQuery);
