<?php
/***************************************************************************
 *                                                                          *
 *   (c) 2004 Vladimir V. Kalynyak, Alexey V. Vinokurov, Ilya M. Shalnev    *
 *                                                                          *
 * This  is  commercial  software,  only  users  who have purchased a valid *
 * license  and  accept  to the terms of the  License Agreement can install *
 * and use this program.                                                    *
 *                                                                          *
 ****************************************************************************
 * PLEASE READ THE FULL TEXT  OF THE SOFTWARE  LICENSE   AGREEMENT  IN  THE *
 * "copyright.txt" FILE PROVIDED WITH THIS DISTRIBUTION PACKAGE.            *
 ****************************************************************************/

namespace Tygh\Shippings\Services;

use SoapClient;
use Tygh\Registry;
use Tygh\Shippings\IService;
use \Exception;
use \stdClass;

/**
 * FedEx shipping service.
 * Uses FedEx Web Services v22 (SOAP)
 */
class FedexDemo implements IService
{
    /**
     * API version
     */
    const VERSION = 22;
    /**
     * Production service URL
     */
    const URL_PRODUCTION = 'https://ws.fedex.com:443/web-services';
    /**
     * Development service URL
     */
    const URL_DEVELOPMENT = 'https://wsbeta.fedex.com:443/web-services';
    /**
     * Address type: Shipper
     */
    const INFO_SHIPPER = 'shipper';
    /**
     * Address type: Recipient
     */
    const INFO_RECIPIENT = 'recipient';
    /**
     * Special services type: Package
     */
    const SPECIAL_PACKAGE = 'package';
    /**
     * Special services type: Shipment
     */
    const SPECIAL_SHIPMENT = 'shipment';
    /**
     * Availability multithreading in this module
     *
     * @var array $_allow_multithreading
     */
    protected $_allow_multithreading = false;
    /**
     * @var string Service URL
     */
    protected $service_url;
    /**
     * @var array Shipping settings
     */
    protected $settings;
    /**
     * @var array Package info
     */
    protected $package;
    /**
     * Stored shipping information
     *
     * @var array $_shipping_info
     */
    protected $_shipping_info = array();

    /**
     * Currency of rates that are present in the service response
     *
     * @var array $response_currencies
     */
    protected $response_currencies = array();

    /**
     * Service reported errors.
     *
     * @var array $errors
     */
    protected $errors = array();

    /**
     * SOAP client to perform service requests.
     *
     * @var SoapClient $client
     */
    protected $client;

    /**
     * @inheritdoc
     */
    public function prepareData($shipping_info)
    {
        $this->_shipping_info = $shipping_info;

        $this->settings = $shipping_info['service_params'];

        $this->package = $shipping_info['package_info'];
        $this->package['origination'] = $this->prepareAddress($this->package['origination']);
        $this->package['location'] = $this->prepareAddress($this->package['location']);

        $this->service_url = isset($this->settings['test_mode']) && $this->settings['test_mode'] == 'Y' ?
            self::URL_DEVELOPMENT :
            self::URL_PRODUCTION;
    }

    /**
     * Fill required address fields
     * TODO: Add to \Tygh\Shippings\IService
     *
     * @param array $address Address data
     *
     * @return array Filled address data
     */
    public function prepareAddress($address)
    {
        $default_fields = array(
            'address' => '',
            'zipcode' => '',
            'city' => '',
            'state' => '',
            'country' => '',
            'name' => '',
        );

        return array_merge($default_fields, $address);
    }

