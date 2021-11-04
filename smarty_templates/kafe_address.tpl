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
    </div>
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
    </div>

    <script>
        {literal}
        enderecoInitAMS(
            '',
            {
                name: 'billing_address',
                addressType: 'billing_address',
                postfixCollection: {
                    countryCode: '#endereco_billing_countrycode',
                    postalCode: '#endereco_billing_postal_code',
                    locality: '#endereco_billing_locality',
                    streetFull: '',
                    streetName: '#endereco_billing_street_name',
                    buildingNumber: '#endereco_billing_building_number',
                    additionalInfo: '#endereco_billing_addinfo',
                    addressTimestamp: '#endereco_billing_ts',
                    addressStatus: '#endereco_billing_status',
                    addressPredictions: '#endereco_billing_predictions'
                }
            },
            function(EAO) {
                window.EnderecoIntegrator.globalSpace.reloadPage = function() {
                    window.location.reload();
                }
                EAO.waitForAllExtension().then( function(EAO) {

                    EAO.onEditAddress.push( function() {
                        window.location = 'bestellvorgang.php?editRechnungsadresse=1';
                    })

                    EAO.onConfirmAddress.push( function(EAO) {
                        EAO._awaits++;
                        EAO.util.axios({
                            method: 'post',
                            url: 'io.php?io=endereco_inner_request',
                            data: {
                                method: 'editBillingAddress',
                                params: {
                                    customerId: '{/literal}{$Kunde->kKunde}{literal}',
                                    address: EAO.address,
                                    copyShippingToo: {/literal}{if $Lieferadresse->kLieferadresse} false {else} true {/if}{literal},
                                    enderecometa: {
                                        ts: EAO.addressTimestamp,
                                        status: EAO.addressStatus,
                                        predictions: EAO.addressPredictions,
                                    }
                                }
                            }
                        }).then( function(response) {
                            window.location.reload();
                        }).catch( function(error) {
                            console.log('Something went wrong.')
                        }).finally( function() {
                            EAO._awaits--;
                        });
                    });

                    EAO.onAfterAddressCheckSelected.push( function(EAO) {
                        EAO.waitForAllPopupsToClose().then(function() {
                            EAO.waitUntilReady().then( function() {
                                if (window.EnderecoIntegrator && window.EnderecoIntegrator.globalSpace.reloadPage) {
                                    window.EnderecoIntegrator.globalSpace.reloadPage();
                                    window.EnderecoIntegrator.globalSpace.reloadPage = undefined;
                                }
                            }).catch()
                        }).catch();
                        EAO._awaits++;
                        EAO.util.axios({
                            method: 'post',
                            url: 'io.php?io=endereco_inner_request',
                            data: {
                                method: 'editBillingAddress',
                                params: {
                                    customerId: '{/literal}{$Kunde->kKunde}{literal}',
                                    address: EAO.address,
                                    copyShippingToo: {/literal}{if $Lieferadresse->kLieferadresse} false {else} true {/if}{literal},
                                    enderecometa: {
                                        ts: EAO.addressTimestamp,
                                        status: EAO.addressStatus,
                                        predictions: EAO.addressPredictions,
                                    }
                                }
                            }
                        }).then( function(response) {
                            window.location.reload();
                        }).catch( function(error) {
                            console.log('Something went wrong.')
                        }).finally( function() {
                            EAO._awaits--;
                        });
                    });
                }).catch();
            }
        )
        {/literal}
    </script>

    {if $Lieferadresse->kLieferadresse}
        <script>
            {literal}
            enderecoInitAMS(
                '',
                {
                    name: 'shipping_address',
                    addressType: 'shipping_address',
                    postfixCollection: {
                        countryCode: '#endereco_shipping_countrycode',
                        postalCode: '#endereco_shipping_postal_code',
                        locality: '#endereco_shipping_locality',
                        streetFull: '',
                        streetName: '#endereco_shipping_street_name',
                        buildingNumber: '#endereco_shipping_building_number',
                        additionalInfo: '#endereco_shipping_addinfo',
                        addressTimestamp: '#endereco_shipping_ts',
                        addressStatus: '#endereco_shipping_status',
                        addressPredictions: '#endereco_shipping_predictions'
                    }
                },
                function(EAO) {
                    window.EnderecoIntegrator.globalSpace.reloadPage = function() {
                        window.location.reload();
                    }
                    EAO.waitForAllExtension().then( function(EAO) {

                        EAO.onEditAddress.push( function() {
                            window.location = 'bestellvorgang.php?editRechnungsadresse=1';
                        });

                        EAO.onConfirmAddress.push( function(EAO) {
                            EAO._awaits++;
                            EAO.util.axios({
                                method: 'post',
                                url: 'io.php?io=endereco_inner_request',
                                data: {
                                    method: 'editShippingAddress',
                                    params: {
                                        shippingAddressId: '{/literal}{$Lieferadresse->kLieferadresse}{literal}',
                                        address: EAO.address,
                                        enderecometa: {
                                            ts: EAO.addressTimestamp,
                                            status: EAO.addressStatus,
                                            predictions: EAO.addressPredictions,
                                        }
                                    }
                                }
                            }).then( function(response) {
                                window.location.reload();
                            }).catch( function(error) {
                                console.log('Something went wrong.')
                            }).finally( function() {
                                EAO._awaits--;
                            });
                        });

                        EAO.onAfterAddressCheckSelected.push( function(EAO) {
                            EAO.waitForAllPopupsToClose().then(function() {
                                EAO.waitUntilReady().then( function() {
                                    if (window.EnderecoIntegrator && window.EnderecoIntegrator.globalSpace.reloadPage) {
                                        window.EnderecoIntegrator.globalSpace.reloadPage();
                                        window.EnderecoIntegrator.globalSpace.reloadPage = undefined;
                                    }
                                }).catch()
                            }).catch();
                            EAO._awaits++;
                            console.log("test");
                            EAO.util.axios({
                                method: 'post',
                                url: 'io.php?io=endereco_inner_request',
                                data: {
                                    method: 'editShippingAddress',
                                    params: {
                                        shippingAddressId: '{/literal}{$Lieferadresse->kLieferadresse}{literal}',
                                        address: EAO.address,
                                        enderecometa: {
                                            ts: EAO.addressTimestamp,
                                            status: EAO.addressStatus,
                                            predictions: EAO.addressPredictions,
                                        }
                                    }
                                }
                            }).then( function(response) {
                                window.location.reload();
                            }).catch( function(error) {
                                console.log('Something went wrong.')
                            }).finally( function() {
                                EAO._awaits--;
                            });
                        });
                    }).catch();
                }
            )
            {/literal}
        </script>
    {/if}
</div>
