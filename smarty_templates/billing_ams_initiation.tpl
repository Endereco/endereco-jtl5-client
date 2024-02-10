<input type="hidden" name="enderecoamsts" value="{$endereco_amsts}" >
<input type="hidden" name="enderecoamsstatus" value="{$endereco_amsstatus|escape:'html'}" >
<input type="hidden" name="enderecoamspredictions" value="{$endereco_amspredictions|escape:'html'}">

<script>
    {literal}
    enderecoInitAMS(
        {
            countryCode: '[name="land"]',
            postalCode: '[name="plz"]',
            locality: '[name="ort"]',
            streetFull: '',
            streetName: '[name="strasse"]',
            buildingNumber: '[name="hausnummer"]',
            addressStatus: '[name="enderecoamsstatus"]',
            addressTimestamp: '[name="enderecoamsts"]',
            addressPredictions: '[name="enderecoamspredictions"]',
            additionalInfo: '[name="adresszusatz"]',
        },
        {
            addressType: 'billing_address',
            name: 'billing_address'
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
