<div class="endereco-fake-addresses" style="display: none !important">
    <div>
        <input id="endereco_billing_countrycode" type="text" value="{$endereco_billing_countrycode}">
        <input id="endereco_billing_postal_code" type="text" value="{$endereco_billing_postal_code}">
        <input id="endereco_billing_locality" type="text" value="{$endereco_billing_locality}">
        <input id="endereco_billing_street_name" type="text" value="{$endereco_billing_street_name}">
        <input id="endereco_billing_building_number" type="text" value="{$endereco_billing_building_number}">
        <input id="endereco_billing_addinfo" type="text" value="{$endereco_billing_addinfo}">

        <input id="endereco_billing_ts" type="text" value="{$endereco_billing_ts}">
        <input id="endereco_billing_status" type="text" value="{$endereco_billing_status|escape:'html'}">
        <input id="endereco_billing_predictions" type="text" value="{$endereco_billing_predictions|escape:'html'}">

        <input
                id="endereco_billing_address"
                data-customer-id="{$Kunde->kKunde}"
                data-country-code="{$endereco_billing_countrycode}"
                data-postal-code="{$endereco_billing_postal_code}"
                data-locality="{$endereco_billing_locality}"
                data-street-name="{$endereco_billing_street_name}"
                data-building-number="{$endereco_billing_building_number}"
                data-additional-info="{$endereco_billing_addinfo}"
                data-copy-shipping="{if !$endereco_shipping_address_is_different}true{else}false{/if}"
        >
    </div>

    <script>
        {literal}
        (function() {
            function afterCreateHandler(EAO) {
                if (!EAO) {
                    return;
                }

                const handleAddressConfirmation = function() {
                    postAddressData(EAO);
                };

                EAO.waitForAllExtension().then( function() {
                    EAO.onEditAddress.push(function() {
                        window.location = 'bestellvorgang.php?editRechnungsadresse=1';
                    });
                    EAO.onConfirmAddress.push(handleAddressConfirmation);
                    EAO.onAfterAddressCheckSelected.push(handleAddressConfirmation);
                })
            }

            function postAddressData(EAO) {
                const originalAddress = document.querySelector('#endereco_billing_address');
                if (!originalAddress) {
                    return;
                }
                EAO._awaits++;
                EAO.util.axios({
                    method: 'post',
                    url: 'io.php?io=endereco_inner_request',
                    data: {
                        method: 'updateBillingAddress',
                        params: {
                            customerId: originalAddress.dataset.customerId,
                            updatedAddress: EAO.address,
                            originalAddress: {
                                countryCode: originalAddress.dataset.countryCode,
                                postalCode: originalAddress.dataset.postalCode,
                                locality: originalAddress.dataset.locality,
                                streetName: originalAddress.dataset.streetName,
                                buildingNumber: originalAddress.dataset.buildingNumber,
                                additionalInfo: originalAddress.dataset.additionalInfo,
                            },
                            copyShippingToo: originalAddress.dataset.copyShipping,
                            enderecometa: {
                                ts: EAO.addressTimestamp,
                                status: EAO.addressStatus,
                                predictions: EAO.addressPredictions,
                            }
                        }
                    }
                }).then(function(response) {
                    const reloadHandler = () => {
                        EAO.waitForAllPopupsToClose().then(() => {
                            window.setTimeout(() => {
                                if(window.EnderecoIntegrator.popupQueue > 0) {
                                    // We are still waiting for all popups to close
                                    reloadHandler();
                                    return;
                                }
                                window.location.href = window.location.href;
                            }, 100);
                        });
                    }
                    reloadHandler();
                }).catch(function(error) {
                    console.error('Error during address update:', error);
                }).finally(function() {
                    EAO._awaits--;
                });
            }

            enderecoInitAMS(
                '',
                {
                    name: 'billing_address',
                    addressType: 'billing_address',
                    postfixCollection: {
                        countryCode: '#endereco_billing_countrycode',
                        postalCode: '#endereco_billing_postal_code',
                        locality: '#endereco_billing_locality',
                        streetName: '#endereco_billing_street_name',
                        buildingNumber: '#endereco_billing_building_number',
                        additionalInfo: '#endereco_billing_addinfo',
                        addressTimestamp: '#endereco_billing_ts',
                        addressStatus: '#endereco_billing_status',
                        addressPredictions: '#endereco_billing_predictions'
                    }
                },
                afterCreateHandler
            );
        })();
    </script>
    {/literal}
</div>
