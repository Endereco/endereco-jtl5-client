<?php

namespace Plugin\endereco_jtl5_client\src\Handler;

use JTL\Checkout\Lieferadresse;
use JTL\Checkout\Rechnungsadresse;
use JTL\Checkout\Bestellung;
use JTL\Plugin\PluginInterface;
use Plugin\endereco_jtl5_client\src\Helper\EnderecoService;
use JTL\Plugin\Data\Config;

class CommentHandler
{
    private PluginInterface $plugin;
    private EnderecoService $enderecoService;

    /**
     * Constructs a new instance of the CommentHandler class.
     *
     * This constructor initializes the CommentHandler with necessary dependencies,
     * including the plugin interface and the EnderecoService for address verification
     * and handling. These dependencies are injected into the handler to facilitate
     * operations such as accessing plugin configurations and performing address
     * verification tasks.
     *
     * @param PluginInterface $plugin The instance of the plugin interface, which provides
     *                                access to plugin-specific functionalities and configurations.
     * @param EnderecoService $enderecoService The instance of the EnderecoService,
     *                                         which is used for address verification and related services.
     */
    public function __construct(
        PluginInterface $plugin,
        EnderecoService $enderecoService
    ) {
        $this->plugin = $plugin;
        $this->enderecoService = $enderecoService;
    }

    /**
     * Checks if the order comment feature is active.
     *
     * This method determines whether the order comment feature is enabled in the plugin's configuration settings.
     * It specifically checks the plugin configuration for a setting that controls whether or not to add comments
     * to orders based on address verification results. The method returns true if this feature is enabled ('on'),
     * indicating that order comments should be processed and updated accordingly, and false otherwise.
     *
     * @return bool Returns true if the order comment feature is enabled in the plugin settings, false otherwise.
     */
    private function isOrderCommentFeatureActive(): bool
    {
        $config = $this->plugin->getConfig();
        $option = $config->getOption('endereco_jtl5_client_ams_to_comment');
        return $option !== null && 'on' === $option->value;
    }

    /**
     * Retrieves the delivery or billing address from an order.
     *
     * This method extracts either the delivery address (Lieferadresse) or the billing address (Rechnungsadresse)
     * from the given order object. It first checks if a delivery address (kLieferadresse) is associated with the
     * order. If present, it returns the delivery address. Otherwise, it falls back to the billing address
     * (kRechnungsadresse) associated with the customer (kKunde) in the order.
     *
     * @param Bestellung $order The order object from which to extract the address.
     *
     * @return Lieferadresse|Rechnungsadresse|null Returns the delivery address if available, otherwise returns the
     *                                        billing address from the order.
     */
    private function getDeliveryAddressFromOrder(Bestellung $order)
    {
        $address = null;
        if ($order->kLieferadresse) {
            $address = new Lieferadresse($order->kLieferadresse);
        } elseif ($order->kRechnungsadresse) {
            $address = new Rechnungsadresse($order->kRechnungsadresse);
        }

        return $address;
    }

    /**
     * Processes address verification status codes to generate a message.
     *
     * Iterates through the list of status codes from the address verification service and
     * generates a message based on the configuration settings for each status code.
     *
     * @param string $statusCodesSerialized An array of status codes from address verification.
     * @param Config $config Configuration object containing message templates for different status codes.
     *
     * @return string A message that reflects the address verification status.
     */
    private function processStatusCodes(string $statusCodesSerialized, Config $config): string
    {
        $messages = [];
        $statusCodes = explode(',', $statusCodesSerialized);

        foreach ($statusCodes as $code) {
            switch ($code) {
                case 'address_correct':
                    $messages[] = $config->getValue('endereco_jtl5_client_wawi_address_correct');
                    break;

                case 'address_needs_correction':
                    $messages[] = $config->getValue('endereco_jtl5_client_wawi_address_needs_correction');
                    break;

                case 'building_number_not_found':
                    $messages[] = $config->getValue('endereco_jtl5_client_wawi_building_number_not_found');
                    break;

                case 'building_number_is_missing':
                    $messages[] = $config->getValue('endereco_jtl5_client_wawi_building_number_is_missing');
                    break;

                case 'address_not_found':
                    $messages[] = $config->getValue('endereco_jtl5_client_wawi_address_not_found');
                    break;

                case 'address_multiple_variants':
                    $messages[] = $config->getValue('endereco_jtl5_client_wawi_address_multiple_variants');
                    break;

                case 'address_selected_by_customer':
                    // This status might be appended to another message
                    $messages[] = $config->getValue('endereco_jtl5_client_wawi_address_selected_by_customer');
                    break;

                case 'address_selected_automatically':
                    // This status might be appended to another message
                    $messages[] = $config->getValue('endereco_jtl5_client_wawi_address_selected_automatically');
                    break;

                default:
                    // Handle unknown status codes, if necessary
                    break;
            }
        }

        // Combine all messages into a single string
        return implode(PHP_EOL, $messages);
    }

