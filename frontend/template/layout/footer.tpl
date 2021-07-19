{block name="layout-footer-js" append}
    <script
            async
            defer
            src="{$ShopURL}/plugins/endereco_jtl5_client/frontend/js/endereco.min.js?ver=123"
            onload="{literal}if(typeof enderecoLoadAMSConfig === 'function'){enderecoLoadAMSConfig();}{/literal}"></script>
{/block}
