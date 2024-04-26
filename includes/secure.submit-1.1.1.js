// Create a new `HPS` object with the necessary configuration
var hps = GlobalPayments.ui.form({
    fields: {
        'card-number': {
            placeholder: '•••• •••• •••• ••••',
            target: '#iframesCardNumber'
        },
        'card-expiration': {
            placeholder: 'MM / YYYY',
            target: '#iframesCardExpiration'
        },
        'card-cvv': {
            placeholder: '•••',
            target: '#iframesCardCvv'
        }
    },
    styles:  {
        'html' : {
            '-webkit-text-size-adjust': '100%'
        },
        'body' : {
        'width' : '100%'
        },
        '#secure-payment-field-wrapper' : {
        'position' : 'relative',
        'justify-content'  : 'flex-end'
        },
        '#secure-payment-field' : {
        'background-color' : '#fff',
        'border'           : '1px solid #ccc',
        'border-radius'    : '4px',
        'display'          : 'block',
        'font-size'        : '14px',
        'height'           : '35px',
        'padding'          : '6px 12px',
        'width'            : '100%',
        },
        '#secure-payment-field:focus' : {
        'border': '1px solid lightblue',
        'box-shadow': '0 1px 3px 0 #cecece',
        'outline': 'none'
        },
        'button#secure-payment-field.submit' : {
            'width': 'unset',
            'flex': 'unset',
            'float': 'right',
            'color': '#fff',
            'background': '#2e6da4',
            'cursor': 'pointer'
        },
        '.card-number::-ms-clear' : {
        'display' : 'none'
        },
        'input[placeholder]' : {
        'letter-spacing' : '.5px',
        },
    }
});

hps.on('token-success', function(resp) {
    if(resp.details.cardSecurityCode == false) {
        document.getElementById('gps-error').style.display = 'block';
        document.getElementById('gps-error').innerText = 'Invalid Card Details';
        $("#tdb5").prop("disabled", false);
    }else{
        secureSubmitResponseHandler(resp);
    }
});

hps.on('token-error', function(resp) {
    if(resp.error){
        resp.reasons.forEach(function(v){
            document.getElementById('gps-error').style.display = 'block';
            document.getElementById('gps-error').innerText = v.message;
        })
    }
    $("#tdb5").prop("disabled", false);
});

function secureSubmitResponseHandler(response) {
    if (response.error !== undefined && response.error.message !== undefined) {
        alert(response.error.message);
    } else {
        var form$ = $("form[name=checkout_confirmation]");

        form$.append("<input type='hidden' name='securesubmit_token' value='" + response.paymentReference + "'/>");
        form$.append("<input type='hidden' name='card_type' value='" + response.details.cardType + "'/>");

        $("form[name='checkout_confirmation']").unbind("submit");
        $("form[name='checkout_confirmation']").submit();
    }
    $("#tdb5").prop("disabled", false);
    return false;
}

var triggerSubmit = function () {
    // manually include iframe submit button
    const fields = ['submit'];
    const target = hps.frames['card-number'];

    for (const type in hps.frames) {
      if (hps.frames.hasOwnProperty(type)) {
        fields.push(type);
      }
    }

    for (const type in hps.frames) {
      if (!hps.frames.hasOwnProperty(type)) {
        continue;
      }

      const frame = hps.frames[type];

      if (!frame) {
        continue;
      }

      GlobalPayments.internal.postMessage.post({
        data: {
          fields: fields,
          target: target.id
        },
        id: frame.id,
        type: 'ui:iframe-field:request-data'
      }, frame.id);
    }
  }