    /**
     * Processes address prediction data and generates correction advice.
     *
     * This method interprets prediction data for an address, comparing it with the original
     * address information, and generates advice for potential corrections.
     *
     * @param string $predictionsSerialized An array of predicted address components.
     * @param mixed  $address               The original address object (either delivery or billing address).
     *
     * @return string A string containing advice for address corrections, if applicable.
     */
    private function processPredictions(string $predictionsSerialized, $address): string
    {
        $correctionAdvice = "";
        $predictions = json_decode($predictionsSerialized, true);

        // Check if there are predictions available and at least one prediction exists
        if (!empty($predictions) && isset($predictions[0])) {
            $predictedAddress = $predictions[0]; // Assuming the first prediction is the most relevant

            // Initialize an array to hold potential corrections
            $corrections = [];

            // Compare each address component with the predicted value and add to corrections if different
            if ($address->cStrasse !== $predictedAddress['streetName']) {
                $corrections[] = $address->cStrasse . " -> " . $predictedAddress['streetName'];
            }
            if ($address->cHausnummer !== $predictedAddress['buildingNumber']) {
                $corrections[] = $address->cHausnummer . " -> " . $predictedAddress['buildingNumber'];
            }
            if ($address->cPLZ !== $predictedAddress['postalCode']) {
                $corrections[] = $address->cPLZ . " -> " . $predictedAddress['postalCode'];
            }
            if ($address->cOrt !== $predictedAddress['locality']) {
                $corrections[] = $address->cOrt . " -> " . $predictedAddress['locality'];
            }
            if (strtolower($address->cLand) !== strtolower($predictedAddress['countryCode'])) {
                $corrections[] = $address->cLand . " -> " . strtoupper($predictedAddress['countryCode']);
            }

            // Combine all corrections into a single string, separated by new lines
            $correctionAdvice = implode(PHP_EOL, $corrections);
        }

        return $correctionAdvice;
    }

    /**
     * Extends the order comment with additional information based on address verification.
     *
     * This method is responsible for adding extra comments to an order based on the results of
     * address verification. It first checks if the order comment feature is active and if the
     * address meta-data contains address verification status. If these conditions are met,
     * it processes the status codes and predictions to generate a message, which is then
     * appended to the order's existing comment.
     *
     * The method primarily serves to enhance the order's comment field with detailed
     * information about the address verification outcome, which can include the verification
     * status and suggested corrections.
     *
     * @param array<string,mixed> $args An associative array containing the order object (`oBestellung`). This array
     *                    is expected to have the structure ['oBestellung' => $orderObject], where
     *                    $orderObject is an instance of the Bestellung class.
     *
     * @return void This method does not return a value but modifies the order's comment field directly.
     */
    public function extendOrderComment(array $args): void
    {
        /** @var Bestellung $order */
        $order = $args['oBestellung'];
        $addressMeta = $this->enderecoService->getOrderAddressMeta();

        $leaveComment = $this->isOrderCommentFeatureActive();

        if (!$leaveComment) {
            return;
        }

        if (!$addressMeta->hasAnyStatus()) {
            return;
        }

        $address = $this->getDeliveryAddressFromOrder($order);
        if (is_null($address)) {
            return;
        }

        $originalComment = $order->cKommentar;
        $mainMessage = $this->processStatusCodes($addressMeta->getStatusAsString(), $this->plugin->getConfig());

        $correctionAdvice = '';
        if ($addressMeta->hasStatus('address_needs_correction')) {
            $correctionAdvice = $this->processPredictions($addressMeta->getPredictionsAsString(), $address);
        }

        // Update order comment logic
        if (!empty($mainMessage)) {
            $comment = trim(implode(PHP_EOL, [$originalComment, $mainMessage, $correctionAdvice]));
            $order->cKommentar = $comment;
            $args['oBestellung']->cKommentar = $comment; // Save in the original object too.
            $order->updateInDb();
        }
    }
}
