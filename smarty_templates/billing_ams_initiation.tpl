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
        },
        function(EAO) {
            if (!!document.querySelector('[name="ort"]')) {
                document.querySelector('[name="ort"]').addEventListener('endereco-blur', function(e) {
                    e.target.dispatchEvent(new CustomEvent('blur'));
                });
            }

            if (!!document.querySelector('[name="plz"]')) {
                document.querySelector('[name="plz"]').addEventListener('endereco-blur', function(e) {
                    e.target.dispatchEvent(new CustomEvent('blur'));
                });
            }
        }
    )

    enderecoInitPS(
        {
            salutation: '[name="anrede"]',
            firstName: '[name="vorname"]',
            lastName: '[name="nachname"]',
            title: '[name="titel"]'
        },
        {
            name: 'billing_person'
        }
    )

    enderecoInitES(
        {
            email: '#email[type="email"]'
        },
        {
            name: 'email',
            errorContainer: '#container-email-error-messages',
            errorInsertMode: 'afterbegin'
        }
    )

    {/literal}
</script>
