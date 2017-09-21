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
class ShopgateVeytonHelperCart
{
    private $log;

    /**
     * ShopgateVeytonHelperCart constructor.
     */
    public function __construct()
    {
        $this->log = ShopgateLogger::getInstance();
    }

    /**
     * includes needed files to use veyton logic for handling coupons
     */
    public function includeVeytonCouponClasses()
    {
        $path      = _SRV_WEBROOT . 'plugins/xt_coupons/classes/class.';
        $extension = '.php';

        $couponClasses = array(
            'xt_coupons',
            'xt_coupons_redeem',
            'xt_coupons_products',
            'xt_coupons_categories',
        );

        foreach ($couponClasses as $couponClass) {
            $file = $path . $couponClass . $extension;
            if (!class_exists($couponClass) && file_exists($file)) {
                require_once $file;
            }
        }
    }

    /**
     * fills the veyton cart with data and let it calculate e.g. prices, discounts
     *
     * @param array $products
     * @param       $customerId
     */
    public function buildVeytonShoppingCart(array $products, $customerId)
    {
        // this object is needed for the plugin eval code
        global $xtPlugin;

        // veyton classes store and use the session to save e.g. customer data, coupon data
        // by default customer and cart instances is created on request
        $_SESSION['customer'] = (!empty($customerId))
            ? new customer($customerId)
            : new customer();

        if ($id = (int)$_SESSION['cart']->customer_id > 0) {
            $_SESSION['registered_customer'] = $id;
        }

        $_SESSION['cart'] = new cart();

        $this->log->log('[checkCart coupons] adding products to veyton cart', ShopgateLogger::LOGTYPE_DEBUG);

        /** @var ShopgateOrderItem $product */
        foreach ($products as $product) {

            // recreated the data structure from veyton
            $data_array = array(
                'action'      => 'add_product',
                'product'     => $product->getItemNumber(),
                'qty'         => $product->getQuantity(),
                'info'        => 1,
                'page'        => 'product',
                'page_action' => 'standardartikel',
            );

            // the following eval snippets were taken from the veyton source before and after a product
            // was added to the shopping cart
            // To ensure that third-party supplier plugins (which do things with the shopping cart) work correct.
            ($plugin_code = $xtPlugin->PluginCode('form_handler.php:data_array_top'))
                ? eval($plugin_code)
                : false;

            $_SESSION['cart']->_addCart($data_array);

            ($plugin_code = $xtPlugin->PluginCode('form_handler.php:add_product_top'))
                ? eval($plugin_code)
                : false;
            $this->triggerAddProductBottomHooks($xtPlugin);

            //the following call recalculates the whole shopping cart
            $_SESSION['cart']->_refresh();
        }
        $this->log->log('veyton cart:' . print_r($_SESSION['cart'], true), ShopgateLogger::LOGTYPE_DEBUG);
    }

    /**
     * returns the xt_sperrgut price for an order or a cart
     *
     * @param ShopgateCartBase $shopgateCartBase
     *
     * @return array
     */
    public function getBulkPrice(ShopgateCartBase $shopgateCartBase)
    {
        $this->log->log('starting xt_sperrgut', ShopgateLogger::LOGTYPE_DEBUG);

        $this->buildVeytonShoppingCart($shopgateCartBase->getItems(), $shopgateCartBase->getExternalCustomerId());
        $sperrGut = new xt_sperrgut;
        $sperrGut->_determinePrice();
        $sperrGut->_addToCart();
        $sperrgutPrice = $_SESSION['cart']->show_sub_content['xt_sperrgut']['products_price'];

        if (empty($sperrgutPrice)) {
            $sperrgutPrice['plain_otax'] = 0;
            $sperrgutPrice['plain']      = 0;
        }

        $this->log->log('xt_sperrgut done', ShopgateLogger::LOGTYPE_DEBUG);

        return $sperrgutPrice;
    }

