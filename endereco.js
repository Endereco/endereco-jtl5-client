import Promise from 'promise-polyfill';
import merge from 'lodash.merge';
import EnderecoIntegrator from './node_modules/@endereco/js-sdk/modules/integrator';
import css from './endereco.scss';

if ('NodeList' in window && !NodeList.prototype.forEach) {
    NodeList.prototype.forEach = function (callback, thisArg) {
        thisArg = thisArg || window;
        for (var i = 0; i < this.length; i++) {
            callback.call(thisArg, this[i], i, this);
        }
    };
}

if (!window.Promise) {
    window.Promise = Promise;
}

EnderecoIntegrator.postfix = {
    ams: {
        countryCode: 'land',
        postalCode: 'plz',
        locality: 'ort',
        streetFull: '',
        streetName: 'strasse',
        buildingNumber: 'hausnummer',
        addressStatus: 'enderecoamsstatus',
        addressTimestamp: 'enderecoamsts',
        addressPredictions: 'enderecoamspredictions',
        additionalInfo: 'adresszusatz',
    },
    personServices: {
        salutation: 'anrede',
        firstName: 'vorname'
    },
    emailServices: {
        email: 'email'
    }
};

EnderecoIntegrator.css = css[0][1];
EnderecoIntegrator.resolvers.countryCodeWrite = function(value) {
    return new Promise(function(resolve, reject) {
        resolve(value.toUpperCase());
    });
}
EnderecoIntegrator.resolvers.countryCodeRead = function(value) {
    return new Promise(function(resolve, reject) {
        resolve(value.toUpperCase());
    });
}
EnderecoIntegrator.resolvers.salutationWrite = function(value) {
    var mapping = {
        'f': 'w',
        'm': 'm'
    } ;
    return new Promise(function(resolve, reject) {
        resolve(mapping[value]);
    });
}
EnderecoIntegrator.resolvers.salutationRead = function(value) {
    var mapping = {
        'w': 'f',
        'm': 'm'
    } ;
    return new Promise(function(resolve, reject) {
        resolve(mapping[value]);
    });
}

EnderecoIntegrator.amsFilters.isAddressMetaStillRelevant.push((isStillRelevant, EAO) => {
    // The rest of the logic is only valid for shipping addresses.
    if (EAO.addressType !== 'shipping_address') {
        return isStillRelevant;
    }

    const radioButtons = document.querySelectorAll('input[type="radio"][name="kLieferadresse"]');

    const isInvalid = Array.from(radioButtons).some(radio => {
        const value = parseInt(radio.value, 10);
        return value > 0 && radio.checked;
    });

    if (isInvalid) {
        isStillRelevant = false;
    }

    return isStillRelevant;
});

if (window.EnderecoIntegrator) {
    window.EnderecoIntegrator = merge(EnderecoIntegrator, window.EnderecoIntegrator);
} else {
    window.EnderecoIntegrator = EnderecoIntegrator;
}

window.EnderecoIntegrator.waitUntilReady().then( function() {
    var $removeTypeahead = setInterval( function() {
        if (
            $ &&
            $('[name="ort"]').length &&
            $('[name="ort"]')[0].classList.contains('tt-input')
        ) {
            $('.city_input').typeahead('destroy');
            $('.city_input').removeClass('bg-typeahead-fix');
            $('.city_input').closest('.typeahead-required').removeClass('typeahead-required');
            clearInterval($removeTypeahead);
        }
    }, 100);

    var $removeTypeahead2 = setInterval( function() {
        if (
            $ &&
            $('[name="register[shipping_address][ort]"]').length &&
            $('[name="register[shipping_address][ort]"]').typeahead
        ) {
            $('[name="register[shipping_address][ort]"]').typeahead('destroy');
            $('[name="register[shipping_address][ort]"]').removeClass('bg-typeahead-fix');
            clearInterval($removeTypeahead2);
        }
    }, 100);
});

var $waitForConfig = setInterval( function() {
    if(typeof enderecoLoadAMSConfig === 'function'){
        enderecoLoadAMSConfig();
        clearInterval($waitForConfig);
    }
}, 1);

EnderecoIntegrator.afterAMSActivation.push( function(EAO) {
    if (!!EAO.onSubmitUnblock) {
        EAO.onSubmitUnblock.push(function(AddressObject) {
            AddressObject.forms.forEach( function(form) {
                if (form.querySelector('[type="submit"][disabled]')) {
                    form.querySelector('[type="submit"][disabled]').removeAttribute('disabled');
                }
            });
        });
    }
});
