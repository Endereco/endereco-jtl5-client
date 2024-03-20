{literal}
<script>
    if (undefined === window.EnderecoIntegrator) {
        window.EnderecoIntegrator = {};
    }
    if (!window.EnderecoIntegrator.onLoad) {
        window.EnderecoIntegrator.onLoad = [];
    }

    function setBlurListener(EAO, elements) {
        for (const key in elements) {
            if (Object.prototype.hasOwnProperty.call(elements, key)) {
                const selector = elements[key];
                if (!selector) {
                    continue;
                }
                const element = document.querySelector(selector);

                if (element) {
                    element.addEventListener('endereco-blur', function(e) {
                        // Dispatch 'focus' and 'blur' events on the target element
                        e.target.dispatchEvent(new EAO.util.CustomEvent('focus', { bubbles: true, cancelable: true }));
                        e.target.dispatchEvent(new EAO.util.CustomEvent('blur', { bubbles: true, cancelable: true }));
                        e.target.dispatchEvent(new EAO.util.CustomEvent('focus', { bubbles: true, cancelable: true }));
                    });
                }
            }
        }
    }
    function enderecoInitAMS(prefix, config, cb = undefined) {
        if (undefined !== window.EnderecoIntegrator.initAMS) {
            const $EAO = window.EnderecoIntegrator.initAMS(prefix, config);
            setBlurListener($EAO, prefix);
            if (cb) {
                cb($EAO);
            }
        } else {
            window.EnderecoIntegrator.onLoad.push( function() {
                const $EAO = window.EnderecoIntegrator.initAMS(prefix, config);
                setBlurListener($EAO, prefix);
                if (cb) {
                    cb($EAO);
                }
            });
        }
    }
    function enderecoInitES(prefix, config) {
        if (undefined !== window.EnderecoIntegrator.initEmailServices) {
            window.EnderecoIntegrator.initEmailServices(prefix, config);
        } else {
            window.EnderecoIntegrator.onLoad.push( function() {
                window.EnderecoIntegrator.initEmailServices(prefix, config);
            });
        }
    }
    function enderecoInitPS(prefix, config) {
        if (undefined !== window.EnderecoIntegrator.initEmailServices) {
            const $EPO = window.EnderecoIntegrator.initPersonServices(prefix, config);
            setBlurListener($EPO, prefix);
        } else {
            window.EnderecoIntegrator.onLoad.push( function() {
                const $EPO = window.EnderecoIntegrator.initPersonServices(prefix, config);
                setBlurListener($EPO, prefix);
            });
        }
    }
    function enderecoLoadAMSConfig() {
        {/literal}
        if (!('{$endereco_plugin_config->getValue('endereco_jtl5_client_api_key')}')) {
            console.log('No API Key specified. Abort.');
            return;
        }
        window.EnderecoIntegrator.themeName = '{$endereco_theme_name}';
        window.EnderecoIntegrator.defaultCountrySelect = false;
        window.EnderecoIntegrator.config.apiUrl = '{$endereco_api_url}';
        window.EnderecoIntegrator.config.apiKey = '{$endereco_plugin_config->getValue('endereco_jtl5_client_api_key')}';
        window.EnderecoIntegrator.config.remoteApiUrl = '{$endereco_plugin_config->getValue('endereco_jtl5_client_remote_url')}';
        window.EnderecoIntegrator.config.agentName = '{$endereco_agent_info}';
        window.EnderecoIntegrator.config.showDebugInfo = ('on' === '{$endereco_plugin_config->getValue('endereco_jtl5_client_show_debug_info')}');
        window.EnderecoIntegrator.config.trigger.onblur = ('on' === '{$endereco_plugin_config->getValue('endereco_jtl5_client_onblur_trigger')}');
        window.EnderecoIntegrator.config.trigger.onsubmit = ('on' === '{$endereco_plugin_config->getValue('endereco_jtl5_client_onsubmit_trigger')}');
        window.EnderecoIntegrator.config.ux.smartFill = false;
        window.EnderecoIntegrator.config.ux.checkExisting = ('on' === '{$endereco_plugin_config->getValue('endereco_jtl5_client_check_existing')}');
        window.EnderecoIntegrator.config.ux.resumeSubmit = ('on' === '{$endereco_plugin_config->getValue('endereco_jtl5_client_resume_submit')}');
        window.EnderecoIntegrator.config.ux.useStandardCss = ('on' === '{$endereco_plugin_config->getValue('endereco_jtl5_client_use_standart_css')}');
        window.EnderecoIntegrator.config.ux.showEmailStatus = ('on' === '{$endereco_plugin_config->getValue('endereco_jtl5_client_show_email_status')}');
        window.EnderecoIntegrator.config.ux.allowCloseModal = ('on' === '{$endereco_plugin_config->getValue('endereco_jtl5_client_allow_close')}');
        window.EnderecoIntegrator.config.ux.confirmWithCheckbox = ('on' === '{$endereco_plugin_config->getValue('endereco_jtl5_client_confirm_with_checkbox')}');
        window.EnderecoIntegrator.config.ux.changeFieldsOrder = true;
        window.EnderecoIntegrator.countryMappingUrl = '';
        window.EnderecoIntegrator.config.templates.primaryButtonClasses = 'btn btn-primary btn-lg';
        window.EnderecoIntegrator.config.templates.secondaryButtonClasses = 'btn btn-secondary btn-lg';
        {literal}
        window.EnderecoIntegrator.config.texts = {
            {/literal}
            popUpHeadline: '{$endereco_locales->getTranslation('endereco_jtl5_client_popup_headline')|escape}',
            popUpSubline: '{$endereco_locales->getTranslation('endereco_jtl5_client_popup_subline')|escape}',
            mistakeNoPredictionSubline: '{$endereco_locales->getTranslation('endereco_jtl5_client_mistake_no_predictions_subline')|escape}',
            notFoundSubline: '{$endereco_locales->getTranslation('endereco_jtl5_client_popup_subline_not_found')|escape}',
            confirmMyAddressCheckbox: '{$endereco_locales->getTranslation('endereco_jtl5_client_confirm_my_address_checkout')|escape}',
            yourInput: '{$endereco_locales->getTranslation('endereco_jtl5_client_your_input')|escape}',
            editYourInput: '{$endereco_locales->getTranslation('endereco_jtl5_client_edit_input')|escape}',
            ourSuggestions: '{$endereco_locales->getTranslation('endereco_jtl5_client_our_suggestions')|escape}',
            useSelected: '{$endereco_locales->getTranslation('endereco_jtl5_client_use_selected')|escape}',
            confirmAddress: '{$endereco_locales->getTranslation('endereco_jtl5_client_confirm_address')|escape}',
            editAddress: '{$endereco_locales->getTranslation('endereco_jtl5_client_edit_address')|escape}',
            warningText: '{$endereco_locales->getTranslation('endereco_jtl5_client_faulty_address_warning')|escape}',
            {literal}
            popupHeadlines: {
                {/literal}
                general_address: '{$endereco_locales->getTranslation('endereco_jtl5_client_general_address')|escape}',
                billing_address: '{$endereco_locales->getTranslation('endereco_jtl5_client_billing_address')|escape}',
                shipping_address: '{$endereco_locales->getTranslation('endereco_jtl5_client_shipping_address')|escape}',
                {literal}
            },
            statuses: {
                {/literal}
                email_not_correct: '{$endereco_locales->getTranslation('endereco_jtl5_client_status_email_not_correct')|escape}',
                email_cant_receive: '{$endereco_locales->getTranslation('endereco_jtl5_client_status_email_cant_receive')|escape}',
                email_syntax_error: '{$endereco_locales->getTranslation('endereco_jtl5_client_status_email_syntax_error')|escape}',
                email_no_mx: '{$endereco_locales->getTranslation('endereco_jtl5_client_status_email_no_mx')|escape}',
                building_number_is_missing: '{$endereco_locales->getTranslation('endereco_jtl5_client_status_error_building_number_is_missing')|escape}',
                building_number_not_found: '{$endereco_locales->getTranslation('endereco_jtl5_client_status_error_building_number_not_found')|escape}',
                street_name_needs_correction: '{$endereco_locales->getTranslation('endereco_jtl5_client_status_error_street_name_needs_correction')|escape}',
                locality_needs_correction: '{$endereco_locales->getTranslation('endereco_jtl5_client_status_error_locality_needs_correction')|escape}',
                postal_code_needs_correction: '{$endereco_locales->getTranslation('endereco_jtl5_client_status_error_postal_code_needs_correction')|escape}',
                country_code_needs_correction: '{$endereco_locales->getTranslation('endereco_jtl5_client_status_error_country_code_needs_correction')|escape}'
                {literal}
            }
        };
        window.EnderecoIntegrator.activeServices = {
            {/literal}
            ams: ('on' === '{$endereco_plugin_config->getValue('endereco_jtl5_client_ams_active')}'),
            emailService: ('on' === '{$endereco_plugin_config->getValue('endereco_jtl5_client_es_active')}'),
            personService: ('on' === '{$endereco_plugin_config->getValue('endereco_jtl5_client_ps_active')}')
            {literal}
        }
        {/literal}
        window.EnderecoIntegrator.countryCodeToNameMapping = JSON.parse('{$endereco_jtl5_client_country_mapping}');
        {literal}
        // Execute all function that have been called throughout the page.
        window.EnderecoIntegrator.onLoad.forEach( function(callback) {
            callback();
        });
        //window.EnderecoIntegrator.countryCodeToNameMapping = {};
        window.EnderecoIntegrator.ready = true;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const sampleRadio = document.querySelector('input[type="radio"][name="kLieferadresse"]');

        if (!sampleRadio) {
            return;
        }

        const form = sampleRadio.closest('form');
        
        if (!form) {
            return;
        }

        function checkAndSetAddressStatus() {
            const radios = form.querySelectorAll('input[type="radio"][name="kLieferadresse"]');
            let isAnyChecked = false;

            for (const radio of radios) {
                if (radio.checked && parseInt(radio.value) > 0) {
                    isAnyChecked = true;
                    break;
                }
            }

            if (
                isAnyChecked && 
                window.EnderecoIntegrator && 
                window.EnderecoIntegrator.integratedObjects.shipping_address_ams
            ) {
                window.EnderecoIntegrator.integratedObjects.shipping_address_ams.addressStatus = [];
            }
        }

        checkAndSetAddressStatus();

        form.addEventListener('change', function(event) {
            if (event.target.type === 'radio' && event.target.name === 'kLieferadresse') {
                checkAndSetAddressStatus();
            }
        });
    });
</script>
{/literal}
