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
class ShopgateVeytonHelperCustomer extends ShopgateVeytonHelperBase
{
    /**
     * Gets token or creates one if needed
     *
     * @param ShopgateCustomer $customer
     *
     * @return null|string
     */
    public function getTokenForCustomer(ShopgateCustomer $customer)
    {
        $token = $this->getToken($customer);
        if (!$token) {
            $token = $this->createToken($customer);
            $customer->setCustomerToken($token);
            $this->saveToken($customer);
        }

        return $token;
    }

    /**
     * read the customers token from the database
     *
     * @param ShopgateCustomer $customer
     *
     * @return string|null
     */
    private function getToken(ShopgateCustomer $customer)
    {
        global $db;
        $query  = "SELECT c.customer_token FROM " . TABLE_SHOPGATE_CUSTOMERS
            . " AS c where c.customer_id = \"{$customer->getCustomerId()}\" ;";
        $result = $db->Execute($query);

        return isset($result->fields['customer_token'])
            ? $result->fields['customer_token']
            : null;
    }

    /**
     * Returns customer token based on mail address and CustomerId
     *
     * @param ShopgateCustomer $customer
     *
     * @return string
     */
    private function createToken(ShopgateCustomer $customer)
    {
        return md5($customer->getMail() . $customer->getCustomerId());
    }

    /**
     * stores a new generated token to an user in the database
     *
     * @param ShopgateCustomer $customer
     *
     * @return bool|mysqli_result|resource
     */
    private function saveToken(ShopgateCustomer $customer)
    {
        global $db;
        $data                   = array();
        $data['customer_id']    = $customer->getCustomerId();
        $data['customer_token'] = $customer->getCustomerToken();
        $db->AutoExecute(TABLE_SHOPGATE_CUSTOMERS, $data, "INSERT");
    }

