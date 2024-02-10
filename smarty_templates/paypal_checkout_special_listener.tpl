<script>
    window.EnderecoIntegrator.onLoad.push(function() {
        const interval = setInterval( function() {
            if (window.EnderecoIntegrator.integratedObjects.billing_address_ams && window.EnderecoIntegrator.integratedObjects.billing_address_ams.active) {
                window.EnderecoIntegrator.integratedObjects.billing_address_ams._changed = true;
                clearInterval(interval);
            }
        }, 500);
    })
</script>