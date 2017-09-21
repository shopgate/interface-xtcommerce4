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
class ShopgateVeytonHelperSettings extends ShopgateVeytonHelperBase
{
    /**
     * Maps payment status from the database configuration
     * page. Uses Approved/Completed if Paid, New/Pending if
     * not paid.
     *
     * @param string     $paymentCode - e.g. xt_paypal_plus, xt_paypal
     * @param string|int $isPaid
     * @param string|int $storeId
     *
     * @return string | false - returns an ID of the status
     */
    public function getPaymentConfigStatus($paymentCode, $isPaid, $storeId)
    {
        $cfgKey = "'" . implode("','", $this->getConfigPaymentKeys($paymentCode, $isPaid)) . "'";
        $qry    = "SELECT config_key, config_value FROM "
            . TABLE_CONFIGURATION_PAYMENT
            . " WHERE config_key IN ($cfgKey)"
            . " AND shop_id = '{$storeId}'";
        $result = $this->db->Execute($qry);
        $fields = $result->fields;

        return isset($fields['config_value'])
            ? $fields['config_value']
            : false;
    }

    /**
     * Retrieves the 'key's to query config_payment database
     * to retrieve paid or not paid payment mappings.
     * PayPal keys are configured differently in some versions
     * so we are adjusting for that, e.g. XT_PAYMENTS_PAYPAL_ or XT_PAYPAL_
     *
     * @param string       $paymentCode - e.g. xt_paypal
     * @param string | int $isPaid      - 1 or 0
     *
     * @return array
     */
    private function getConfigPaymentKeys($paymentCode, $isPaid)
    {
        $origKey = strtoupper($paymentCode);
        if ($paymentCode === 'xt_paypal') {
            $key = str_replace('XT_', 'XT_PAYMENTS_', $origKey);
            if ($isPaid) {
                $cfgKeys[] = $key . '_ORDER_STATUS_APPROVED';
                $cfgKeys[] = $key . '_ORDER_STATUS_COMPLETED';
            } else {
                $cfgKeys[] = $key . '_ORDER_STATUS_NEW';
                $cfgKeys[] = $key . '_ORDER_STATUS_PENDING';
            }
        }

        if ($isPaid) {
            $cfgKeys[] = $origKey . '_ORDER_STATUS_APPROVED';
            $cfgKeys[] = $origKey . '_ORDER_STATUS_COMPLETED';
        } else {
            $cfgKeys[] = $origKey . '_ORDER_STATUS_NEW';
            $cfgKeys[] = $origKey . '_ORDER_STATUS_PENDING';
        }

        return $cfgKeys;
    }
}