    /**
     * returns the discount of a veyton cart
     *
     * @param ShopgateOrderItem[] $orderItems
     * @param array               $result
     * @param string              $logPosition
     *
     * @return int
     */
    public function getCouponDiscountGrossFromCart(array $orderItems, $result, $logPosition = 'checkCart')
    {
        $discountGross = 0;

        $logText = '[' . $logPosition . ' coupons] getting price from ';
        if (!empty($_SESSION['cart']->total_discount)) {
            $this->log->log(
                $logText . '"total_discount" field',
                ShopgateLogger::LOGTYPE_DEBUG
            );
            $discountGross = $_SESSION['cart']->total_discount;
        } elseif (!empty($_SESSION['cart']->discount['plain'])
            && is_array($_SESSION['cart']->discount)
        ) {
            // is_array() / isset() is needed here. $_SESSION['cart']->discount['plain'] returned in one case "f".
            $this->log->log(
                $logText . '"plain" field out of the discount array',
                ShopgateLogger::LOGTYPE_DEBUG
            );
            $discountGross = $_SESSION['cart']->discount['plain'];
        } elseif (!empty($_SESSION['cart']->coupon_fix_discount)) {
            $this->log->log(
                $logText . ' "coupon_fix_discount" field out of the cart',
                ShopgateLogger::LOGTYPE_DEBUG
            );
            $discountGross = $_SESSION['cart']->coupon_fix_discount;
        } else {
            // workaround if the discount wasn't
            // calculated through the shopping cart
            $this->log->log(
                '[' . $logPosition . ' coupons] discount could\'t be loaded from the shopping cart.'
                . ' it will be taken from the veyton coupon object',
                ShopgateLogger::LOGTYPE_DEBUG
            );

            if (!empty($result['coupon_percent'])) {
                $discountGross = 0;
                foreach ($orderItems as $orderItem) {
                    $discountGross += $orderItem->getUnitAmountWithTax() * $orderItem->getQuantity();
                }
            } elseif (!empty($result['coupon_amount'])) {
                $discountGross = $result['coupon_amount'];
            }
        }

        return (float)$discountGross;
    }

    /**
     * update the total shipping value to an order
     *
     * @param $ordersTotalId
     *
     * @return mixed
     */
    public function updateOrderTotalShipping($ordersTotalId)
    {
        global $db;

        return $db->AutoExecute(
            TABLE_ORDERS_TOTAL,
            array(
                'orders_total_price' => 0,
                'orders_total_name'  => TEXT_XT_COUPON_FREE_SHIPPING,

            ),
            "UPDATE",
            "orders_total_id = {$ordersTotalId}"
        );
    }

    /**
     * update the products data to an order
     *
     * @param $discount
     * @param $price
     * @param $insertId
     */
    public function updateOrderProduct($discount, $price, $insertId)
    {
        global $db;

        $db->AutoExecute(
            TABLE_ORDERS_PRODUCTS,
            array(
                'products_discount' => $discount,
                'products_price'    => $price,
            ),
            "UPDATE",
            "orders_products_id = {$insertId}"
        );
    }

    /**
     * use a veyton function to get all product uids to an coupon
     *
     * @param int $couponId
     *
     * @return int[]
     */
    public function getCouponProductIds($couponId)
    {
        $xtCouponsProducts = new xt_coupons_products();

        return $xtCouponsProducts->_getIDs($couponId);
    }

    /**
     * read all category uids to an coupon
     *
     * @param int $couponId
     * @param int $shopId
     *
     * @return int[]
     */
    public function getCouponCategoriesProductIds($couponId, $shopId)
    {
        global $db;

        $where = array(
            'coupon_id = ' . (int)$couponId,
        );

        $result = $db->GetOne(
            'SHOW COLUMNS FROM ' . TABLE_COUPONS_CATEGORIES . ' LIKE \'store_id\''
        );

        if (!empty($result)) {
            $where[] = 'store_id = ' . (int)$shopId;
        }

        /** @noinspection PhpParamsInspection */
        $query = 'SELECT * FROM ' . TABLE_COUPONS_CATEGORIES . ' AS c
			 JOIN ' . TABLE_PRODUCTS_TO_CATEGORIES . ' AS ptc ON c.categories_id = ptc.categories_id
			 WHERE ' . implode(' AND ', $where);

        $record = $db->Execute($query);

        if ($record->RecordCount() > 0) {
            while (!$record->EOF) {
                $records = $record->fields;
                $data[]  = $records['products_id'];
                $record->MoveNext();
            }
            $record->Close();
        }

        return $data;
    }

    /**
     * avoid triggering the hook of xt_cart_popup as it causes a fatal error
     * if the request was not handled by the index.php
     *
     * @param hookpoint $xtPlugin
     */
    private function triggerAddProductBottomHooks($xtPlugin)
    {
        $plugin_code = $xtPlugin->PluginCode('form_handler.php:add_product_bottom');
        if (!empty($plugin_code) && !strstr($plugin_code, 'xt_cart_popup')) {
            eval($plugin_code);
        }
    }
}