    /**
     * @inheritdoc
     */
    public function processResponse($response)
    {
        $return = array(
            'cost' => false,
            'error' => false,
            'delivery_time' => false,
        );

        $code = $this->_shipping_info['service_code'];
        // FIXME: FexEx returned GROUND for international as "FEDEX_GROUND" and not INTERNATIONAL_GROUND
        // We sent a request to clarify this situation to FedEx.
        $intl_code = str_replace('INTERNATIONAL_', 'FEDEX_', $code);
        $rates = $this->processRates($response);

        if (array_key_exists($code, $rates)) {
            $rate = $rates[$code];
        } elseif (array_key_exists($intl_code, $rates)) {
            $rate = $rates[$intl_code];
        } else {
            $rate = false;
        }

        if ($rate) {
            $return['cost'] = $rate;
        } elseif ($rate === null) {
            $return['error'] = __('shippings.fedex.currency_is_missing', array('[currency]' => implode(', ', $this->response_currencies)));
        } else {
            $return['error'] = $this->processErrors($response);
        }

        return $return;
    }

    /**
     * @inheritdoc
     */
    public function processRates($rate_reply)
    {
        $currencies = Registry::get('currencies');

        $return = array();

        if (isset($rate_reply->RateReplyDetails)) {
            $rate = $rate_reply->RateReplyDetails;
            $return[$rate->ServiceType] = $this->getShipmentRate($rate->RatedShipmentDetails, $currencies, CART_PRIMARY_CURRENCY);
        }

        return $return;
    }

    /**
     * @inheritdoc
     */
    public function processErrors($rate_reply)
    {
        if (isset($rate_reply->HighestSeverity) && $rate_reply->HighestSeverity == 'ERROR') {
            $this->errors[] = $rate_reply->Notifications->LocalizedMessage;
        }

        return $this->errors ? implode('; ', $this->errors) : false;
    }

    /**
     * @inheritdoc
     */
    public function allowMultithreading()
    {
        return $this->_allow_multithreading;
    }

    /**
     * Provides path to WSDL file describing Rates service.
     *
     * @return string
     */
    protected function getWsdlPath()
    {
        $path = sprintf('%s/%s/var/wsdl/RateService_v%d.wsdl',
            Registry::get('config.dir.addons'),
            $this->getInfo()['id'],
            self::VERSION
        );

        return $path;
    }

    /**
     * Initiates SOAP client to communicate with FedEx API.
     *
     * @return \SoapClient
     */
    protected function getClient()
    {
        if ($this->client === null) {
            $this->client = new SoapClient($this->getWsdlPath());
            $this->client->__setLocation($this->service_url);
        }

        return $this->client;
    }

    /**
     * @inheritdoc
     */
    public function getSimpleRates()
    {
        $rate_request = $this->getRequestData();
        $rate_reply = null;

        try {
            $rate_reply = $this->getClient()->getRates($rate_request);
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
        }

        return $rate_reply;
    }

    /**
     * @inheritdoc
     */
    public function getRequestData()
    {
        $rate_request = array(
            'WebAuthenticationDetail' => array(
                'UserCredential' => array(
                    'Key' => $this->settings['user_key'],
                    'Password' => $this->settings['user_key_password']
                )
            ),
            'ClientDetail' => array(
                'AccountNumber' => $this->settings['account_number'],
                'MeterNumber' => $this->settings['meter_number'],
            ),
            'TransactionDetail' => array(
                'CustomerTransactionId' => 'Rates Request',
            ),
            'Version' => array(
                'ServiceId' => 'crs',
                'Major' => self::VERSION,
                'Intermediate' => 0,
                'Minor' => 0,
            ),
            'RequestedShipment' => array(
                'DropoffType' => $this->settings['drop_off_type'],
                'ServiceType' => $this->_shipping_info['service_code'],
                'PackagingType' => $this->settings['package_type'],
                'PreferredCurrency' => CART_PRIMARY_CURRENCY,
                'Shipper' => $this->prepareShippingInfo($this->package['origination'], self::INFO_SHIPPER),
                'Recipient' => $this->prepareShippingInfo($this->package['location'], self::INFO_RECIPIENT, $this->_shipping_info['service_code']),
                'ShippingChargesPayment' => array(
                    'PaymentType' => 'SENDER',
                    'Payor' => array(
                        'ResponsibleParty' => array(
                            'AccountNumber' => $this->settings['account_number'],
                        ),
                    ),
                ),
                'RateRequestTypes' => 'PREFERRED',
            ),
        );

        $rate_request['RequestedShipment'] += $this->preparePackages();

        return $rate_request;
    }

