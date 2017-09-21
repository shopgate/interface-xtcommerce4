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
class ShopgateOrderModel
{
    private $db;

    private $log;

    private $orderStatusCanceled;

    private $orderStatusShipped;

    private $merchantApi;

    const SG_REMOVE_ORDER_REMOVE_ITEM_HOOK_SECTION = "removeOrderItem";
    const SG_REMOVE_ORDER_UPDATE_ITEM_HOOK_SECTION = "updateOrderItem";

    /**
     * @param mixed $db
     */
    public function setDb($db)
    {
        $this->db = $db;
    }

    /**
     * @param mixed $orderStatusCanceled
     */
    public function setOrderStatusCanceled($orderStatusCanceled)
    {
        $this->orderStatusCanceled = $orderStatusCanceled;
    }

    /**
     * @param mixed $orderStatusShipped
     */
    public function setOrderStatusShipped($orderStatusShipped)
    {
        $this->orderStatusShipped = $orderStatusShipped;
    }

    /**
     * @param mixed $log
     */
    public function setLog($log)
    {
        $this->log = $log;
    }

    /**
     * @param mixed $merchantApi
     */
    public function setMerchantApi($merchantApi)
    {
        $this->merchantApi = $merchantApi;
    }

    /**
     * read order from database by order uid
     *
     * @param $orderId
     *
     * @return mixed
     */
    public function getOrder($orderId)
    {
        // get the order from the database
        $shopgateOrder = $this->db->Execute(
            "SELECT * FROM " . TABLE_SHOPGATE_ORDERS
            . " WHERE orders_id = {$orderId} LIMIT 1;"
        );

        return $shopgateOrder;
    }

    /**
     * update the history to an order
     *
     * @param $statusArr
     */
    public function updateOrderHistory($statusArr)
    {
        // update order history
        $keyString   = "`" . implode("`, `", array_keys($statusArr)) . "`";
        $valueString = "'" . implode("', '", $statusArr) . "'";
        $qry         = "INSERT INTO `" . TABLE_ORDERS_STATUS_HISTORY
            . "`\n ($keyString)\n VALUES\n ($valueString)";

        if (!$this->db->Execute($qry)) {
            $this->log->log('Failed query: ' . $qry, ShopgateLogger::LOGTYPE_ERROR);
        }
    }

    /**
     * flag a shopgate order entry as "cancellation sent to shopgate"
     *
     * @param $shopgateOrderNumber
     */
    public function setShopgateOrderAsCancelled($shopgateOrderNumber)
    {
        $qry = "UPDATE " . TABLE_SHOPGATE_ORDERS
            . " SET is_cancellation_sent = 1 WHERE shopgate_order_number={$shopgateOrderNumber};";
        if (!$this->db->Execute($qry)) {
            $this->log->log('Failed query: ' . $qry, ShopgateLogger::LOGTYPE_ERROR);
        }
    }

    /**
     * read order data from the database by its status
     *
     * @param $status
     *
     * @return array
     */
    private function getOrdersByStatus($status)
    {
        $shopgateOrders = $this->db->Execute(
            "SELECT DISTINCT " .
            "`" . TABLE_SHOPGATE_ORDERS . "`.`orders_id`, " .
            "`shopgate_order_number` " .
            "FROM `" . TABLE_SHOPGATE_ORDERS . "` " .
            "WHERE " .
            "`" . TABLE_SHOPGATE_ORDERS . "`.`is_cancellation_sent` = 0 " .
            ";"
        );

        $sgOrdersList = array();
        while (!$shopgateOrders->EOF) {
            $sgOrdersList[] = $shopgateOrders->fields;
            $shopgateOrders->moveNext();
        }
        $orders = array();

        foreach ($sgOrdersList as $sgOrder) {
            $query = "SELECT DISTINCT o.orders_id FROM " . TABLE_ORDERS . " AS o "
                . "LEFT JOIN " . TABLE_ORDERS_STATUS_HISTORY . " AS osh ON o.orders_id = osh.orders_id "
                . "WHERE o.orders_id='{$sgOrder['orders_id']}' AND (o.orders_status = '{$status}' OR osh.orders_status_id = '{$status}');";

            $orderData = $this->db->Execute($query);

            if (!$orderData->EOF) {
                $orders[] = $sgOrder;
            }
        }

        return $orders;
    }

