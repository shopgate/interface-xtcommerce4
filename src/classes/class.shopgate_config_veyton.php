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
    include_once __DIR__ . '/../vendor/autoload.php';
}
include_once __DIR__ . "/constants.php";

class ShopgateConfigVeyton extends ShopgateConfig
{
    const EXPORT_DESCRIPTION                  = 0;
    const EXPORT_SHORTDESCRIPTION             = 1;
    const EXPORT_DESCRIPTION_SHORTDESCRIPTION = 2;
    const EXPORT_SHORTDESCRIPTION_DESCRIPTION = 3;

    protected $export_description_type;

    protected $order_status_open;

    protected $order_status_shipped;

    protected $order_status_shipping_blocked;

    protected $order_status_canceled;

    protected $default_user_group_id;

    protected $send_order_confirmation_mail;

    public function startup()
    {
        // overwrite some library defaults
        $this->plugin_name                    = 'Veyton';
        $this->enable_redirect_keyword_update = 24;
        $this->enable_ping                    = 1;
        $this->enable_add_order               = 1;
        $this->enable_update_order            = 1;
        $this->enable_get_orders              = 1;
        $this->enable_get_customer            = 1;
        $this->enable_get_items_csv           = 1;
        $this->enable_get_items               = 1;
        $this->enable_get_categories_csv      = 1;
        $this->enable_get_categories          = 1;
        $this->enable_get_reviews_csv         = 1;
        $this->enable_get_reviews             = 1;
        $this->enable_get_pages_csv           = 0;
        $this->enable_get_log_file            = 1;
        $this->enable_mobile_website          = 1;
        $this->enable_cron                    = 1;
        $this->enable_clear_logfile           = 1;
        $this->enable_register_customer       = 1;
        $this->enable_get_settings            = 1;
        $this->enable_check_cart              = 1;
        $this->enable_check_stock             = 1;
        $this->encoding                       = 'UTF-8';

        // set Veyton specific file paths
        $this->export_folder_path = _SRV_WEBROOT . _SRV_WEB_EXPORT;
        $this->log_folder_path    = _SRV_WEBROOT . _SRV_WEB_LOG;
        $this->cache_folder_path  = _SRV_WEBROOT . _SRV_WEB_PLUGIN_CACHE;

        // initialize plugin specific stuff
        $this->export_description_type
                                             = ShopgateConfigVeyton::EXPORT_DESCRIPTION;
        $this->order_status_open             = 16;
        $this->order_status_shipped          = 33;
        $this->order_status_canceled         = 34;
        $this->default_user_group_id         = 0;
        $this->send_order_confirmation_mail  = 0;
        $this->supported_fields_check_cart   =
            array("shipping_methods", "currency", "external_coupons", "items", "customer", "payment_methods");
        $this->supported_fields_get_settings = array("customer_groups", "tax", "payment_methods");
    }

    public function save(array $fieldList, $validate = true)
    {
        global $db, $storeId;

        if (empty($storeId)) {
            $storeId = $this->getStoreId();

            if (empty($storeId)) {
                return null;
            }
        }

        if ($validate) {
            $this->validate($fieldList);
        }

        $pluginConfigFields   = $this->toArray();
        $pluginConfigFieldsDb = $this->getPluginConfigFromDatabase(
            $storeId
        );
        $pluginConfigBackendFieldsDb
                              = $this->getPluginConfigBackendFromDatabase(
            $storeId
        );

        foreach ($fieldList as $fieldlistField) {
            if (array_key_exists($fieldlistField, $pluginConfigFields)) {
                $updateQuery = "";

                if (array_key_exists($fieldlistField, $pluginConfigFieldsDb)) {
                    $updateQuery = "UPDATE " . TABLE_PLUGIN_CONFIGURATION
                        . " AS c SET c.config_value = '"
                        . $pluginConfigFields[$fieldlistField]
                        . "' WHERE c.config_key = 'XT_SHOPGATE_" . strtoupper(
                            $fieldlistField
                        ) . "' AND c.shop_id = {$storeId};";
                } elseif (array_key_exists(
                    $fieldlistField,
                    $pluginConfigBackendFieldsDb
                )) {
                    $updateQuery = "UPDATE " . TABLE_SHOPGATE_CONFIG
                        . " AS c SET c.value = '"
                        . $pluginConfigFields[$fieldlistField]
                        . "' WHERE c.key = 'XT_SHOPGATE_" . strtoupper(
                            $fieldlistField
                        ) . "' AND shop_id = {$storeId};";
                } else {
                    continue;
                }

                if (!empty($updateQuery)) {
                    $db->query($updateQuery);
                }
            }
        }
    }

