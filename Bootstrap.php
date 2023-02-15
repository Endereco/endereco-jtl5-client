<?php
namespace Plugin\endereco_jtl5_client;

use JTL\Checkout\Bestellung;
use JTL\Checkout\Rechnungsadresse;
use JTL\Link\LinkInterface;
use JTL\Checkout\Lieferadresse;
use JTL\Helpers\Text;
use JTL\Customer\Customer;
use JTL\Customer\DataHistory;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use JTL\phpQuery\phpQuery;
use JTL\Events\Dispatcher;
use JTL\Template\Model;
use JTL\Session\Frontend;
use Plugin\endereco_jtl5_client\src\Helper\EnderecoService;

class Bootstrap extends Bootstrapper
{
    private static $_bootStrIncluded = false;

    /**
     * @inheritdoc
     */
    public function boot(Dispatcher $dispatcher)
    {
        parent::boot($dispatcher);

        if (Shop::isFrontend() === false) {
            return;
        }

        $plugin = $this->getPlugin();
        $EnderecoService = new EnderecoService($plugin);

        $dispatcher->listen('shop.hook.' . \HOOK_SMARTY_OUTPUTFILTER, static function (array $args) use ($plugin) {
            if (!empty($plugin->getConfig()->getValue('endereco_jtl5_client_api_key'))) {

                // Set variables.
                $smarty = $args['smarty'];

                // Add init calls to billing form.
                if (phpQuery::pq('[name="land"]')->length && phpQuery::pq('[name="strasse"]')->length) {
                    if (!empty($_SESSION['EnderecoBillingAddressMeta'])) {
                        // Load to smarty.
                        $smarty->assign('endereco_amsts', $_SESSION['EnderecoBillingAddressMeta']['enderecoamsts'])
                            ->assign('endereco_amsstatus', $_SESSION['EnderecoBillingAddressMeta']['enderecoamsstatus'])
                            ->assign('endereco_amspredictions', $_SESSION['EnderecoBillingAddressMeta']['enderecoamspredictions']);
                    }
                    $file = __DIR__ . '/smarty_templates/billing_ams_initiation.tpl';
                    $html = $smarty->fetch($file);

                    if (1 === phpQuery::pq('[name="strasse"]')->length) {
                        phpQuery::pq('[name="strasse"]')->after($html);
                    }

                    self::_includeBoots($smarty, $plugin);
                }

                // Add init calls to shipping.
                if (phpQuery::pq('[name="register[shipping_address][land]"]')->length && phpQuery::pq('[name="register[shipping_address][strasse]"]')->length) {
                    if (!empty($_SESSION['EnderecoShippingAddressMeta'])) {
                        // Load to smarty.
                        $smarty->assign('endereco_delivery_amsts', $_SESSION['EnderecoShippingAddressMeta']['enderecoamsts'])
                            ->assign('endereco_delivery_amsstatus', $_SESSION['EnderecoShippingAddressMeta']['enderecoamsstatus'])
                            ->assign('endereco_delivery_amspredictions', $_SESSION['EnderecoShippingAddressMeta']['enderecoamspredictions']);
                    }
                    $file = __DIR__ . '/smarty_templates/shipping_ams_initiation.tpl';
                    $html = $smarty->fetch($file);

                    if (1 === phpQuery::pq('[name="register[shipping_address][strasse]"]')->length) {
                        phpQuery::pq('[name="register[shipping_address][strasse]"]')->after($html);
                    }

                    self::_includeBoots($smarty, $plugin);
                }

                // Add fake form in checkout.
                global $step;
                if (PAGE_BESTELLVORGANG === Shop::getPageType()
                    && 'Bestaetigung' === $step
                ) {
                    // Set smarty values for billing.
                    $smarty->assign('endereco_billing_countrycode', $_SESSION['Kunde']->cLand)
                        ->assign('endereco_billing_postal_code', $_SESSION['Kunde']->cPLZ)
                        ->assign('endereco_billing_locality', $_SESSION['Kunde']->cOrt)
                        ->assign('endereco_billing_street_name', $_SESSION['Kunde']->cStrasse)
                        ->assign('endereco_billing_building_number', $_SESSION['Kunde']->cHausnummer)
                        ->assign('endereco_billing_addinfo', $_SESSION['Kunde']->cAdressZusatz)
                        ->assign('endereco_billing_ts', $_SESSION['EnderecoBillingAddressMeta']['enderecoamsts'])
                        ->assign('endereco_billing_status', $_SESSION['EnderecoBillingAddressMeta']['enderecoamsstatus'])
                        ->assign('endereco_billing_predictions', $_SESSION['EnderecoBillingAddressMeta']['enderecoamspredictions']);

                    $smarty->assign('endereco_shipping_countrycode', $_SESSION['Lieferadresse']->cLand)
                        ->assign('endereco_shipping_postal_code', $_SESSION['Lieferadresse']->cPLZ)
                        ->assign('endereco_shipping_locality', $_SESSION['Lieferadresse']->cOrt)
                        ->assign('endereco_shipping_street_name', $_SESSION['Lieferadresse']->cStrasse)
                        ->assign('endereco_shipping_building_number', $_SESSION['Lieferadresse']->cHausnummer)
                        ->assign('endereco_shipping_addinfo', $_SESSION['Lieferadresse']->cAdressZusatz)
                        ->assign('endereco_shipping_ts', $_SESSION['EnderecoShippingAddressMeta']['enderecoamsts'])
                        ->assign('endereco_shipping_status', $_SESSION['EnderecoShippingAddressMeta']['enderecoamsstatus'])
                        ->assign('endereco_shipping_predictions', $_SESSION['EnderecoShippingAddressMeta']['enderecoamspredictions']);

                    $file = __DIR__ . '/smarty_templates/kafe_address.tpl';
                    $html = $smarty->fetch($file);
                    phpQuery::pq('body')->prepend($html);

                    self::_includeBoots($smarty, $plugin);
                }
            }
        });

        // Set config values.
        $dispatcher->listen('shop.hook.' . \HOOK_SMARTY_INC, static function (array $args) use ($plugin) {

        });

        // Registration hook.
        $dispatcher->listen('shop.hook.' . \HOOK_REGISTRIEREN_PAGE, static function (array $args) use ($EnderecoService) {
            unset($_SESSION['EnderecoBillingAddressMeta']);
            unset($_SESSION['EnderecoShippingAddressMeta']);
        });

        // Registration hook.
        $dispatcher->listen('shop.hook.' . \HOOK_REGISTRIEREN_PAGE_REGISTRIEREN_PLAUSI, static function (array $args) use ($EnderecoService) {
            if ('POST' === $_SERVER['REQUEST_METHOD']) {
                $EnderecoService->doAccountings(
                    $EnderecoService->findSessions()
                );

                // Save address check result.
                $customer = Frontend::getCustomer();
                $ts = $_POST['enderecoamsts'];
                $statuses = $_POST['enderecoamsstatus'];
                $predictions = $_POST['enderecoamspredictions'];
                if (0 < $customer->kKunde && !empty($statuses)) {
                    // Save tams.
                    Shop::Container()->getDB()->queryPrepared(
                        "INSERT INTO `xplugin_endereco_jtl5_client_tams` 
                        (`kKunde`, `kRechnungsadresse`, `kLieferadresse`, `enderecoamsts`, `enderecoamsstatus`, `enderecoamspredictions`, `last_change_at`)
                        VALUES 
                        (:kKunde, NULL, NULL, :enderecoamsts, :enderecoamsstatus, :enderecoamspredictions, now())
                        ON DUPLICATE KEY UPDATE    
                        `kKunde`=:kKunde2, `enderecoamsts`=:enderecoamsts2, `enderecoamsstatus`=:enderecoamsstatus2, `enderecoamspredictions`=:enderecoamspredictions2, `last_change_at`=now()
                        ",
                        [
                            ':kKunde' => $customer->kKunde,
                            ':enderecoamsts' => $ts,
                            ':enderecoamsstatus' => $statuses,
                            ':enderecoamspredictions' => $predictions,
                            ':kKunde2' => $customer->kKunde,
                            ':enderecoamsts2' => $ts,
                            ':enderecoamsstatus2' => $statuses,
                            ':enderecoamspredictions2' => $predictions,
                        ],
                        1
                    );
                }

                if (!empty($_POST['enderecoamsstatus'])) {
                    $_SESSION['EnderecoBillingAddressMeta'] = [
                        'enderecoamsts' => $_POST['enderecoamsts'],
                        'enderecoamsstatus' => $_POST['enderecoamsstatus'],
                        'enderecoamspredictions' => $_POST['enderecoamspredictions'],
                    ];
                } else {
                    unset($_SESSION['EnderecoBillingAddressMeta']);
                }

                // Save delivery address to session.
                if (!empty($_POST['enderecodeliveryamsstatus'])) {
                    $_SESSION['EnderecoShippingAddressMeta'] = [
                        'enderecoamsts' => $_POST['enderecodeliveryamsts'],
                        'enderecoamsstatus' => $_POST['enderecodeliveryamsstatus'],
                        'enderecoamspredictions' => $_POST['enderecodeliveryamspredictions'],
                    ];
                } else {
                    unset($_SESSION['EnderecoShippingAddressMeta']);
                }
            }
        });

        $dispatcher->listen('shop.hook.' . \HOOK_BESTELLVORGANG_INC_UNREGISTRIERTBESTELLEN_PLAUSI, static function (array $args) use ($EnderecoService) {
            if ('POST' === $_SERVER['REQUEST_METHOD']) {
                $EnderecoService->doAccountings(
                    $EnderecoService->findSessions()
                );

                // Save tams to session.
                // Save tams for billing address.
                $_SESSION['EnderecoBillingAddressMeta'] = [
                    'enderecoamsts' => $_POST['enderecoamsts'],
                    'enderecoamsstatus' => $_POST['enderecoamsstatus'],
                    'enderecoamspredictions' => $_POST['enderecoamspredictions'],
                ];

                // Save tams for shipping address.
                if (!empty($_POST['enderecodeliveryamsstatus'])) {
                    // Save tams for shipping address.
                    $_SESSION['EnderecoShippingAddressMeta'] = [
                        'enderecoamsts' => $_POST['enderecodeliveryamsts'],
                        'enderecoamsstatus' => $_POST['enderecodeliveryamsstatus'],
                        'enderecoamspredictions' => $_POST['enderecodeliveryamspredictions'],
                    ];
                }
            }
        });

        /**
         * This hook is executed when the customer klicks on "Edit Address", which means "Save Address", in his account.
         * First we check if there is any accountable sessions in the post data.
         * Then we save metadata of the address check in the database.
         */
        $dispatcher->listen('shop.hook.' . \HOOK_JTL_PAGE, static function (array $args) use ($EnderecoService) {
            if ('GET' === $_SERVER['REQUEST_METHOD'] && !empty($_GET['editRechnungsadresse'])) {


                // If session variable is not set, load it.
                if (empty($_SESSION['EnderecoBillingAddressMeta'])) {
                    $customer = Frontend::getCustomer();
                    if ($customer->kKunde) {
                        $addressMeta = Shop::Container()->getDB()->queryPrepared(
                            "SELECT `xplugin_endereco_jtl5_client_tams`.*
                    FROM `xplugin_endereco_jtl5_client_tams`
                    WHERE `kKunde` = :id",
                            [':id' => $customer->kKunde],
                            1
                        );
                        $_SESSION['EnderecoBillingAddressMeta'] = [
                            'enderecoamsts' => $addressMeta->enderecoamsts,
                            'enderecoamsstatus' => $addressMeta->enderecoamsstatus,
                            'enderecoamspredictions' =>  $addressMeta->enderecoamspredictions,
                        ];
                    }
                }
            }

            if ('POST' === $_SERVER['REQUEST_METHOD'] && !empty($_GET['editRechnungsadresse'])) {
                // Check if there are some doAccountings.
                $EnderecoService->doAccountings(
                    $EnderecoService->findSessions()
                );

                // Save address check result.
                $customer = Frontend::getCustomer();
                $ts = $_POST['enderecoamsts'];
                $statuses = $_POST['enderecoamsstatus'];
                $predictions = $_POST['enderecoamspredictions'];
                if (0 < $customer->kKunde && !empty($statuses)) {
                    // Save tams.
                    Shop::Container()->getDB()->queryPrepared(
                        "INSERT INTO `xplugin_endereco_jtl5_client_tams` 
                        (`kKunde`, `kRechnungsadresse`, `kLieferadresse`, `enderecoamsts`, `enderecoamsstatus`, `enderecoamspredictions`, `last_change_at`)
                        VALUES 
                        (:kKunde, NULL, NULL, :enderecoamsts, :enderecoamsstatus, :enderecoamspredictions, now())
                        ON DUPLICATE KEY UPDATE    
                        `kKunde`=:kKunde2, `enderecoamsts`=:enderecoamsts2, `enderecoamsstatus`=:enderecoamsstatus2, `enderecoamspredictions`=:enderecoamspredictions2, `last_change_at`=now()
                        ",
                        [
                            ':kKunde' => $customer->kKunde,
                            ':enderecoamsts' => $ts,
                            ':enderecoamsstatus' => $statuses,
                            ':enderecoamspredictions' => $predictions,
                            ':kKunde2' => $customer->kKunde,
                            ':enderecoamsts2' => $ts,
                            ':enderecoamsstatus2' => $statuses,
                            ':enderecoamspredictions2' => $predictions,
                        ],
                        1
                    );
                }

                // Load to session.
                $_SESSION['EnderecoBillingAddressMeta'] = [
                    'enderecoamsts' => $ts,
                    'enderecoamsstatus' => $statuses,
                    'enderecoamspredictions' => $predictions,
                ];
            }
        });

        $dispatcher->listen('shop.hook.' . \HOOK_BESTELLVORGANG_PAGE_STEPBESTAETIGUNG, static function (array $args) use ($plugin, $EnderecoService) {
            $config = $plugin->getConfig();
            $checkExisting = 'on' === $config->getOption('endereco_jtl5_client_check_existing')->value;
            $checkPayPalExpress = 'on' === $config->getOption('endereco_jtl5_client_check_paypal_express')->value;

            // Load tams.
            if (empty($_SESSION['EnderecoBillingAddressMeta']) || empty($_SESSION['EnderecoBillingAddressMeta']['enderecoamsstatus'])) {
                // If customer, try to load from db.
                $customer = Frontend::getCustomer();
                if ($customer->kKunde) {
                    $addressMeta = Shop::Container()->getDB()->queryPrepared(
                        "SELECT `xplugin_endereco_jtl5_client_tams`.*
                    FROM `xplugin_endereco_jtl5_client_tams`
                    WHERE `kKunde` = :id",
                        [':id' => $customer->kKunde],
                        1
                    );
                    if (!empty($addressMeta) && !empty($addressMeta->enderecoamsstatus)) {
                        $_SESSION['EnderecoBillingAddressMeta'] = [
                            'enderecoamsts' => $addressMeta->enderecoamsts,
                            'enderecoamsstatus' => $addressMeta->enderecoamsstatus,
                            'enderecoamspredictions' => $addressMeta->enderecoamspredictions,
                        ];
                    }
                }

                // If status is still empty -> check address.
                if (empty($_SESSION['EnderecoBillingAddressMeta']) || empty($_SESSION['EnderecoBillingAddressMeta']['enderecoamsstatus'])) {

                    $checkResults = [];
                    if ($checkExisting && !empty($customer->kKunde)) {
                        $checkResults = $EnderecoService->checkAddress($customer);
                    } elseif((strpos($_SESSION['Zahlungsart']->cModulId, 'paypalexpress') !== false) && $checkPayPalExpress) {
                        $checkResults = $EnderecoService->checkAddress($_SESSION['Kunde']);
                    }

                    if (!empty($checkResults)) {
                        $_SESSION['EnderecoBillingAddressMeta'] = [
                            'enderecoamsts' => $checkResults->enderecoamsts,
                            'enderecoamsstatus' => $checkResults->enderecoamsstatus,
                            'enderecoamspredictions' => $checkResults->enderecoamspredictions,
                        ];
                    }
                }
            }

            $_SESSION['EnderecoIncludeFakeAddress'] = true;

            if (!empty($_SESSION['Lieferadresse']->kLieferadresse)) {
                // If shipping is different.
                if (empty($_SESSION['EnderecoShippingAddressMeta']) || empty($_SESSION['EnderecoShippingAddressMeta']['enderecoamsstatus'])) {
                    // If customer, try to load from db.
                    $addressMeta = Shop::Container()->getDB()->queryPrepared(
                        "SELECT `xplugin_endereco_jtl5_client_tams`.*
                    FROM `xplugin_endereco_jtl5_client_tams`
                    WHERE `kLieferadresse` = :id",
                        [':id' => $_SESSION['Lieferadresse']->kLieferadresse],
                        1
                    );
                    if (!empty($addressMeta) && !empty($addressMeta->enderecoamsstatus)) {
                        $_SESSION['EnderecoShippingAddressMeta'] = [
                            'enderecoamsts' => $addressMeta->enderecoamsts,
                            'enderecoamsstatus' => $addressMeta->enderecoamsstatus,
                            'enderecoamspredictions' => $addressMeta->enderecoamspredictions,
                        ];
                    } else {
                        $checkResults = [];
                        if ($checkExisting) {
                            $checkResults = $EnderecoService->checkAddress($_SESSION['Lieferadresse']);
                        }

                        if (!empty($checkResults)) {
                            $_SESSION['EnderecoShippingAddressMeta'] = [
                                'enderecoamsts' => $checkResults->enderecoamsts,
                                'enderecoamsstatus' => $checkResults->enderecoamsstatus,
                                'enderecoamspredictions' => $checkResults->enderecoamspredictions,
                            ];
                        }
                    }
                }
            }
        });

        $dispatcher->listen('shop.hook.' . \HOOK_BESTELLVORGANG_PAGE, static function (array $args) use ($EnderecoService) {
            if (!empty($_GET['editRechnungsadresse'])) {

                // If session variable is not set, load it.
                if (empty($_SESSION['EnderecoBillingAddressMeta'])) {
                    $customer = Frontend::getCustomer();
                    $addressMeta = Shop::Container()->getDB()->queryPrepared(
                        "SELECT `xplugin_endereco_jtl5_client_tams`.*
                    FROM `xplugin_endereco_jtl5_client_tams`
                    WHERE `kKunde` = :id",
                        [':id' => $customer->kKunde],
                        1
                    );

                }

                if (!empty($addressMeta) && !empty($addressMeta->enderecoamsstatus)) {
                    $_SESSION['EnderecoBillingAddressMeta'] = [
                        'enderecoamsts' => $addressMeta->enderecoamsts,
                        'enderecoamsstatus' => $addressMeta->enderecoamsstatus,
                        'enderecoamspredictions' => $addressMeta->enderecoamspredictions,
                    ];
                }
            }
        });

        // IO Listener
        $dispatcher->listen('shop.hook.' . \HOOK_IO_HANDLE_REQUEST, static function (array $args) use ($plugin) {
            if (('endereco_request' === $_REQUEST['io'])) {
                if ('GET' === $_SERVER['REQUEST_METHOD']) {
                    die('We expect a POST request here.');
                }

                $agent_info  = "Endereco JTL5 Client v" . $plugin->getMeta()->getVersion();
                $post_data   = json_decode(file_get_contents('php://input'), true);
                $api_key     = trim($_SERVER['HTTP_X_AUTH_KEY']);
                $data_string = json_encode($post_data);
                $ch          = curl_init(trim($_SERVER['HTTP_X_REMOTE_API_URL']));

                if ($_SERVER['HTTP_X_TRANSACTION_ID']) {
                    $tid = $_SERVER['HTTP_X_TRANSACTION_ID'];
                } else {
                    $tid = 'not_set';
                }

                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
                curl_setopt($ch, CURLOPT_TIMEOUT, 6);
                curl_setopt(
                    $ch,
                    CURLOPT_HTTPHEADER,
                    array(
                        'Content-Type: application/json',
                        'X-Auth-Key: ' . $api_key,
                        'X-Transaction-Id: ' . $tid,
                        'X-Agent: ' . $agent_info,
                        'X-Transaction-Referer: ' . $_SERVER['HTTP_X_TRANSACTION_REFERER'],
                        'Content-Length: ' . strlen($data_string))
                );

                $result = curl_exec($ch);
                curl_close($ch);

                header('Content-Type: application/json');
                echo $result;
                exit();
            }

            if (('endereco_inner_request' === $_REQUEST['io'])) {

                if ('GET' === $_SERVER['REQUEST_METHOD']) {
                    die('We expect a POST request here.');
                }

                $post_data   = json_decode(file_get_contents('php://input'), true);

                if ('editBillingAddress' === $post_data['method']) {

                    // Update in DB.
                    if (!empty($post_data['params']['customerId'])) {
                        // Change customer address.
                        $Kunde = new Customer(intval($post_data['params']['customerId']));
                        $Kunde->cStrasse      = (isset($post_data['params']['address']['streetName'])) ? Text::filterXSS($post_data['params']['address']['streetName']) : $Kunde->cStrasse;
                        $Kunde->cHausnummer   = (isset($post_data['params']['address']['buildingNumber'])) ? Text::htmlentities(Text::filterXSS($post_data['params']['address']['buildingNumber'])) : $Kunde->cHausnummer;
                        $Kunde->cAdressZusatz = (isset($post_data['params']['address']['additionalInfo'])) ? Text::htmlentities(Text::filterXSS($post_data['params']['address']['additionalInfo'])) : $Kunde->cAdressZusatz;
                        $Kunde->cPLZ          = (isset($post_data['params']['address']['postalCode'])) ? Text::htmlentities(Text::filterXSS($post_data['params']['address']['postalCode'])) : $Kunde->cPLZ;
                        $Kunde->cOrt          = (isset($post_data['params']['address']['locality'])) ? Text::filterXSS($post_data['params']['address']['locality']) : $Kunde->cOrt;
                        $Kunde->cLand         = (isset($post_data['params']['address']['countryCode'])) ? strtoupper(Text::htmlentities(Text::filterXSS($post_data['params']['address']['countryCode']))) : $Kunde->cLand;
                        $Kunde->updateInDB();

                        DataHistory::saveHistory($_SESSION['Kunde'], $Kunde, DataHistory::QUELLE_BESTELLUNG);
                        $_SESSION['Kunde'] = new Customer($Kunde->kKunde);

                        // Save meta.
                        Shop::Container()->getDB()->queryPrepared(
                            "INSERT INTO `xplugin_endereco_jtl5_client_tams` 
                    (`kKunde`, `kRechnungsadresse`, `kLieferadresse`, `enderecoamsts`, `enderecoamsstatus`, `enderecoamspredictions`, `last_change_at`)
                 VALUES 
                    (:kKunde, NULL,  NULL, :enderecoamsts, :enderecoamsstatus, :enderecoamspredictions, now())
                ON DUPLICATE KEY UPDATE    
                   `kKunde`=:kKunde2, `enderecoamsts`=:enderecoamsts2, `enderecoamsstatus`=:enderecoamsstatus2, `enderecoamspredictions`=:enderecoamspredictions2, `last_change_at`=now()
                ",
                            [
                                ':kKunde' => intval($post_data['params']['customerId']),
                                ':enderecoamsts' => Text::filterXSS($post_data['params']['enderecometa']['ts']),
                                ':enderecoamsstatus' => implode(',', Text::filterXSS($post_data['params']['enderecometa']['status'])),
                                ':enderecoamspredictions' => json_encode(Text::filterXSS($post_data['params']['enderecometa']['predictions'])),
                                ':kKunde2' => intval($post_data['params']['customerId']),
                                ':enderecoamsts2' => Text::filterXSS($post_data['params']['enderecometa']['ts']),
                                ':enderecoamsstatus2' => implode(',', Text::filterXSS($post_data['params']['enderecometa']['status'])),
                                ':enderecoamspredictions2' => json_encode(Text::filterXSS($post_data['params']['enderecometa']['predictions'])),
                            ],
                            1
                        );
                    }

                    // Update in session.
                    if (!empty($_SESSION['Kunde'])) {
                        $_SESSION['Kunde']->cStrasse      = (isset($post_data['params']['address']['streetName'])) ? Text::filterXSS($post_data['params']['address']['streetName']) : $Kunde->cStrasse;
                        $_SESSION['Kunde']->cHausnummer   = (isset($post_data['params']['address']['buildingNumber'])) ? Text::htmlentities(Text::filterXSS($post_data['params']['address']['buildingNumber'])) : $Kunde->cHausnummer;
                        $_SESSION['Kunde']->cAdressZusatz = (isset($post_data['params']['address']['additionalInfo'])) ? Text::htmlentities(Text::filterXSS($post_data['params']['address']['additionalInfo'])) : $Kunde->cAdressZusatz;
                        $_SESSION['Kunde']->cPLZ          = (isset($post_data['params']['address']['postalCode'])) ? Text::htmlentities(Text::filterXSS($post_data['params']['address']['postalCode'])) : $Kunde->cPLZ;
                        $_SESSION['Kunde']->cOrt          = (isset($post_data['params']['address']['locality'])) ? Text::filterXSS($post_data['params']['address']['locality']) : $Kunde->cOrt;
                        $_SESSION['Kunde']->cLand         = (isset($post_data['params']['address']['countryCode'])) ? strtoupper(Text::htmlentities(Text::filterXSS($post_data['params']['address']['countryCode']))) : $Kunde->cLand;
                    }

                    $_SESSION['EnderecoBillingAddressMeta']['enderecoamsstatus'] = implode(',', $post_data['params']['enderecometa']['status']);
                    $_SESSION['EnderecoBillingAddressMeta']['enderecoamspredictions'] = json_encode($post_data['params']['enderecometa']['predictions']);
                    $_SESSION['EnderecoBillingAddressMeta']['enderecoamsts'] = $post_data['params']['enderecometa']['ts'];

                    if ($post_data['params']['copyShippingToo']) {
                        $_SESSION['Lieferadresse']->cStrasse      = (isset($post_data['params']['address']['streetName'])) ? Text::filterXSS($post_data['params']['address']['streetName']) : $Kunde->cStrasse;
                        $_SESSION['Lieferadresse']->cHausnummer   = (isset($post_data['params']['address']['buildingNumber'])) ? Text::htmlentities(Text::filterXSS($post_data['params']['address']['buildingNumber'])) : $Kunde->cHausnummer;
                        $_SESSION['Lieferadresse']->cAdressZusatz = (isset($post_data['params']['address']['additionalInfo'])) ? Text::htmlentities(Text::filterXSS($post_data['params']['address']['additionalInfo'])) : $Kunde->cAdressZusatz;
                        $_SESSION['Lieferadresse']->cPLZ          = (isset($post_data['params']['address']['postalCode'])) ? Text::htmlentities(Text::filterXSS($post_data['params']['address']['postalCode'])) : $Kunde->cPLZ;
                        $_SESSION['Lieferadresse']->cOrt          = (isset($post_data['params']['address']['locality'])) ? Text::filterXSS($post_data['params']['address']['locality']) : $Kunde->cOrt;
                        $_SESSION['Lieferadresse']->cLand         = (isset($post_data['params']['address']['countryCode'])) ? strtoupper(Text::htmlentities(Text::filterXSS($post_data['params']['address']['countryCode']))) : $Kunde->cLand;

                        $_SESSION['EnderecoShippingAddressMeta']['enderecoamsstatus'] = implode(',', $post_data['params']['enderecometa']['status']);
                        $_SESSION['EnderecoShippingAddressMeta']['enderecoamspredictions'] = json_encode($post_data['params']['enderecometa']['predictions']);
                        $_SESSION['EnderecoShippingAddressMeta']['enderecoamsts'] = $post_data['params']['enderecometa']['ts'];
                    }
                }

                if ('editShippingAddress' === $post_data['method']) {

                    if (!empty($post_data['params']['shippingAddressId'])) {
                        // Change customer address.
                        $originalId = intval($post_data['params']['shippingAddressId']);
                        $Lieferadresse = new Lieferadresse($originalId);

                        // Get all shipping addresses.
                        $tlieferadressen = Shop::Container()->getDB()->queryPrepared(
                            "SELECT `tlieferadresse`.*
            FROM `tlieferadresse`
            WHERE `tlieferadresse`.`kKunde` = :kKunde",
                            [':kKunde' => $_SESSION['Kunde']->kKunde],
                            9
                        );
                        $sameAddress = null;

                        foreach ($tlieferadressen as $delivery_address) {
                            $tmp_address = [
                                'cVorname' => trim($delivery_address['cVorname']),
                                'cNachname' => trim(entschluesselXTEA($delivery_address['cNachname'])),
                                'cStrasse' => trim(entschluesselXTEA($delivery_address['cStrasse'])),
                                'cHausnummer' => trim($delivery_address['cHausnummer']),
                                'cAdressZusatz' => trim($delivery_address['cAdressZusatz']),
                                'cPLZ' => trim($delivery_address['cPLZ']),
                                'cOrt' => trim($delivery_address['cOrt']),
                                'cLand' => trim($delivery_address['cLand'])
                            ];

                            if (
                                ($Lieferadresse->cVorname == $tmp_address['cVorname']) &&
                                ($Lieferadresse->cNachname == $tmp_address['cNachname']) &&
                                (Text::filterXSS($post_data['params']['address']['streetName']) == $tmp_address['cStrasse']) &&
                                (Text::htmlentities(Text::filterXSS($post_data['params']['address']['buildingNumber'])) == $tmp_address['cHausnummer']) &&
                                (Text::htmlentities(Text::filterXSS($post_data['params']['address']['additionalInfo'])) == $tmp_address['cAdressZusatz'] )&&
                                (Text::htmlentities(Text::filterXSS($post_data['params']['address']['postalCode'])) == $tmp_address['cPLZ']) &&
                                (Text::filterXSS($post_data['params']['address']['locality']) == $tmp_address['cOrt']) &&
                                (strtoupper(Text::htmlentities(Text::filterXSS($post_data['params']['address']['countryCode']))) == $tmp_address['cLand'])
                            ) {
                                $sameAddress = $delivery_address;
                                break;
                            }
                        }

                        unset($_SESSION['Lieferadresse']);

                        if (!empty($sameAddress)) {
                            $Lieferadresse = new Lieferadresse($sameAddress['kLieferadresse']);
                        } else {
                            $Lieferadresse->cStrasse      = (isset($post_data['params']['address']['streetName'])) ? Text::filterXSS($post_data['params']['address']['streetName']) : $Lieferadresse->cStrasse;
                            $Lieferadresse->cHausnummer   = (isset($post_data['params']['address']['buildingNumber'])) ? Text::htmlentities(Text::filterXSS($post_data['params']['address']['buildingNumber'])) : $Lieferadresse->cHausnummer;
                            $Lieferadresse->cAdressZusatz = (isset($post_data['params']['address']['additionalInfo'])) ? Text::htmlentities(Text::filterXSS($post_data['params']['address']['additionalInfo'])) : $Lieferadresse->cAdressZusatz;
                            $Lieferadresse->cPLZ          = (isset($post_data['params']['address']['postalCode'])) ? Text::htmlentities(Text::filterXSS($post_data['params']['address']['postalCode'])) : $Lieferadresse->cPLZ;
                            $Lieferadresse->cOrt          = (isset($post_data['params']['address']['locality'])) ? Text::filterXSS($post_data['params']['address']['locality']) : $Lieferadresse->cOrt;
                            $Lieferadresse->cLand         = (isset($post_data['params']['address']['countryCode'])) ? strtoupper(Text::htmlentities(Text::filterXSS($post_data['params']['address']['countryCode']))) : $Lieferadresse->cLand;
                            $newKLieferadresse = $Lieferadresse->insertInDB();

                            $Lieferadresse = new Lieferadresse($newKLieferadresse);
                        }

                        $_SESSION['Lieferadresse'] = $Lieferadresse;

                        // Save meta.
                        Shop::Container()->getDB()->queryPrepared(
                            "INSERT INTO `xplugin_endereco_jtl5_client_tams` 
                    (`kKunde`, `kRechnungsadresse`, `kLieferadresse`, `enderecoamsts`, `enderecoamsstatus`, `enderecoamspredictions`, `last_change_at`)
                 VALUES 
                    ( NULL,  NULL, :kLieferadresse, :enderecoamsts, :enderecoamsstatus, :enderecoamspredictions, now())
                ON DUPLICATE KEY UPDATE    
                   `kLieferadresse`=:kLieferadresse2, `enderecoamsts`=:enderecoamsts2, `enderecoamsstatus`=:enderecoamsstatus2, `enderecoamspredictions`=:enderecoamspredictions2, `last_change_at`=now()
                ",
                            [
                                ':kLieferadresse' => $Lieferadresse->kLieferadresse,
                                ':enderecoamsts' => Text::filterXSS($post_data['params']['enderecometa']['ts']),
                                ':enderecoamsstatus' => implode(',', Text::filterXSS($post_data['params']['enderecometa']['status'])),
                                ':enderecoamspredictions' => json_encode(Text::filterXSS($post_data['params']['enderecometa']['predictions'])),
                                ':kLieferadresse2' => $Lieferadresse->kLieferadresse,
                                ':enderecoamsts2' => Text::filterXSS($post_data['params']['enderecometa']['ts']),
                                ':enderecoamsstatus2' => implode(',', Text::filterXSS($post_data['params']['enderecometa']['status'])),
                                ':enderecoamspredictions2' => json_encode(Text::filterXSS($post_data['params']['enderecometa']['predictions'])),
                            ],
                            1
                        );

                        if (!empty($_SESSION['oBesucher']->kBestellung)) {
                            $Bestellung = new Bestellung($_SESSION['oBesucher']->kBestellung);
                            $Bestellung->kLieferadresse = $Lieferadresse->kLieferadresse;
                            $Bestellung->updateInDB();
                        }

                        $_SESSION['Warenkorb']->kLieferadresse = $Lieferadresse->kLieferadresse;

                        $_SESSION['EnderecoShippingAddressMeta']['enderecoamsstatus'] = implode(',', $post_data['params']['enderecometa']['status']);
                        $_SESSION['EnderecoShippingAddressMeta']['enderecoamspredictions'] = json_encode($post_data['params']['enderecometa']['predictions']);
                        $_SESSION['EnderecoShippingAddressMeta']['enderecoamsts'] = $post_data['params']['enderecometa']['ts'];
                    }
                }

                exit();
            }
        });

        // Write comment to order.
        $dispatcher->listen('shop.hook.' . \HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB_ENDE, static function (array $args) use ($plugin) {
            // Save status.
            if (!empty($args['oBestellung']->kLieferadresse)) {
                if ($_SESSION['EnderecoShippingAddressMeta'] && !empty($_SESSION['EnderecoShippingAddressMeta']['enderecoamsstatus'])) {
                    $delivery_status = $_SESSION['EnderecoShippingAddressMeta']['enderecoamsstatus'];
                    $delivery_predictions = $_SESSION['EnderecoShippingAddressMeta']['enderecoamspredictions'];
                    $delivery_timestamp = $_SESSION['EnderecoShippingAddressMeta']['enderecoamsts'];
                }
            } elseif ($args['oBestellung']->kKunde) {
                if (!empty($_SESSION['EnderecoBillingAddressMeta']) && !empty($_SESSION['EnderecoBillingAddressMeta']['enderecoamsstatus'])) {
                    $delivery_status = $_SESSION['EnderecoBillingAddressMeta']['enderecoamsstatus'];
                    $delivery_predictions = $_SESSION['EnderecoBillingAddressMeta']['enderecoamspredictions'];
                    $delivery_timestamp = $_SESSION['EnderecoBillingAddressMeta']['enderecoamsts'];
                }
            }

            // Save delivery address status as attribute.
            if (!empty($delivery_status)) {
                try {
                    Shop::Container()->getDB()->queryPrepared(
                        "INSERT INTO `tbestellattribut` 
                    (`kBestellung`, `cName`, `cValue`)
                 VALUES 
                    (:id1, :name1, :value1),
                    (:id2, :name2, :value2),
                    (:id3, :name3, :value3) 
                ON DUPLICATE KEY UPDATE    
                   `cValue`=VALUES(`cValue`)
                ",
                        [
                            ':id1' => $args['oBestellung']->kBestellung,
                            ':name1' => 'enderecoamsts',
                            ':value1' => $delivery_timestamp,
                            ':id2' => $args['oBestellung']->kBestellung,
                            ':name2' => 'enderecoamsstatus',
                            ':value2' => $delivery_status,
                            ':id3' => $args['oBestellung']->kBestellung,
                            ':name3' => 'enderecoamspredictions',
                            ':value3' => $delivery_predictions,
                        ],
                        1
                    );
                } catch(\Exception $e) {
                    // TODO: log it.
                }
            }

            /**
             * Write check details to the order entry in the database, so it can be exported to jtl wawi.
             */
            $config = $plugin->getConfig();
            $leaveComment = 'on' === $config->getOption('endereco_jtl5_client_ams_to_comment')->value;
            if ($leaveComment) {
                if ($args['oBestellung']->kLieferadresse) {
                    $shipping_address = new Lieferadresse($args['oBestellung']->kLieferadresse);
                } else if ($args['oBestellung']->kKunde) {
                    $shipping_address = new Rechnungsadresse($args['oBestellung']->kRechnungsadresse);
                }
                $comment = $args['oBestellung']->cKommentar;
                if (!empty($delivery_status)) {
                    // Predictions
                    $predictions = json_decode(
                        $delivery_predictions,
                        true
                    );
                    $statusCodes = explode(',', $delivery_status);

                    $mainMessage = "";
                    if (in_array('address_correct', $statusCodes)) {
                        $mainMessage = $config->getValue('endereco_jtl5_client_wawi_address_correct');
                    }

                    if (in_array('address_needs_correction', $statusCodes)) {
                        $mainMessage = $config->getValue('endereco_jtl5_client_wawi_address_needs_correction');

                        if (in_array('building_number_not_found', $statusCodes)) {
                            $mainMessage = $config->getValue('endereco_jtl5_client_wawi_building_number_not_found');
                        }

                        if (in_array('building_number_is_missing', $statusCodes)) {
                            $mainMessage = $config->getValue('endereco_jtl5_client_wawi_building_number_is_missing');
                        }
                    }

                    if (in_array('address_not_found', $statusCodes)) {
                        $mainMessage = $config->getValue('endereco_jtl5_client_wawi_address_not_found');
                    }

                    if (in_array('address_multiple_variants', $statusCodes)) {
                        $mainMessage = $config->getValue('endereco_jtl5_client_wawi_address_multiple_variants');
                    }

                    if (!empty($mainMessage) && in_array('address_selected_by_customer', $statusCodes)) {
                        $mainMessage .= $config->getValue('endereco_jtl5_client_wawi_address_selected_by_customer');
                    }

                    if (!empty($mainMessage) && in_array('address_selected_automatically', $statusCodes)) {
                        $mainMessage .= $config->getValue('endereco_jtl5_client_wawi_address_selected_automatically');
                    }

                    $correctionAdvice = "";
                    if (in_array('address_needs_correction', $statusCodes)
                        && !in_array('building_number_not_found', $statusCodes)
                        && !in_array('building_number_is_missing', $statusCodes)
                        && !empty($predictions[0])
                    ) {
                        $correctionAdvice = "\n";
                        if ($shipping_address->cStrasse !== $predictions[0]['streetName']) {
                            $correctionAdvice .= $shipping_address->cStrasse . ' -> ' . $predictions[0]['streetName'] . PHP_EOL;
                        }
                        if ($shipping_address->cPLZ !== $predictions[0]['postalCode']) {
                            $correctionAdvice .= $shipping_address->cPLZ . ' -> ' . $predictions[0]['postalCode'] . PHP_EOL;
                        }
                        if ($shipping_address->cOrt !== $predictions[0]['locality']) {
                            $correctionAdvice .= $shipping_address->cOrt . ' -> ' . $predictions[0]['locality'] . PHP_EOL;
                        }
                        if (strtolower($shipping_address->cLand) !== strtolower($predictions[0]['countryCode'])) {
                            $correctionAdvice .= $shipping_address->cLand . ' -> ' . strtoupper($predictions[0]['countryCode']) . PHP_EOL;
                        }
                    }

                    if (!empty($mainMessage)) {
                        if (!empty($comment)) {
                            $comment .= PHP_EOL;
                        }
                        $comment .= $mainMessage . $correctionAdvice;
                        // Update order.
                        $args['oBestellung']->cKommentar = $comment;
                        $args['oBestellung']->updateInDb();
                    }
                }
            }

            // Delete sessions.
            unset($_SESSION['EnderecoShippingAddressMeta']);
            unset($_SESSION['EnderecoBillingAddressMeta']);
        });


    }