    /**
     * @param $status
     *
     * @return array
     */
    private function getByCancellationNotSent($status)
    {
        $shopgateOrders = $this->db->Execute(
            "SELECT DISTINCT " .
            "`" . TABLE_SHOPGATE_ORDERS . "`.`orders_id`, " .
            "`shopgate_order_number`, `order_data`, `shopgate_orders_id`, `cancellation_data`" .
            "FROM `" . TABLE_SHOPGATE_ORDERS . "` " .
            "WHERE " .
            "`" . TABLE_SHOPGATE_ORDERS . "`.`is_cancellation_sent` = 0 " .
            ";"
        );

        $sgOrdersList = array();
        while (!$shopgateOrders->EOF) {
            $sgOrdersList[] = $shopgateOrders->fields;
            $shopgateOrders->moveNext();
        }

        $orders = array();

        foreach ($sgOrdersList as $sgOrder) {
            $query = "SELECT DISTINCT o.orders_id FROM " . TABLE_ORDERS . " AS o "
                . "LEFT JOIN " . TABLE_ORDERS_STATUS_HISTORY . " AS osh ON o.orders_id = osh.orders_id "
                . "WHERE o.orders_id='{$sgOrder['orders_id']}' AND (o.orders_status != '{$status}' OR osh.orders_status_id != '{$status}');";

            $orderData = $this->db->Execute($query);

            if (!$orderData->EOF) {
                $orders[] = $sgOrder;
            }
        }

        return $orders;
    }

    /**
     * Marks cancelled orders as "cancelled" at Shopgate.
     *
     * This will find all orders that are marked "cancelled" at Veyton but not at
     * Shopgate yet and marks them "cancelled" at Shopgate via Shopgate Merchant API.
     *
     * @param string $message    Process log will be appended to this reference.
     * @param int    $errorCount This reference gets incremented on errors.
     */
    public function cronSetOrdersCanceled(&$message, &$errorCount)
    {
        $orders = $this->getOrdersByStatus($this->orderStatusCanceled);
        foreach ($orders as $sgOrder) {
            try {
                $this->setOrderCanceled($sgOrder['shopgate_order_number'], $sgOrder['orders_id']);
            } catch (Exception $e) {
                $errorCount++;
                $msg =
                    "[cronSetOrdersCanceled] Shopgate order number:'{$sgOrder['shopgate_order_number']}' [Exception] {$e->getCode()}, {$e->getMessage()}\n";
                $this->log->log($msg, ShopgateLogger::LOGTYPE_ERROR);
                $message .= $msg;
            }
        }
    }

