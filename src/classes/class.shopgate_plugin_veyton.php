<?php
/**
 * Copyright Shopgate Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author    Shopgate Inc, 804 Congress Ave, Austin, Texas 78701 <interfaces@shopgate.com>
 * @copyright Shopgate Inc
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

require_once(dirname(__FILE__) . '/constants.php');

class ShopgatePluginVeyton extends ShopgatePlugin
{
    const SHIPPING_TYPE_API    = "PLUGINAPI";
    const SHIPPING_TYPE_MANUAL = 'MANUAL';
    const EXPORT_MODE_XML      = 'XML';
    const EXPORT_MODE_CSV      = 'CSV';

    /**
     * @var ShopgateConfigVeyton
     */
    protected $config;

    /**
     * @var ShopgateVeytonHelperCheckPlugin
     */
    protected $checkPluginHelper;

    /**
     * @var ShopgateVeytonHelperCustomer
     */
    protected $customerHelper;

    /**
     * @var int
     */
    protected $shopId = 0;

    /**
     * @var bool
     */
    protected $permissionBlacklist = true;

    /**
     * @var int
     */
    protected $orderShippingApprovedStatusId = 33;

    /**
     * @var int
     */
    protected $maxCategoriesOrder = 0;

    public function startup()
    {
        /** @var ADOConnection $db */
        global $db;

        $this->requireFiles();

        // Got your own config? Load it here . . .
        include_once('class.shopgate_config_veyton.php');
        $this->config = new ShopgateConfigVeyton();
        $this->config->load();
        $this->config->defineShopgateLanguageConstants();

        $this->permissionBlacklist = _SYSTEM_GROUP_PERMISSIONS == 'blacklist';
        $this->shopId              = $this->config->getStoreId();

        // instatiate common helpers; specific helpers should be instantiated before use where needed
        $this->checkPluginHelper = new ShopgateVeytonHelperCheckPlugin($db);
    }

    private function requireFiles()
    {
        // load helpers
        include_once(dirname(__FILE__) . '/helpers/Base.php');
        include_once(dirname(__FILE__) . '/helpers/CheckPlugin.php');
        include_once(dirname(__FILE__) . '/helpers/Category.php');
        include_once(dirname(__FILE__) . '/helpers/Review.php');
        include_once(dirname(__FILE__) . '/helpers/Cart.php');
        include_once(dirname(__FILE__) . '/helpers/Item.php');
        include_once(dirname(__FILE__) . '/helpers/Customer.php');
        include_once(dirname(__FILE__) . '/helpers/Settings.php');

        // load models
        include_once(dirname(__FILE__) . '/model/category/ShopgateCategoryModel.php');
        include_once(dirname(__FILE__) . '/model/category/ShopgateCategoryXmlModel.php');
        include_once(dirname(__FILE__) . '/model/general/ShopgateCustomFieldModel.php');
        include_once(dirname(__FILE__) . '/model/item/ShopgateItemModel.php');
        include_once(dirname(__FILE__) . '/model/item/ShopgateItemXmlModel.php');
        include_once(dirname(__FILE__) . '/model/order/ShopgateOrderModel.php');
        include_once(dirname(__FILE__) . '/model/reviews/ShopgateReviewModel.php');
        include_once(dirname(__FILE__) . '/model/reviews/ShopgateReviewXmlModel.php');
    }

    public function registerCustomer($user, $pass, ShopgateCustomer $customer)
    {
        /** @var ADOConnection $db */
        global $db;

        require_once(_SRV_WEBROOT . _SRV_WEB_FRAMEWORK . 'classes/class.customer.php');
        require_once(_SRV_WEBROOT . _SRV_WEB_FRAMEWORK . 'classes/class.check_fields.php');
        $fieldHandler = new ShopgateCustomFieldModel();
        $customerHlpr = new ShopgateVeytonHelperCustomer($db);
        $data         = $fieldMap = array();
        $addressList  = $customer->getAddresses();

        foreach ($addressList as $key => $address) {
            $addressKey   = null;
            $addressClass = null;

            if ($customerHlpr->areAddressesEqual($addressList)
                || $address->getAddressType() == ShopgateAddress::BOTH
                || count($addressList) == 1
            ) {
                $addressClass = 'default';
                $addressKey   = 'default_address';
            } elseif ($address->getAddressType() == ShopgateAddress::INVOICE) {
                $addressClass = 'payment';
                $addressKey   = 'payment_address';
            } else {
                $addressClass = 'shipping';
                $addressKey   = 'shipping_address';
            }

            $customersDob = (is_null($address->getBirthday()))
                ? $customer->getBirthday()
                : $address->getBirthday();

            $data[$addressKey] = array(
                'customers_gender'         => $address->getGender(),
                'customers_firstname'      => $address->getFirstName(),
                'customers_lastname'       => $address->getLastName(),
                'customers_company'        => (string)$address->getCompany(),
                'customers_company_2'      => '',
                'customers_company_3'      => '',
                'customers_street_address' => $address->getStreet1(),
                'customers_suburb'         => '',
                'customers_postcode'       => $address->getZipcode(),
                'customers_city'           => $address->getCity(),
                'customers_country_code'   => $address->getCountry(),
                'customers_dob'            => $customersDob,
                'customers_phone'          => (string)$address->getPhone(),
                'customers_fax'            => '',
                'address_class'            => $addressClass,
                'date_added'               => date('Y-m-d H:i:s'),
                'date_modified'            => date('Y-m-d H:i:s'),
            );

            $fieldMap[$addressClass] = $fieldHandler->getCustomFieldsMap($address, TABLE_CUSTOMERS_ADDRESSES);

            if (version_compare(_SYSTEM_VERSION, '4.1.10', '>=')) {
                $data[$addressKey]["customers_mobile_phone"] = "";
            }

            $data[$addressKey]['customers_street_address'] = $address->getStreet1();
            if ($address->getStreet2()) {
                $data[$addressKey]['customers_street_address'] .= "<br />" . $address->getStreet2();
            }
        }

        /**
         * If ship and billing are different, create default address
         */
        if (count($data) > 1) {
            $data['default_address']                  =
                !empty($data['payment_address'])
                    ? $data['payment_address']
                    : $data['shipping_address'];
            $fieldMap['default']                      =
                !empty($fieldMap['payment'])
                    ? $fieldMap['payment']
                    : $fieldMap['shipping'];
            $data['default_address']['address_class'] = 'default';
        }

        $data['cust_info'] = array(
            'customers_email_address'         => $user,
            'customers_email_address_confirm' => $user,
            'customers_password'              => $pass,
            'customers_password_confirm'      => $pass,
            'password_required'               => 1,
            'guest'                           => 0,
        );

        $veytonCustomer = new customer();
        $result         = $veytonCustomer->_registerCustomer($data, 'both', 'insert', true, false);

        /**
         * Save shopgate custom fields to database
         */
        $tmp = $fieldHandler->getCustomFieldsMap($customer, TABLE_CUSTOMERS);
        if (!empty($tmp)) {
            $db->AutoExecute(TABLE_CUSTOMERS, $tmp, "UPDATE", "customers_id='{$veytonCustomer->data_customer_id}'");
        }

        foreach ($fieldMap as $key => $val) {
            if (!empty($val)) {
                $db->AutoExecute(
                    TABLE_CUSTOMERS_ADDRESSES,
                    $val,
                    "UPDATE",
                    "address_class='{$key}' AND customers_id='{$veytonCustomer->data_customer_id}'"
                );
            }
        }

        $errorTexts = array(
            "Es existiert bereits ein Kundenkonto mit dieser E-Mail Adresse",
            "Учётная запись с этим адресом электронной почты уже существует",
            "Ya existe una cuenta con esta dirección de E-mail",
            "Il existe déjà un compte avec cette adresse e-mail",
            "indirizzo email gia\' esistente",
            "電子メールのレジストリメンバーされている",
            "Mūsų parduotuvėje jau yra registruotas klientas su šiuo e-pašto adresu.",
            "Er bestaat al een klantaccount met dit e-mail-adres",
            "adres e-mailowy w użyciu",
        );

        if (is_array($result) && array_key_exists('error', $result)) {
            //globals variable from veyton which contains error
            global $info;
            $veytonErrorContent = $info->info_content;

            foreach ($errorTexts as $text) {
                if (strpos($veytonErrorContent, $text) !== false) {
                    throw new ShopgateLibraryException(
                        ShopgateLibraryException::REGISTER_USER_ALREADY_EXISTS,
                        $veytonErrorContent, true, true
                    );
                }
            }

            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_REGISTER_CUSTOMER_ERROR,
                $veytonErrorContent, true, true
            );
        }
    }

    public function getCustomer($user, $pass)
    {
        /** @var ADOConnection $db */
        global $db, $language;

        $customerHelper = new ShopgateVeytonHelperCustomer($db);

        // get customer data by email address
        /** @noinspection SqlDialectInspection */
        /** @noinspection PhpToStringImplementationInspection */
        /** @noinspection PhpParamsInspection */
        $qry = "
            SELECT
                customer.customers_id,
                customer.customers_cid,
                customer.customers_password,
                status.customers_status_id,
                status.customers_status_name,
                customer.customers_email_address
            FROM " . TABLE_CUSTOMERS . " AS customer
            JOIN " . TABLE_CUSTOMERS_STATUS . " AS s
                ON customer.customers_status = s.customers_status_id
            JOIN " . TABLE_CUSTOMERS_STATUS_DESCRIPTION . " AS status
                ON (s.customers_status_id = status.customers_status_id AND status.language_code = '"
            . $language->code . "')
            WHERE customers_email_address = " . $db->qstr($user) . "
            LIMIT 1
        ";
        /** @noinspection PhpParamsInspection */
        $result = $db->Execute($qry);

        // check for database errors
        if (empty($result) || !($result instanceof ADORecordSet)) {
            throw new ShopgateLibraryException(ShopgateLibraryException::PLUGIN_DATABASE_ERROR);
        }

        if ($result->RowCount() < 1) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_WRONG_USERNAME_OR_PASSWORD
            );
        }
        $customerData = $result->fields;

        if (!$customerHelper->validatePassword($user, $pass)) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_WRONG_USERNAME_OR_PASSWORD
            );
        }

        // get the customer's address data
        /** @noinspection SqlDialectInspection */
        $qry = "
            SELECT
                address.address_book_id,
                address.address_class,
                address.customers_gender,
                address.customers_firstname,
                address.customers_lastname,
                date_format(address.customers_dob, '%Y-%m-%d') as customers_birthday,
                address.customers_street_address,
                address.customers_suburb,
                address.customers_company,
                address.customers_postcode,
                address.customers_city,
                address.customers_country_code,
                address.customers_phone
            FROM " . TABLE_CUSTOMERS_ADDRESSES . " AS address
            WHERE address.customers_id = {$customerData['customers_id']}
        ";
        /** @noinspection PhpParamsInspection */
        $result = $db->Execute($qry);

        // check for database errors
        if (empty($result) || !($result instanceof ADORecordSet)) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_DATABASE_ERROR,
                'No addresses found. Username: ' . $user
            );
        }

        // check if addresses have been found (although getting the customer data should have failed then)
        if (empty($result) || $result->RowCount() < 1) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_NO_ADDRESSES_FOUND,
                'Username: ' . $user
            );
        } else {
            $addressDatasets = $result;
        }

        // build address objects list
        $addresses      = array();
        $defaultAddress = null;
        while (!empty($addressDatasets) && !$addressDatasets->EOF) {
            $addressData = $addressDatasets->fields;

            try {
                // c = company, we don't use that
                if (!empty($addressData['customers_gender'])
                    && $addressData['customers_gender'] == 'c'
                ) {
                    $addressData['customers_gender'] = null;
                }

                $address = new ShopgateAddress();
                $address->setId($addressData['address_book_id']);
                $address->setGender($addressData['customers_gender']);
                $address->setFirstName($addressData['customers_firstname']);
                $address->setLastName($addressData['customers_lastname']);
                $address->setBirthday($addressData['customers_birthday']);
                $address->setCompany($addressData['customers_company']);
                $address->setStreet1($addressData['customers_street_address']);
                $address->setStreet2($addressData['customers_suburb']);
                $address->setZipcode($addressData['customers_postcode']);
                $address->setCity($addressData['customers_city']);
                $address->setCountry($addressData['customers_country_code']);
                $address->setPhone($addressData['customers_phone']);
            } catch (ShopgateLibraryException $e) {
                // logging is done on exception construction
                continue;
            }

            // set address type
            switch (strtolower($addressData['address_class'])) {
                case 'default':
                    $address->setAddressType(ShopgateAddress::BOTH);
                    $defaultAddress = $address;
                    array_unshift(
                        $addresses,
                        $defaultAddress
                    ); // default address should be the first in line
                    break;

                case 'payment':
                    $address->setAddressType(ShopgateAddress::INVOICE);
                    $addresses[] = $address;
                    break;

                case 'shipping':
                    $address->setAddressType(ShopgateAddress::DELIVERY);
                    $addresses[] = $address;
                    break;

                default:
                    $addresses[] = $address;
            }

            $addressDatasets->MoveNext();
        }

        // check for valid addresses (one invoice and one delivery at least)
        if (empty($addresses) || ((count($addresses) < 2) && empty($defaultAddress))) {
            throw new ShopgateLibraryException(ShopgateLibraryException::PLUGIN_NO_ADDRESSES_FOUND);
        }

        // build customer object and return it
        $customer = new ShopgateCustomer();
        try {
            // there is only one customer group per customer
            $customerGroups = array();
            if (!empty($customerData['customers_status_id']) && !empty($customerData['customers_status_name'])) {
                $customerGroup = new ShopgateCustomerGroup();
                $customerGroup->setId($customerData['customers_status_id']);
                $customerGroup->setName($customerData['customers_status_name']);
                $customerGroups[] = $customerGroup;
            }
            $customer->setCustomerId($customerData["customers_id"]);
            $customer->setCustomerNumber($customerData["customers_cid"]);
            $customer->setCustomerGroups($customerGroups);
            $customer->setGender($defaultAddress->getGender());
            $customer->setFirstName($defaultAddress->getFirstName());
            $customer->setLastName($defaultAddress->getLastName());
            $customer->setMail($customerData["customers_email_address"]);
            $customer->setPhone($defaultAddress->getPhone());
            $customer->setBirthday($defaultAddress->getBirthday());
            $customer->setAddresses($addresses);
            $customer->setCustomerToken($customerHelper->getTokenForCustomer($customer));
        } catch (ShopgateLibraryException $e) {
            // Logging is done on exception construction but getCustomer() should not fail at this point
        }

        return $customer;
    }

    public function createShopInfo()
    {
        /** @var ADOConnection $db */
        global $db;
        $shopInfo = array();

        $result                 = $db->Execute("SELECT count(*) cnt FROM " . TABLE_PRODUCTS);
        $shopInfo['item_count'] = $result->fields['cnt'];

        $result                     = $db->Execute("SELECT count(*) cnt FROM " . TABLE_CATEGORIES);
        $shopInfo['category_count'] = $result->fields['cnt'];

        if (defined('TABLE_PRODUCTS_REVIEWS')) {
            $result                   = $db->Execute("SELECT COUNT(*) AS cnt FROM " . TABLE_PRODUCTS_REVIEWS);
            $shopInfo['review_count'] = $result->fields['cnt'];
        }

        if (defined('TABLE_MEDIA')) {
            $mediaQry                = "SELECT COUNT(*) AS cnt FROM " . TABLE_MEDIA
                . " AS m WHERE m.`class` = 'product' AND m.`type` = 'files'";
            $result                  = $db->Execute($mediaQry);
            $shopInfo['media_count'] = $result->fields['cnt'];
        }

        $pluginQry = "SELECT pp.name,pp.plugin_id AS 'id', pp.`version`, pp.plugin_status AS 'plugin_status'
                      FROM " . TABLE_PLUGIN_PRODUCTS . " AS pp ORDER BY pp.plugin_id";
        $result    = $db->Execute($pluginQry);

        $plugins = array();

        while (!$result->EOF) {
            $plugins[] = $result->fields;
            $result->MoveNext();
        }

        $shopInfo['plugins_installed '] = $plugins;

        return $shopInfo;
    }

    public function addOrder(ShopgateOrder $order)
    {
        $this->_useNativeMobileMode();
        $logPrefix = "[addOrder {$order->getOrderNumber()}] ";

        $this->log($logPrefix . 'inovked...', ShopgateLogger::LOGTYPE_DEBUG);
        /** @var ADOConnection $db */
        global $db, $language;

        $c = new countries();

        $this->log($logPrefix . 'database check shopgate order ...', ShopgateLogger::LOGTYPE_DEBUG);
        /* prüfe ob die Shopgate-Bestellung schon existiert */
        $qry    = "SELECT * FROM " . TABLE_SHOPGATE_ORDERS
            . " WHERE shopgate_order_number = '{$order->getOrderNumber()}'";
        $result = $db->Execute($qry);
        if ($result->RecordCount() > 0) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_DUPLICATE_ORDER,
                "Shopgate order number: '{$order->getOrderNumber()}'."
            );
        }

        // insert shopgate specific order information
        $db->AutoExecute(
            TABLE_SHOPGATE_ORDERS,
            array(
                'shopgate_order_number' => $order->getOrderNumber(),
                'is_paid'               => $order->getIsPaid(),
                'is_shipping_blocked'   => $order->getIsShippingBlocked(),
                'payment_infos'         => $this->jsonEncode(
                    $order->getPaymentInfos()
                ),
                'is_cancellation_sent'  => 0,
                'cancellation_data'     => $this->jsonEncode(array()),
                'modified'              => date('Y-m-d H:i:s'),
                'created'               => date('Y-m-d H:i:s'),
                'order_data'            => serialize($order),
            ),
            'INSERT'
        );

        $shopgateOrderDbId = $db->Insert_ID();

        // if the order contains coupons but the plugin is disable, we need
        // prevent the plugin for inserting the order into the shop system
        if (count($order->getExternalCoupons()) > 0 && !$this->checkPluginHelper->checkPlugin('xt_coupons')) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::UNKNOWN_ERROR_CODE, 'veyton plugin xt_coupons" disabled"', true
            );
        }

        $deliveryAddress = $order->getDeliveryAddress();
        $billingAddress  = $order->getInvoiceAddress();

        $this->log($logPrefix . 'check country is active ...', ShopgateLogger::LOGTYPE_DEBUG);
        // check country is active in shop
        if (!isset($c->countries_list[$deliveryAddress->getCountry()])) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_UNKNOWN_COUNTRY_CODE,
                "'{$deliveryAddress->getCountry()}' not active in shop", true
            );
        }
        if (!isset($c->countries_list[$billingAddress->getCountry()])) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_UNKNOWN_COUNTRY_CODE,
                "'{$billingAddress->getCountry()}' not active in shop", true
            );
        }

        $phone = $order->getPhone();
        if (empty($phone)) {
            $phone = $order->getMobile();
        }

        $customerId = $order->getExternalCustomerId();
        if (empty($customerId)) {
            $customerId = $this->_createGuestUser($order);
        }
        $user = $this->_loadOrderUserById($customerId);

        $orderArr                  = array();
        $orderArr['customers_id']  = $user['customers_id'];
        $orderArr['customers_cid'] = $user['customers_cid'];
        //        $orderArr['customers_vat_id']=0;
        $orderArr['customers_status']        = $user['customers_status'];
        $orderArr['customers_email_address'] = $order->getMail();

        // Fill delivery address information
        $orderArr['delivery_gender']         = $deliveryAddress->getGender();
        $orderArr['delivery_phone']          = $phone;
        $orderArr['delivery_fax']            = '';
        $orderArr['delivery_firstname']      = $deliveryAddress->getFirstName();
        $orderArr['delivery_lastname']       = $deliveryAddress->getLastName();
        $orderArr['delivery_company']        = $deliveryAddress->getCompany();
        $orderArr['delivery_company_2']      = '';
        $orderArr['delivery_company_3']      = '';
        $orderArr['delivery_street_address'] = $deliveryAddress->getStreet1();
        $orderArr['delivery_street_address'] .= ($deliveryAddress->getStreet2())
            ? '<br />' . $deliveryAddress->getStreet2()
            : '';

        $orderArr['delivery_suburb']       = '';
        $orderArr['delivery_city']         = $deliveryAddress->getCity();
        $orderArr['delivery_postcode']     = $deliveryAddress->getZipcode();
        $orderArr['delivery_country']
                                           = $c->countries_list[$deliveryAddress->getCountry()]["countries_name"];
        $orderArr['delivery_country_code'] = $deliveryAddress->getCountry();

        $orderArr['billing_gender']         = $billingAddress->getGender();
        $orderArr['billing_phone']          = $phone;
        $orderArr['billing_fax']            = '';
        $orderArr['billing_firstname']      = $billingAddress->getFirstName();
        $orderArr['billing_lastname']       = $billingAddress->getLastName();
        $orderArr['billing_company']        = $billingAddress->getCompany();
        $orderArr['billing_company_2']      = '';
        $orderArr['billing_company_3']      = '';
        $orderArr['billing_street_address'] = $billingAddress->getStreet1();
        $orderArr['billing_street_address'] .= ($billingAddress->getStreet2())
            ?
            "<br />" . $billingAddress->getStreet2()
            : '';

        $orderArr['billing_suburb']   = '';
        $orderArr['billing_city']     = $billingAddress->getCity();
        $orderArr['billing_postcode'] = $billingAddress->getZipcode();
        $orderArr['billing_zone']     = '';
        //$orderArr['billing_zone_code']            = ""; // TODO: Kommt bei der neuen API mit
        $orderArr['billing_country']
                                          = $c->countries_list[$billingAddress->getCountry()]['countries_name'];
        $orderArr['billing_country_code'] = $billingAddress->getCountry();

        $shippingCode = 'Standard';

        //id is set in <name> field
        //name is set in <displayName> field
        //<id> field does not exist
        if ($order->getShippingType() == self::SHIPPING_TYPE_API) {
            $shippingId     = null;
            $veytonShipping = new shipping();
            $shippingInfos  = $order->getShippingInfos();

            if (ctype_digit($shippingInfos->getName())) {
                $shippingId = (int)$shippingInfos->getName();
            }

            foreach ($veytonShipping->_getPossibleShipping() as $shippingMethod) {
                if (!empty($shippingId) && $shippingId == (int)$shippingMethod['shipping_id']) {
                    $shippingCode = $shippingMethod['shipping_code'];
                    break;
                }
                if ($shippingMethod['shipping_name'] == $shippingInfos->getDisplayName()) {
                    $shippingCode = $shippingMethod['shipping_code'];
                    break;
                }
            }
        } elseif ($order->getShippingType() == self::SHIPPING_TYPE_MANUAL) {
            $veytonShipping = new shipping();
            foreach ($veytonShipping->_getPossibleShipping() as $shippingMethod) {
                if ($order->getShippingInfos()->getName() == $shippingMethod['shipping_code']) {
                    $shippingCode = $order->getShippingInfos()->getName();
                }
            }
        }

        $orderArr['shipping_code']  = $shippingCode;
        $orderArr['currency_code']  = $order->getCurrency();
        $orderArr['currency_value'] = 1;
        $orderArr['language_code']  = $language->code;
        $orderArr['comments']       = 'Added By Shopgate' . date('Y-m-d H:i:s');
        $orderArr['last_modified']  = date('Y-m-d H:i:s');
        $orderArr['account_type']   = $user['account_type'];
        $orderArr['allow_tax']      = true;
        $orderArr['customers_ip']   = '';
        $orderArr['shop_id']        = $this->config->getStoreId();

        $fieldHandler = new ShopgateCustomFieldModel();
        $data         = array('shopgate_order_number' => $order->getOrderNumber());

        /**
         * 1. Saves fields to order table if columns exist
         * 2. Print fields that were not saved to order table
         */
        $objects = array(
            'xt_shopgate_custom_fields_order'            => $order,
            'xt_shopgate_custom_fields_shipping_address' => $order->getDeliveryAddress(),
            'xt_shopgate_custom_fields_billing_address'  => $order->getInvoiceAddress(),
        );
        foreach ($objects as $key => $object) {
            $saved      = $fieldHandler->getCustomFieldsMap($object);
            $data[$key] = $fieldHandler->buildCustomFieldsHtml($object, $saved);
            $orderArr   = array_merge($orderArr, $saved);
        }

        $orderArr['orders_data']    = serialize($data);
        $orderArr['date_purchased'] = $order->getCreatedTime('Y-m-d H:i:s');
        $orderArr['orders_status']  = $this->config->getOrderStatusOpen();

        // save the order
        $this->log($logPrefix . 'database insert order ...', ShopgateLogger::LOGTYPE_DEBUG);
        $db->AutoExecute(TABLE_ORDERS, $orderArr, "INSERT");

        $dbOrderId = $db->Insert_ID();

        $this->log($logPrefix . 'database insert order items ...', ShopgateLogger::LOGTYPE_DEBUG);
        $itemInsertIds = $this->_insertOrderItems(
            $order,
            $dbOrderId,
            $orderArr['orders_status']
        );

        $this->log($logPrefix . 'database insert order total ...', ShopgateLogger::LOGTYPE_DEBUG);
        $totalsInsertIds = $this->_insertOrderTotal(
            $order,
            $dbOrderId,
            $orderArr['orders_status']
        );

        $this->log($logPrefix . 'database insert total ...', ShopgateLogger::LOGTYPE_DEBUG);
        $this->_insertOrderCoupons(
            $order,
            $dbOrderId,
            $currentOrderStatus,
            $itemInsertIds,
            $totalsInsertIds
        );

        $this->log($logPrefix . 'database insert order status ...', ShopgateLogger::LOGTYPE_DEBUG);
        $this->_insertOrderStatus(
            $order,
            $dbOrderId,
            $orderArr['orders_status']
        );

        $paymentCode = $this->_setOrderPayment($order, $dbOrderId, $user['customers_id'], $orderArr['orders_status']);

        $settingsHelper = new ShopgateVeytonHelperSettings($db);
        $cfgStatus      = $settingsHelper->getPaymentConfigStatus(
            $paymentCode,
            $order->getIsPaid(),
            $this->config->getStoreId()
        );
        if ($cfgStatus) {
            $orderArr['orders_status'] = $cfgStatus;
            $comment                   = 'Nutzt Status Mapping aus Einstellung -> Zahlungsweise';
            $this->_addOrderStatus($dbOrderId, $orderArr['orders_status'], $comment);
        }

        $this->_updateItemsStock($order);

        // update order status
        $updateOrderArr                  = array();
        $updateOrderArr['orders_status'] = $orderArr['orders_status'];
        $db->AutoExecute(
            TABLE_ORDERS,
            $updateOrderArr,
            'UPDATE',
            "orders_id = {$dbOrderId}"
        );

        $this->log($logPrefix . 'database insert shopgate order table entry ...', ShopgateLogger::LOGTYPE_DEBUG);

        // update shopgate order table with order id
        $db->AutoExecute(
            TABLE_SHOPGATE_ORDERS,
            array(
                'orders_id' => $dbOrderId,
            ),
            'UPDATE',
            'shopgate_orders_id = ' . $shopgateOrderDbId
        );

        $this->log($logPrefix . 'database insert order stats ...', ShopgateLogger::LOGTYPE_DEBUG);

        // save the order complete amount
        $db->AutoExecute(
            TABLE_ORDERS_STATS,
            array(
                'orders_id'          => $dbOrderId,
                'orders_stats_price' => $order->getAmountComplete(),
                'products_count'     => count($order->getItems()),
            ),
            'INSERT'
        );

        $this->pushOrderToAfterBuy($dbOrderId, $order);

        if ($this->config->getSendOrderConfirmationMail()) {
            $this->log($logPrefix . 'try to send order confirmation mail...', ShopgateLogger::LOGTYPE_DEBUG);
            if (!empty($dbOrderId) && isset($user['customers_id'])
                && !empty($user['customers_id'])
            ) {
                $sent_order_mail = new order($dbOrderId, $user['customers_id']);

                if ($sent_order_mail->_sendOrderMail()) {
                    $this->log($logPrefix . 'sent', ShopgateLogger::LOGTYPE_DEBUG);
                } else {
                    $this->log($logPrefix . 'not sent', ShopgateLogger::LOGTYPE_DEBUG);
                }
            } else {
                $this->log(
                    $logPrefix . "order id [{$dbOrderId}] or user id [{$user["customers_id"]}] is not set.",
                    ShopgateLogger::LOGTYPE_DEBUG
                );
            }
        }

        return array(
            'external_order_id'     => $dbOrderId,
            'external_order_number' => $dbOrderId,
        );
    }

    /**
     * creates and stores a guest user in the database
     *
     * @param ShopgateOrder $order
     */
    private function _createGuestUser(ShopgateOrder $order)
    {
        $this->log("start _createGuestUser() ...", ShopgateLogger::LOGTYPE_DEBUG);
        global $db;

        /** @var ShopgateAddress $invoiceAddress */
        $invoiceAddress = $order->getInvoiceAddress();

        // Create guest customer
        $customer = $this->createGuestUserCostumer($order, $invoiceAddress);
        $this->log("database insert customer ...", ShopgateLogger::LOGTYPE_DEBUG);
        $db->AutoExecute(TABLE_CUSTOMERS, $customer, "INSERT");

        $customerId = $db->Insert_ID();

        // Insert guest address
        $guestUserDefaultAddress = $this->createGuestUserAddress($customerId, $invoiceAddress, 'default');
        $this->log("database insert customer addresses 1 ...", ShopgateLogger::LOGTYPE_DEBUG);
        $db->AutoExecute(TABLE_CUSTOMERS_ADDRESSES, $guestUserDefaultAddress, "INSERT");

        /// Insert shipping address
        $deliveryAddress          = $order->getDeliveryAddress();
        $guestUserShippingAddress = $this->createGuestUserAddress($customerId, $deliveryAddress, 'shipping');
        $this->log("database insert customer addresses 2 ...", ShopgateLogger::LOGTYPE_DEBUG);
        $db->AutoExecute(TABLE_CUSTOMERS_ADDRESSES, $guestUserShippingAddress, "INSERT");

        /// Insert payment address
        $guestUserPaymentAddress = $this->createGuestUserAddress($customerId, $invoiceAddress, 'payment');
        $this->log("database insert customer addresses 3 ...", ShopgateLogger::LOGTYPE_DEBUG);
        $db->AutoExecute(TABLE_CUSTOMERS_ADDRESSES, $guestUserPaymentAddress, "INSERT");

        $this->log("end _createGuestUser() ...", ShopgateLogger::LOGTYPE_DEBUG);

        return $customerId;
    }

    /**
     * @param ShopgateOrder $order
     * @param               $invoiceAddress
     *
     * @return mixed
     */
    private function createGuestUserCostumer(ShopgateOrder $order, $invoiceAddress)
    {
        $customer                            = array();
        $customer["customers_status"]        = _STORE_CUSTOMERS_STATUS_ID_GUEST; // TODO
        $customer["customers_email_address"] = utf8_decode($order->getMail());

        $customer["customers_cid"]              = '';
        $customer["customers_vat_id"]           = '';
        $customer["customers_vat_id_status"]    = 0;
        $customer["date_added"]                 = date('Y-m-d H:i:s');
        $customer["last_modified"]              = date('Y-m-d H:i:s');
        $customer["shop_id"]                    = $this->config->getStoreId();
        $customer["customers_default_currency"] = strtoupper(
            $order->getCurrency()
        );
        $customer["customers_default_language"] = strtolower(
            $invoiceAddress->getCountry()
        );
        //        $customer["campaign_id"] = 0;
        $customer["payment_unallowed"]  = "";
        $customer["shipping_unallowed"] = "";

        return $customer;
    }

    /**
     * Returns addresses for guest users by $addressClass
     *
     * @param int             $customerId
     * @param ShopgateAddress $shopgateAddress
     * @param string          $addressClass
     *
     * @return array
     */
    private function createGuestUserAddress($customerId, ShopgateAddress $shopgateAddress, $addressClass)
    {
        $guestUserAddress = array(
            'external_id'              => null,
            'customers_id'             => $customerId,
            'customers_gender'         => $shopgateAddress->getGender(),
            'customers_phone'          => (string)$shopgateAddress->getPhone(),
            'customers_fax'            => '',
            'customers_company'        => (string)$shopgateAddress->getCompany(),
            'customers_company_2'      => '',
            'customers_company_3'      => '',
            'customers_firstname'      => $shopgateAddress->getFirstName(),
            'customers_lastname'       => $shopgateAddress->getLastName(),
            'customers_street_address' => $shopgateAddress->getStreet1(),
            'customers_suburb'         => '',
            'customers_postcode'       => $shopgateAddress->getZipcode(),
            'customers_city'           => $shopgateAddress->getCity(),
            'customers_country_code'   => $shopgateAddress->getCountry(),
            'customers_dob'            => (!is_null($shopgateAddress->getBirthday()))
                ? date('d.m.Y', strtotime('1801-01-01'))
                : date('d.m.Y', strtotime($shopgateAddress->getBirthday())),
            'customers_dob'            => $shopgateAddress->getBirthday(),
            'address_class'            => $addressClass,
            'date_added'               => date('Y-m-d H:i:s'),
            'last_modified'            => date('Y-m-d H:i:s'),
        );

        if ($addressClass == 'default') {
            $guestUserAddress['customers_street_address'] = $shopgateAddress->getStreet1();
            if ($shopgateAddress->getStreet2()) {
                $guestUserAddress['customers_street_address'] .= '<br />' . $shopgateAddress->getStreet2();
            }
        }

        if (version_compare(_SYSTEM_VERSION, '4.1.10', '>=')) {
            $guestUserAddress['customers_mobile_phone'] = '';
        }

        return $guestUserAddress;
    }

    /**
     * read a customers data from the database by the uid
     *
     * @param int $userid
     *
     * @return null
     */
    private function _loadOrderUserById($userid)
    {
        $this->log("start _loadOrderUserById() ...", ShopgateLogger::LOGTYPE_DEBUG);
        /** @var ADOConnection $db */
        global $db;

        if (empty($userid)) {
            return null;
        }

        $qry    = "SELECT c.* FROM " . TABLE_CUSTOMERS . " c WHERE c.customers_id = " . $userid . " ";
        $result = $db->Execute($qry);
        $user   = $result->fields;

        $this->log(
            "end _loadOrderUserById() ...",
            ShopgateLogger::LOGTYPE_DEBUG
        );

        return $user;
    }

    /**
     * stores all products to an order in the database
     *
     * @param ShopgateOrder $order
     * @param int           $dbOrderId
     * @param               $currentOrderStatus
     *
     * @return array
     */
    private function _insertOrderItems(ShopgateOrder $order, $dbOrderId, &$currentOrderStatus)
    {
        /** @var ADOConnection $db */
        global $db;
        $this->log("start insertOrderItems() ...", ShopgateLogger::LOGTYPE_DEBUG);

        $errors        = '';
        $items         = $order->getItems();
        $itemInsertIds = array();
        foreach ($items as $item) {
            $products_model = $item->getItemNumber();

            $orderInfo = $item->getInternalOrderInfo();
            $orderInfo = $this->jsonDecode($orderInfo, true);

            $product = $this->_loadProduct($products_model);

            if (empty($product) && $products_model == 'COUPON') {
                // workaround for shopgate coupons
                $product                   = array();
                $product['products_id']    = 0;
                $product['products_model'] = $products_model;
            } else {
                if (empty($product)) {
                    $this->log(
                        ShopgateLibraryException::buildLogMessageFor(
                            ShopgateLibraryException::PLUGIN_ORDER_ITEM_NOT_FOUND,
                            'Shopgate-Order-Number: ' . $order->getOrderNumber()
                            . ', DB-Order-Id: ' . $dbOrderId
                            . '; item (item_number: ' . $products_model
                            . '). The item will be skipped.'
                        )
                    );
                    $errors .= "\nItem (item_number: " . $products_model
                        . ") can not be found in your shoppingsystem. Please contact Shopgate. The item will be skipped.";

                    $product['products_id']    = 0;
                    $product['products_model'] = $products_model;
                }
            }

            $itemArr                       = array();
            $itemArr["orders_id"]          = $dbOrderId;
            $itemArr["products_id"]        = $product['products_id'];
            $itemArr["products_model"]     = $product['products_model'];
            $itemArr["products_name"]      = $item->getName();
            $itemArr["products_price"]
                                           =
                $item->getUnitAmountWithTax() / (1 + ($item->getTaxPercent()
                        / 100));
            $itemArr["products_tax"]       = $item->getTaxPercent();
            $itemArr["products_tax_class"] = $this->_getTaxClassByTaxRate(
                $item->getTaxPercent()
            );
            $itemArr["products_quantity"]  = $item->getQuantity();
            $itemArr["allow_tax"]          = true;

            $options = $item->getOptions();
            $inputs  = $item->getInputs();

            if ((!empty($options) || !empty($inputs))
                && $this->checkPluginHelper->checkPlugin('xt_product_options', '2.4.0')
            ) {
                $obj   = array();
                $xtpop = new product(
                    $product['products_id'], null, $item->getQuantity()
                );
                $xtpo  = new xt_product_options(
                    $xtpop->data['products_id'], $xtpop->data
                );

                $xtpo->oData           = $xtpo->_getData();
                $xtpo->oDataGrouped    = $xtpo->_reGroupoData();
                $xtpo->possible_groups = $xtpo->_buildPossibleGroups();
                $xtpo->possible_values = $xtpo->_buildPossibleValues();
                $xtpo->groups          = $xtpo->_getGroups();
                $xtpo->values          = $xtpo->_getValues();

                if (is_array($options)) {
                    foreach ($options as $option) {
                        $optionNumber = explode(
                            '_',
                            $option->getOptionNumber()
                        );

                        if (count($optionNumber) > 1) {
                            // => checkbox
                            $obj['products_info'][$product['products_id']][$optionNumber[0]]
                                = $optionNumber[1];
                        } elseif ($fieldtype = $xtpo->_getFieldType(
                                $xtpo->groups[$option->getOptionNumber()]['option_group_field'],
                                $xtpo->values[$option->getOptionNumber()][$option->getValueNumber(
                                )]['option_value_field']
                            ) == 'select'
                        ) {
                            // => select
                            $obj['products_info'][$product['products_id']][$option->getOptionNumber() . 'SELECT']
                                = $option->getValueNumber();
                        } elseif ($fieldtype = $xtpo->_getFieldType(
                                $xtpo->groups[$option->getOptionNumber()]['option_group_field'],
                                $xtpo->values[$option->getOptionNumber()][$option->getValueNumber(
                                )]['option_value_field']
                            ) == 'radio'
                        ) {
                            // => radio
                            $obj['products_info'][$product['products_id']][$option->getOptionNumber()]
                                = $option->getValueNumber();
                        }
                    }
                }

                if (is_array($inputs)) {
                    foreach ($inputs as $input) {
                        $obj['products_info'][$product['products_id']][$input->getInputNumber()]
                            = $input->getUserInput();
                    }
                }

                $obj['product']     = $product['products_id'];
                $obj['products_id'] = $product['products_id'];

                $pdata        = $xtpop->data;
                $product_info = $xtpo->_rebuildOptionsData(
                    $obj,
                    $product['products_id']
                );

                $arrnew = $xtpo->_reGroupoData();
                foreach ($product_info['options'] as $key => $val) {
                    $group_data = $xtpo->_getGroupData($val['option_group_id']);
                    $value_data = $xtpo->_getValueData($val['option_value_id']);

                    $key = explode("_", $key);

                    if (is_array($arrnew[$key[0]][$key[1]]) && is_array($val)
                        && is_array($group_data)
                        && is_array($value_data)
                    ) {
                        $product_info['options'][$key[0] . '_' . $key[1]]
                            = array_merge(
                            $val,
                            $arrnew[$key[0]][$key[1]],
                            $group_data,
                            $value_data
                        );
                    }

                    $product_info['options'][$key[0] . '_'
                    . $key[1]]['products_price']
                        = $pdata['products_price'];
                    $product_info['options'][$key[0] . '_'
                    . $key[1]]['products_tax_value']
                        = $pdata['products_tax_rate'];
                }

                $itemArr["products_data"] = $product_info;
            }

            if (!isset($itemArr["products_data"]["options"])) {
                // Needed for afterbuy_pimpmyxt
                $itemArr["products_data"]["options"] = array();
            }

            // add refund
            $itemArr = $this->_insertOrderItemsAddRefund(
                $itemArr,
                $item,
                $order->getCurrency()
            );

            $itemArr['products_data'] = serialize($itemArr['products_data']);

            $db->AutoExecute(TABLE_ORDERS_PRODUCTS, $itemArr, "INSERT");
            $itemInsertIds[$product['products_id']] = $db->Insert_ID();
        }

        if (!empty($errors)) {
            $this->_addOrderStatus(
                $dbOrderId,
                $currentOrderStatus,
                'Beim Importieren der Bestellung sind Fehler aufgetreten: '
                . $errors
            );
        }

        $this->log("end insertOrderItems() ...", ShopgateLogger::LOGTYPE_DEBUG);

        return $itemInsertIds;
    }

    /**
     * read product data from the database by a products model
     *
     * @param $products_model
     *
     * @return mixed
     */
    private function _loadProduct($products_model)
    {
        /** @var ADOConnection $db */
        global $db;

        $qry    = "SELECT p.* FROM " . TABLE_PRODUCTS . " p WHERE p.products_id = '$products_model'";
        $result = $db->Execute($qry);

        return $result->fields;
    }

    /**
     * read the tax class data from the database by the tax rate
     *
     * @param $rate
     *
     * @return int
     */
    private function _getTaxClassByTaxRate($rate)
    {
        /** @var ADOConnection $db */
        global $db;

        $rate  = (float)$rate;
        $qry   = "SELECT tax_class_id FROM " . TABLE_TAX_RATES . " WHERE tax_rate = $rate";
        $class = $db->GetOne($qry);

        if (empty($class)) {
            $class = 1;
        }

        return $class;
    }

    /**
     * currently this function does nothing
     *
     * @param                   $itemArr
     * @param ShopgateOrderItem $item
     * @param                   $currency
     *
     * @return mixed
     */
    private function _insertOrderItemsAddRefund($itemArr, ShopgateOrderItem $item, $currency)
    {
        return $itemArr;
    }

    /**
     * stores a comment to the history of an order in the database
     *
     * @param int    $dbOrderId
     * @param int    $orders_status
     * @param string $comment
     * @param int    $customer_show_comment
     * @param int    $customer_notified
     */
    private function _addOrderStatus(
        $dbOrderId,
        $orders_status,
        $comment,
        $customer_show_comment = 0,
        $customer_notified = 0
    ) {
        /** @var ADOConnection $db */
        global $db;

        static $commentNr = 0;

        $status['orders_id']             = $dbOrderId;
        $status['orders_status_id']      = $orders_status;
        $status['customer_notified']     = (int)$customer_notified;
        $status['date_added']            = date(
            "Y-m-d H:i:s",
            time() + $commentNr++
        );
        $status['comments']              = $comment;
        $status['change_trigger']        = 'shopgate';
        $status['callback_id']           = '0';
        $status['customer_show_comment'] = (int)$customer_show_comment;
        $db->AutoExecute(TABLE_ORDERS_STATUS_HISTORY, $status, "INSERT");
    }

    /**
     * stores the totals to an order in the database
     * totals e.g.:
     *  - refund
     *  - bulk
     *  - payment
     *
     * @param ShopgateOrder $order
     * @param               $dbOrderId
     *
     * @return array
     */
    private function _insertOrderTotal(ShopgateOrder $order, $dbOrderId)
    {
        $this->log(
            "start insertOrderTotal() ...",
            ShopgateLogger::LOGTYPE_DEBUG
        );
        /** @var ADOConnection $db */
        global $db;

        $shippingTaxPercent = $order->getShippingTaxPercent();
        $shippingInfos      = $order->getShippingInfos();
        $totals             = array();
        $insertTotalsIds    = array();

        $totals['shipping'] = array(
            'orders_total_key'       => 'shipping',
            'orders_total_key_id'    => '1',
            'orders_total_model'     => 'Standard',
            'orders_total_name'      => $order->getShippingType() == self::SHIPPING_TYPE_API
                ? $shippingInfos->getDisplayName()
                : 'Versand',
            'orders_total_price'     => $order->getAmountShipping() / (1
                    + ($shippingTaxPercent / 100)),
            'orders_total_tax'       => $shippingTaxPercent,
            'orders_total_tax_class' => $this->_getTaxClassByTaxRate(
                $shippingTaxPercent
            ),
            'orders_total_quantity'  => "1",
            // nur einmal Versand berechnen != count($order->getOrderItems());
            'allow_tax'              => "1",
        );

        $totals = $this->_insertOrderTotalAddBulkCharge($totals, $order);
        $totals = $this->_insertOrderTotalAddRefund($totals, $order);

        if ($order->getAmountShopPayment() != 0) {
            $paymentInfos      = $order->getPaymentInfos();
            $totals['payment'] = array(
                'orders_total_key'       => 'payment',
                'orders_total_key_id'    => '2',
                'orders_total_model'     => 'Standard',
                'orders_total_name'      => 'Zahlungsartkosten'
                    . (!empty($paymentInfos['shopgate_payment_name'])
                        ?
                        ' (' . $paymentInfos['shopgate_payment_name'] . ')'
                        : ''),
                'orders_total_price'     =>
                    $order->getAmountShopPayment() / (100
                        + $order->getPaymentTaxPercent()) * 100,
                'orders_total_tax'       => $order->getPaymentTaxPercent(),
                'orders_total_tax_class' => $this->_getTaxClassByTaxRate(
                    $order->getPaymentTaxPercent()
                ),
                'orders_total_quantity'  => '1',
                'allow_tax'              => '1',
            );
        }

        // run through all the totals, add the order's ID and save it into the database
        foreach ($totals as $key => $total) {
            $total['orders_id'] = $dbOrderId;
            $db->AutoExecute(TABLE_ORDERS_TOTAL, $total, 'INSERT');
            $insertTotalsIds[$key] = $db->Insert_ID();
        }
        $this->log("end insertOrderTotal() ...", ShopgateLogger::LOGTYPE_DEBUG);

        return $insertTotalsIds;
    }

    /**
     * Adds bulk charge to the order if the plugin is enabled.
     *
     * @param array         $totals The list of order total arrays to be modified for export.
     * @param ShopgateOrder $order  The ShopgateOrder object to import.
     *
     * @return mixed The modified list of order total arrays for export.
     *
     */
    private function _insertOrderTotalAddBulkCharge($totals, ShopgateOrder $order)
    {
        global $price, $db;

        if (!$this->checkPluginHelper->checkPlugin('xt_sperrgut')) {
            return $totals;
        }

        $cartHelper = new ShopgateVeytonHelperCart();
        $bulkPrice  = $cartHelper->getBulkPrice($order);
        $bulkAmount = $bulkPrice['plain_otax'];

        // add the bulk charge row to orders total
        $tax = $price->buildPriceData($bulkAmount, XT_SPERRGUT_TAX_CLASS);

        // remove the bulk charge from the total shipping amount
        $totals['shipping']['orders_total_price'] -= $bulkAmount;
        $totals['bulk_charge'] = array(
            'orders_total_key'       => 'xt_sperrgut',
            'orders_total_key_id'    => '',
            'orders_total_model'     => '',
            'orders_total_name'      => 'Sperrgutzuschlag',
            'orders_total_price'     => $bulkAmount,
            'orders_total_tax'       => $tax['tax_rate'],
            'orders_total_tax_class' => XT_SPERRGUT_TAX_CLASS,
            'orders_total_quantity'  => '1',
            'allow_tax'              => '1',
        );

        return $totals;
    }

    /**
     * Adds refund pricing to the order if the plugin is enabled.
     *
     * @param               $totals The list of order total arrays to be modified for export.
     * @param ShopgateOrder $order  The ShopgateOrder object to import.
     *
     * @return mixed The modified list of order total arrays for export.
     */
    private function _insertOrderTotalAddRefund($totals, ShopgateOrder $order)
    {
        return $totals;
    }

    /**
     * redeems the coupons from the shop system and adds the information to the order
     * no need to handle shopgate coupons cause they were submitted through the items
     *
     * @param \ShopgateOrder $order
     * @param                $dbOrderId
     * @param                $currentOrderStatus
     * @param                $itemInsertIds
     *
     * @throws \ShopgateLibraryException
     */
    private function _insertOrderCoupons(
        ShopgateOrder $order,
        $dbOrderId,
        &$currentOrderStatus,
        $itemInsertIds,
        $totalsInsertIds
    ) {
        /** @var ADOConnection $db */
        global $db;

        $coupons = $order->getExternalCoupons();

        if (empty($coupons)) {
            return;
        }

        // sort the coupons by sort order value
        if (count($coupons) > 1) {
            usort($coupons, 'sortArraysByArrayValue');
        }

        $cartHelper = new ShopgateVeytonHelperCart();
        $cartHelper->buildVeytonShoppingCart($order->getItems(), $order->getExternalCustomerId());
        $cartHelper->includeVeytonCouponClasses();

        foreach ($coupons as $coupon) {
            $veytonCoupon = new xt_coupons();
            $result       = $veytonCoupon->_check_coupon_avail($coupon->getCode());

            if (!$result) {
                if (!empty($veytonCoupon->error_info)) {
                    $errorMessage = $veytonCoupon->error_info;
                } else {
                    $errorMessage = "Unknown coupon error [{$coupon->getCode()}]";
                }
                $this->log('[addOrder] Error: ' . $errorMessage, ShopgateLogger::LOGTYPE_ERROR);
                throw new ShopgateLibraryException(
                    ShopgateLibraryException::COUPON_NOT_VALID, $errorMessage, true
                );
            }

            $this->log('[addOrder] coupons found, starting to redeem...', ShopgateLogger::LOGTYPE_DEBUG);
            $discountValueGross = $cartHelper->getCouponDiscountGrossFromCart(
                $order->getItems(),
                $result,
                'addOrder'
            );

            $couponData = array(
                'coupon_id'       => $result['coupon_id'],
                'coupon_token_id' => $result['coupons_token_id'],
                'redeem_date'     => $order->getCreatedTime(),
                'redeem_ip'       => '',
                'customers_id'    => $order->getExternalCustomerId(),
                'order_id'        => $dbOrderId,
                'redeem_amount'   => $discountValueGross,
            );

            $db->AutoExecute(TABLE_COUPONS_REDEEM, $couponData, "INSERT");

            // If the coupon represents free shipping
            // this was considered in the check_cart request
            // so no need to handle this case in here
            if (((int)$result['coupon_free_shipping']) == 0) {
                if (!empty($result['coupon_percent'])) {
                    $discountAmount      = $result['coupon_percent'];
                    $couponHasPercentage = true;
                } else {
                    $discountAmount      = $result['coupon_amount'];
                    $couponHasPercentage = false;
                }

                $this->log(
                    '[addOrder] coupon' . $coupon->getCode()
                    . ' represents free shipping discount, amount:' . $discountAmount,
                    ShopgateLogger::LOGTYPE_DEBUG
                );

                // here the coupon got percental discount
                // This amount is used to every item in the basket
                if (!empty($itemInsertIds) && $couponHasPercentage) {
                    // check if coupons point to specified products
                    $couponProductIds           = $cartHelper->getCouponProductIds($result['coupon_id']);
                    $couponCategoriesProductIds = $cartHelper->getCouponCategoriesProductIds(
                        $result['coupon_id'],
                        $this->shopId
                    );

                    foreach ($order->getItems() as $sgItem) {
                        $productId = $sgItem->getItemNumber();

                        if (!isset($itemInsertIds[$productId])) {
                            continue;
                        }

                        if (
                            $this->couponHasRestrictions($couponProductIds, $couponCategoriesProductIds)
                            && !$this->couponValidForProduct($productId, $couponProductIds)
                            && !$this->couponValidForCategory($productId, $couponCategoriesProductIds)
                        ) {
                            continue;
                        }

                        $insertId          = $itemInsertIds[$productId];
                        $reducedItemAmount =
                            $sgItem->getUnitAmount() * ((100 - $result['coupon_percent']) / 100);
                        $cartHelper->updateOrderProduct(
                            $discountAmount,
                            $reducedItemAmount,
                            $insertId
                        );
                    }
                } elseif ($result['coupon_amount'] && !$couponHasPercentage) {
                    $taxClass = $result['coupon_tax_class'];
                    $tax      = new tax();
                    $taxRates = $tax->_getTaxRates($taxClass);

                    $couponData = array(
                        'orders_total_key'       => 'xt_coupon',
                        'orders_total_name'      => TEXT_XT_COUPON_TITLE,
                        'orders_total_tax'       => $taxRates,
                        'orders_total_tax_class' => $taxClass,
                        'orders_id'              => $dbOrderId,
                        'orders_total_quantity'  => 1.00,
                        'allow_tax'              => 0,
                        'orders_total_price'     => ($discountAmount > 0)
                            ? $discountAmount * (-1)
                            : $discountAmount,
                    );
                    $db->AutoExecute(TABLE_ORDERS_TOTAL, $couponData, "INSERT");
                }

                if (!empty($veytonCoupon->coupon_data['coupon_free_on_100_status'])
                    && $veytonCoupon->coupon_data['coupon_free_on_100_status'] == '1'
                    && $_SESSION['cart']->cart_total_full <= $_SESSION['cart']->total_discount
                ) {
                    $cartHelper->updateOrderTotalShipping($totalsInsertIds['shipping']);
                }
            } elseif (((int)$result['coupon_free_shipping']) == 1 || $coupon->getIsFreeShipping()) {
                $cartHelper->updateOrderTotalShipping($totalsInsertIds['shipping']);
            }

            if (!empty($result['coupons_token_id'])) {
                // setting this field causes the coupon is marked as used
                $db->AutoExecute(
                    TABLE_COUPONS_TOKEN,
                    array('coupon_token_order_id' => $dbOrderId),
                    "UPDATE",
                    "coupons_token_id = {$result['coupons_token_id']}"
                );
            }

            // by default veyton supports only one coupon
            // we took the first one based on the sort order
            // submitted by shopgate
            break;
        }
    }

    /**
     * @param int[] $couponProductIds
     * @param int[] $couponCategoriesProductIds
     *
     * @return bool
     */
    private function couponHasRestrictions($couponProductIds, $couponCategoriesProductIds)
    {
        return !empty($couponProductIds) || !empty($couponCategoriesProductIds);
    }

    /**
     * @param int   $productId
     * @param int[] $couponProductIds
     *
     * @return bool
     */
    private function couponValidForProduct($productId, $couponProductIds)
    {
        return in_array($productId, $couponProductIds);
    }

    /**
     * @param int   $productId
     * @param int[] $couponCategoriesProductIds
     *
     * @return bool
     */
    private function couponValidForCategory($productId, $couponCategoriesProductIds)
    {
        return in_array($productId, $couponCategoriesProductIds);
    }

    /**
     * stores the status to an order in the database
     *
     * @param ShopgateOrder $order
     * @param               $dbOrderId
     * @param               $currentOrderStatus
     */
    private function _insertOrderStatus(ShopgateOrder $order, $dbOrderId, &$currentOrderStatus)
    {
        $this->log("start insertOrderStatus() ...", ShopgateLogger::LOGTYPE_DEBUG);

        /** @var ADOConnection $db */
        global $db;

        // set the status
        if ($order->getIsShippingBlocked() == 0) {
            $commentShipping = "<br/>Die Bestellung ist von Shopgate freigegeben.<br/>";
        } else {
            $currentOrderStatus = $this->config->getOrderStatusShippingBlocked();
            $commentShipping    = "<br/>Die Bestellung ist von Shopgate noch nicht freigegeben.<br/>";
        }

        // order is added and visible for customer
        $comment = "Bestellung von Shopgate hinzugefügt.";
        $this->_addOrderStatus($dbOrderId, $currentOrderStatus, $comment, 1, 1);

        $comment = '';
        if ($order->getIsTest()) {
            $comment .= "#### DIES IST EINE TESTBESTELLUNG ####<br/>";
        }
        $comment .= "Bestellnummer: " . $order->getOrderNumber() . '<br/>';

        $paymentTransactionNumber = $order->getPaymentTransactionNumber();
        if (!empty($paymentTransactionNumber)) {
            $comment
                .= "Payment-Transaktionsnummer: " . $paymentTransactionNumber
                . '<br/>';
        }

        $comment .= $commentShipping;

        // the invoice must not been sent twice to prevent false payments!
        // add a comment to inform the merchant about that fact
        if ($order->getIsCustomerInvoiceBlocked()) {
            $comment .= '<br/><b>Achtung:</b><br/>Für diese Bestellung wurde bereits eine Rechnung versendet.'
                . '<br/>Um eine reibungslose Abwicklung zu gewährleisten, <b>darf keine</b> weitere '
                . 'Rechnung an den Kunden versendet werden!<br/>';
        }

        $this->_addOrderStatus($dbOrderId, $currentOrderStatus, $comment);
        $this->log("end insertOrderStatus() ...", ShopgateLogger::LOGTYPE_DEBUG);
    }

    /**
     * store payment data to an order in the database
     *
     * @param ShopgateOrder $order
     * @param int           $dbOrderId
     * @param null|int      $userId
     * @param string        $currentOrderStatus
     *
     * @return string
     */
    private function _setOrderPayment(ShopgateOrder $order, $dbOrderId, $userId = null, &$currentOrderStatus)
    {
        $this->log(
            'start _setOrderPayment() ...',
            ShopgateLogger::LOGTYPE_DEBUG
        );
        /** @var ADOConnection $db */
        global $db;
        $orderArr                    = $data = array();
        $orderArr['subpayment_code'] = '';

        $payment      = $order->getPaymentMethod();
        $paymentInfos = $order->getPaymentInfos();

        $query = 'SELECT orders_data FROM ' . TABLE_ORDERS . " WHERE orders_id='{$dbOrderId}'";
        /** @noinspection PhpParamsInspection */
        $tmpData = $db->GetRow($query);
        if ($tmpData) {
            $data = unserialize($tmpData['orders_data']);
        }

        switch ($payment) {
            case ShopgateOrder::SHOPGATE:
                $orderArr['payment_code'] = 'shopgate';
                break;
            case ShopgateOrder::PREPAY:
                $orderArr['payment_code'] = 'xt_prepayment';
                $data                     = array_merge(
                    $data,
                    array(
                        'shopgate_order_number' => $order->getOrderNumber(),
                        'shopgate_purpose'      => $paymentInfos['purpose'],
                    )
                );

                break;
            case ShopgateOrder::INVOICE:
                $orderArr['payment_code'] = 'xt_invoice';
                break;
            case ShopgateOrder::COD:
                $orderArr['payment_code'] = 'xt_cashondelivery';
                break;
            case ShopgateOrder::DEBIT:
                $orderArr['payment_code'] = 'xt_banktransfer';
                $data                     = array_merge(
                    $data,
                    array(
                        'shopgate_order_number'  => $order->getOrderNumber(),
                        'customer_id'            => $userId,
                        'banktransfer_owner'     => $paymentInfos['bank_account_holder'],
                        'banktransfer_bank_name' => $paymentInfos['bank_name'],
                        'banktransfer_blz'       => $paymentInfos['bank_code'],
                        'banktransfer_number'    => $paymentInfos['bank_account_number'],
                        'banktransfer_iban'      => $paymentInfos['iban'],
                        'banktransfer_bic'       => $paymentInfos['bic'],
                    )
                );

                break;
            case ShopgateOrder::PAYPAL:
                $orderArr['payment_code'] = 'xt_paypal';
                $data                     = $paymentInfos['transaction_id'];

                $this->_addOrderStatus(
                    $dbOrderId,
                    $currentOrderStatus,
                    $this->_createPaymentInfos($paymentInfos)
                );

                break;
            default:
                $orderArr['payment_code'] = 'mobile_payment';

                $this->_addOrderStatus(
                    $dbOrderId,
                    $currentOrderStatus,
                    $this->_createPaymentInfos($paymentInfos)
                );

                break;
        }
        $orderArr['orders_data'] = is_array($data)
            ? serialize($data)
            : $data;
        $db->AutoExecute(TABLE_ORDERS, $orderArr, 'UPDATE', "orders_id = {$dbOrderId}");
        $this->log('end _setOrderPayment() ...', ShopgateLogger::LOGTYPE_DEBUG);

        return $orderArr['payment_code'];
    }

    /**
     * Parse the paymentInfo - array and get as output a string
     *
     * @param array $paymentInfos
     *
     * @return mixed String
     */
    private function _createPaymentInfos($paymentInfos)
    {
        $paymentInformation = '';
        foreach ($paymentInfos as $key => $value) {
            $paymentInformation .= $key . ': ' . $value . "<br/>";
        }

        return $paymentInformation;
    }

    /**
     * update the stock quantity of products from an order
     *
     * @param ShopgateOrder $order
     */
    private function _updateItemsStock(ShopgateOrder $order)
    {
        $this->log(
            "start _updateItemsStock() ...",
            ShopgateLogger::LOGTYPE_DEBUG
        );
        /** @var ADOConnection $db */
        global $db;

        if (!_SYSTEM_STOCK_HANDLING) {
            return;
        }

        $items = $order->getItems();

        foreach ($items as $item) {
            $orderInfo = $item->getInternalOrderInfo();

            $product_id = $item->getItemNumber();

            $product = $this->_loadProduct($product_id);

            $newQty = $product["products_quantity"] - $item->getQuantity();
            $db->AutoExecute(
                TABLE_PRODUCTS,
                array("products_quantity" => $newQty),
                "UPDATE",
                "products_id = '{$product_id}'"
            );
        }

        $this->log("end _updateItemsStock() ...", ShopgateLogger::LOGTYPE_DEBUG);
    }

    /**
     * transfers the needed order data to afterbuy
     *
     * @param               $orderID
     * @param ShopgateOrder $order
     */
    protected function pushOrderToAfterBuy($orderID, ShopgateOrder $order)
    {
        /** @var ADOConnection $db */
        global $db;

        $this->log("start pushOrderToAfterBuy()...", ShopgateLogger::LOGTYPE_DEBUG);

        if (!$order->getIsShippingBlocked()) {
            // AFTERBUY MODULE xt_pimpmyxt
            if ($this->checkPluginHelper->checkPlugin("xt_pimpmyxt")) {
                $afterbuy_class_path = _SRV_WEBROOT . _SRV_WEB_PLUGINS . '/xt_pimpmyxt/classes/afterbuy_veyton.php';
                if (file_exists($afterbuy_class_path)) {
                    require_once($afterbuy_class_path);
                    $afterbuy = new class_afterbuy($orderID);
                    $afterbuy->send();
                }
            }
            // AFTERBUY MODULE xt_afterbuy
            if ($this->checkPluginHelper->checkPlugin("xt_afterbuy")) {
                $afterbuy_class_path = _SRV_WEBROOT . _SRV_WEB_PLUGINS . '/xt_afterbuy/classes/class.xt_afterbuy.php';
                if (file_exists($afterbuy_class_path)) {
                    require_once($afterbuy_class_path);
                    $afterbuy = new borlabs_afterbuy(
                        $orderID, $db, $this->config->getLanguage()
                    );
                    $afterbuy->process();
                }
            }
        }
        $this->log("end pushOrderToAfterBuy()...", ShopgateLogger::LOGTYPE_DEBUG);
    }

    public function updateOrder(ShopgateOrder $order)
    {
        /** @var ADOConnection $db */
        global $db;

        $orderModel = new ShopgateOrderModel();
        $orderModel->setDb($db);
        $orderModel->setLog(ShopgateLogger::getInstance());
        $orderModel->setOrderStatusShipped($this->config->getOrderStatusShipped());
        $orderModel->setOrderStatusCanceled($this->config->getOrderStatusCanceled());
        $orderModel->setMerchantApi($this->merchantApi);

        $qry
            = "
            SELECT
                o.*,
                so.shopgate_orders_id,
                so.is_paid,
                so.is_shipping_blocked,
                so.payment_infos
            FROM " . TABLE_ORDERS . " o
            JOIN " . TABLE_SHOPGATE_ORDERS . " so
                ON (so.orders_id = o.orders_id)
            WHERE so.shopgate_order_number = '{$order->getOrderNumber()}'";

        $orderArr = $db->GetRow($qry);

        if (!is_array($orderArr) || (count($orderArr)) <= 0) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_ORDER_NOT_FOUND,
                "Shopgate order number: '{$order->getOrderNumber()}'."
            );
        }

        $dbOrderId = $orderArr["orders_id"];
        unset($orderArr["orders_id"]);

        $errorOrderStatusIsSent          = false;
        $errorOrderStatusAlreadySet      = array();
        $statusShoppingsystemOrderIsPaid = $orderArr['is_paid'];
        $statusShoppingsystemOrderIsShippingBlocked
                                         = $orderArr['is_shipping_blocked'];
        $orderArr['last_modified']       = date("Y-m-d H:i:s");

        // check if shipping is already done, then throw at end of method a OrderStatusIsSent - Exception
        if ($orderArr['orders_status'] == $this->config->getOrderStatusShipped()
            && ($statusShoppingsystemOrderIsShippingBlocked
                || $order->getIsShippingBlocked())
        ) {
            $errorOrderStatusIsSent = true;
        }

        if ($order->getUpdatePayment() == 1) {
            if (!is_null($statusShoppingsystemOrderIsPaid)
                && $order->getIsPaid() == $statusShoppingsystemOrderIsPaid
                && !is_null($orderArr['payment_infos'])
                && $orderArr['payment_infos'] == $this->jsonEncode(
                    $order->getPaymentInfos()
                )
            ) {
                $errorOrderStatusAlreadySet[] = 'payment';
            }

            if (!is_null($statusShoppingsystemOrderIsPaid)
                && $order->getIsPaid() == $statusShoppingsystemOrderIsPaid
            ) {
                // do not update is_paid
            } else {
                $comment = '';
                if ($order->getIsPaid()) {
                    $comment
                        = 'Bestellstatus von Shopgate geändert: Zahlung erhalten';
                } else {
                    $comment
                        = 'Bestellstatus von Shopgate geändert: Zahlung noch nicht erhalten';
                }
                $this->_addOrderStatus(
                    $dbOrderId,
                    $orderArr['orders_status'],
                    $comment,
                    '0',
                    '0'
                );

                // update the shopgate order status information
                $ordersShopgateOrder = array(
                    "is_paid"  => (int)$order->getIsPaid(),
                    "modified" => date("Y-m-d H:i:s"),
                );
                $db->AutoExecute(
                    TABLE_SHOPGATE_ORDERS,
                    $ordersShopgateOrder,
                    "UPDATE",
                    "shopgate_orders_id = {$orderArr['shopgate_orders_id']}"
                );

                // update var
                $statusShoppingsystemOrderIsPaid = $order->getIsPaid();
            }

            // update paymentinfos
            if (!is_null($orderArr['payment_infos'])
                && $orderArr['payment_infos'] != $this->jsonEncode(
                    $order->getPaymentInfos()
                )
            ) {
                $dbPaymentInfos = $this->jsonDecode(
                    $orderArr['payment_infos'],
                    true
                );
                $paymentInfos   = $order->getPaymentInfos();

                switch ($order->getPaymentMethod()) {
                    case ShopgateOrder::SHOPGATE:
                    case ShopgateOrder::INVOICE:
                    case ShopgateOrder::COD:
                        break;
                    case ShopgateOrder::PREPAY:
                        if (isset($dbPaymentInfos['purpose'])
                            && $paymentInfos['purpose']
                            != $dbPaymentInfos['purpose']
                        ) {
                            // Order is not paid yet
                            $this->_addOrderStatus(
                                $dbOrderId,
                                $orderArr['orders_status'],
                                "Shopgate: Zahlungsinformationen wurden aktualisiert: <br/><br/>Der Kunde wurde angewiesen Ihnen das Geld mit dem Verwendungszweck: \""
                                .
                                $paymentInfos["purpose"]
                                . "\" auf Ihr Bankkonto zu überweisen",
                                '0',
                                '0'
                            );

                            $orderData["orders_data"] = serialize(
                                array(
                                    "shopgate_purpose" => $paymentInfos["purpose"],
                                )
                            );
                            $db->AutoExecute(
                                TABLE_ORDERS,
                                $orderData,
                                "UPDATE",
                                "orders_id = {$dbOrderId}"
                            );
                        }

                        break;
                    case ShopgateOrder::DEBIT:

                        $orderData["orders_data"] = serialize(
                            array(
                                "shopgate_order_number"  => $order->getOrderNumber(),
                                "banktransfer_owner"     => $paymentInfos["bank_account_holder"],
                                "banktransfer_bank_name" => $paymentInfos["bank_name"],
                                "banktransfer_blz"       => $paymentInfos["bank_code"],
                                "banktransfer_number"    => $paymentInfos["bank_account_number"],
                                "banktransfer_iban"      => $paymentInfos["iban"],
                                "banktransfer_bic"       => $paymentInfos["bic"],
                            )
                        );
                        $db->AutoExecute(
                            TABLE_ORDERS,
                            $orderData,
                            "UPDATE",
                            "orders_id = {$dbOrderId}"
                        );

                        $comment
                            =
                            "Shopgate: Zahlungsinformationen wurden aktualisiert: <br/><br/>"
                            . $this->_createPaymentInfos($paymentInfos);
                        $this->_addOrderStatus(
                            $dbOrderId,
                            $orderArr['orders_status'],
                            $comment,
                            '0',
                            '0'
                        );

                        break;
                    case ShopgateOrder::PAYPAL:

                        // Save paymentinfos in history
                        $comment
                            =
                            "Shopgate: Zahlungsinformationen wurden aktualisiert: <br/><br/>"
                            . $this->_createPaymentInfos($paymentInfos);
                        $this->_addOrderStatus(
                            $dbOrderId,
                            $orderArr['orders_status'],
                            $comment,
                            '0',
                            '0'
                        );

                        break;
                    default:
                        // mobile_payment

                        // Save paymentinfos in history
                        $comment
                            =
                            "Shopgate: Zahlungsinformationen wurden aktualisiert: <br/><br/>"
                            . $this->_createPaymentInfos($paymentInfos);
                        $this->_addOrderStatus(
                            $dbOrderId,
                            $orderArr['orders_status'],
                            $comment,
                            '0',
                            '0'
                        );

                        break;
                }
            }

            $ordersShopgateOrder = array(
                "payment_infos" => $this->jsonEncode($order->getPaymentInfos()),
                "modified"      => date("Y-m-d H:i:s"),
            );
            $db->AutoExecute(
                TABLE_SHOPGATE_ORDERS,
                $ordersShopgateOrder,
                "UPDATE",
                "shopgate_orders_id = {$orderArr['shopgate_orders_id']}"
            );
        }

        // These are expected and should not be added to error count:
        $ignoreCodes = array(
            ShopgateMerchantApiException::ORDER_ALREADY_COMPLETED,
            ShopgateMerchantApiException::ORDER_SHIPPING_STATUS_ALREADY_COMPLETED,
        );

        if ($orderArr['orders_status'] == $this->config->getOrderStatusShipped()) {
            $orderModel->setOrderShippingCompleted($orderArr['shopgate_order_number'], $orderArr['order_number']);
        }

        if ($orderArr['orders_status'] == $this->config->getOrderStatusCanceled()) {
            $orderModel->setOrderCanceled($orderArr['shopgate_order_number'], $orderArr['order_number']);
        }

        if ($order->getUpdateShipping() == 1) {
            if (!is_null($statusShoppingsystemOrderIsShippingBlocked)
                && $order->getIsShippingBlocked()
                == $statusShoppingsystemOrderIsShippingBlocked
            ) {
                // shipping information already updated
                $errorOrderStatusAlreadySet[] = 'shipping';
            } else {
                if ($orderArr['orders_status']
                    != $this->config->getOrderStatusShipped()
                ) {
                    // set "new" status
                    if ($order->getIsShippingBlocked() == 1) {
                        $orderArr['orders_status']
                            = $this->config->getOrderStatusShippingBlocked();
                    } else {
                        $orderArr['orders_status']
                            = $this->config->getOrderStatusOpen();
                    }
                }

                // Insert changes in history
                if ($order->getIsShippingBlocked() == 0) {
                    $comment
                        = 'Bestellstatus von Shopgate geändert: Bestellung freigegeben!';
                } else {
                    $comment
                        = 'Bestellstatus von Shopgate geändert: Bestellung ist nicht freigegeben!';
                }
                $this->_addOrderStatus(
                    $dbOrderId,
                    $orderArr['orders_status'],
                    $comment,
                    '0',
                    '0'
                );

                // update the shopgate order status information
                $ordersShopgateOrder = array(
                    "is_shipping_blocked" => (int)$order->getIsShippingBlocked(),
                    "modified"            => date("Y-m-d H:i:s"),
                );
                $db->AutoExecute(
                    TABLE_SHOPGATE_ORDERS,
                    $ordersShopgateOrder,
                    "UPDATE",
                    "shopgate_orders_id = {$orderArr['shopgate_orders_id']}"
                );

                // update order stats
                $updateOrderArr                  = array();
                $updateOrderArr['orders_status'] = $orderArr['orders_status'];
                $db->AutoExecute(
                    TABLE_ORDERS,
                    $updateOrderArr,
                    "UPDATE",
                    "orders_id = {$dbOrderId}"
                );
            }
        }

        if ($errorOrderStatusIsSent) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_ORDER_STATUS_IS_SENT
            );
        }

        if (!empty($errorOrderStatusAlreadySet)) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_ORDER_ALREADY_UP_TO_DATE,
                implode(',', $errorOrderStatusAlreadySet), true
            );
        }

        $this->pushOrderToAfterBuy($dbOrderId, $order);

        return array(
            'external_order_id'     => $dbOrderId,
            'external_order_number' => $dbOrderId,
        );
    }

    /**
     * event on update product position in order
     */
    public function orderDeleteProductBottomHook()
    {
        if (!empty($_REQUEST['pg'])) {
            $updatePartialCancellationResult = array();

            global $db;
            $orderModel = new ShopgateOrderModel();
            $orderModel->setDb($db);
            $order = $orderModel->getOrder($_REQUEST['orders_id']);
            $order = $order->fields;

            if (!is_array($order)) {
                return;
            }

            switch ($_REQUEST['pg']) {
                case ShopgateOrderModel::SG_REMOVE_ORDER_REMOVE_ITEM_HOOK_SECTION:
                    $updatePartialCancellationResult =
                        $orderModel->updatePartialCancellationData($order, array($_REQUEST['products_id'] => 0));
                    break;
                case ShopgateOrderModel::SG_REMOVE_ORDER_UPDATE_ITEM_HOOK_SECTION:
                    $updatePartialCancellationResult = $orderModel->updatePartialCancellationData(
                        $order,
                        array($_REQUEST['products_id'] => $_REQUEST['order_products_quantity'])
                    );
                    break;
            }

            if (array_key_exists('items', $updatePartialCancellationResult)
                && count(
                    $updatePartialCancellationResult['items']
                )
            ) {
                try {
                    $this->merchantApi->cancelOrder(
                        $order['shopgate_order_number'],
                        false,
                        $updatePartialCancellationResult['items']
                    );
                    $historyMessage = 'Änderungen (' . $updatePartialCancellationResult['totalCancellations']
                        . ' Positionen) wurden an Shopgate übermittelt.';
                    //$orderModel->setShopgateOrderAsCancelled($order['shopgate_order_number']);
                } catch (ShopgateLibraryException $e) {
                    $historyMessage =
                        "Es ist ein Fehler im Shopgate-Plugin aufgetreten ({$e->getCode()}): {$e->getMessage()}";
                } catch (ShopgateMerchantApiException $e) {
                    $historyMessage =
                        "Es ist ein Fehler bei Shopgate aufgetreten ({$e->getCode()}): {$e->getMessage()}";
                } catch (Exception $e) {
                    $historyMessage =
                        "Es ist ein unbekannter Fehler aufgetreten ({$e->getCode()}): {$e->getMessage()}";
                }

                $this->_addOrderStatus($order['orders_id'], null, $historyMessage);
            }
        }
    }

    /**
     * Requests the Shopgate Merchant API to update the status of an order
     *
     * This one gets called at the "class.order.php:_updateOrderStatus_bottom" hookpoint. It
     * checks the status of the order at Veyton. If it's completed, a request to Shopgate's
     * Merchant API is sent and in case of success, a note is added to the Veyton order.
     *
     * @param mixed[] $data Information about the order.
     *
     * @return bool false if there was an error, true otherwise
     */
    public function updateOrderStatus($data)
    {
        /** @var ADOConnection $db */
        global $db;
        $orderModel = new ShopgateOrderModel();
        $orderModel->setDb($db);
        $orderModel->setLog(ShopgateLogger::getInstance());
        $orderModel->setOrderStatusShipped($this->config->getOrderStatusShipped());
        $orderModel->setOrderStatusCanceled($this->config->getOrderStatusCanceled());
        $orderModel->setMerchantApi($this->merchantApi);
        // if the shipping is not completet yet just return:
        if ($data["orders_status_id"] != $this->config->getOrderStatusShipped()
            && $data["orders_status_id"] != $this->config->getOrderStatusCanceled()
        ) {
            return true;
        }

        // get the order from the database
        $shopgateOrder = $orderModel->getOrder($data["orders_id"]);

        if (empty($shopgateOrder->fields)) {
            return true;// This is not a Shopgate-Order
        }

        switch ($data["orders_status_id"]) {
            case $this->config->getOrderStatusCanceled():
                try {
                    $orderModel->setOrderCanceled($shopgateOrder->fields['shopgate_order_number'], $data['orders_id']);
                } catch (Exception $e) {
                    $msg =
                        "[setOrderCanceled] Shopgate order number:'{$data['shopgate_order_number']}' [Exception] {$e->getCode()}, {$e->getMessage()}\n";
                    $this->log($msg, ShopgateLogger::LOGTYPE_ERROR);
                }

                break;
            case $this->config->getOrderStatusShipped():
                try {
                    $orderModel->setOrderShippingCompleted(
                        $shopgateOrder->fields['shopgate_order_number'],
                        $data['orders_id']
                    );
                } catch (Exception $e) {
                    $msg =
                        "[setOrderShippingCompleted] Shopgate order number:'{$data['shopgate_order_number']}' [Exception] {$e->getCode()}, {$e->getMessage()}\n";
                    $this->log($msg, ShopgateLogger::LOGTYPE_ERROR);
                }

                break;

            default:
                break;
        }
    }

    public function cron($jobName, $params, &$message, &$errorCount)
    {
        /** @var ADOConnection $db */
        global $db;

        $orderModel = new ShopgateOrderModel();
        $orderModel->setDb($db);
        $orderModel->setLog(ShopgateLogger::getInstance());
        $orderModel->setOrderStatusShipped($this->config->getOrderStatusShipped());
        $orderModel->setOrderStatusCanceled($this->config->getOrderStatusCanceled());
        $orderModel->setMerchantApi($this->merchantApi);

        switch ($jobName) {
            case 'set_shipping_completed':
                $orderModel->cronSetOrdersShippingCompleted($message, $errorCount);
                break;
            case 'cancel_orders':
                $orderModel->cronSetOrdersCanceled($message, $errorCount);
                $orderModel->cronSetOrdersPositionCanceled($message, $errorCount);
                break;
            default:
                throw new ShopgateLibraryException(
                    ShopgateLibraryException::PLUGIN_CRON_UNSUPPORTED_JOB,
                    'Job name: "' . $jobName . '"', true
                );
        }
    }

    /**
     * used by checkCoupon function to sort the coupons by order index
     * if there are more than one
     *
     * @param \ShopgateExternalCoupon $couponEven
     * @param \ShopgateExternalCoupon $couponOdd
     *
     * @return int
     */
    public function sortArraysByArrayValue(ShopgateExternalCoupon $couponEven, ShopgateExternalCoupon $couponOdd)
    {
        return $couponEven->getOrderIndex() - $couponOdd->getOrderIndex();
    }

    public function checkCart(ShopgateCart $shopgateCart)
    {
        $this->_useNativeMobileMode();

        return array(
            'external_coupons' => $this->checkCoupon($shopgateCart),
            'shipping_methods' => $this->getShipping($shopgateCart),
            'items'            => $this->checkCartItems($shopgateCart),
            'currency'         => $shopgateCart->getCurrency(),
            'customer'         => $this->getCartCustomer($shopgateCart),
            'payment_methods'  => $this->getPaymentMethods($shopgateCart),
        );
    }

    /**
     * this function uses the veyton shopping cart to calculate
     * the whole cart price considering coupons
     *
     * @param \ShopgateCart $shopgateCart
     *
     * @return array
     * @throws \ShopgateLibraryException
     */
    public function checkCoupon(ShopgateCart $shopgateCart)
    {
        $this->log('[checkCart coupons] beginning...', ShopgateLogger::LOGTYPE_DEBUG);

        $resultCoupons = array();
        $coupons       = $shopgateCart->getExternalCoupons();

        if (empty($coupons) || !$this->checkPluginHelper->checkPlugin("xt_coupons")) {
            return array();
        }

        // there is a setting which allows to prevent customers to
        // redeem a coupon if they are not logged in
        if (XT_COUPONS_LOGIN == 'true' && is_null($shopgateCart->getExternalCustomerId())) {
            throw new ShopgateLibraryException(ShopgateLibraryException::COUPON_INVALID_USER);
        }

        global $currency;

        // sort the coupons by sort order value
        if (count($coupons) > 1) {
            usort($coupons, 'sortArraysByArrayValue');
        }

        $cartHelper = new ShopgateVeytonHelperCart();

        $cartHelper->buildVeytonShoppingCart(
            $shopgateCart->getItems(),
            $shopgateCart->getExternalCustomerId()
        );
        $cartHelper->includeVeytonCouponClasses();

        // veyton supports only one coupon by default
        // the coupons were sorted by sort order field before
        /* @var ShopgateExternalCoupon $coupon */
        foreach ($coupons as $coupon) {
            $veytonCoupon = new xt_coupons();
            $result       = $veytonCoupon->_check_coupon_avail($coupon->getCode());

            if (!empty($result["coupon_token_code"]) || !empty($result["coupon_code"])) {
                $this->log(
                    '[checkCart coupons] adding valid coupons '
                    . ' to veyton cart:'
                    . print_r($result, true),
                    ShopgateLogger::LOGTYPE_DEBUG
                );

                // put the coupon into the shopping cart
                // and recaculate the discount
                $veytonCoupon->_set_coupon($result);

                $coupon->setIsValid(true);
                $coupon->setCurrency($currency->code);
                $coupon->setName($result["coupon_code"]);

                if (empty($result["coupon_token_code"])) {
                    $coupon->setCode($result["coupon_code"]);
                } else {
                    $coupon->setCode($result["coupon_token_code"]);
                }

                if (((int)$result['coupon_free_shipping']) == 1) {
                    $coupon->setIsFreeShipping(true);
                } else {
                    $amountComplete = 0;

                    foreach ($shopgateCart->getItems() as $item) {
                        $amountComplete += $item->getUnitAmountWithTax() * $item->getQuantity();
                    }

                    $coupon->setAmountGross(
                        $cartHelper->getCouponDiscountGrossFromCart(
                            $shopgateCart->getItems(),
                            $result
                        )
                    );
                    //in this case shipping is free if the flag
                    // 'coupon_free_on_100_status' for this coupon is set true
                    // and the discount is 100%
                    if (!empty($veytonCoupon->coupon_data['coupon_free_on_100_status'])
                        && $veytonCoupon->coupon_data['coupon_free_on_100_status'] == '1'
                        && $_SESSION['cart']->cart_total_full <= $_SESSION['cart']->total_discount
                    ) {
                        $coupon->setIsFreeShipping(true);
                    }
                }

                $resultCoupons[] = $coupon;
                // by default veyton supports only one coupon
                // we took the first one based on the sort order
                // submitted by shopgate
                break;
            } else {
                if (!empty($veytonCoupon->error_info)) {
                    $errorMessage = $veytonCoupon->error_info;
                } else {
                    $errorMessage = "Unknown coupon error [{$coupon->getCode()}]";
                }

                $this->log('[checkCart coupons] Error: ' . $errorMessage, ShopgateLogger::LOGTYPE_ERROR);

                $coupon->setIsValid(false);
                $coupon->setNotValidMessage($errorMessage);
                $resultCoupons[] = $coupon;
            }
        }
        //remove cart data from session and database
        $_SESSION['cart']->_resetCart();
        $this->log('[checkCart coupons] end...', ShopgateLogger::LOGTYPE_DEBUG);

        return $resultCoupons;
    }

    /**
     * Fetches all valid payments for given cart
     *
     * @param ShopgateCart $shopgateCart
     *
     * @return array
     */
    private function getPaymentMethods(ShopgateCart $shopgateCart)
    {
        $paymentMethods = array();
        $data           = $this->prepareDataForCart($shopgateCart);

        $cartHelper = new ShopgateVeytonHelperCart();
        $cartHelper->buildVeytonShoppingCart(
            $shopgateCart->getItems(),
            $shopgateCart->getExternalCustomerId()
        );

        $veytonPaymentModel = new payment();
        $veytonPaymentModel->_payment($data);
        $veytonPayments = $veytonPaymentModel->payment_data;

        foreach ($veytonPayments as $veytonPayment) {
            if (isset($veytonPayment['payment_code'])) {
                $method = new ShopgatePaymentMethod();
                $method->setId($veytonPayment['payment_code']);
                $paymentMethods[] = $method;
            }
        }
        $_SESSION['cart']->_resetCart();

        return $paymentMethods;
    }

    /**
     * return all valid shipping merhods to an customer
     *
     * @param ShopgateCart $shopgateCart
     *
     * @return array of ShopgateShippingMethod
     */
    private function getShipping(ShopgateCart $shopgateCart)
    {
        /** @var ADOConnection $db */
        global $db, $price;

        $itemHelper = new ShopgateVeytonHelperItem($db);
        $itemHelper->setPrice($price);
        $itemHelper->setCheckPluginHelper(new ShopgateVeytonHelperCheckPlugin($db, ShopgateLogger::getInstance()));

        $veytonShippings = new shipping();
        $veytonCart      = new cart();
        $veytonCustomer  = new customer($shopgateCart->getExternalCustomerId());
        $shippingAddress = $shopgateCart->getDeliveryAddress();

        if (empty($shippingAddress)) {
            return array();
        }

        $data = $this->prepareDataForCart($shopgateCart);

        $bulkAmount        = 0;
        $bulkAmountWithTax = 0;

        if ($this->checkPluginHelper->checkPlugin('xt_sperrgut')) {
            $cartHelper        = new ShopgateVeytonHelperCart();
            $bulkPrice         = $cartHelper->getBulkPrice($shopgateCart);
            $bulkAmount        = $bulkPrice['plain_otax'];
            $bulkAmountWithTax = $bulkPrice['plain'];
        }

        foreach ($shopgateCart->getItems() as $item) {
            $id                                 =
                !is_null($item->getParentItemNumber())
                    ? $item->getParentItemNumber()
                    : $item->getItemNumber();
            $veytonProduct                      = new product($id, 'default', $item->getQuantity());
            $veytonProduct->data["customer_id"] = $veytonCustomer->customers_id;
            $veytonCart->_addCart($veytonProduct->data);
        }

        $resultShipping = array();
        $veytonShippings->_shipping($data);

        foreach ($veytonShippings->shipping_data as $shp) {
            $sgShippingMethod = new ShopgateShippingMethod();
            $sgShippingMethod->setTitle($shp["shipping_name"]);
            $sgShippingMethod->setDescription($shp["shipping_desc"]);
            $sgShippingMethod->setId($shp["shipping_id"]);

            $amount        = 0;
            $amountWithTax = 0;
            if (!empty($shp["shipping_price"])) {
                $amount        = $shp["shipping_price"]["plain_otax"];
                $amountWithTax = $shp["shipping_price"]["plain"];
            }

            $sgShippingMethod->setAmount($amount + $bulkAmount);
            $sgShippingMethod->setAmountWithTax(
                $amountWithTax + $bulkAmountWithTax
            );

            $resultShipping[] = $sgShippingMethod;
        }

        return $resultShipping;
    }

    /**
     * Prepares the data needed for fetching cart based shippings/payments
     *
     * @param ShopgateCart $shopgateCart
     *
     * @return array
     */
    private function prepareDataForCart(ShopgateCart $shopgateCart)
    {
        global $db, $countries;

        $shippingAddress = $shopgateCart->getDeliveryAddress();
        $billingAddress  = $shopgateCart->getInvoiceAddress();
        $mail            = $shopgateCart->getMail();
        $customerId      = $shopgateCart->getExternalCustomerId();

        $data = array(
            'total'    => array('plain' => 0),
            'weight'   => 0,
            'products' => array(),
            'count'    => count($shopgateCart->getItems()),
            'language' => $this->config->getLanguage(),
        );

        if (!empty($shippingAddress)) {
            $countries                         = new countries('true');
            $country                           = $countries->_getCountryData($shippingAddress->getCountry());
            $data['customers_country']         = $country['countries_name'];
            $data['customers_zone']            = $country['zone_id'];
            $data['customer_shipping_address'] = $shippingAddress->toArray();
            $data['customer_shipping_address'] = array(
                'customers_country_code' => $shippingAddress->getCountry(),
                'shipping_country_code'  => $shippingAddress->getCountry(),
                'customers_zone'         => $data['customers_zone'],
            );
        }

        if (!empty($billingAddress)) {
            $country                          = $countries->_getCountryData($billingAddress->getCountry());
            $data['customer_default_address'] = $billingAddress->toArray();
            $data['customer_default_address'] = array(
                'customers_country_code' => $billingAddress->getCountry(),
                'shipping_country_code'  => $billingAddress->getCountry(),
                'customers_zone'         => $country['zone_id'],
            );
        }

        if (!empty($customerId)
            || !empty($mail)
        ) {
            $query = "SELECT c.customers_status as status FROM " . TABLE_CUSTOMERS . " AS c WHERE ";
            $query .= !empty($customerId)
                ? "c.customers_id='{$customerId}';"
                : "c.customers_email_address='{$mail}';";

            $res = $db->execute($query);
            $res = $res->fields;

            $data["customers_status_id"] = !empty($res)
                ? $res["status"]
                : _STORE_CUSTOMERS_STATUS_ID_GUEST;
        } else {
            $data["customers_status_id"] = _STORE_CUSTOMERS_STATUS_ID_GUEST;
        }

        foreach ($shopgateCart->getItems() as $item) {
            $id    = !is_null($item->getParentItemNumber())
                ? $item->getParentItemNumber()
                : $item->getItemNumber();
            $query =
                "SELECT p.products_weight as weight FROM " . TABLE_PRODUCTS . " AS p WHERE p.products_id={$id} ";
            $res   = $db->execute($query);
            $res   = $res->fields;
            $data['total']['plain'] += $item->getUnitAmount() * $item->getQuantity();
            $data['weight'] += $res["weight"] * $item->getQuantity();
            $data['products']['products_id'] = $id;
        }

        return $data;
    }

    /**
     * validates all items in the cart
     *
     * @param ShopgateCart $cart
     *
     * @return array
     * @throws ShopgateLibraryException
     */
    private function checkCartItems(ShopgateCart $cart)
    {
        /** @var ADOConnection $db */
        global $db;
        $return     = array();
        $itemHelper = new ShopgateVeytonHelperItem($db);

        foreach ($cart->getItems() as $orderItem) {
            $id            = $orderItem->getItemNumber();
            $qty           = $orderItem->getQuantity();
            $sgCartItem    = new ShopgateCartItem();
            $veytonProduct = new product($id, 'default', $qty);
            $sgCartItem->setItemNumber($id);
            if ($veytonProduct->is_product != true || $veytonProduct->data['allow_add_cart'] != true) {
                $sgCartItem->setIsBuyable(false);
                $sgCartItem->setQtyBuyable(0);
                $sgCartItem->setStockQuantity(0);
                $sgCartItem->setError(ShopgateLibraryException::CART_ITEM_PRODUCT_NOT_FOUND);
                $return[] = $sgCartItem;
                continue;
            }
            $sgCartItem->setAttributes($itemHelper->getAttributes($id));

            $sgCartItem->setUnitAmount(
                $this->formatPriceNumber($veytonProduct->data['products_price']['plain_otax'], 2)
            );
            $sgCartItem->setUnitAmountWithTax(
                $this->formatPriceNumber($veytonProduct->data['products_price']['plain'], 2)
            );
            if (_STORE_STOCK_CHECK_BUY == 'true') {
                $sgCartItem->setIsBuyable(true);
                $sgCartItem->setQtyBuyable($qty);
                $return[] = $sgCartItem;
                continue;
            }
            $availableQty = (int)$veytonProduct->data['products_quantity'];
            if ($availableQty <= 0) {
                $sgCartItem->setIsBuyable(false);
                $sgCartItem->setQtyBuyable(0);
                $sgCartItem->setStockQuantity(0);
                $sgCartItem->setError(ShopgateLibraryException::CART_ITEM_OUT_OF_STOCK);
                $return[] = $sgCartItem;
                continue;
            }
            if ($availableQty < $qty) {
                $sgCartItem->setIsBuyable(true);
                $sgCartItem->setQtyBuyable($availableQty);
                $sgCartItem->setStockQuantity($availableQty);
                $sgCartItem->setError(ShopgateLibraryException::CART_ITEM_REQUESTED_QUANTITY_NOT_AVAILABLE);
                $return[] = $sgCartItem;
                continue;
            }
            $sgCartItem->setIsBuyable(true);
            $sgCartItem->setQtyBuyable($qty);
            $sgCartItem->setStockQuantity($availableQty);
            $return[] = $sgCartItem;
        }

        return $return;
    }

    /**
     * Returns the customer to the corresponding external customer ID in the cart if set.
     *
     * @param ShopgateCart $cart
     *
     * @return ShopgateCartCustomer
     */
    private function getCartCustomer(ShopgateCart $cart)
    {
        /** @var ADOConnection $db */
        global $db;

        $customerNumber = (int)$cart->getExternalCustomerId();
        $customer       = new ShopgateCartCustomer();
        $customerGroups = array();
        $customer->setCustomerGroups($customerGroups);

        if (empty($customerNumber)) {
            // set the master ID to the "guest" ID so it'll be looked up,
            // additionally all its master customer groups will be looked up in the process
            $masterId = _STORE_CUSTOMERS_STATUS_ID_GUEST;
        } else {
            $stmt = $db->Prepare(
                "SELECT cs.customers_status_id, cs.customers_status_master\n" .
                "FROM " . TABLE_CUSTOMERS . " AS c\n" .
                "JOIN " . TABLE_CUSTOMERS_STATUS . " AS cs ON c.customers_status = cs.customers_status_id\n" .
                "WHERE c.customers_id = ?"
            );

            $result = $db->Execute($stmt, array((int)$customerNumber));

            if (empty($result) || !($result instanceof ADORecordSet) || ($result->RowCount() < 1)) {
                return $customer;
            } else {
                $customerData     = $result->fields;
                $customerGroups[] = new ShopgateCartCustomerGroup(array('id' => $customerData['customers_status_id']));
            }

            $masterId = $customerData['customers_status_master'];
        }

        while (!empty($masterId)) {
            $customerGroups[] = new ShopgateCartCustomerGroup(array('id' => $masterId));

            $stmt   = $db->Prepare(
                'SELECT customers_status_master FROM ' . TABLE_CUSTOMERS_STATUS . ' WHERE customers_status_id = ?'
            );
            $result = $db->Execute($stmt, array($masterId));

            if (empty($result) || !($result instanceof ADORecordSet) || ($result->RowCount() < 1)) {
                break;
            }

            $masterId = $result->fields['customers_status_master'];
        }

        $customer->setCustomerGroups($customerGroups);

        return $customer;
    }

    public function redeemCoupons(ShopgateCart $shopgateCart)
    {
    }

    public function getSettings()
    {
        $customerGroups = $this->getCustomerGroups();

        $veytonTaxRates   = $this->getTaxRates();
        $customerTaxClass = array(
            'id'         => "1",
            'key'        => 'default',
            'is_default' => "1",
        );
        $taxRates         = array();
        $taxRules         = array();
        foreach ($veytonTaxRates as $veytonTaxRate) {
            // build and append tax rate
            $taxRates[] = array(
                'id'            => $veytonTaxRate['country'] . '-' . $veytonTaxRate['tax_rates_id'],
                'key'           => $veytonTaxRate['country'] . '-' . $veytonTaxRate['tax_rates_id'],
                'display_name'  => $veytonTaxRate['tax_class_title'] . (!empty($veytonTaxRate['state'])
                        ? ' '
                        . $veytonTaxRate['state']
                        : ''),
                'tax_percent'   => $veytonTaxRate['tax_rate'],
                'country'       => $veytonTaxRate['country'],
                'state'         => $veytonTaxRate['state'],
                'zip_code_type' => 'all',
            );

            // build and append tax rules
            if (!empty($taxRules[$veytonTaxRate['tax_rates_id']])) {
                $taxRules[$veytonTaxRate['tax_rates_id']]['tax_rates'][] = array(
                    // one rate per rule (since rates are in fact also rules) in veyton
                    'id'  => $veytonTaxRate['country'] . '-' . $veytonTaxRate['tax_rates_id'],
                    'key' => $veytonTaxRate['country'] . '-' . $veytonTaxRate['tax_rates_id'],
                );
            } else {
                $taxRules[$veytonTaxRate['tax_rates_id']] = array(
                    'id'                   => $veytonTaxRate['tax_rates_id'],
                    'name'                 => $veytonTaxRate['tax_class_title'],
                    'priority'             => 1,
                    'product_tax_classes'  => array(
                        array(
                            'id'  => $veytonTaxRate['tax_class_id'],
                            'key' => $veytonTaxRate['tax_class_title'],
                        ),
                    ),
                    'customer_tax_classes' => array(
                        array(
                            'id'  => 1,
                            'key' => 'default',
                        ),
                    ),
                    'tax_rates'            => array(
                        array(
                            'id'  => $veytonTaxRate['country'] . '-' . $veytonTaxRate['tax_rates_id'],
                            'key' => $veytonTaxRate['country'] . '-' . $veytonTaxRate['tax_rates_id'],
                        ),
                    ),
                );
            }
        }

        $paymentMethods     = array();
        $paymentFilters     = array(
            'status_check'   => false,
            'group_check'    => false,
            'shipping_check' => false,
        );
        $veytonPaymentModel = new payment();
        $veytonPayments     = $veytonPaymentModel->_getPossiblePayment($paymentFilters);

        foreach ($veytonPayments as $veytonPayment) {
            if (isset($veytonPayment['payment_code'])) {
                $paymentMethods[] = array(
                    'id' => $veytonPayment['payment_code'],
                );
            }
        }

        return array(
            'customer_groups' => $customerGroups,
            'tax'             => array(
                'product_tax_classes'  => $this->getTaxClasses(),
                'customer_tax_classes' => array($customerTaxClass),
                'tax_rates'            => $taxRates,
                'tax_rules'            => $taxRules,
            ),
            'payment_methods' => $paymentMethods,
        );
    }

    /**
     * read the customer group data, by the language,  from the database
     *
     * @return array
     * @throws ShopgateLibraryException
     */
    protected function getCustomerGroups()
    {
        /** @var ADOConnection $db */
        global $db;
        $query = "SELECT
                        c.customers_status_id AS 'id',
                        csd.customers_status_name AS 'name',
                        '0' AS 'is_default'
                    FROM " . TABLE_CUSTOMERS_STATUS . " AS c
                    JOIN " . TABLE_CUSTOMERS_STATUS_DESCRIPTION .
            " AS csd ON csd.customers_status_id = c.customers_status_id
                    WHERE csd.language_code = '" . $this->config->getLanguage() . "'";

        $result = $db->execute($query);

        $customerGroups = array();
        if (empty($result)) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_DATABASE_ERROR,
                "Shopgate Plugin - Error selecting.", true
            );
        } else {
            while (!$result->EOF) {
                if ($result->fields['id'] == _STORE_CUSTOMERS_STATUS_ID_GUEST) {
                    $result->fields['is_default'] = 1;
                }
                $result->fields['customer_tax_class_key'] = 'default';
                $customerGroups[]                         = $result->fields;
                $result->MoveNext();
            }
        }

        return $customerGroups;
    }

    /**
     * read the whole tax rate data from the database
     *
     * @return array
     * @throws ShopgateLibraryException
     */
    protected function getTaxRates()
    {
        /** @var ADOConnection $db */
        global $db;

        $countryTablesExist = defined('TABLE_FEDERAL_STATES') && defined('TABLE_FEDERAL_STATES_DESCRIPTION');

        $sqlQuery = "SELECT
                            tr.tax_rates_id,
                            tr.tax_zone_id,
                            tr.tax_rate,
                            c.countries_iso_code_2 AS 'country',
                            tc.tax_class_id,
                            tc.tax_class_title,
        ";

        if ($countryTablesExist) {
            $sqlQuery .= " CONCAT('-',fs.country_iso_code_2,fsd.state_name) AS 'state' ";
        } else {
            $sqlQuery .= " '' AS 'state' ";
        }

        $sqlQuery .= "FROM " . TABLE_TAX_RATES . " as tr
            JOIN " . TABLE_TAX_CLASS . " AS tc ON tr.tax_class_id = tc.tax_class_id
            JOIN " . TABLE_COUNTRIES . " AS c ON tr.tax_zone_id = c.zone_id
            JOIN " . TABLE_COUNTRIES_DESCRIPTION . " AS cd ON c.countries_iso_code_2 = cd.countries_iso_code_2
        ";

        if ($countryTablesExist) {
            $sqlQuery .= "LEFT JOIN " . TABLE_FEDERAL_STATES . " AS fs ON fs.country_iso_code_2 = c.countries_iso_code_2
                          LEFT JOIN " . TABLE_FEDERAL_STATES_DESCRIPTION . " AS fsd ON fsd.states_id = fs.states_id ";
        }

        $sqlQuery .= " WHERE c.`status` = 1 AND cd.language_code = '"
            . $this->config->getLanguage() . "'";

        $queryResult = $db->execute($sqlQuery);

        $result = array();
        if (!$queryResult) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_DATABASE_ERROR,
                "Shopgate Plugin - Error selecting.", true
            );
        } else {
            while (!$queryResult->EOF) {
                $result[] = $queryResult->fields;
                $queryResult->MoveNext();
            }
        }

        return $result;
    }

    /**
     * read the whole tax class data from the database
     *
     * @return array
     * @throws ShopgateLibraryException
     */
    private function getTaxClasses()
    {
        /** @var ADOConnection $db */
        global $db;

        $taxClasses = array();
        $result     = $db->Execute(
            "SELECT tc.tax_class_id AS id, tc.tax_class_title AS 'key' FROM "
            . TABLE_TAX_CLASS . " AS tc;"
        );

        if (!$result) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_DATABASE_ERROR,
                "Shopgate Plugin - Error selecting.", true
            );
        } else {
            while (!$result->EOF) {
                $taxClasses[] = $result->fields;
                $result->MoveNext();
            }
        }

        return $taxClasses;
    }

    public function checkStock(ShopgateCart $cart)
    {
        /** @var ADOConnection $db */
        global $db;

        $return     = array();
        $itemHelper = new ShopgateVeytonHelperItem($db);

        foreach ($cart->getItems() as $orderItem) {
            $id            = $itemHelper->getProductIdFromOrderItem($orderItem);
            $sgCartItem    = new ShopgateCartItem();
            $veytonProduct = new product($id);
            $sgCartItem->setItemNumber($id);
            if ($veytonProduct->is_product != true || $veytonProduct->data['allow_add_cart'] != true) {
                $sgCartItem->setIsBuyable(false);
                $sgCartItem->setStockQuantity(0);
                $sgCartItem->setError(ShopgateLibraryException::CART_ITEM_PRODUCT_NOT_FOUND);
                $return[] = $sgCartItem;
                continue;
            }
            $availableQty = (int)$veytonProduct->data['products_quantity'];

            $sgCartItem->setAttributes($itemHelper->getAttributes($id));

            if (_STORE_STOCK_CHECK_BUY == 'true') {
                $sgCartItem->setIsBuyable(true);
                $sgCartItem->setStockQuantity($availableQty);
                $return[] = $sgCartItem;
                continue;
            }
            if ($availableQty <= 0) {
                $sgCartItem->setIsBuyable(false);
                $sgCartItem->setStockQuantity(0);
                $sgCartItem->setError(ShopgateLibraryException::CART_ITEM_OUT_OF_STOCK);
                $return[] = $sgCartItem;
                continue;
            }
            $sgCartItem->setIsBuyable(true);
            $sgCartItem->setStockQuantity($availableQty);
            $return[] = $sgCartItem;
        }

        return $return;
    }

    public function syncFavouriteList($customerToken, $items)
    {
        // TODO: Implement syncFavouriteList() method.
    }

    public function getOrders(
        $customerToken,
        $customerLanguage,
        $limit = 10,
        $offset = 0,
        $orderDateFrom = '',
        $sortOrder = 'created_desc'
    ) {
        global $db;

        $customerHelper = new ShopgateVeytonHelperCustomer($db);

        return $customerHelper->getOrdersByToken(
            $customerToken,
            $customerLanguage,
            $limit,
            $offset,
            $orderDateFrom,
            $sortOrder
        );
    }

    protected function createItemsCsv()
    {
        $uids = (isset($_REQUEST['item_numbers']))
            ? $_REQUEST['item_numbers']
            : array();
        $this->_buildItems(self::EXPORT_MODE_CSV, $this->exportLimit, $this->exportOffset, $uids);
    }

    /**
     * Prepares item model for export.
     *
     * @param string   $mode
     * @param int|null $limit
     * @param int|null $offset
     * @param array    $uids
     */
    protected function _buildItems($mode = self::EXPORT_MODE_XML, $limit = null, $offset = null, array $uids = array())
    {
        /** @var ADOConnection $db */
        global $db, $language, $price, $currency;

        $this->log("Start export items...", ShopgateLogger::LOGTYPE_DEBUG);

        $itemHelper = new ShopgateVeytonHelperItem($db);
        $itemHelper->setLanguageCode($language->code);
        $itemHelper->setPermissionBlacklist($this->permissionBlacklist);
        $itemHelper->setShopId($this->shopId);
        $itemHelper->setPrice($price);
        $itemHelper->setCurrency($currency);
        $itemHelper->setPermissionBlacklist($this->permissionBlacklist);
        $itemHelper->setPriceHelper($this->getHelper(ShopgateObject::HELPER_PRICING));
        $itemHelper->setCheckPluginHelper(new ShopgateVeytonHelperCheckPlugin($db, ShopgateLogger::getInstance()));
        $itemHelper->setDefaultuserGroupId($this->config->getDefaultUserGroupId());
        $itemHelper->setSplittedExportValues($limit, $offset);
        $itemHelper->setExportUids($uids);

        $this->maxProductsSort = $itemHelper->getMaxProductsSort();
        $seoUrls               = $itemHelper->getSeoUrls();

        /** @noinspection PhpParamsInspection */
        $productsResult = $db->Execute($itemHelper->getProductQuery());
        $this->log("execute SQL system status ...", ShopgateLogger::LOGTYPE_DEBUG);

        $rules = $itemHelper->getStockRules();

        while (!empty($productsResult) && !$productsResult->EOF) {
            $this->log(
                "start export products_id = " . $productsResult->fields["products_id"] . " ...",
                ShopgateLogger::LOGTYPE_DEBUG
            );

            $item = $this->_buildItem($mode, $itemHelper, $productsResult->fields, $rules, $seoUrls);
            $productsResult->MoveNext();

            if (!($item instanceof ShopgateItemModel)) {
                continue;
            }
            switch ($mode) {
                case self::EXPORT_MODE_CSV:
                    $this->addItemRow($item->asCsvArray($this->buildDefaultItemRow()));
                    foreach ($item->getData('children') as $variation) {
                        $this->addItemRow($variation->asCsvArray($this->buildDefaultItemRow(), $item));
                    }
                    break;
                case self::EXPORT_MODE_XML:
                    $this->addItemModel($item);
                    break;
            }
        }
    }

    /**
     * @param                          $mode
     * @param ShopgateVeytonHelperItem $itemHelper
     * @param                          $item
     * @param array                    $statusRules
     * @param array                    $seoUrls
     *
     * @return ShopgateItemModel|null
     */
    private function _buildItem($mode, $itemHelper, $item, $statusRules = array(), $seoUrls = array())
    {
        global $currency;

        $this->log("execute _buildItem() ...", ShopgateLogger::LOGTYPE_DEBUG);

        $unitAmount = $priceData = $oldPriceData = null;
        $itemHelper->getProductPrice($item, $unitAmount, $priceData, $oldPriceData);

        $weight = (empty($item['products_weight'])
            ? 0
            : $item['products_weight'] * 1000);

        $identifierArr       = array();
        $identifierSkuObject = new Shopgate_Model_Catalog_Identifier();
        $identifierSkuObject->setType('SKU');
        $identifierSkuObject->setValue($item['products_model']);
        $identifierArr[] = $identifierSkuObject;

        $identifierEanObject = new Shopgate_Model_Catalog_Identifier();
        $identifierEanObject->setType('EAN');
        $identifierEanObject->setValue($item['products_ean']);
        $identifierArr[] = $identifierEanObject;

        $itemArr   = $this->buildDefaultItemRow();
        $itemModel = new ShopgateItemModel();
        $itemModel->setCheckPluginHelper($this->checkPluginHelper);
        $itemModel->setUid($item["products_id"]);
        $itemModel->setIdentifiers($identifierArr);
        $itemModel->setLastUpdate($item['last_modified']);

        if (!empty($item['manufacturers_name'])) {
            $manufacturer = new Shopgate_Model_Catalog_Manufacturer();
            $manufacturer->setTitle($item['manufacturers_name']);
            $itemModel->setManufacturer($manufacturer);
        }

        $itemModel->setName(str_replace("\n", '<br />', $item["products_name"]));
        $itemModel->setDescription(
            $itemHelper->getDescriptionToProduct($this->config->getExportDescriptionType(), $item)
        );
        $itemModel->setWeight($weight);

        // calculate and set prices
        $priceModel = new Shopgate_Model_Catalog_Price();
        $priceModel->setSalePrice($this->formatPriceNumber($unitAmount, 2));
        if (!empty($oldPriceData)) {
            $priceModel->setPrice($this->formatPriceNumber($oldPriceData["plain"], 2));
        }
        $priceModel->setBasePrice(
            $itemHelper->getAmountInfoTextToProduct($item['products_id'], $item['products_quantity'])
        );

        if ($priceData['tax_rate'] > 0) {
            $priceModel->setType(Shopgate_Model_Catalog_Price::DEFAULT_PRICE_TYPE_GROSS);
        } else {
            $priceModel->setType(Shopgate_Model_Catalog_Price::DEFAULT_PRICE_TYPE_NET);
        }

        if ($priceModel->getSalePrice() != $priceModel->getPrice()) {
            $tierPrices = $itemHelper->getGroupPricesToProduct($item['products_id']);
            foreach ($tierPrices as $tierPriceData) {
                $tierPrice = $tierPriceData['price'];
                $qty       = $tierPriceData['qty'];
                $groupId   = $tierPriceData['group_id'];

                $tierPrice      = $tierPrice * (1 + $priceData['tax_rate'] / 100);
                $reduction      = $priceModel->getSalePrice() - $tierPrice;
                $tierPriceModel = new Shopgate_Model_Catalog_TierPrice();
                $tierPriceModel->setFromQuantity($qty);
                $tierPriceModel->setReduction($this->formatPriceNumber($reduction, 2));
                $tierPriceModel->setReductionType(Shopgate_Model_Catalog_TierPrice::DEFAULT_TIER_PRICE_TYPE_FIXED);
                $tierPriceModel->setAggregateChildren(false);

                if (!is_null($groupId)) {
                    $tierPriceModel->setCustomerGroupUid($groupId);
                }

                if ($reduction > 0) {
                    $priceModel->addTierPriceGroup($tierPriceModel);
                }
            }
        }

        $itemModel->setPrice($priceModel);
        $itemModel->setCurrency($currency->code);
        $itemModel->setTaxClass($item['products_tax_class_id']);
        $itemModel->setTaxPercent($priceData['tax_rate']);

        // prepare stock information
        $stockQty = (int)$item['products_quantity'];
        $useStock = _STORE_STOCK_CHECK_BUY == 'false'
            ? '1'
            : '0';

        $stockModel = new Shopgate_Model_Catalog_Stock();
        $stockModel->setAvailabilityText($itemHelper->generateAvailableText($item));
        $stockModel->setStockQuantity($stockQty);
        if ($useStock) {
            $isSalable = $stockQty > 0
                ? 1
                : 0;

            $stockModel->setIsSaleable($isSalable);
            $stockModel->setUseStock(1);
        } else {
            $stockModel->setIsSaleable(1);
            $stockModel->setUseStock(0);
        }

        $itemModel->setStock($stockModel);

        $images    = $itemHelper->getProductImages($item);
        $imagesArr = array();
        $i         = 1;
        foreach ($images as $image) {
            $imagesItemObject = new Shopgate_Model_Media_Image();
            $imagesItemObject->setUrl($image);
            $imagesItemObject->setTitle($itemModel->getName());
            $imagesItemObject->setAlt($itemModel->getName());
            $imagesItemObject->setSortOrder($i++);
            $imagesArr[] = $imagesItemObject;
        }
        $itemModel->setImages($imagesArr);

        /*
         * set categories
         */
        $catSort         = array();
        $categoryArr     = array();
        $categoryNumbers = $itemHelper->getProductCategoryIds($item["products_id"], $catSort);

        // a product having $oldPriceData implies it being in the "TABLE_PRODUCTS_PRICE_SPECIAL"
        // products in that table are shown in the xt_special_products category
        if (!empty($oldPriceData)
            && $this->checkPluginHelper->checkPlugin('xt_special_products', '1.0.0')
        ) {
            $categoryNumbers[]              = 'xt_special_products';
            $catSort['xt_special_products'] = array(
                'value' => strtotime($item['date_added']),
                'order' => 'desc',
            );
        }
        foreach ($categoryNumbers as $catNumber) {
            if (isset($catSort[$catNumber]['value'])) {
                $sort = $catSort[$catNumber]['value'];
            } else {
                $field = empty($catSort[$catNumber]['field'])
                    ? 'products_sort'
                    : empty($catSort[$catNumber]['field']);
                $sort  = $item[$field];

                if (!empty($catSort[$catNumber]['order'])
                    && strtolower($catSort[$catNumber]['order']) == "asc"
                ) {
                    $sort = $this->maxProductsSort - $sort;
                }
            }

            $categoryItemObject = new Shopgate_Model_Catalog_CategoryPath();
            $categoryItemObject->setUid($catNumber);
            $categoryItemObject->setSortOrder($sort);

            $categoryArr[] = $categoryItemObject;
        }
        $itemModel->setCategoryPaths($categoryArr);
        $itemModel->setTags($itemHelper->generateTags($item));
        $itemModel->setProperties($itemHelper->generatePropertiesToProduct($item, $statusRules));
        $itemModel->setAgeRating($itemHelper->generateAgeRating($item));
        $itemModel->setRelations($itemHelper->generateRelatedShopItems($item));
        $itemModel->setDeeplink($itemHelper->generateSeoUrl($item, $seoUrls));
        $itemModel->setInternalOrderInfo($itemHelper->generateInternalOrderInfo($itemArr, $item));

        $isHighlight           = !empty($item['products_startpage'])
            ? $item['products_startpage']
            : '';
        $isHighlightOrderIndex = !empty($item['products_startpage_sort'])
            ? $item['products_startpage_sort']
            : '';

        $itemModel->setData('is_highlight', $isHighlight);
        $itemModel->setData('is_highlight_order_index', $isHighlightOrderIndex);

        // set shipping costs
        $shipping = new Shopgate_Model_Catalog_Shipping();
        $shipping->setAdditionalCostsPerUnit(
            $itemHelper->getShippingCostPerUnitToProduct($itemArr, $item)
        );
        $itemModel->setShipping($shipping);

        if ($itemHelper->hasProductOptionsAndMasterSlaveRelation($item)) {
            $this->log(
                'Product is skipped as it is a parent product with options',
                ShopgateLogger::LOGTYPE_DEBUG
            );

            return null; // we don't support xt_product_options with master/slave products
        }

        if ($itemHelper->hasProductOptions($item)) {
            if ($mode == self::EXPORT_MODE_XML) {
                $itemModel->setInputs($itemHelper->generateOptionDataForXml($item));
            } else {
                $itemModel->setInputs($itemHelper->generateOptionDataForCsv($item));
            }
        }

        if ($itemHelper->hasProductMasterSlaveRelation($item)) {
            $this->_generateVariations($mode, $itemHelper, $item, $itemModel, $seoUrls);
        }

        return $itemModel;
    }

    protected function buildDefaultItemRow()
    {
        /** @var ADOConnection $db */
        global $db;
        $tables = array(
            TABLE_PRODUCTS,
            DB_PREFIX . "_plg_product_to_options",
            DB_PREFIX . "_plg_product_option_groups",
        );

        $tableExsistQuery = "SHOW TABLES LIKE '";
        $tablesExist      = true;
        foreach ($tables as $table) {
            $tableResult = $db->Execute($tableExsistQuery . "{$table}';");
            if ($tableResult->RowCount() < 1) {
                $tablesExist = false;
                break;
            }
        }

        if ($tablesExist) {
            $optionAmount = $db->Execute(
                "SELECT COUNT(DISTINCT pog.option_group_id) AS amount
                                FROM " . TABLE_PRODUCTS . " AS p
                                JOIN " . DB_PREFIX . "_plg_product_to_options AS pto ON p.products_id = pto.products_id
                                JOIN " . DB_PREFIX . "_plg_product_option_groups AS pog ON pog.option_group_id=pto.option_group_id
                                GROUP BY p.products_id
                                ORDER BY amount
                                DESC
                                LIMIT 1"
            );

            if ($row = $optionAmount->fields) {
                $amount = null;
                if (!empty($row["amount"])) {
                    $amount = $row["amount"];
                }
                $this->defaultItemRowOptionCount = (!is_null($amount)
                    && $amount > $this->defaultItemRowOptionCount)
                    ? $amount
                    : $this->defaultItemRowOptionCount;
            }
        }

        return parent::buildDefaultItemRow();
    }

    /**
     * Generates item models for each child product.
     *
     * @param string                   $mode    - XML or CSV
     * @param ShopgateVeytonHelperItem $itemHelper
     * @param array                    $item    - database row of product
     * @param ShopgateItemModel        $parent
     * @param array                    $seoUrls - key/value urls
     */
    private function _generateVariations($mode, $itemHelper, $item, &$parent, $seoUrls = array())
    {
        $this->log('execute _generateVariations() ...', ShopgateLogger::LOGTYPE_DEBUG);
        $result     = $itemHelper->getAttributesToProduct($item);
        $variations = $attributeGroups = array();

        while (!$result->EOF) {
            $item      = $result->fields;
            $itemModel = $this->_buildItem($mode, $itemHelper, $item, array(), $seoUrls);
            $itemModel->setIsChild(true);
            $childAttributes = array();

            if (is_array($itemModel)) {
                /** @var ShopgateItemModel $itemModel */
                $itemModel = $itemModel[0];
            }

            $tierPrices = $itemModel->getPrice()->getTierPricesGroup();
            if (empty($tierPrices)) {
                $itemModel->getPrice()->setTierPricesGroup(
                    $parent->getPrice()->getTierPricesGroup()
                );
            }

            /* use picture from parent if missing on children */
            $images       = $itemModel->getImages();
            $parentImages = $parent->getImages();
            if (empty($images) && !empty($parentImages)) {
                $itemModel->setImages($parentImages);
            }

            $itemModel->setCategoryPaths($parent->getCategoryPaths());

            $options = $this->_getOptions($item);
            foreach ($options as $option) {
                $attributeGroup = new Shopgate_Model_Catalog_AttributeGroup();
                $attributeGroup->setUid($option['attributes_parent']);
                $attributeGroup->setLabel($option['attributes_basename']);
                $attributeGroups[bin2hex($option['attributes_basename'])] = $attributeGroup;

                $childAttribute = new Shopgate_Model_Catalog_Attribute();
                $childAttribute->setGroupUid($option['attributes_parent']);
                $childAttribute->setLabel($option['attributes_name']);
                $childAttributes[] = $childAttribute;
            }

            $itemModel->setAttributes($childAttributes);
            $variations[] = $itemModel;
            $result->MoveNext();
        }

        $parent->setAttributeGroups($attributeGroups);
        $parent->setChildren($variations);

        if (!empty($variations)) {
            $parent->setDisplayType(Shopgate_Model_Catalog_Product::DISPLAY_TYPE_SELECT);
            $parent->getStock()->setUseStock(1);
            $parent->getStock()->setStockQuantity(0);
        } else {
            $parent->setDisplayType(Shopgate_Model_Catalog_Product::DISPLAY_TYPE_SIMPLE);
        }
    }

    /**
     * read variations from database
     *
     * In xt:Commerce 4 the products have a master/slave relation
     *
     * @param array $item
     * * @return array
     */
    private function _getOptions($item)
    {
        /** @var ADOConnection $db */
        global $db, $language;

        $qry
            = "
            SELECT DISTINCT
                a.attributes_id,
                a.attributes_parent,
                a.attributes_model,
                a.attributes_image,
                ad2.attributes_name AS attributes_basename,
                ad.attributes_name,
                ad.attributes_desc

            FROM " . DB_PREFIX . "_plg_products_attributes a

            JOIN " . DB_PREFIX . "_plg_products_attributes_description ad
                ON (ad.attributes_id = a.attributes_id AND ad.language_code = '"
            . $language->code . "')

            JOIN " . DB_PREFIX . "_plg_products_to_attributes pta
                ON (
                    pta.attributes_id = a.attributes_id AND
                    pta.attributes_parent_id = a.attributes_parent
                )

            JOIN " . DB_PREFIX . "_plg_products_attributes a2
                ON (
                    (a2.attributes_parent = 0 OR a2.attributes_parent IS NULL) AND
                    a2.attributes_id = a.attributes_parent
                )

            JOIN " . DB_PREFIX . "_plg_products_attributes_description ad2
                ON (
                    ad2.attributes_id = a2.attributes_id AND
                    ad2.language_code = '" . $language->code . "'
                )

            LEFT JOIN " . DB_PREFIX . "_plg_products_to_attributes pta2
                ON (
                    pta2.attributes_id = a2.attributes_id AND
                    pta2.attributes_parent_id = a2.attributes_parent
                )

            WHERE
                a.status = 1 AND
                pta.products_id = " . $item["products_id"] . "

            ORDER BY a.attributes_parent, a.sort_order
        ";

        $result = $db->Execute($qry);

        $variations = array();

        while (!$result->EOF) {
            $variations[] = $result->fields;
            $result->MoveNext();
        }

        return $variations;
    }

    protected function createItems($limit = null, $offset = null, array $uids = array())
    {
        $this->_buildItems(self::EXPORT_MODE_XML, $limit, $offset, $uids);
    }

    protected function createReviewsCsv()
    {
        $this->buildReviews(self::EXPORT_MODE_CSV);
    }

    /**
     * wrapper function to export review data for xml or csv
     *
     * @param string $mode type of export xml/csv
     * @param null   $limit
     * @param null   $offset
     * @param array  $uids
     *
     * @throws ShopgateLibraryException
     */
    private function buildReviews($mode = self::EXPORT_MODE_XML, $limit = null, $offset = null, array $uids = array())
    {
        /** @var ADOConnection $db */
        global $db;

        $reviewHelper = new ShopgateVeytonHelperReview($db);
        $reviews      = $reviewHelper->getReviews($limit, $offset, $uids);

        if (!$this->checkPluginHelper->checkPlugin('xt_reviews', '1.0.0')) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_DATABASE_ERROR, 'xt_reviews is not activated or version < 1.0.0'
            );
        }

        while (!$reviews->EOF) {
            $reviewData  = $reviews->fields;
            $reviewModel = $reviewHelper->generateReviewFromDatabaseRow($reviewData);

            switch ($mode) {
                case self::EXPORT_MODE_CSV:
                    $this->addReviewRow($reviewModel->asCsvArray($this->buildDefaultReviewRow()));
                    break;

                case self::EXPORT_MODE_XML:
                    $this->addReviewModel($reviewModel);
                    break;
            }

            $reviews->MoveNext();
        }
    }

    protected function createReviews($limit = null, $offset = null, array $uids = array())
    {
        $this->buildReviews(self::EXPORT_MODE_XML, $limit, $offset, $uids);
    }

    protected function createCategoriesCsv()
    {
        $this->buildCategories(self::EXPORT_MODE_CSV);
    }

    /**
     *wrapper function to export category data for xml or csv
     *
     * @param string $mode type of export xml/csv
     * @param null   $limit
     * @param null   $offset
     * @param array  $uids
     */
    private function buildCategories(
        $mode = self::EXPORT_MODE_XML,
        $limit = null,
        $offset = null,
        array $uids = array()
    ) {
        /** @var ADOConnection $db */
        global $db, $language;

        $this->log(
            'Starting export of categories. Memory peak: ' . (memory_get_peak_usage(true) / (1024 * 1024)) . ' MB',
            ShopgateLogger::LOGTYPE_DEBUG
        );

        $categoryHelper       = new ShopgateVeytonHelperCategory($db, $language);
        $categories           =
            $categoryHelper->getCategories($this->shopId, $this->permissionBlacklist, $limit, $offset, $uids);
        $seoUrlsByCategoryIds = $categoryHelper->getCategorySeoUrls();
        $maxOrderIndex        = $categoryHelper->getMaximumOrderIndex();
        $webPrefix            = _SYSTEM_BASE_HTTP . _SRV_WEB . _SRV_WEB_IMAGES . "category/popup/";

        // plugin 'xt_special_products' adds a virtual category which is exported here in case:
        // * the plugin is active
        // * it's the first run of a split export ($offset === 0)
        // * it's not a split export at all ($offset === null)
        // * a list of category UIDs should be exported which does not contain 'xt_special_products'
        if (
            $this->checkPluginHelper->checkPlugin('xt_special_products', '1.0.0')
            && (empty($offset))
            && (empty($uids) || in_array('xt_special_products', $uids))
        ) {
            switch ($mode) {
                case self::EXPORT_MODE_CSV:
                    $this->addCategoryRow(
                        $categoryHelper->generateSpecialProductsCategory()->asCsvArray(
                            $this->buildDefaultCategoryRow()
                        )
                    );
                    break;

                case self::EXPORT_MODE_XML:
                    $this->addCategoryModel($categoryHelper->generateSpecialProductsCategory());
                    break;
            }
        }

        while (!$categories->EOF) {
            $categoryData  = $categories->fields;
            $categoryModel = $categoryHelper->generateCategoryFromDatabaseRow(
                $categoryData,
                $seoUrlsByCategoryIds,
                $maxOrderIndex,
                $webPrefix
            );

            switch ($mode) {
                case self::EXPORT_MODE_CSV:
                    $this->addCategoryRow($categoryModel->asCsvArray($this->buildDefaultCategoryRow()));
                    break;

                case self::EXPORT_MODE_XML:
                    $this->addCategoryModel($categoryModel);
                    break;
            }
            $categories->MoveNext();
        }

        $this->log(
            'Finished export of categories. Memory peak: ' . (memory_get_peak_usage(true) / (1024 * 1024)) . ' MB',
            ShopgateLogger::LOGTYPE_DEBUG
        );
    }

    protected function createCategories($limit = null, $offset = null, array $uids = array())
    {
        $this->buildCategories(self::EXPORT_MODE_XML, $limit, $offset, $uids);
    }

    protected function createMediaCsv()
    {
        // TODO: Implement createMediaCsv() method.
    }

    /**
     * read a customers data from the database by the uid
     *
     * @param $userid
     *
     * @return null
     */
    private function _loadOrderUserByNumber($userid)
    {
        /** @var ADOConnection $db */
        global $db;

        if (empty($userid)) {
            return null;
        }

        $qry    = "SELECT c.* FROM " . TABLE_CUSTOMERS . " c WHERE c.customers_cid = " . $userid . " ";
        $result = $db->Execute($qry);
        $user   = $result->fields;

        return $user;
    }

    private function _setOrderAsShipped($order)
    {
        $orderAPI = new ShopgateOrderApi();
        $orderAPI->setShippingComplete($order);
    }

    private function _useNativeMobileMode()
    {
        $_SESSION['isMobile'] = true;
    }
}
