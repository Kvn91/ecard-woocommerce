jQuery(document).ready(function($) {
  const modal = document.getElementById('pledg-popup');
  const trackingPixelSrc = 'https://pledg-assets.s3.eu-west-1.amazonaws.com/pledg-tracking/pixel.png';

  $('.pledg-modal-link').on('click', function(e) {
    e.preventDefault();

    if (pledg_payment_types.installment === $(this).data('paymentType')) {
      let content = $('#pledg-popup-' + pledg_payment_types.installment).html();
      $(modal).find('.pledg-popup').html(content);
    } else if (pledg_payment_types.deferred === $(this).data('paymentType')) {
      let content = $('#pledg-popup-' + pledg_payment_types.deferred).html();
      $(modal).find('.pledg-popup').html(content);
    } else {
      return false;
    }

    setPledgCookie('pledg_' + $(this).data('widgetType') + '_widget', 'clicked', 1);
    $(modal).find('.pledg-popup').append('<img src="' + trackingPixelSrc + '" style="display:none;">');

    $('.pledg-popup-close').on('click', function() {
      $(modal).removeClass('show');
    });

    $(modal).addClass('show');

    $('html, body').animate({
      scrollTop: 0
    }, 'slow');

    return false;
  });

  window.onclick = function(event) {
    if (event.target === modal) {
      $(modal).toggleClass('show');
    }
  }
});

function setPledgCookie(name, value, exdays) {
  const d = new Date();
  d.setTime(d.getTime() + (exdays*24*60*60*1000));
  let expires = "expires="+ d.toUTCString();
  document.cookie = name + "=" + value + ";" + expires + ";path=/";
}

function getPledgCookie(name) {
  const cookieArr = document.cookie.split(";");

  for (let i = 0; i < cookieArr.length; i++) {
    let cookiePair = cookieArr[i].split("=");
    if (name === cookiePair[0].trim()) {
      return decodeURIComponent(cookiePair[1]);
    }
  }

  return null;
}
