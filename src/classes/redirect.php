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

require_once('class.shopgate_config_veyton.php');
$shopgateMobileHeader = '';
$shopgateJsHeader     = '';

// load the CheckPlugin helper so we can check for active plugins
require_once(dirname(__FILE__) . '/helpers/Base.php');
require_once(dirname(__FILE__) . '/helpers/CheckPlugin.php');

try {
    $shopgateJsHeader = getShopgateJsHeader();
} catch (Exception $e) {
    // never abort in front-end pages!
}

function getShopgateJsHeader()
{
    /** @var $db ADOConnection */
    global $db, $p_info, $category, $tpl_data;

    $shopgateCheckPluginHelper = new ShopgateVeytonHelperCheckPlugin($db);
    $shopgate_config           = new ShopgateConfigVeyton();
    $shopgate_config->load();

    // instantiate and set up redirect class
    $shopgateBuilder = new ShopgateBuilder($shopgate_config);
    $redirector      = $shopgateBuilder->buildRedirect();

    // product
    if (!empty($p_info->data['products_model']) || !empty($p_info->pID)) {
        return $redirector->buildScriptItem(
            !empty($p_info->pID)
                ? $p_info->pID
                : $p_info->data['products_model']
        );
    }

    // special products category
    if (
        $shopgateCheckPluginHelper->checkPlugin('xt_special_products', '1.0.0')
        && !empty($_GET['page'])
        && ($_GET['page'] == 'xt_special_products')
    ) {
        return $redirector->buildScriptCategory('xt_special_products');
    }

    if (!empty($tpl_data['page'])) {

        // index
        if ($tpl_data['page'] == 'index') {
            return $redirector->buildScriptShop();
        }

        // category (check for both spellings in case Veyton fixes the typo sometime)
        if (in_array($tpl_data['page'], array('categorie', 'category')) && !empty($category->current_category_id)) {
            return $redirector->buildScriptCategory($category->current_category_id);
        }

        // search
        if ($tpl_data['page'] == 'search' && !empty($_GET['keywords'])) {
            return $redirector->buildScriptSearch($_GET['keywords']);
        }

        // brand
        if ($tpl_data['page'] == 'manufacturers' && !empty($_GET['mnf']) && is_numeric($_GET['mnf'])) {
            $manufacturer = $db->GetOne(
                "SELECT manufacturers_name FROM " . TABLE_MANUFACTURERS . " WHERE manufacturers_id = {$_GET['mnf']}"
            );
            if (!empty($manufacturer)) {
                return $redirector->buildScriptBrand($manufacturer);
            }
        }
    }

    // default redirect
    return $redirector->buildScriptDefault();
}