    /**
     * Prepares shipping information for request data
     *
     * @param array  $address      Address data (Zipcode, Country, State, etc)
     * @param string $address_type 'recipient' or 'shipper'
     * @param string $code         Service code (E.g.: SMART_POST)
     *
     * @return array Shipping info
     */
    protected function prepareShippingInfo($address, $address_type = self::INFO_SHIPPER, $code = '')
    {
        $shipping_info = array(
            'Address' => array(
                'StreetLines' => $address['address'],
                'City' => $address['city'],
                'StateOrProvinceCode' => (strlen($address['state']) > 2) ? '' : $address['state'],
                'PostalCode' => self::formatPostalCode($address['zipcode']),
                'CountryCode' => $address['country'],
            )
        );

        if ($address_type == self::INFO_RECIPIENT && ($code == 'GROUND_HOME_DELIVERY' || empty($address['address_type']) || $address['address_type'] == 'residential')) {
            $shipping_info['Address']['Residential'] = 'true';
        }

        if ($address_type == self::INFO_RECIPIENT && $code == 'FEDEX_GROUND') {
            $shipping_info['Address']['Residential'] = 'false';
        }

        return $shipping_info;
    }

    /**
     * Formats postal code
     *
     * @param string $code Not formatted postal code
     *
     * @return string Formatted postal code
     */
    public static function formatPostalCode($code)
    {
        if (preg_match_all("/[\d\w]/", $code, $matches)) {
            return implode('', $matches[0]);
        }

        return '';
    }

    /**
     * Prepares packages information
     *
     * @param bool $is_freight If true, packages will be calculated for the freight shipment.
     *                                  Otherwise - for the regular shipment
     *
     * @return array Prepared packages information
     */
    protected function preparePackages($is_freight = false)
    {
        $length = empty($this->settings['length']) ? 0 : $this->settings['length'];
        $width = empty($this->settings['width']) ? 0 : $this->settings['width'];
        $height = empty($this->settings['height']) ? 0 : $this->settings['height'];

        $packages = array();
        if (empty($this->package['packages'])) {
            $packages[] = array(
                'shipping_params' => array(
                    'box_length' => $length,
                    'box_width' => $width,
                    'box_height' => $height,
                ),
                'weight' => $this->package['W'],
                'cost' => $this->package['C']
            );
        } else {
            $packages = $this->package['packages'];
        }

        if ($is_freight) {
            $package_items = array();
            $property_name = 'LineItems';
            $line_item_fields = array('FreightClass', 'Weight', 'Dimensions');
        } else {
            $package_items = array(
                'PackageCount' => count($this->package['packages'])
            );
            $property_name = 'RequestedPackageLineItems';
            $line_item_fields = array('SequenceNumber', 'GroupPackageCount', 'Weight', 'Dimensions');
        }

        $sequence_number = 1;
        foreach ($packages as $package) {
            $package_length = empty($package['shipping_params']['box_length']) ? $length : $package['shipping_params']['box_length'];
            $package_width = empty($package['shipping_params']['box_width']) ? $width : $package['shipping_params']['box_width'];
            $package_height = empty($package['shipping_params']['box_height']) ? $height : $package['shipping_params']['box_height'];
            $package_weight = fn_expand_weight($package['weight']);

            $line_item = array();
            foreach ($line_item_fields as $field) {
                switch ($field) {
                    case 'SequenceNumber':
                        $value = $sequence_number++;
                        break;
                    case 'GroupPackageCount':
                        $value = 1;
                        break;
                    case 'Weight':
                        $value = array(
                            'Units' => 'LB',
                            'Value' => $package_weight['full_pounds'],
                        );
                        break;
                    case 'Dimensions':
                        $value = array(
                            'Length' => $package_length,
                            'Width' => $package_width,
                            'Height' => $package_height,
                            'Units' => 'IN',
                        );
                        break;
                    case 'FreightClass':
                        $value = self::getFreightClass($package_length, $package_width, $package_height, $package_weight['full_pounds']);
                        break;
                }
                if (isset($value)) {
                    $line_item[$field] = $value;
                }
            }

            if ($line_item) {
                $package_items[$property_name][] = $line_item;
            }
        }

        return $package_items;
    }

