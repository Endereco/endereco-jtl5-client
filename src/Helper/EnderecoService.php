<?php
namespace Plugin\endereco_jtl5_client\src\Helper;

use JTL\Plugin\Plugin;
use JTL\Shop;

class EnderecoService {

    public $plugin;
    public $clientInfo;

    /**
     * constructor
     *
     * @param Plugin $oPlugin
     */
    public function __construct(Plugin $oPlugin)
    {
        $this->plugin = $oPlugin;
        $this->clientInfo = 'Endereco JTL5 Client v' . $oPlugin->getMeta()->getVersion();
    }

    public function findSessions() {
        $accountableSessionIds = array();
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            foreach ($_POST as $sVarName => $sVarValue) {
                if ((strpos($sVarName, '_session_counter') !== false) && 0 < intval($sVarValue)) {
                    $sSessionIdName = str_replace('_session_counter', '', $sVarName) . '_session_id';
                    $accountableSessionIds[$_POST[$sSessionIdName]] = true;
                }
            }
            $accountableSessionIds = array_keys($accountableSessionIds);
        }
        return $accountableSessionIds;
    }

    public function generateSesionId() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function checkAddress($Address) {
        $return = [];

        // Load config.
        $config = $this->plugin->getConfig();

        // Generate Session ID.
        $sessionId = $this->generateSesionId();
        // Check address.
        try {
            if (empty(trim($Address->cHausnummer))) {
                $message = array(
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'addressCheck',
                    'params' => array(
                        'language' => 'de',
                        'country' => strtoupper($Address->cLand),
                        'postCode' => $Address->cPLZ,
                        'cityName' => $Address->cOrt,
                        'streetFull' => $Address->cStrasse
                    )
                );
            } else {
                $message = array(
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'addressCheck',
                    'params' => array(
                        'language' => 'de',
                        'country' => strtoupper($Address->cLand),
                        'postCode' => $Address->cPLZ,
                        'cityName' => $Address->cOrt,
                        'street' => $Address->cStrasse,
                        'houseNumber' => $Address->cHausnummer,
                    )
                );
            }

            $newHeaders = array(
                'Content-Type' => 'application/json',
                'X-Auth-Key' => $config->getValue('endereco_jtl5_client_api_key'),
                'X-Transaction-Id' => $sessionId,
                'X-Transaction-Referer' => $_SERVER['HTTP_REFERER'] ?? 'not_set',
                'X-Agent' => $this->clientInfo,
            );
            $result = $this->sendRequest($message, $newHeaders);

            // Save result in the database and return meta infos.
            if (!empty($result['result'])) {
                // Save to DB.
                $ts = time();
                $statuses = implode(',', $result['result']['status']);

                $predictionsArray = $result['result']['predictions'];
                $predictionsArrayRightFormat = [];
                foreach($predictionsArray as $prediction) {
                    $tempPrediction = [];
                    if (!empty($prediction['country'])) {
                        $tempPrediction['countryCode'] = $prediction['country'] ?? '';
                    }
                    if (!empty($prediction['cityName'])) {
                        $tempPrediction['locality'] = $prediction['cityName'] ?? '';
                    }
                    if (!empty($prediction['postCode'])) {
                        $tempPrediction['postalCode'] = $prediction['postCode'] ?? '';
                    }
                    if (!empty($prediction['street'])) {
                        $tempPrediction['streetName'] = $prediction['street'] ?? '';
                    }
                    if (!empty($prediction['houseNumber'])) {
                        $tempPrediction['buildingNumber'] = $prediction['houseNumber'] ?? '';
                    }
                    if (!empty($prediction['additionalInfo'])) {
                        $tempPrediction['additionalInfo'] = $prediction['additionalInfo'] ?? '';
                    }
                    $predictionsArrayRightFormat[] = $tempPrediction;
                }

                $predictions = json_encode($predictionsArrayRightFormat);

                // If customer address
                if ('JTL\Customer\Customer' === get_class($Address)) {
                    Shop::Container()->getDB()->queryPrepared(
                        "INSERT INTO `xplugin_endereco_jtl5_client_tams` 
                        (`kKunde`, `kRechnungsadresse`, `kLieferadresse`, `enderecoamsts`, `enderecoamsstatus`, `enderecoamspredictions`, `last_change_at`)
                        VALUES 
                        (:kKunde, NULL, NULL, :enderecoamsts, :enderecoamsstatus, :enderecoamspredictions, now())
                        ON DUPLICATE KEY UPDATE    
                        `kKunde`=:kKunde2, `enderecoamsts`=:enderecoamsts2, `enderecoamsstatus`=:enderecoamsstatus2, `enderecoamspredictions`=:enderecoamspredictions2, `last_change_at`=now()
                        ",
                        [
                            ':kKunde' => $Address->kKunde,
                            ':enderecoamsts' => $ts,
                            ':enderecoamsstatus' => $statuses,
                            ':enderecoamspredictions' => $predictions,
                            ':kKunde2' => $Address->kKunde,
                            ':enderecoamsts2' => $ts,
                            ':enderecoamsstatus2' => $statuses,
                            ':enderecoamspredictions2' => $predictions,
                        ],
                        1
                    );
                }

                // If lieferadresse.
                if ('JTL\Checkout\Lieferadresse' === get_class($Address)) {
                    Shop::Container()->getDB()->queryPrepared(
                        "INSERT INTO `xplugin_endereco_jtl5_client_tams` 
                        (`kKunde`, `kRechnungsadresse`, `kLieferadresse`, `enderecoamsts`, `enderecoamsstatus`, `enderecoamspredictions`, `last_change_at`)
                        VALUES 
                        (NULL, NULL, :kLieferadresse, :enderecoamsts, :enderecoamsstatus, :enderecoamspredictions, now())
                        ON DUPLICATE KEY UPDATE    
                        `kLieferadresse`=:kLieferadresse2, `enderecoamsts`=:enderecoamsts2, `enderecoamsstatus`=:enderecoamsstatus2, `enderecoamspredictions`=:enderecoamspredictions2, `last_change_at`=now()
                        ",
                        [
                            ':kLieferadresse' => $Address->kLieferadresse,
                            ':enderecoamsts' => $ts,
                            ':enderecoamsstatus' => $statuses,
                            ':enderecoamspredictions' => $predictions,
                            ':kLieferadresse2' => $Address->kLieferadresse,
                            ':enderecoamsts2' => $ts,
                            ':enderecoamsstatus2' => $statuses,
                            ':enderecoamspredictions2' => $predictions,
                        ],
                        1
                    );
                }

                // Prepare return.
                $return = new \stdClass();
                $return->enderecoamsts = $ts;
                $return->enderecoamsstatus = $statuses;
                $return->enderecoamspredictions = $predictions;
            }
        } catch(\Exception $e) {
            // TODO: log error
        }

        return $return;
    }

    public function doAccountings($sessionIds) {

        // Get sessionids.
        if (!$sessionIds) {
            return;
        }

        $config = $this->plugin->getConfig();

        $anyDoAccounting = false;

        foreach ($sessionIds as $sessionId) {
            try {
                $message = array(
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'doAccounting',
                    'params' => array(
                        'sessionId' => $sessionId
                    )
                );
                $newHeaders = array(
                    'Content-Type' => 'application/json',
                    'X-Auth-Key' => $config->getValue('endereco_jtl5_client_api_key'),
                    'X-Transaction-Id' => $sessionId,
                    'X-Transaction-Referer' => $_SERVER['HTTP_REFERER'],
                    'X-Agent' => $this->clientInfo,
                );
                $this->sendRequest($message, $newHeaders);
                $anyDoAccounting = true;

            } catch(\Exception $e) {
                // TODO: log error
            }
        }

        if ($anyDoAccounting) {
            try {
                $message = array(
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'doConversion',
                    'params' => array()
                );
                $newHeaders = array(
                    'Content-Type' => 'application/json',
                    'X-Auth-Key' => $config->getValue('endereco_jtl5_client_api_key'),
                    'X-Transaction-Id' => 'not_required',
                    'X-Transaction-Referer' => $_SERVER['HTTP_REFERER'],
                    'X-Agent' => $this->clientInfo,
                );
                $this->sendRequest($message, $newHeaders);
            } catch(\Exception $e) {
                // Do nothing.
            }
        }
    }

    public function sendRequest($body, $headers) {
        $config = $this->plugin->getConfig();
        $serviceUrl = $config->getValue('endereco_jtl5_client_remote_url');
        $ch = curl_init(trim($serviceUrl));
        $dataString = json_encode($body);
        $parsedHeaders = array();
        foreach ($headers as $headerName=>$headerValue) {
            $parsedHeaders[] = $headerName . ': ' . $headerValue;
        }
        $parsedHeaders[] = 'Content-Length: ' . strlen($dataString);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            $parsedHeaders
        );

        $result = json_decode(curl_exec($ch), true);
        return $result;
    }
}