    public function load(array $settings = null)
    {
        $storeId = $this->getStoreId();

        if (empty($storeId)) {
            return null;
        }

        $dbConfigArray        = $this->getPluginConfigFromDatabase($storeId);
        $dbConfigArrayBackend = $this->getPluginConfigBackendFromDatabase(
            $storeId
        );

        $dbConfigArray = array_merge($dbConfigArray, $dbConfigArrayBackend);

        if (!empty($settings)) {
            foreach ($settings as $key => $value) {
                if (array_key_exists($key, $dbConfigArray)) {
                    $dbConfigArray[$key] = $value;
                }
            }
        }

        $this->loadArray($dbConfigArray);

        // overwrite file names to have different files for every shop in a multishop system
        $this->items_csv_filename
            =
            ShopgateConfigInterface::SHOPGATE_FILE_PREFIX . 'items_' . $storeId
            . '.csv';
        $this->items_xml_filename
            =
            ShopgateConfigInterface::SHOPGATE_FILE_PREFIX . 'items_' . $storeId
            . '.xml';
        $this->items_json_filename
            =
            ShopgateConfigInterface::SHOPGATE_FILE_PREFIX . 'items_' . $storeId
            . '.json';

        $this->media_csv_filename
            =
            ShopgateConfigInterface::SHOPGATE_FILE_PREFIX . 'media_' . $storeId
            . '.csv';
        $this->categories_csv_filename
            = ShopgateConfigInterface::SHOPGATE_FILE_PREFIX . 'categories_'
            . $storeId . '.csv';
        $this->categories_xml_filename
            = ShopgateConfigInterface::SHOPGATE_FILE_PREFIX . 'categories_'
            . $storeId . '.xml';
        $this->categories_json_filename
            = ShopgateConfigInterface::SHOPGATE_FILE_PREFIX . 'categories_'
            . $storeId . '.json';

        $this->reviews_csv_filename
            = ShopgateConfigInterface::SHOPGATE_FILE_PREFIX . 'reviews_'
            . $storeId . '.csv';

        $this->access_log_filename
            =
            ShopgateConfigInterface::SHOPGATE_FILE_PREFIX . 'access_' . $storeId
            . '.log';
        $this->request_log_filename
            = ShopgateConfigInterface::SHOPGATE_FILE_PREFIX . 'request_'
            . $storeId . '.log';
        $this->error_log_filename
            =
            ShopgateConfigInterface::SHOPGATE_FILE_PREFIX . 'error_' . $storeId
            . '.log';
        $this->debug_log_filename
            =
            ShopgateConfigInterface::SHOPGATE_FILE_PREFIX . 'debug_' . $storeId
            . '.log';

        $this->redirect_keyword_cache_filename
            =
            ShopgateConfigInterface::SHOPGATE_FILE_PREFIX . 'redirect_keywords_'
            . $storeId . '.txt';
        $this->redirect_skip_keyword_cache_filename
            = ShopgateConfigInterface::SHOPGATE_FILE_PREFIX
            . 'skip_redirect_keywords_' . $storeId . '.txt';
    }

    /**
     * Override to return null if $this->cname is set to "0"
     * (which is a dirty hack for veyton not allowing empty input
     * fields when saving the configuration).
     *
     * @see ShopgateConfig::getCname()
     */
    public function getCname()
    {
        if (empty($this->cname)) {
            return null;
        }

        return parent::getCname();
    }

    /**
     * read the plugin configuration from the database
     *
     * @param $storeId
     *
     * @return array
     */
    private function getPluginConfigBackendFromDatabase($storeId)
    {
        global $db;

        $getConfigQuery = "SELECT * FROM " . TABLE_SHOPGATE_CONFIG
            . " AS c WHERE c.key like \"XT_SHOPGATE%\" AND c.shop_id = {$storeId}";
        $dbConfig       = $db->execute($getConfigQuery);
        $dbConfigArray  = array();

        while (!empty($dbConfig) && !$dbConfig->EOF) {
            $dbConfigTmp = $dbConfig->fields;
            $key         = strtolower(
                mb_substr(
                    $dbConfigTmp["key"],
                    strpos($dbConfigTmp["key"], "_", 3) + 1,
                    strlen($dbConfigTmp["key"])
                )
            );
            if ($this->isJson($dbConfigTmp["value"])) {
                $dbConfigTmp["value"] = $this->jsonDecode($dbConfigTmp["value"], true);
            }
            $dbConfigArray[$key] = $dbConfigTmp["value"];
            $dbConfig->MoveNext();
        }

        return $dbConfigArray;
    }

    /**
     * read the plugin configuration from the database
     *
     * @param $storeId
     *
     * @return array
     */
    private function getPluginConfigFromDatabase($storeId)
    {
        global $db;

        $configValuesQuery = "SELECT * FROM " . TABLE_PLUGIN_CONFIGURATION
            . " AS c WHERE c.config_key LIKE \"XT_SHOPGATE%\" AND c.shop_id = {$storeId}";
        $result            = $db->Execute($configValuesQuery);
        $dbConfigArray     = array();

        while (!empty($result) && !$result->EOF) {
            $dbConfigTmp = $result->fields;
            $key         = strtolower(
                mb_substr(
                    $dbConfigTmp["config_key"],
                    strpos($dbConfigTmp["config_key"], "_", 3) + 1,
                    strlen($dbConfigTmp["config_key"])
                )
            );
            if ($this->isJson($dbConfigTmp["value"])) {
                $dbConfigTmp["value"] = $this->jsonDecode($dbConfigTmp["value"], true);
            }
            $dbConfigArray[$key] = $dbConfigTmp["config_value"];
            $result->MoveNext();
        }

        return $dbConfigArray;
    }

