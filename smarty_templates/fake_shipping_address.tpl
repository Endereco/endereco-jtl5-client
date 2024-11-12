<div class="endereco-fake-addresses" style="display: none !important">
    <div>
        <input id="endereco_shipping_countrycode" type="text" value="{$endereco_shipping_countrycode}">
        <input id="endereco_shipping_postal_code" type="text" value="{$endereco_shipping_postal_code}">
        <input id="endereco_shipping_locality" type="text" value="{$endereco_shipping_locality}">
        <input id="endereco_shipping_street_name" type="text" value="{$endereco_shipping_street_name}">
        <input id="endereco_shipping_building_number" type="text" value="{$endereco_shipping_building_number}">
        <input id="endereco_shipping_addinfo" type="text" value="{$endereco_shipping_addinfo}">

        <input id="endereco_shipping_ts" type="text" value="{$endereco_shipping_ts}">
        <input id="endereco_shipping_status" type="text" value="{$endereco_shipping_status|escape:'html'}">
        <input id="endereco_shipping_predictions" type="text" value="{$endereco_shipping_predictions|escape:'html'}">

        <input
                id="endereco_shipping_address"
                data-customer-id="{$Kunde->kKunde}"
                data-country-code="{$endereco_shipping_countrycode}"
                data-postal-code="{$endereco_shipping_postal_code}"
                data-locality="{$endereco_shipping_locality}"
                data-street-name="{$endereco_shipping_street_name}"
                data-building-number="{$endereco_shipping_building_number}"
                data-additional-info="{$endereco_shipping_addinfo}"
        >
    </div>

    <script>
        var ioUrl = ''
        {if $endereco_jtl_5_1_legacymode}
            ioUrl = 'io.php?io=endereco_inner_request';
        {else}
            ioUrl = 'io?io=endereco_inner_request';
        {/if}
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
                const originalAddress = document.querySelector('#endereco_shipping_address');
                if (!originalAddress) {
                    return;
                }
                EAO._awaits++;
                EAO.util.axios({
                    method: 'post',
                    url: ioUrl,
                    data: {
                        method: 'updateShippingAddress',
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
                    name: 'shipping_address',
                    addressType: 'shipping_address',
                    postfixCollection: {
                        countryCode: '#endereco_shipping_countrycode',
                        postalCode: '#endereco_shipping_postal_code',
                        locality: '#endereco_shipping_locality',
                        streetName: '#endereco_shipping_street_name',
                        buildingNumber: '#endereco_shipping_building_number',
                        additionalInfo: '#endereco_shipping_addinfo',
                        addressTimestamp: '#endereco_shipping_ts',
                        addressStatus: '#endereco_shipping_status',
                        addressPredictions: '#endereco_shipping_predictions'
                    }
                },
                afterCreateHandler
            );
        })();
    </script>
    {/literal}
</div>
