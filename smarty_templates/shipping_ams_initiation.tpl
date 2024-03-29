<input type="hidden" name="enderecodeliveryamsts" value="{$endereco_delivery_amsts}" >
<input type="hidden" name="enderecodeliveryamsstatus" value="{$endereco_delivery_amsstatus|escape:'html'}" >
<input type="hidden" name="enderecodeliveryamspredictions" value="{$endereco_delivery_amspredictions|escape:'html'}">

<script>
    {literal}
    enderecoInitAMS(
        {
            countryCode: '[name="register[shipping_address][land]"]',
            postalCode: '[name="register[shipping_address][plz]"]',
            locality: '[name="register[shipping_address][ort]"]',
            streetFull: '',
            streetName: '[name="register[shipping_address][strasse]"]',
            buildingNumber: '[name="register[shipping_address][hausnummer]"]',
            addressStatus: '[name="enderecodeliveryamsstatus"]',
            addressTimestamp: '[name="enderecodeliveryamsts"]',
            addressPredictions: '[name="enderecodeliveryamspredictions"]',
            additionalInfo: '[name="register[shipping_address][adresszusatz]"]',
        },
        {
            name: 'shipping_address',
            addressType: 'shipping_address'
        },
        function(EAO) {
            // Compatibility issue with DHL Wunschpaket.
            if (document.querySelector('select#kLieferadresse')) {
                document.querySelector('select#kLieferadresse').addEventListener('change', function() {
                    var $attr = document.querySelector('select#kLieferadresse').selectedOptions[0].getAttribute('data-jtlpack')
                    if ('-2' === $attr) {
                        EAO.addressType = 'packstation';
                        EAO.street = "Packstation";
                    } else if('-3' === $attr) {
                        EAO.addressType = 'postoffice';
                        EAO.street = "Postfiliale";
                    } else {
                        EAO.addressType = 'shipping_address';
                    }
                })
            }
        }
    )
    enderecoInitPS(
        {
            salutation: 'register[shipping_address][anrede]',
            firstName: 'register[shipping_address][vorname]',
            lastName: 'register[shipping_address][nachname]',
            title: 'register[shipping_address][titel]'
        },
        {
            name: 'shipping_person'
        }
    )

    enderecoInitES(
        {
            email: '[name="register[shipping_address][email]"]'
        },
        {
            name: 'shipping_email',
            errorContainer: '#container-shipping-email-error-messages',
            errorInsertMode: 'afterbegin'
        }
    )
    {/literal}
</script>
