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

window.EnderecoIntegrator.TypeaheadManager = class TypeaheadManager {
    static SELECTORS = {
        ZIP_INPUT: 'input[name="plz"]',
        CITY_INPUT: 'input[name="ort"]',
        SHIPPING_ZIP: '[name="register[shipping_address][plz]"]',
        SHIPPING_CITY: '[name="register[shipping_address][ort]"]'
    };

    constructor() {
        Object.values(TypeaheadManager.SELECTORS).forEach(selector => {
            const element = document.querySelector(selector);

            if (element) {
                this.removeFromElement(element);
            }
        });
    }

    removeFromElement(element) {
        if (this.isInitialized(element)) {
            this.destroy(element);
        } else {
            this.observeChanges(element);
        }
    }

    destroy(element) {

        // check for jQuery and the typeahead plugin
        if (!$?.fn?.typeahead) {
            return;
        }

        $(element).typeahead('destroy');

        // remove remains from JTL's typeahead implementation
        element.classList.remove('bg-typeahead-fix');
        element.classList.remove('typeahead');

        const container = element.closest('.typeahead-required');

        if (container) {
            container.classList.remove('typeahead-required');
        }
    }

    observeChanges(element) {
        const observer = new MutationObserver(() => {
            if (this.isInitialized(element)) {
                observer.disconnect();
                this.destroy(element);
            }
        });

        observer.observe(document.body, {
            childList: true,
            attributes: true,
            subtree: true
        });
    }

    isInitialized(element) {
        return element.classList.contains('tt-input');
    }
}

window.EnderecoIntegrator.waitUntilReady().then( function() {
    new window.EnderecoIntegrator.TypeaheadManager();
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
