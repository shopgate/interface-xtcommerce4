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
class ShopgateVeytonHelperCheckPlugin extends ShopgateVeytonHelperBase
{
    /**
     * Check if a plugin is installed and active by pluginCode
     *
     * See `xt_plugin_products`.`code`
     *
     * Return true if plugin is available and active
     * otherwise false
     *
     * @param string $pluginCode
     * @param string $minVersion lowest supported version. usually something like: '2.0.0'
     *
     * @return bool
     */
    public function checkPlugin($pluginCode, $minVersion = null)
    {
        $this->log("execute checkPlugin() ...", ShopgateLogger::LOGTYPE_DEBUG);

        $plugin = $this->db->Execute(
            "SELECT * FROM " . TABLE_PLUGIN_PRODUCTS
            . " WHERE UPPER(code) = UPPER('{$pluginCode}') LIMIT 1;"
        );

        // plugin found?
        if (empty($plugin->fields)) {
            return false;
        }

        // version okay?
        if (!empty($minVersion)
            && !version_compare(
                $plugin->fields['version'],
                $minVersion,
                '>='
            )
        ) {
            return false;
        }

        // active?
        return $plugin->fields["plugin_status"];
    }
}
