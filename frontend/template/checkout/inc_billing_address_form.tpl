{block name='checkout-inc-billing-address-form' append}
    <input type="hidden" name="enderecoamsts" value="{$endereco_amsts}" >
    <input type="hidden" name="enderecoamsstatus" value="{$endereco_amsstatus|escape:'html'}" >
    <input type="hidden" name="enderecoamspredictions" value="{$endereco_amspredictions|escape:'html'}">

    <script>
        {literal}
        enderecoInitAMS(
            '',
            {
                addressType: 'billing_address',
                name: 'billing_address'
            }
        )
        enderecoInitPS(
            '',
            {
                name: 'billing_person'
            }
        )
        enderecoInitES(
            '',
            {
                name: 'billing_email',
                postfixCollection: {
                    email: '#panel-register-form [name="email"]'
                }
            }
        )
        {/literal}
    </script>
{/block}
