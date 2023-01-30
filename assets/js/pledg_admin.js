(function($) {
  $(document).ready(function() {
    const el = document.querySelector('input[name^=woocommerce_pledg][name$=_logo]');
    el.addEventListener('click', function() {
      const frame = new wp.media.view.MediaFrame.Select({
        title: pledg_trad.modal_title,
        multiple: false,
        library: {
          order: 'ASC',
          orderby: 'title',
          type: 'image',
          uploadedTo: null,
        },
        button: {
          text: pledg_trad.modal_button,
        },
      });
      frame.on('select', function() {
        el.setAttribute('value', (frame.state().get('selection').models[0].attributes.url));
      });
      frame.open();
    });

    const title_lang = document.querySelector('select[name^=woocommerce_pledg][name$=_title_lang]');
    const description_lang = document.querySelector('select[name^=woocommerce_pledg][name$=_description_lang]');

    const titles = [];
    const descriptions = [];

    for (let i = 0; i < title_lang.options.length; i++) {
      titles.push(document.querySelector(
        'input[name^=woocommerce_pledg][name$=_title_' + title_lang.options[i].text + ']',
      ));
      if (title_lang.selectedIndex !== i) {
        titles[i].parentElement.parentElement.parentElement.setAttribute('style', 'display:none');
      }
    }
    for (let i = 0; i < description_lang.options.length; i++) {
      descriptions.push(document.querySelector(
        'input[name^=woocommerce_pledg][name$=_description_' + description_lang.options[i].text + ']',
      ));
      if (description_lang.selectedIndex !== i) {
        descriptions[i].parentElement.parentElement.parentElement.setAttribute('style', 'display:none');
      }
    }

    title_lang.addEventListener('change', function() {
      titles.forEach(function(v, i) {
        if (i !== title_lang.selectedIndex) {
          v.parentElement.parentElement.parentElement.setAttribute('style', 'display:none');
        } else {
          v.parentElement.parentElement.parentElement.setAttribute('style', 'display:');
        }
      });
    });
    description_lang.addEventListener('change', function() {
      descriptions.forEach(function(v, i) {
        if (i !== description_lang.selectedIndex) {
          v.parentElement.parentElement.parentElement.setAttribute('style', 'display:none');
        } else {
          v.parentElement.parentElement.parentElement.setAttribute('style', 'display:');
        }
      });
    });
    if ($('input[name^=woocommerce_pledg][name$=_merchant_id]').length) {
      let rows = 0;
      let merchantIdObject;
      const merchant_id_input = $('input[name^=woocommerce_pledg][name$=_merchant_id]');
      const availableCountries = $(merchant_id_input).data('countries');
      try {
        merchantIdObject = JSON.parse($(merchant_id_input).val());
      } catch {
        merchantIdObject = {
          FR: $(merchant_id_input).val(),
        };
      }
      const container = $(merchant_id_input).parent();
      $(merchant_id_input).addClass('hidden');

      $(container).prepend('<a class="js-add-row" href="#">Add a Merchant Id Mapping</a>');
      $(container).find('a.js-add-row').on('click', function(e) {
        e.preventDefault();
        addRow('', '');
        rows++;
      });

      let html = '<table class="table" id="merchantIdAdder">';
      html += '<thead>';
      html += '<tr>';
      html += '<th style="padding-top:0; padding-bottom:0.5rem">Country</th>';
      html += '<th style="padding-top:0; padding-bottom:0.5rem">Merchant Id</th>';
      html += '<th style="padding-top:0; padding-bottom:0.5rem">Action</th>';
      html += '</tr>';
      html += '</thead>';
      html += '<tbody>';
      html += '</tbody>';
      html += '</table>';
      $(container).prepend(html);
      const merchant_id_adder_body = $('#merchantIdAdder tbody');
      for (const [country, merchantId] of Object.entries(merchantIdObject)) {
        if (typeof availableCountries[country] !== 'undefined') {
          addRow(country, merchantId.replace(/"/g, '&quot;'));
          rows++;
        }
      }
      if (rows === 0) {
        addRow('', '');
        rows++;
      }

      function addRow(country, merchantId) {
        const r = rows;
        let html = '';
        html += '<tr id="row' + r + '" class="bodyRow">';
        html += '<td style="padding-top:0; padding-bottom:0.5rem">';
        // Country
        html += '<select class="form-select" id="select' + r + '">';
        for (const [c, v] of Object.entries(availableCountries)) {
          if (c === country) {
            html += '<option value="' + c + '" selected>' + v + '</option>';
          } else {
            html += '<option value="' + c + '">' + v + '</option>';
          }
        }
        html += '</select>';
        html += '</td>';
        html += '<td style="padding-top:0; padding-bottom:0.5rem">';
        // MerchantId
        html += '<input value="' + merchantId + '" type="text" class="form-control" id="merchantId' + r + '"/>';
        html += '</td>';
        html += '<td style="padding-top:0; padding-bottom:0.5rem">';
        // Remove button
        html += '<a class="btn btn-info" href="#"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/><path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/></svg></a>';
        html += '</td>';
        html += '</tr>';
        $(merchant_id_adder_body).append(html);
        $(merchant_id_adder_body).find('#row' + r + ' .btn').on('click', function(e) {
          removeRow(r, e);
        });
        $(merchant_id_adder_body).find('#row' + r + ' input').on('input', function() {
          updateMerchantIdInput();
        });
        $(merchant_id_adder_body).find('#row' + r + ' select').on('change', function() {
          updateMerchantIdInput();
        });
        updateMerchantIdInput();
      }

      function removeRow(rowNb, e) {
        e.preventDefault();
        if ($(merchant_id_adder_body).find('tr.bodyRow').length > 1) {
          const row = $(merchant_id_adder_body).find('tr#row' + rowNb);
          if (row.length) {
            $(row).first().remove();
          }
          updateMerchantIdInput();
        }
      }

      function updateMerchantIdInput() {
        const r = $(merchant_id_adder_body).find('tr.bodyRow').toArray();
        const Cs = [];
        r.forEach((el) => {
          const country = $(el).find('.form-select').val();
          Cs.push(country);
          const merchantId = $(el).find('input').val();
          if (merchantId !== '') {
            merchantIdObject[country] = merchantId;
          }
        });
        for (const [c, v] of Object.entries(merchantIdObject)) {
          if (! Cs.includes(c) && c !== 'default') {
            delete merchantIdObject[c];
          }
        }
        $(merchant_id_input).val(JSON.stringify(merchantIdObject));
      }
    }
  });
}(jQuery));