    /**
     * Returns shipping service information
     *
     * @return array information
     */
    public static function getInfo()
    {
        return array(
            'id' => 'fedex_demo',
            'name' => __('addons.fedex_demo.fedex_demo'),
            'tracking_url' => 'https://www.fedex.com/apps/fedextrack/?action=track&trackingnumber=%s'
        );
    }

    /**
     * Determines freight class of the package.
     *
     * @param float $length Length in inches
     * @param float $width  Width in inches
     * @param float $height Height in inches
     * @param float $weight Weight in pounds
     *
     * @return string Freight class
     */
    protected static function getFreightClass($length, $width, $height, $weight)
    {
        $class = '500';

        $volume = $length * $width * $height / pow(12, 3); // volume in cubic feets

        if ($volume > 0) {
            $density = $weight / $volume; // density in lbs per cubic feet
            $classes = array(
                '50'   => array(50,   INF),
                '55'   => array(35,   50),
                '60'   => array(30,   35),
                '65'   => array(22.5, 30),
                '70'   => array(15,   22.5),
                '77.5' => array(13.5, 15),
                '85'   => array(12,   13.5),
                '92.5' => array(10.5, 12),
                '100'  => array( 9,   10.5),
                '110'  => array( 8,    9),
                '125'  => array( 7,    8),
                '150'  => array( 6,    7),
                '175'  => array( 5,    6),
                '200'  => array( 4,    5),
                '250'  => array( 3,    4),
                '300'  => array( 2,    3),
                '400'  => array( 1,    2),
                '500'  => array(-INF,  1)
            );
            foreach ($classes as $class => $limits) {
                if ($density >= $limits[0] && $density < $limits[1]) {
                    break;
                }
            }
        }

        return 'CLASS_' . ((float) $class < 100 ? '0' : '') . str_replace('.', '_', $class);
    }

    /**
     * Returns shipment rate calculated in the primary currency.
     *
     * @param stdClass $shipment         RatedShipmentDetails node from the service XML response
     * @param array            $currencies       Store currencies
     * @param string           $primary_currency Store primary currency
     *
     * @return float|null Shipment rate or null when none found
     */
    protected function getShipmentRate($shipment, array $currencies, $primary_currency)
    {
        $rates_list = $this->collectShipmentRatesFromResponse($shipment);

        // reorder rates, put the one in the primary currency first
        uksort($rates_list, function($currency_code) use ($primary_currency) {
            return (int) $primary_currency == $currency_code;
        });

        foreach ($rates_list as $currency_code => $rate) {
            // check if specified currency exists in the store and convert to primary
            if (!empty($currencies[$currency_code])) {
                return $rate * $currencies[$currency_code]['coefficient'] / $currencies[$primary_currency]['coefficient'];
            }
        }

        return null;
    }

    /**
     * Collects shipments rates in all provided currencies from the service XML response.
     *
     * @param stdClass $shipment RatedShipmentDetails node from the service XML response
     *
     * @return array Shipment rates; keys of the array are currency codes and values are amounts
     */
    protected function collectShipmentRatesFromResponse($shipment)
    {
        $rates_list = array();
        $shipment = is_array($shipment) ? $shipment : array($shipment);

        // collect rates in all provided currencies
        /** @var stdClass $additional_rate */
        foreach ($shipment as $additional_rate) {
            $response_currency = $additional_rate->ShipmentRateDetail->TotalNetCharge->Currency;
            $rates_list[$response_currency] = $additional_rate->ShipmentRateDetail->TotalNetCharge->Amount;
            $this->response_currencies[] = $response_currency;
        }

        return $rates_list;
    }
}