    /**
     * Includes endereco js and config to the page.
     *
     * @param Object $smarty Smarty object.
     * @param Object $plugin Plugin object.
     *
     * @return void
     */
    private static function _includeBoots($smarty, $plugin) {

        if (!self::$_bootStrIncluded) {
            // Get template name.
            $template = Shop::Container()->getTemplateService()->getActiveTemplate();
            // Get country mapping.
            $countires = Shop::Container()->getDB()->queryPrepared(
                "SELECT *
FROM `tland`",
                [],
                2
            );
            $countryMapping = [];
            foreach ($countires as $country) {
                if (!empty($_SESSION['cISOSprache']) && 'ger' === $_SESSION['cISOSprache']) {
                    $countryMapping[$country->cISO] = $country->cDeutsch;
                } else {
                    $countryMapping[$country->cISO] = $country->cEnglisch;
                }
            }

            $pluginIOPath = URL_SHOP . '/plugins/endereco_jtl5_client/io.php';
            $agentInfo = "Endereco JTL5 Client v" . $plugin->getMeta()->getVersion();

            $smarty->assign('endereco_theme_name', strtolower($template->getDir()))
                ->assign('endereco_plugin_config', $plugin->getConfig())
                ->assign('endereco_locales', $plugin->getLocalization())
                ->assign('endereco_plugin_ver', $plugin->getMeta()->getVersion())
                ->assign('endereco_agent_info', $agentInfo)
                ->assign('endereco_api_url', $pluginIOPath)
                ->assign('endereco_jtl5_client_country_mapping', str_replace('\'', '\\\'', json_encode($countryMapping)));

            $file = __DIR__ . '/smarty_templates/config.tpl';
            $html = $smarty->fetch($file);
            phpQuery::pq('head')->prepend($html);

            // Add js loader in footer.
            $file = __DIR__ . '/smarty_templates/load_js.tpl';
            $html = $smarty->fetch($file);
            phpQuery::pq('body')->append($html);

            self::$_bootStrIncluded = true;
        }

    }

}
