<script>
    window.EnderecoIntegrator.onLoad.push(function() {
        const interval = setInterval( function() {
            if (window.EnderecoIntegrator.integratedObjects.billing_address && window.EnderecoIntegrator.integratedObjects.billing_address.active) {
                window.EnderecoIntegrator.integratedObjects.billing_address._changed = true;
                clearInterval(interval);
            }
        }, 500);
    })
</script>