    /**
     * read order data from database by a customers token
     *
     * @param        $customerToken
     * @param        $customerLanguage
     * @param int    $limit
     * @param int    $offset
     * @param string $orderDateFrom
     * @param string $sortOrder
     *
     * @return array
     * @throws ShopgateLibraryException
     */
    public function getOrdersByToken(
        $customerToken,
        $customerLanguage,
        $limit = 10,
        $offset = 0,
        $orderDateFrom = '',
        $sortOrder = 'created_desc'
    ) {
        global $db;

        $customerLanguageCode = explode("_", $customerLanguage);
        $customerLanguageCode = $customerLanguageCode[0];
        $customerId           = $this->getIdFromToken($customerToken);
        $orders               = array();
        if (!is_numeric($customerId)) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_CUSTOMER_TOKEN_INVALID,
                "Shopgate Plugin - no customer found with token: " . $customerToken,
                true
            );
        }

        $qry
            = "
			SELECT
				o.*
			FROM " . TABLE_ORDERS . " o
			WHERE o.customers_id = " . $customerId;

        if (!empty($orderDateFrom)) {
            $qry .= " AND o.date_purchased >= '" . $orderDateFrom . "'";
        }
        $sortOrder = strtoupper(str_replace('created_', '', $sortOrder));
        $qry .= " ORDER BY o.date_purchased {$sortOrder}";
        $qry .= " LIMIT " . $limit . " OFFSET " . $offset;

        $result = $db->Execute($qry);

        while (!$result->EOF) {
            $row               = $result->fields;
            $veytonOrderNumber = $row["orders_id"];
            $order             = new ShopgateExternalOrder();
            $veytonOrderHelper = new order($veytonOrderNumber);
            $data              = $veytonOrderHelper->_buildData($veytonOrderNumber);

            $order->setOrderNumber($data['order_data']['order_info_data']['shopgate_order_number']);
            $order->setExternalOrderNumber($veytonOrderNumber);
            $order->setExternalOrderId($veytonOrderNumber);
            $order->setStatusName($data['order_data']['orders_status']);
            $order->setCreatedTime(date(DateTime::ISO8601, strtotime($data['order_data']['date_purchased'])));
            $order->setMail($data['order_data']['customers_email_address']);
            $order->setPhone($data['order_data']['delivery_phone']);
            $order->setMobile($data['order_data']['delivery_mobile_phone']);
            $order->setCurrency($data['order_data']['currency_code']);

            $order->setPaymentMethod(
                $this->getPaymentMethodTitle($data['order_data']['payment_code'], $customerLanguageCode)
            );

            $order->setIsPaid(false);
            $order->setIsShippingCompleted(false);

            foreach ($data['order_history'] as $status) {
                switch ($status['orders_status_id']) {
                    case 23:
                        $order->setIsPaid(true);
                        $order->setPaymentTime(date(DateTime::ISO8601, strtotime($status['date_added'])));
                        break;
                    case 33:
                        $order->setIsShippingCompleted(true);
                        $order->setShippingCompletedTime(date(DateTime::ISO8601, strtotime($status['date_added'])));
                }
            }

            $order->setAmountComplete($data['order_total']['total']['plain']);

            $invoiceAddress = new ShopgateAddress();

            $invoiceAddress->setGender($data['order_data']["billing_gender"]);
            $invoiceAddress->setFirstName($data['order_data']["billing_firstname"]);
            $invoiceAddress->setLastName($data['order_data']["billing_lastname"]);
            $invoiceAddress->setCompany($data['order_data']["billing_company"]);
            $invoiceAddress->setStreet1($data['order_data']["billing_street_address"]);
            $invoiceAddress->setCity($data['order_data']["billing_city"]);
            $invoiceAddress->setZipcode($data['order_data']["billing_postcode"]);
            $invoiceAddress->setCountry($data['order_data']["billing_country_code"]);
            $invoiceAddress->setState($data['order_data']["billing_federal_state_code_iso"]);
            $invoiceAddress->setIsDeliveryAddress(false);
            $invoiceAddress->setIsInvoiceAddress(true);

            $order->setInvoiceAddress($invoiceAddress);

            $deliveryAddress = new ShopgateAddress();

            $deliveryAddress->setGender($data['order_data']["delivery_gender"]);
            $deliveryAddress->setFirstName($data['order_data']["delivery_firstname"]);
            $deliveryAddress->setLastName($data['order_data']["delivery_lastname"]);
            $deliveryAddress->setCompany($data['order_data']["delivery_company"]);
            $deliveryAddress->setStreet1($data['order_data']["delivery_street_address"]);
            $deliveryAddress->setCity($data['order_data']["delivery_city"]);
            $deliveryAddress->setZipcode($data['order_data']["delivery_postcode"]);
            $deliveryAddress->setCountry($data['order_data']["delivery_country_code"]);
            $deliveryAddress->setState($data['order_data']["delivery_federal_state_code_iso"]);
            $deliveryAddress->setIsDeliveryAddress(true);
            $deliveryAddress->setIsInvoiceAddress(false);

            $order->setDeliveryAddress($deliveryAddress);

            $coupons = $this->getCouponsFromOrder(
                $veytonOrderNumber,
                $customerLanguageCode,
                $data['order_data']['currency_code']
            );
            $order->setExternalCoupons($coupons);

            $orderItems = array();
            foreach ($data['order_products'] as $product) {
                $item = new ShopgateExternalOrderItem;

                $item->setItemNumber($product["products_id"]);
                $item->setItemNumberPublic($product["products_model"]);
                $item->setQuantity($product["products_quantity"]);
                $item->setName($product["products_name"]);
                $item->setUnitAmount($product["products_price"]['plain_otax']);
                $item->setUnitAmountWithTax($product["products_price"]['plain']);
                $item->setTaxPercent($product["products_tax_rate"]);
                $item->setCurrency($data['order_data']['currency_code']);
                $orderItems[] = $item;
            }
            $order->setItems($orderItems);

            $orderTaxes = array();
            foreach ($data['order_total']['product_tax'] as $tax) {
                $orderTax = new ShopgateExternalOrderTax();

                $orderTax->setLabel($tax["tax_key"] . '%');
                $orderTax->setTaxPercent($tax["tax_key"]);
                $orderTax->setAmount($tax["tax_value"]["plain"]);
                $orderTaxes[] = $orderTax;
            }
            $order->setOrderTaxes($orderTaxes);

            $extraCosts = array();
            foreach ($data['order_total_data'] as $orderTotal) {
                $extraCost = new ShopgateExternalOrderExtraCost();
                switch ($orderTotal['orders_total_key']) {
                    case 'xt_coupon':
                        continue(2);
                    case 'shipping':
                        $extraCost->setType('shipping');
                        break;
                    case 'payment':
                        $extraCost->setType('payment');
                        break;
                    default:
                        $extraCost->setType('misc');
                }
                $extraCost->setTaxPercent($orderTotal['orders_total_tax_rate']);
                $extraCost->setAmount($orderTotal['orders_total_final_price']['plain']);
                $extraCost->setLabel($orderTotal['orders_total_name']);

                $extraCosts[] = $extraCost;
            }
            $order->setExtraCosts($extraCosts);

            $deliveryNotes = $this->getTrackingsFromOrder($veytonOrderNumber);
            $order->setDeliveryNotes($deliveryNotes);
            $orders[] = $order;
            $result->MoveNext();
        }

        return $orders;
    }

    /**
     * read the customers uid from the database by an token
     *
     * @param $token
     *
     * @return int|null
     */
    public function getIdFromToken($token)
    {
        global $db;
        $query  = "SELECT c.customer_id FROM " . TABLE_SHOPGATE_CUSTOMERS
            . " AS c where c.customer_token = \"{$token}\" ;";
        $result = $db->Execute($query);

        return isset($result->fields['customer_id'])
            ? $result->fields['customer_id']
            : null;
    }

    /**
     * read the title of an payment method from the database by the language- and payment code
     *
     * @param $paymentCode
     * @param $languageCode
     *
     * @return mixed
     */
    public function getPaymentMethodTitle($paymentCode, $languageCode)
    {
        global $db;
        $title = $paymentCode;
        $qry
               = "
			SELECT * FROM " . TABLE_PAYMENT . " p
			LEFT JOIN " . TABLE_PAYMENT_DESCRIPTION . " pd ON p.payment_id = pd.payment_id
			WHERE p.payment_code = \"{$paymentCode}\" ;";
        try {
            $paymentTitleResult = $db->Execute($qry);
        } catch (Exception $e) {
            return $paymentCode; // fallback
        }
        while (!$paymentTitleResult->EOF) {
            if ($paymentTitleResult->fields['language_code'] == $languageCode) {
                return $paymentTitleResult->fields['payment_name'];
            }
            if ($paymentTitleResult->fields['language_code'] == 'en') {
                $title = $paymentTitleResult->fields['payment_name'];
            }
            $paymentTitleResult->MoveNext();
        }

        return $title;
    }

    /**
     * read coupons data from the database by the orders uids
     *
     * @param $orderId
     * @param $customerLanguageCode
     *
     * @return array
     */
    private function getCouponsFromOrder($orderId, $customerLanguageCode, $currency)
    {
        global $db;

        $coupons = array();
        $qry
                 = "
			SELECT * FROM " . TABLE_COUPONS_REDEEM . " cr
			LEFT JOIN " . TABLE_COUPONS . " c ON cr.coupon_id = c.coupon_id
			LEFT JOIN " . TABLE_COUPONS_DESCRIPTION . " cd ON (cr.coupon_id = cd.coupon_id AND cd.language_code = '"
            . $customerLanguageCode . "')
			WHERE cr.order_id = " . $orderId;
        try {
            $couponResult = $db->Execute($qry);
        } catch (Exception $e) {
            return $coupons; // coupon tables not found
        }

        while (!$couponResult->EOF) {
            $coupon       = new ShopgateExternalCoupon();
            $veytonCoupon = $couponResult->fields;
            $coupon->setCode($veytonCoupon['coupon_code']);
            $coupon->setName($veytonCoupon['coupon_name']);
            $coupon->setOrderIndex($veytonCoupon['coupons_redeem_id']);
            $coupon->setDescription($veytonCoupon['coupon_description']);
            $coupon->setAmount($veytonCoupon['redeem_amount']);
            $coupon->setCurrency($currency);
            $coupon->setIsFreeShipping($veytonCoupon['coupon_free_shipping'] == 1);
            $coupons[] = $coupon;
            $couponResult->MoveNext();
        }

        return $coupons;
    }

    /**
     * read tracking codes to an oder from the database
     *
     * @param $orderId
     *
     * @return array
     */
    private function getTrackingsFromOrder($orderId)
    {
        global $db;

        $deliveryNotes = array();
        if (file_exists(_SRV_WEBROOT . "/plugins/xt_ship_and_track/classes/constants.php")) {
            include_once _SRV_WEBROOT . "/plugins/xt_ship_and_track/classes/constants.php";
        } else {
            return $deliveryNotes;
        }
        $qry
            = "
			SELECT * FROM " . TABLE_TRACKING . " t
			LEFT JOIN " . TABLE_SHIPPER . " s ON t.tracking_shipper_id = s.id
			WHERE t.tracking_order_id = " . $orderId;
        try {
            $trackingResult = $db->Execute($qry);
        } catch (Exception $e) {
            return $deliveryNotes; // coupon tracking not found
        }

        while (!$trackingResult->EOF) {
            $deliveryNote   = new ShopgateDeliveryNote();
            $veytonTracking = $trackingResult->fields;

            $deliveryNote->setShippingServiceId($veytonTracking['shipper_code']);
            $deliveryNote->setShippingTime($veytonTracking['tracking_added']);
            $deliveryNote->setTrackingNumber($veytonTracking['tracking_code']);

            $deliveryNotes[] = $deliveryNote;
            $trackingResult->MoveNext();
        }

        return $deliveryNotes;
    }

    /**
     * Determines whether the two addresses in array
     * are equal to each other
     *
     * @param ShopgateAddress[] $shopgateAddresses
     *
     * @return bool
     */
    public function areAddressesEqual(array $shopgateAddresses)
    {
        if (count($shopgateAddresses) == 2) {
            $whiteList =
                array(
                    'gender',
                    'first_name',
                    'last_name',
                    'street_1',
                    'street_2',
                    'zipcode',
                    'city',
                    'country',
                    'custom_fields',
                );

            return $shopgateAddresses[0]->compare($shopgateAddresses[0], $shopgateAddresses[1], $whiteList);
        }

        return false;
    }

    /**
     * @param string $user
     * @param string $pass
     *
     * @return bool
     */
    public function validatePassword($user, $pass)
    {
        return $this->assertMinimumVersion('5.0.00')
            ? $this->validatePasswordXtc5($user, $pass)
            : $this->validatePasswordXtc4($user, $pass);
    }

    /**
     * @param string $user
     * @param string $pass
     *
     * @return bool
     */
    private function validatePasswordXtc4($user, $pass)
    {
        /** @noinspection PhpToStringImplementationInspection */
        /** @noinspection PhpParamsInspection */
        /** @var ADORecordSet $result */
        $result = $this->db->Execute(
            "SELECT `customers_password` FROM " . TABLE_CUSTOMERS . " " .
            "WHERE `customers_email_address` = {$this->db->qstr($user)} LIMIT 1;"
        );

        /** @noinspection PhpIncludeInspection */
        require_once _SRV_WEBROOT . _SRV_WEB_FRAMEWORK . 'functions/check_pw.inc.php';

        return _checkPW($pass, $result->fields['customers_password']);
    }

    /**
     * @param string $user
     * @param string $pass
     *
     * @return bool
     */
    private function validatePasswordXtc5($user, $pass)
    {
        /** @noinspection PhpToStringImplementationInspection */
        /** @noinspection PhpParamsInspection */
        /** @var ADORecordSet $result */
        $result = $this->db->Execute(
            "SELECT `customers_id`, `customers_password`, `password_type` FROM " . TABLE_CUSTOMERS . " " .
            "WHERE `customers_email_address` = {$this->db->qstr($user)} LIMIT 1;"
        );

        if (!$result) {
            return false;
        }

        /** @noinspection PhpIncludeInspection */
        require_once _SRV_WEBROOT . _SRV_WEB_FRAMEWORK . 'classes/class.xt_password.php';

        $xtPassword = new xt_password();

        /** @noinspection PhpParamsInspection */
        /** @noinspection PhpFieldNotSetInspection */
        return $xtPassword->verify_password(
            $pass,
            $result->fields['customers_password'],
            $result->fields['customers_id'],
            $result->fields['password_type']
        );
    }
}
