(function($) {
  const regex = /pledg[1-5]{0,1}/;
  const XHR_STATE_DONE = 4;

  $('body')
    .on('updated_checkout', function() {
      document.querySelectorAll('input[name="payment_method"]').forEach((elem) => {
        elem.addEventListener('change', function(event) {
          const item = event.target;
          if (item.value.match(regex)) {
            paymentDetail(item);
          }
        });
      });
      const el = document.querySelector('input[name="payment_method"]:checked');
      if (el && el.value.match(regex)) {
        paymentDetail(el);
      }
    });

  async function paymentDetail(el) {
    try {
      const box = el.parentNode.querySelector('div.payment_box');
      let child = box.querySelector('#payment-detail-container');
      if (child !== null) {
        child.remove();
      }
      const locale = box.querySelector('input[name=locale_' + el.value).value;
      child = document.createElement('div');
      $(child).addClass('spinner-parent');
      child.id = 'payment-detail-container';
      child.innerHTML = '<span class="spinner-border"></span>';

      box.appendChild(child);

      const {currency, currencySign, theTrad, deferredTrad, deadlineTrad, feesTrad} = JSON.parse(box.querySelector('input[name^=payment_detail_trad_]').value);

      const urlAPI = JSON.parse(box.querySelector('input[name^=url_api_]').value);
      const xhttp = new XMLHttpRequest();
      xhttp.onreadystatechange = function() {
        if (this.readyState === XHR_STATE_DONE && this.status === 200) {
          const data = JSON.parse(this.responseText);
          if ('INSTALLMENT' in data) {
            const paymentSchedule = data.INSTALLMENT;
            let ret = "<div class='screen-section' style='padding-top: 0px;'>";

            for (let i = 0; i < paymentSchedule.length; i++) {
              const share = ((paymentSchedule[i].amount_cents + paymentSchedule[i].fees) / 100).toFixed(2);
              const shareFormatted = amountFormat(share, currency, currencySign);

              ret += `<p style="margin-top: 30px;"><b style="float: left;">
                ${ deadlineTrad } ${ (i + 1) } ${ theTrad }
                ${ new Date(paymentSchedule[i].payment_date).toLocaleDateString(locale) }</b>
                <b style="float: right; text-align: right;"> ${ shareFormatted }</b></p>`;

              if (paymentSchedule[i].fees) {
                const fees = (paymentSchedule[i].fees / 100).toFixed(2);
                const feesFormatted = amountFormat(fees, currency, currencySign);

                ret += `<br><p><b style="float: right; text-align: right;">
                  <span style="font-size: 0.85em;">
                  ${ feesTrad.replace('%s', feesFormatted) }
                  </span></b></p>`;
              }
              ret += '<div style="clear: both; margin-bottom: 30px;"></div>';
            }
            ret += '</div>';
            child.innerHTML = ret;
            $(child).removeClass('spinner-parent');
          } else if ('DEFERRED' in data) {
            const paymentSchedule = data.DEFERRED;
            const share = ((paymentSchedule.amount_cents + paymentSchedule.fees) / 100).toFixed(2);
            const shareFormatted = amountFormat(share, currency, currencySign);

            let ret = `<div class='screen-section' style='padding-top: 0px;'><p><b>
                ${ deferredTrad.replace('%s1', shareFormatted)
    .replace('%s2', new Date(paymentSchedule.payment_date).toLocaleDateString(locale)) }
                </b></p>`;

            if (paymentSchedule.fees) {
              const fees = (paymentSchedule.fees / 100).toFixed(2);
              const feesFormatted = amountFormat(fees, currency, currencySign);

              ret += `<p><b><span style="font-size: 0.85em;"> 
                  ${ feesTrad.replace('%s', feesFormatted) }
                  <br></span></b>`;
            }

            ret += '</div>';
            child.innerHTML = ret;
            child.classList.remove('spinner-parent');
          } else {
            child.innerHTML = '';
            child.classList.remove('spinner-parent');
          }
        }
      };
      xhttp.open('POST', urlAPI.url, true);
      xhttp.send(JSON.stringify(urlAPI.payload));
    } catch (e) {
      console.log(e);
    }
  }

  function amountFormat(amount, currency, currencySign) {
    if (currencySign === 'before') {
      return currency + amount;
    }
    return amount + currency;
  }
}(jQuery));