    /**
     * check if a committed string is a valid json string
     *
     * @param $string
     *
     * @return bool
     */
    private function isJson($string)
    {
        $val = $this->jsonDecode($string, true);

        return (empty($val))
            ? false
            : true;
    }

    /**
     * get the actual store Id
     *
     * @return mixed
     */
    public function getStoreId()
    {
        global $store_handler;

        if (isset($_REQUEST["shopgate"]) && $_REQUEST["shopgate"] == "shopgate"
            && isset($_REQUEST["shop_number"])
            && $_REQUEST["shop_number"] != ""
        ) {
            return $this->getStoreIdFromDb($_REQUEST["shop_number"]);
        }

        if (!empty($store_handler->shop_id)) {
            return $store_handler->shop_id;
        }

        return null;
    }

    /**
     * read the language constants from database and defines them
     */
    public function defineShopgateLanguageConstants()
    {
        global $db;
        $query  = "SELECT lc.language_key,lc.language_value FROM "
            . TABLE_LANGUAGE_CONTENT
            . " AS lc WHERE lc.language_key LIKE '%SHOPGATE%' AND lc.language_code = '"
            . $this->language . "';";
        $result = $db->Execute($query);
        while (!$result->EOF) {
            if (!defined(strtoupper($result->fields['language_key']))) {
                define(
                    strtoupper($result->fields['language_key']),
                    $result->fields['language_value']
                );
            }
            $result->MoveNext();
        }
    }

    /**
     * read the store id from the database
     *
     * @param $shopNumber
     *
     * @return mixed
     */
    private function getStoreIdFromDb($shopNumber)
    {
        global $db;

        $qry
            = "
			SELECT pc.shop_id
			FROM " . TABLE_PLUGIN_CONFIGURATION . " pc
			JOIN " . TABLE_PLUGIN_PRODUCTS . " pp ON pp.plugin_id = pc.plugin_id
			WHERE pp.code = 'xt_shopgate'
			  AND pc.config_key = 'XT_SHOPGATE_SHOP_NUMBER'
			  AND pc.config_value = '$shopNumber'
		";

        return $db->GetOne($qry);
    }

    /**
     * read the plugin configuration from the database
     * it's for old version where the one part of the
     * plugin setting were stored in the database and
     * the other ones in a config file
     *
     * @param $shopId
     *
     * @return array
     * @deprecated
     */
    private function getStoreConfig($shopId)
    {
        global $db;

        $qry
            = "
			SELECT pc.*
			FROM " . TABLE_PLUGIN_CONFIGURATION . " pc
			JOIN " . TABLE_PLUGIN_PRODUCTS . " pp ON pp.plugin_id = pc.plugin_id
			WHERE pp.code = 'xt_shopgate'
			  AND pc.shop_id = '$shopId'
		";

        $result = $db->Execute($qry);

        $config = array();
        while (!$result->EOF) {
            $row                        = $result->fields;
            $config[$row["config_key"]] = $row["config_value"];

            $result->MoveNext();
        }

        return $config;
    }

    public function getExportDescriptionType()
    {
        return $this->export_description_type;
    }

    public function getOrderStatusOpen()
    {
        return $this->order_status_open;
    }

    public function getOrderStatusShipped()
    {
        return $this->order_status_shipped;
    }

    public function getOrderStatusShippingBlocked()
    {
        return $this->order_status_shipping_blocked;
    }

    public function getOrderStatusCanceled()
    {
        return $this->order_status_canceled;
    }

    public function getDefaultUserGroupId()
    {
        return $this->default_user_group_id;
    }

    public function getSendOrderConfirmationMail()
    {
        return $this->send_order_confirmation_mail;
    }

    public function setExportDescriptionType($value)
    {
        $this->export_description_type = $value;
    }

    public function setOrderStatusOpen($value)
    {
        $this->order_status_open = $value;
    }

    public function setOrderStatusShipped($value)
    {
        $this->order_status_shipped = $value;
    }

    public function setOrderStatusShippingBlocked($value)
    {
        $this->order_status_shipping_blocked = $value;#
    }

    public function setOrderStatusCanceled($value)
    {
        $this->order_status_canceled = $value;
    }

    public function setDefaultUserGroupId($value)
    {
        $this->default_user_group_id = $value;
    }

    public function setSendOrderConfirmationMail($value)
    {
        $this->send_order_confirmation_mail = $value;
    }
}
