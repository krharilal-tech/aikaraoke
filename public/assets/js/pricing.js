(function ($) {
  'use strict';

  const $modal = $('#buyPackageModal');
  const $modalName = $('#buyPackageModalName');
  const $phone = $('#buyPackagePhone');
  const $error = $('#buyPackageError');
  const $confirmBtn = $('#buyPackageConfirmBtn');

  let selectedPackageId = null;
  // The order "Buy Now" recorded, moved on to Cashfree at checkout.
  let startedOrderId = null;
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
    startedOrderId = null;
    $modalName.text($(this).data('package-name'));
    clearError();

    // Records the click for the admin's Orders page. The box opens either
    // way; without an id, checkout simply records a new order.
    const packageId = selectedPackageId;
    $.ajax({
      url: window.AK.baseUrl + '/payments/' + packageId + '/start',
      method: 'POST',
      dataType: 'json',
    }).done(function (response) {
      if (response && response.success && packageId === selectedPackageId) {
        startedOrderId = response.order_id;
      }
    });

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
      data: { phone: phone, order_id: startedOrderId || '' },
      dataType: 'json',
    })
      .done(function (response) {
        if (!response || !response.success || !response.payment_session_id) {
          showError((response && response.message) || 'Could not start the payment. Please try again.');
          $confirmBtn.prop('disabled', false).text('Continue to Payment');
          return;
        }

        if (typeof Cashfree !== 'function') {
          showError('The payment page could not load. Please refresh and try again.');
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
