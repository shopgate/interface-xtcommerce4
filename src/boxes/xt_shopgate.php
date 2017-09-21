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

defined('_VALID_CALL') or die('Direct Access is not allowed.');

if (defined("XT_SHOPGATE_ENABLE") && XT_SHOPGATE_ENABLE == "true") {
    include_once _SRV_WEBROOT . "/plugins/xt_shopgate/classes/constants.php";
    include_once _SRV_WEBROOT . "/plugins/xt_shopgate/classes/class.shopgate_config_veyton.php";

    try {
        global $db, $store_handler;

        $shopgateConfigVeyton = new ShopgateConfigVeyton();
        $shopgateConfigVeyton->load();

        $qrLink = $smarty->get_template_vars("shopgate_qr_code");

        if (!empty($qrLink) && $shopgateConfigVeyton->getShopIsActive()
            && isset($xtPlugin->active_modules['xt_shopgate'])
            && XT_SHOPGATE_ID != ''
            && XT_SHOPGATE_KEY != ''
        ) {
            $tpl_data["shopgate_qr_code"] = $qrLink;

            $hasOwnApp  = $db->GetOne(
                "SELECT value FROM `" . TABLE_SHOPGATE_CONFIG . "` WHERE `key` = 'has_own_app' AND `shop_id` = "
                . $store_handler->shop_id
            );
            $itunesLink = $db->GetOne(
                "SELECT value FROM `" . TABLE_SHOPGATE_CONFIG . "` WHERE `key` = 'itunes_link' AND `shop_id` = "
                . $store_handler->shop_id
            );

            $backgroundColor = $db->GetOne(
                "SELECT value FROM `" . TABLE_SHOPGATE_CONFIG . "` WHERE `key` = 'background_color' AND `shop_id` = "
                . $store_handler->shop_id
            );
            $foregroundColor = $db->GetOne(
                "SELECT value FROM `" . TABLE_SHOPGATE_CONFIG . "` WHERE `key` = 'foreground_color' AND `shop_id` = "
                . $store_handler->shop_id
            );

            $tpl_data["backgroundColor"] = $backgroundColor;
            $tpl_data["foregroundColor"] = $foregroundColor;

            if (($hasOwnApp == '1' || $hasOwnApp == 'on') && !empty($itunesLink)) {
                $tpl_data["shopgate_itunes_url"] = $itunesLink;
                $tpl_data["has_own_app"]         = ($hasOwnApp == '1' || $hasOwnApp == 'on'
                    ? '1'
                    : '0');
            } else {
                $tpl_data["shopgate_itunes_url"] = SHOPGATE_ITUNES_URL;
                $tpl_data["has_own_app"]         = '0';
            }

            $tpl_data["XT_SHOPGATE_ENABLE"] = "true";

            $show_box = true;
        } else {
            $show_box = false;
        }
    } catch (Exception $e) {
    }
} else {
    $tpl_data["XT_SHOPGATE_ENABLE"] = "false";
}
