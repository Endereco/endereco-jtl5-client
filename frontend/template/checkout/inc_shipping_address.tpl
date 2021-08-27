{block name='checkout-inc-shipping-address-shipping-address' append}
    <input type="hidden" name="enderecodeliveryamsts" value="{$endereco_delivery_amsts}" >
    <input type="hidden" name="enderecodeliveryamsstatus" value="{$endereco_delivery_amsstatus|escape:'html'}" >
    <input type="hidden" name="enderecodeliveryamspredictions" value="{$endereco_delivery_amspredictions|escape:'html'}">

    <script>
        {literal}
        enderecoInitAMS(
            '',
            {
                name: 'shipping_address',
                addressType: 'shipping_address',
                postfixCollection: {
                    countryCode: 'register[shipping_address][land]',
                    postalCode: 'register[shipping_address][plz]',
                    locality: 'register[shipping_address][ort]',
                    streetFull: '',
                    streetName: 'register[shipping_address][strasse]',
                    buildingNumber: 'register[shipping_address][hausnummer]',
                    addressStatus: 'enderecodeliveryamsstatus',
                    addressTimestamp: 'enderecodeliveryamsts',
                    addressPredictions: 'enderecodeliveryamspredictions',
                    additionalInfo: 'register[shipping_address][adresszusatz]',
                }
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
            '',
            {
                name: 'shipping_person',
                postfixCollection: {
                    salutation: 'register[shipping_address][anrede]',
                    firstName: 'register[shipping_address][vorname]'
                }
            }
        )
        {/literal}
    </script>
{/block}