    /**
     * @param $message
     * @param $errorCount
     */
    public function cronSetOrdersPositionCanceled(&$message, &$errorCount)
    {
        $orders                           = $this->getByCancellationNotSent($this->orderStatusCanceled);
        $updatePartialCancellationResults = array();

        foreach ($orders as $sgOrder) {
            $order     = new order($sgOrder['orders_id']);
            $orderData = $order->_buildData($sgOrder['orders_id']);
            /** @var ShopgateOrder $shopgateApiOrder */
            $shopgateApiOrder = unserialize($sgOrder['order_data']);

            $canceledProducts = array();

            $resultItem = array(
                'shopgate_order_number' => $shopgateApiOrder->getOrderNumber(),
                'shopgate_orders_id'    => $sgOrder['shopgate_orders_id'],
                'order_number'          => $sgOrder['orders_id'],
            );

            $itemDetect = array();
            foreach ($orderData['order_products'] as $product) {
                array_push($itemDetect, $product['products_id']);
                if ($newQuantity = $this->getDifferentOrderItemQty($product, $shopgateApiOrder->getItems())) {
                    $canceledProducts[$product['products_id']] = $newQuantity;
                }
            }

            foreach ($shopgateApiOrder->getItems() as $item) {
                if ($item->getItemNumber() === 'COUPON' && !in_array($item->getItemNumber(), $itemDetect)) {
                    continue;
                }

                if (!in_array($item->getItemNumber(), $itemDetect)) {
                    $canceledProducts[$item->getItemNumber()] = 0;
                }
            }

            array_push(
                $updatePartialCancellationResults,
                array_merge(
                    $resultItem,
                    $this->updatePartialCancellationData($sgOrder, $canceledProducts)
                )
            );
        }

        foreach ($updatePartialCancellationResults as $updatePartialCancellationResult) {
            if (array_key_exists('items', $updatePartialCancellationResult)
                && count(
                    $updatePartialCancellationResult['items']
                )
            ) {
                try {
                    $this->merchantApi->cancelOrder(
                        $updatePartialCancellationResult['shopgate_order_number'],
                        false,
                        $updatePartialCancellationResult['items']
                    );
                    $historyMessage = 'Änderungen (' . $updatePartialCancellationResult['totalCancellations']
                        . ' Positionen) wurden an Shopgate übermittelt.';
                    //$this->setShopgateOrderAsCancelled($updatePartialCancellationResult['shopgate_order_number']);
                } catch (ShopgateLibraryException $e) {
                    $errorCount++;
                    $historyMessage =
                        "Es ist ein Fehler im Shopgate-Plugin aufgetreten ({$e->getCode()}): {$e->getMessage()}";
                    $message .= $historyMessage;
                } catch (ShopgateMerchantApiException $e) {
                    $errorCount++;
                    $historyMessage =
                        "Es ist ein Fehler bei Shopgate aufgetreten ({$e->getCode()}): {$e->getMessage()}";
                    $message .= $historyMessage;
                } catch (Exception $e) {
                    $errorCount++;
                    $historyMessage =
                        "Es ist ein unbekannter Fehler aufgetreten ({$e->getCode()}): {$e->getMessage()}";
                    $message .= $historyMessage;
                }

                $this->_addOrderStatus($updatePartialCancellationResult['order_number'], null, $historyMessage);
            }
        }
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
     * @param array $orderProduct
     * @param array $shopgateOrderItems
     *
     * @return mixed
     */
    protected function getDifferentOrderItemQty($orderProduct, $shopgateOrderItems)
    {
        foreach ($shopgateOrderItems as $shopgateOrderItem) {
            /** @var $shopgateOrderItem ShopgateOrderItem */
            if ($shopgateOrderItem->getItemNumber() == $orderProduct['products_id']) {
                if ($shopgateOrderItem->getQuantity() > $orderProduct['products_quantity']) {
                    return $orderProduct['products_quantity'];
                }
            }
        }

        return false;
    }

    /**
     * sent an request via the shopgate merchant api to set an order cancelled at shopgate.
     * Furthermore the exceptions which were response are handled
     *
     * @param                     $shopgateOrderNumber
     * @param                     $orderId
     *
     * @throws \Exception
     * @throws \ShopgateLibraryException
     * @throws \ShopgateMerchantApiException
     */
    public function setOrderCanceled($shopgateOrderNumber, $orderId)
    {
        $success = false;
        // These are expected and should not be added to error count:
        $ignoreCodes = array(
            ShopgateMerchantApiException::ORDER_ALREADY_COMPLETED,
            ShopgateMerchantApiException::ORDER_SHIPPING_STATUS_ALREADY_COMPLETED,
        );

        $statusArr                          = array();
        $statusArr['orders_id']             = $orderId;
        $statusArr['orders_status_id']      = $this->orderStatusCanceled;
        $statusArr['customer_notified']     = true;
        $statusArr['date_added']            = date("Y-m-d H:i:s");
        $statusArr['change_trigger']        = 'shopgate';
        $statusArr['callback_id']           = '0';
        $statusArr['customer_show_comment'] = true;

        try {
            $this->merchantApi->cancelOrder($shopgateOrderNumber, true);// send request to Shopgate Merchant API
            $statusArr['comments'] =
                'Bestellung wurde bei Shopgate storniert';// prepare message for order history
            $success               = true;
        } catch (ShopgateLibraryException $e) {
            $statusArr['customer_notified']     = false;
            $statusArr['customer_show_comment'] = false;
            $statusArr['comments']              =
                "Es ist ein Fehler im Shopgate-Plugin aufgetreten ({$e->getCode()}): {$e->getMessage()}";
        } catch (ShopgateMerchantApiException $e) {
            $statusArr['customer_notified']     = false;
            $statusArr['customer_show_comment'] = false;
            $statusArr['comments']              =
                "Es ist ein Fehler bei Shopgate aufgetreten ({$e->getCode()}): {$e->getMessage()}";
            $success                            = (in_array($e->getCode(), $ignoreCodes))
                ? true
                : false;
        } catch (Exception $e) {
            $statusArr['customer_notified']     = false;
            $statusArr['customer_show_comment'] = false;
            $statusArr['comments']              =
                "Es ist ein unbekannter Fehler aufgetreten ({$e->getCode()}): {$e->getMessage()}";
        }

        $this->updateOrderHistory($statusArr);

        if ($success) {// Update shopgate order on success
            $this->setShopgateOrderAsCancelled($shopgateOrderNumber);
        } else {
            throw $e;
        }
    }

    /**
     * Marks shipped orders as "shipped" at Shopgate.
     *
     * This will find all orders that are marked "shipped" at Veyton but not at Shopgate yet and marks them "shipped"
     * at Shopgate via Shopgate Merchant API.
     *
     * @param string $message    Process log will be appended to this reference.
     * @param int    $errorCount This reference gets incremented on errors.
     */
    public function cronSetOrdersShippingCompleted(&$message, &$errorCount)
    {
        $orders = $this->getOrdersByStatus($this->orderStatusShipped);
        foreach ($orders as $sgOrder) {
            try {
                $this->setOrderShippingCompleted($sgOrder['shopgate_order_number'], $sgOrder['orders_id']);
            } catch (Exception $e) {
                $errorCount++;
                $msg =
                    "[cronSetOrdersShippingCompleted] Shopgate order number:'{$sgOrder['shopgate_order_number']}' [Exception] {$e->getCode()}, {$e->getMessage()}\n";
                $this->log->log($msg, ShopgateLogger::LOGTYPE_ERROR);
                $message .= $msg;
            }
        }
    }

    /**
     * Sets the order status of a Shopgate order to "shipped" via Shopgate Merchant API
     *
     * @param string $shopgateOrderNumber The number of the order at Shopgate.
     * @param int    $orderId             The ID of the order at Veyton.
     *
     * @throws \Exception
     * @throws \ShopgateLibraryException
     * @throws \ShopgateMerchantApiException
     */
    public function setOrderShippingCompleted($shopgateOrderNumber, $orderId)
    {
        $success                            = false;
        $ignoreCodes
                                            = array(// These are expected and should not be added to error count:
            ShopgateMerchantApiException::ORDER_ALREADY_COMPLETED,
            ShopgateMerchantApiException::ORDER_SHIPPING_STATUS_ALREADY_COMPLETED,
        );
        $statusArr                          = array();
        $statusArr['orders_id']             = $orderId;
        $statusArr['orders_status_id']      = $this->orderStatusShipped;
        $statusArr['customer_notified']     = true;
        $statusArr['date_added']            = date("Y-m-d H:i:s");
        $statusArr['change_trigger']        = 'shopgate';
        $statusArr['callback_id']           = '0';
        $statusArr['customer_show_comment'] = true;

        try {
            $this->merchantApi->setOrderShippingCompleted(
                $shopgateOrderNumber
            );// send request to Shopgate Merchant API
            $statusArr['comments']
                     = 'Bestellung wurde bei Shopgate als versendet markiert';// prepare message for order history
            $success = true;
        } catch (ShopgateLibraryException $e) {
            // prepare message for order history
            $statusArr['customer_notified']     = false;
            $statusArr['customer_show_comment'] = false;
            $statusArr['comments']
                                                = "Es ist ein Fehler im Shopgate-Plugin aufgetreten ({$e->getCode(
            )}): {$e->getMessage()}";
        } catch (ShopgateMerchantApiException $e) {
            $statusArr['customer_notified']     = false;
            $statusArr['customer_show_comment'] = false;
            $statusArr['comments']
                                                = "Es ist ein Fehler bei Shopgate aufgetreten ({$e->getCode(
            )}): {$e->getMessage()}";
            $success                            = (in_array(
                $e->getCode(),
                $ignoreCodes
            ))
                ? true
                : false;
        } catch (Exception $e) {
            $statusArr['customer_notified']     = false;
            $statusArr['customer_show_comment'] = false;
            $statusArr['comments']
                                                = "Es ist ein unbekannter Fehler aufgetreten ({$e->getCode(
            )}): {$e->getMessage()}";
        }

        $this->updateOrderHistory($statusArr);

        if ($success) {// Update shopgate order on success
            $this->setShopgateOrderAsCancelled($shopgateOrderNumber);
        } else {
            throw $e;
        }
    }

    /**
     * @param array      $sgOrder
     * @param            $cancelProducts
     *
     * @return array
     */
    public function updatePartialCancellationData(array $sgOrder, $cancelProducts)
    {
        $items              = array();
        $needUpdate         = false;
        $orderObject        = unserialize($sgOrder['order_data']);
        $cancellationData   = json_decode($sgOrder['cancellation_data'], JSON_OBJECT_AS_ARRAY);
        $totalCancellations = 0;

        foreach ($orderObject->getItems() as $item) {
            /** @var ShopgateOrderItem $item */
            if ($item->getType() != ShopgateOrderItem::TYPE_SHOPGATE_COUPON
                && in_array($item->getItemNumber(), array_keys($cancelProducts))
            ) {
                $originalQuantity    = (int)$item->getQuantity();
                $lastCurrentQuantity = $originalQuantity;

                if (array_key_exists($item->getItemNumber(), $cancellationData)) {
                    $lastCancellationItem = end($cancellationData[$item->getItemNumber()]);
                    $lastCurrentQuantity  = $lastCancellationItem['current_quantity'];
                }

                if ($cancelProducts[$item->getItemNumber()] == 0) {
                    /** total position cancel */
                    $cancelledQuantity = $lastCurrentQuantity;
                    $newQuantity       = 0;
                } else {
                    /** single position cancel */
                    $cancelledQuantity = $lastCurrentQuantity - $cancelProducts[$item->getItemNumber()];
                    $newQuantity       = $cancelProducts[$item->getItemNumber()];
                }

                $cancellationItem = array(
                    'original_quantity'  => $originalQuantity,
                    'last_quantity'      => $lastCurrentQuantity,
                    'current_quantity'   => (int)$newQuantity,
                    'cancelled_quantity' => $cancelledQuantity,
                    'created_at'         => date("d.m.Y H:i:s"),
                );

                $cancellationData[$item->getItemNumber()][] = $cancellationItem;

                if ($cancelledQuantity > 0) {
                    $totalCancellations = $totalCancellations + $cancelledQuantity;

                    $needUpdate = true;
                    $items[]    = array(
                        'order_item_number' => $item->getOrderItemId(),
                        'item_number'       => $item->getItemNumber(),
                        'quantity'          => $cancelledQuantity,
                    );
                }
            }
        }

        if ($needUpdate) {
            /** update shopgate order::cancellation_data */
            $this->db->AutoExecute(
                TABLE_SHOPGATE_ORDERS,
                array("cancellation_data" => json_encode($cancellationData)),
                'UPDATE',
                'shopgate_orders_id = ' . (int)$sgOrder['shopgate_orders_id']
            );
        }

        return array(
            'items'              => $items,
            'totalCancellations' => $totalCancellations,
        );
    }
}
