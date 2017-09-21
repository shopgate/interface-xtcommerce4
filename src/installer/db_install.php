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

include_once realpath(dirname(__FILE__) . '/../') . '/classes/constants.php';
include_once realpath(dirname(__FILE__) . '/../')
    . '/classes/class.shopgate_config_veyton.php';
include_once realpath(dirname(__FILE__) . '/../')
    . '/classes/ShopgateInstallHelper.php';
include_once dirname(__FILE__) . '/schema/Schema.php';
include_once dirname(__FILE__) . '/schema/SchemaBuilder.php';
include_once dirname(__FILE__) . '/schema/SchemaBuilderConfig.php';
include_once dirname(__FILE__) . '/schema/SchemaBuilderCustomers.php';
include_once dirname(__FILE__) . '/schema/SchemaBuilderOrders.php';
include_once dirname(__FILE__) . '/schema/field/Field.php';
include_once dirname(__FILE__) . '/Installer.php';

/** @var ADOConnection $db Defined by Veyton framework */
global $db;

/** @var string $DB_PREFIX Defined in ../classes/constants.php */
global $DB_PREFIX;

$installer = new Shopgate_Installer($db);

## initialize the XT_SHOPGATE_ID constant and get the plugin ID
$shopgatePluginId = $installer->stepInitializePluginId();

## create/update required schemas
$installer->stepCreateDatabaseSchemas($DB_PREFIX);

## insert the "shipping blocked" order status
$installer->stepAddShippingBlockedStatus();

## insert backend navigation entries
$installer->stepAddNavigationEntries($shopgatePluginId);

## insert an empty identifier for the shop so it can be updated by ShopgateInstallHelper later on
$installer->stepAddEmptyIdentifierToConfiguration(ShopgateInstallHelper::SHOPGATE_DATABASE_CONFIG_KEY);

## initialize Shopgate configuration in the "plugin configuration" table of xt:Commerce
$installer->stepInitializePluginConfiguration(new ShopgateConfigVeyton(), 'XT_SHOPGATE', $DB_PREFIX);

## send plugin installation event to Shopgate
$shopgateInstallHelper = new ShopgateInstallHelper();
$shopgateInstallHelper->sendData();
