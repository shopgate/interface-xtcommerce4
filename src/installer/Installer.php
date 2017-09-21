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

defined('_VALID_CALL') || die('Direct Access is not allowed.');

class Shopgate_Installer
{
    /** @var ADOConnection */
    private $db;

    /**
     * @param ADOConnection $db
     */
    public function __construct(ADOConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @return int
     */
    public function stepInitializePluginId()
    {
        if (defined('XT_SHOPGATE_ID')) {
            return (int)XT_SHOPGATE_ID;
        }

        /** @noinspection PhpParamsInspection */
        $id = $this->db->GetOne("SELECT plugin_id FROM `" . TABLE_PLUGIN_PRODUCTS . "` WHERE code = 'xt_shopgate'");

        if (!empty($id)) {
            define('XT_SHOPGATE_ID', $id);

            return (int)$id;
        }

        return 0;
    }

    /**
     * @param string $tablePrefix
     */
    public function stepCreateDatabaseSchemas($tablePrefix = 'xt_')
    {
        $this->createTable(Shopgate_Installer_SchemaBuilderConfig::newInstance($tablePrefix)->build());
        $this->createTable(Shopgate_Installer_SchemaBuilderOrders::newInstance($tablePrefix)->build());
        $this->createTable(Shopgate_Installer_SchemaBuilderCustomers::newInstance($tablePrefix)->build());
    }

    public function stepAddShippingBlockedStatus()
    {
        $shippingBlockedStatusId = $this->getShippingBlockedStatusIdFromDb();

        if (null === $shippingBlockedStatusId) {
            $shippingBlockedStatusId = $this->createShippingBlockedStatus();

            // save translations
            $this->createShippingBlockedStatusDescription(
                $shippingBlockedStatusId,
                'de',
                'Versand blockiert (Shopgate)'
            );
            $this->createShippingBlockedStatusDescription(
                $shippingBlockedStatusId,
                'en',
                'Shipping blocked (Shopgate)'
            );
        }

        // update configuration table
        $this->updateShopgateConfiguration('XT_SHOPGATE_ORDER_STATUS_SHIPPING_BLOCKED', $shippingBlockedStatusId);
    }

    /**
     * @param int $pluginId
     */
    public function stepAddNavigationEntries($pluginId)
    {
        $iframeWrapper = '../plugins/xt_shopgate/pages/iframe_wrapper.php?url=';
        $iframeHome    = $iframeWrapper . urlencode('https://www.shopgate.com');
        $iframeAdmin   = $iframeWrapper . urlencode('https://admin.shopgate.com');

        $iframeSupport =
            $iframeWrapper .
            urlencode('https://support.shopgate.com/hc/de/articles/202837246-Connecting-to-xt-Commerce-4-VEYTON-');

        $shopgateConfigLink =
            'adminHandler.php?load_section=plugin_installed&pg=overview&parentNode=node_plugin_installed&edit_id=' .
            $pluginId . '&gridHandle=plugin_installedgridForm';

        $this->insertBackendNavigationEntry('xt_shopgate_shopgate', null, 'G');
        $this->insertBackendNavigationEntry('xt_shopgate_install_manual', $iframeHome);
        $this->insertBackendNavigationEntry('xt_shopgate_info', $iframeSupport);
        $this->insertBackendNavigationEntry('xt_shopgate_config', $shopgateConfigLink);
        $this->insertBackendNavigationEntry('xt_shopgate_merchant_area', $iframeAdmin);
    }

    /**
     * @param string $key
     */
    public function stepAddEmptyIdentifierToConfiguration($key)
    {
        $query =
            'INSERT INTO ' . TABLE_CONFIGURATION .
            '(`config_key`,`config_value`,`group_id`)' .
            "VALUES('{$key}', '', 0);";

        /** @noinspection PhpParamsInspection */
        $this->db->Execute($query);
    }

    /**
     * @param ShopgateConfig $config
     * @param string         $configKeyPrefix
     * @param string         $tablePrefix
     */
    public function stepInitializePluginConfiguration(ShopgateConfig $config, $configKeyPrefix, $tablePrefix = 'xt_')
    {
        $shopIds         = $this->getShopIdsFromDb($tablePrefix);
        $dbConfiguration = $this->getConfigurationFromDatabase($configKeyPrefix);

        $this->addConfigurationFieldsToDatabase($config, $dbConfiguration, $shopIds, $configKeyPrefix);
    }

    /**
     * @return int|null
     */
    private function getShippingBlockedStatusIdFromDb()
    {
        // check shopgate status "shipping blocked"
        $query =
            "SELECT ssd.status_id, ssd.status_name FROM " . TABLE_SYSTEM_STATUS . " ss " .
            "JOIN " . TABLE_SYSTEM_STATUS_DESCRIPTION . " ssd " .
            "ON ((ss.status_id = ssd.status_id) AND (ssd.language_code = 'de')) " .
            "WHERE ssd.status_name = 'Versand blockiert (Shopgate)'";

        /** @noinspection PhpParamsInspection */
        $result = $this->db->GetRow($query);

        return !empty($result) && !empty($result['status_id'])
            ? (int)$result['status_id']
            : null;
    }

    /**
     * @param Shopgate_Installer_Schema $schema
     */
    private function createTable(Shopgate_Installer_Schema $schema)
    {
        ## drop schema if exists and requested
        if ($schema->getDropOnCreation()) {
            /** @noinspection PhpParamsInspection */
            $this->db->Execute($this->buildDropTableStatement($schema));
        }

        ## create initial schema if not exists
        /** @noinspection PhpParamsInspection */
        $this->db->Execute($this->buildCreateTableStatement($schema));

        ## check for missing fields and add them in case the table already existed
        $currentFieldNames = $this->getFieldNamesFromDatabase($schema->getName());

        foreach ($schema->getFields() as $field) {
            if (in_array($field->getName(), $currentFieldNames)) {
                continue;
            }

            $this->addField($schema->getName(), $field);
        }
    }

    /**
     * @param string $configKey
     * @param mixed  $configValue
     */
    private function updateShopgateConfiguration($configKey, $configValue)
    {
        $this->db->AutoExecute(
            TABLE_PLUGIN_CONFIGURATION,
            array('config_value' => $configValue),
            'UPDATE',
            "config_key='{$configKey}'"
        );
    }

    /**
     * @param string $schemaName
     *
     * @return string[]
     */
    private function getFieldNamesFromDatabase($schemaName)
    {
        /** @noinspection PhpParamsInspection */
        /** @var ADORecordSet $result */
        $result = $this->db->Execute("SHOW FIELDS FROM `{$schemaName}`");

        $fieldNames = array();
        while (!empty($result) && !$result->EOF) {
            $fieldNames[] = $result->fields['Field'];

            $result->MoveNext();
        }

        return $fieldNames;
    }

    /**
     * @param Shopgate_Installer_Schema $schema
     *
     * @return string
     */
    private function buildCreateTableStatement(Shopgate_Installer_Schema $schema)
    {
        return
            "CREATE TABLE IF NOT EXISTS `{$schema->getName()}` " .
            "({$schema->fieldsToString()}) {$schema->tableOptionsToString()}";
    }

    /**
     * @param Shopgate_Installer_Schema $schema
     *
     * @return string
     */
    private function buildDropTableStatement(Shopgate_Installer_Schema $schema)
    {
        return "DROP TABLE IF EXISTS `{$schema->getName()}`";
    }

    /**
     * @param string                          $schemaName
     * @param Shopgate_Installer_Schema_Field $field
     */
    private function addField($schemaName, Shopgate_Installer_Schema_Field $field)
    {
        $after = ($field->getAfter() != '')
            ? " AFTER `{$field->getAfter()}`"
            : '';

        /** @noinspection PhpParamsInspection */
        $this->db->Execute("ALTER TABLE `{$schemaName}` ADD {$field} {$after}");
    }

    /**
     * @return int
     */
    private function createShippingBlockedStatus()
    {
        $systemStatus = array(
            'status_class'  => 'order_status',
            'status_values' => serialize(
                array(
                    'data' => array(
                        'enable_download'     => 0,
                        'visible'             => 0,
                        'visible_admin'       => '1',
                        'calculate_statistic' => 0,
                        'reduce_stock'        => '0',
                    ),
                )
            ),
        );

        $this->db->AutoExecute(TABLE_SYSTEM_STATUS, $systemStatus, "INSERT");

        return (int)$this->db->Insert_ID();
    }

    /**
     * @param int    $statusId
     * @param string $languageCode
     * @param string $statusName
     */
    private function createShippingBlockedStatusDescription($statusId, $languageCode, $statusName)
    {
        $systemStatusDescription = array(
            'status_id'     => $statusId,
            'language_code' => $languageCode,
            'status_name'   => $statusName,
        );

        $this->db->AutoExecute(TABLE_SYSTEM_STATUS_DESCRIPTION, $systemStatusDescription, 'INSERT');
    }

    /**
     * @param string $text
     * @param string $url_d
     * @param string $type
     * @param string $navtype
     * @param int    $sortorder
     * @param string $parent
     * @param string $icon
     */
    private function insertBackendNavigationEntry(
        $text,
        $url_d,
        $type = 'I',
        $navtype = 'config',
        $sortorder = 10,
        $parent = 'xt_shopgate_shopgate',
        $icon = '../plugins/xt_shopgate/images/shopgate_small.png'
    ) {
        /** @noinspection PhpParamsInspection */
        $this->db->Execute(
            'INSERT INTO `' . TABLE_ADMIN_NAVIGATION . '` ' .
            '(`text`, `url_d`, `type`, `navtype`, `sortorder`, `parent`, `icon`) ' .
            ' VALUES ' .
            "('{$text}', '{$url_d}', '{$type}', '{$navtype}', {$sortorder}, '{$parent}', '{$icon}')"
        );
    }

    /**
     * @param string $tablePrefix
     *
     * @return array
     */
    private function getShopIdsFromDb($tablePrefix = 'xt_')
    {
        $query = "SHOW TABLES LIKE '{$tablePrefix}config_%'";

        /** @noinspection PhpParamsInspection */
        /** @var ADORecordSet $result */
        $result = $this->db->Execute($query);

        $shopIds = array();
        while (!empty($result) && !$result->EOF) {
            $fields = (array)$result->fields;
            $field  = end($fields);

            if (false === $field) {
                break;
            }

            if (preg_match('/_(?<shopId>[0-9]+)$/', $field, $matches)) {
                $shopIds[] = $matches['shopId'];
            }

            $result->MoveNext();
        }

        return $shopIds;
    }

    /**
     * Fetches a list of configuration values from the database, converting keys to match those in ShopgateConfig.
     *
     * The resulting array's keys will be taken from the "plugin_config" table and will be converted to be lower-cased,
     * underscored and the 'XT_SHOPGATE_' part gets cut off.
     *
     * @param string $configKeyPrefix
     *
     * @return array [string, mixed]
     */
    private function getConfigurationFromDatabase($configKeyPrefix)
    {
        $query =
            "SELECT * FROM " . TABLE_PLUGIN_CONFIGURATION . " AS c WHERE c.config_key LIKE '{$configKeyPrefix}_%'";

        /** @noinspection PhpParamsInspection */
        /** @var ADORecordSet $result */
        $result = $this->db->Execute($query);

        $configuration = array();
        while (!empty($result) && !$result->EOF) {
            $fields = $result->fields;

            if (preg_match('/xt_shopgate_(?<keyName>.+)$/i', $fields['config_key'], $matches)) {
                $configuration[mb_strtolower($matches['keyName'])] = $fields["config_value"];
            }

            $result->MoveNext();
        }

        return $configuration;
    }

    /**
     * @param ShopgateConfig $config
     * @param array          $dbConfiguration [string, mixed]
     * @param int[]          $shopIds
     * @param string         $configKeyPrefix
     */
    private function addConfigurationFieldsToDatabase(
        ShopgateConfig $config,
        array $dbConfiguration,
        array $shopIds,
        $configKeyPrefix
    ) {
        if (empty($shopIds)) {
            return;
        }

        $configKeyShopId = "{$configKeyPrefix}_PLUGIN_SHOP_ID";
        $configKeyApiUrl = "{$configKeyPrefix}_API_URL";

        foreach ($config->toArray() as $key => $value) {
            if ($this->isKeyInDatabase($key, $dbConfiguration)) {
                continue;
            }

            $databaseKey   = $configKeyPrefix . '_' . strtoupper($key);
            $databaseValue = is_array($value)
                ? $config->jsonEncode($value)
                : $value;

            // build the entries once for every shop ID
            $insertValues = array();
            foreach ($shopIds as $shopId) {
                // make sure the shop ID setting is always redundantly set to the ID of the shop the setting is for
                // this is for legacy compatibility and may be subject of investigation for future refactorings
                if ($key === $configKeyShopId) {
                    $databaseValue = $shopId;
                }

                // make sure the API_URL setting is not saved to the DB
                if ($key === $configKeyApiUrl) {
                    continue;
                }

                $insertValues[] = "({$shopId}, '{$databaseKey}', '{$databaseValue}')";
            }

            /** @noinspection PhpParamsInspection */
            $this->db->execute(
                "INSERT INTO " . TABLE_SHOPGATE_CONFIG . " (`shop_id`, `key`, `value`) VALUES\n" .
                implode(",\n", $insertValues)
            );
        }
    }

    /**
     * @param string $key
     * @param array  $dbConfiguration [string, mixed]
     *
     * @return bool
     */
    private function isKeyInDatabase($key, $dbConfiguration)
    {
        $keyLower              = mb_strtolower($key);
        $keyWithoutUnderscores = str_replace('_', '', $keyLower);

        return
            array_key_exists($keyLower, $dbConfiguration) || array_key_exists($keyWithoutUnderscores, $dbConfiguration);
    }
}
