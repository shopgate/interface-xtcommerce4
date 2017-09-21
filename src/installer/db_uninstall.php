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
include_once realpath(dirname(__FILE__) . '/../')
    . '/classes/ShopgateInstallHelper.php';

$db->Execute(
    "DELETE FROM " . TABLE_ADMIN_NAVIGATION . " WHERE text like 'xt_shopgate_%'"
);
$db->Execute("DROP TABLE IF EXISTS `" . TABLE_SHOPGATE_CONFIG . "`");
$db->Execute(
    "DELETE FROM " . TABLE_CONFIGURATION . " WHERE config_key = '"
    . ShopgateInstallHelper::SHOPGATE_DATABASE_CONFIG_KEY . "'"
);
