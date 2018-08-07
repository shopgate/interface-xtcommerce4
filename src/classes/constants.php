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

define("SHOPGATE_PLUGIN_VERSION", "2.9.51");

// Database constants
if (DB_PREFIX != '') {
    $DB_PREFIX = DB_PREFIX . '_';
} else {
    define('DB_PREFIX', 'xt');
    $DB_PREFIX = DB_PREFIX . '_';
}
define('TABLE_SHOPGATE_CONFIG', $DB_PREFIX . 'shopgate_config');
define('TABLE_SHOPGATE_ORDERS', $DB_PREFIX . 'shopgate_orders');
define('TABLE_SHOPGATE_CUSTOMERS', $DB_PREFIX . 'shopgate_customers');
