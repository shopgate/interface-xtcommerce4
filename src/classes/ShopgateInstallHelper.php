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
class ShopgateInstallHelper
{
    /**
     * salt to create hash. This hash identifies the shop
     */
    const SHOPGATE_SALT = "shopgate_veyton";
    /**
     * defines the shopsystem (predefined by sg)
     */
    const SHOPGATE_SHOP_TYPE = 159;
    /**
     * url to the sg api controller. calling the action log (live)
     */
    const SHOPGATE_REQUEST_URL = 'https://api.shopgate.com/log';
    /**
     * database configuration key
     */
    const SHOPGATE_DATABASE_CONFIG_KEY = "_SYSTEM_SHOPGATE_IDENT";
    /**
     * file where the ident hash will be stored
     */
    const SHOPGATE_HASH_FILE = "/sg_identity.php";
    /**
     * default currency configuration key
     */
    const SHOPGATE_DEFAULT_CURRENCY_KEY = "_STORE_CURRENCY";
    const SHOPGATE_DEFAULT_CONTACT_PHONE_KEY = "_STORE_SHOPOWNER_TELEPHONE";
    /**
     * default store name configuration key
     */
    const SHOPGATE_DEFAULT_STORE_NAME_KEY = "_STORE_NAME";
    /**
     * default store address configuration key
     */
    const SHOPGATE_DEFAULT_STORE_NAME_ADDRESS_KEY = "STORE_NAME_ADDRESS";
    /**
     *
     */
    const SHOPGATE_SYSTEM_GROUP_PERMISSIONS_KEY = "_SYSTEM_GROUP_PERMISSIONS";

    /**
     * send information about the store to sg
     */
    public function sendData()
    {
        global $db;
        $stores          = $db->Execute("SELECT shop_id FROM `" . TABLE_MANDANT_CONFIG . "`");
        $groupPermission = $this->getGroupPermission();

        $postData = array(
            'uid'                => $this->getUid(),
            'plugin_version'     => $this->getPluginVersion(),
            'shopping_system_id' => $this->getShopSystemId(),
        );

        $adminInformation = $this->getAdminInformation();

        while (!$stores->EOF) {
            $storeId               = $stores->fields["shop_id"];
            $shopHolderInformation = $this->getStoreInformation($storeId);
            $postData[]            = array(
                'action'              => 'interface_install',
                'uid'                 => $storeId,
                'url'                 => $stores->fields['shop_http'],
                'name'                => $shopHolderInformation[SHOPGATE_DEFAULT_STORE_NAME_KEY],
                'contact_name'        => $adminInformation['firstname'],
                'contact_phone'       => $shopHolderInformation[SHOPGATE_DEFAULT_CONTACT_PHONE_KEY],
                'contact_email'       => $adminInformation['email'],
                'stats_items'         => $this->getProductCount($groupPermission, $storeId),
                'stats_categories'    => $this->getCategoryCount($groupPermission, $storeId),
                'stats_orders'        => $this->getOrderAmount($this->getDate()),
                'stats_acs'           => $this->getAcs(),
                'stats_currency'      => $this->getDefaultCurrency($storeId),
                'stats_unique_visits' => '',
                'stats_mobile_visits' => '',
            );
            $stores->MoveNext();
        }

        $this->sendPostRequest($postData);
    }

    /**
     * read the group permission data from the database
     *
     * @return mixed
     */
    private function getGroupPermission()
    {
        global $db;

        $config = $db->Execute(
            "SELECT config_value as val FROM " . TABLE_CONFIGURATION . " WHERE config_key = '"
            . self::SHOPGATE_SYSTEM_GROUP_PERMISSIONS_KEY . "'"
        );

        return $config->fields['val'];
    }

    /**
     * get an unique hash to identify the shop
     *
     * @return string
     */
    private function getUid()
    {
        global $db;

        $hashFile = @realpath(dirname(__FILE__) . "/../../../../../") . self::SHOPGATE_HASH_FILE;

        if (file_exists($hashFile)) {
            $content = file_get_contents($hashFile);
            preg_match("/([a-z0-9]{32})/", $content, $result);
            if (is_array($result)) {
                return (count($result) > 1)
                    ? $result[1]
                    : $result[0];
            }
        }

        $keyQuery    = 'SELECT c.config_value AS val FROM ' . TABLE_CONFIGURATION . ' AS c WHERE c.config_key = "'
            . self::SHOPGATE_DATABASE_CONFIG_KEY . '" LIMIT 1;';
        $result      = $db->Execute($keyQuery);
        $configValue = $result->fields['val'];

        if (!empty($configValue) && $configValue != 0) {
            return $configValue;
        }

        $licenseFileDir = realpath(dirname(__FILE__) . "/../../../") . '/lic/';
        $key            = '';
        $dh             = opendir($licenseFileDir);
        while (false !== ($filename = readdir($dh))) {
            if ($filename == "." || $filename == "..") {
                continue;
            }

            $handle = @fopen($licenseFileDir . '/' . $filename, 'rb');
            if ($handle === false) {
                continue;
            }

            $contents = '';
            $contents = fread($handle, filesize($licenseFileDir . $filename));
            $key .= substr($contents, strpos($contents, '------'), strlen($contents));
            fclose($handle);
        }

        if (!empty($key)) {
            $saltedHash = md5($key . self::SHOPGATE_SALT);
            $content    = "<?php //" . $saltedHash;
        } else {
            $url = $this->getUrl();
            if (!empty($url)) {
                $hashString = $url;
            } else {
                $adminInfo  = $this->getAdminInformation();
                $hashString = $adminInfo['email'];
            }

            $saltedHash = md5($hashString . self::SALT);
            $content    = "<?php //" . $saltedHash;
        }

        if (@file_put_contents($hashFile, $content) === false) { /*error*/
        }

        $updateKeyQuery =
            'UPDATE ' . TABLE_CONFIGURATION . ' AS c  SET c.config_value ="' . $saltedHash . '" WHERE c.config_key = "'
            . self::SHOPGATE_DATABASE_CONFIG_KEY . '";';
        $db->Execute($updateKeyQuery);

        return $saltedHash;
    }

    /**
     * read the information to a store by the store id from the database
     *
     * @param $storeId
     *
     * @return mixed
     */
    private function getStoreInformation($storeId)
    {
        global $db;
        $query  = 'SELECT * FROM ' . TABLE_CONFIGURATION . '_' . $storeId . ' ;';
        $result = $db->Execute($query);
        while (!$result->EOF) {
            $ret[$result->fields['config_key']] = $result->fields['config_value'];
            $result->MoveNext();
        }

        return $ret;
    }

    /**
     * read information about admin user from the database
     *
     * @return Array mixed
     */
    private function getAdminInformation()
    {
        global $db;
        $userQry = 'SELECT email,firstname,lastname 
	 				  FROM ' . DB_PREFIX . '_acl_user As usr
							INNER JOIN (SELECT MIN(user_id) AS usr_id FROM ' . DB_PREFIX . '_acl_user) AS users ON (usr_id = usr.user_id)
							WHERE user_id = (SELECT c.group_id FROM ' . DB_PREFIX
            . '_acl_groups As c INNER JOIN (SELECT MIN(user_id) AS id FROM ' . DB_PREFIX
            . '_acl_user) AS custId ON (custId.id = c.group_id))';
        $result  = $db->Execute($userQry);

        return $result->fields;
    }

    /**
     * return the amount of all categories in the shop system
     *
     * @param      $groupPermission
     * @param      $storeId
     * @param bool $ignoreDeactivated
     *
     * @return mixed
     */
    private function getCategoryCount($groupPermission, $storeId, $ignoreDeactivated = false)
    {
        global $db;
        $query = "SELECT count(*) as cnt FROM " . TABLE_CATEGORIES . " AS c ";

        if ($groupPermission == 'blacklist') {
            $query .= "LEFT JOIN " . TABLE_CATEGORIES_PERMISSION
                . "  AS cp ON cp.pid  = c.categories_id WHERE cp.pid IS NULL OR cp.pgroup != 'shop_" . $storeId . "'";
        } else {
            $query .= "RIGHT JOIN " . TABLE_CATEGORIES_PERMISSION
                . " AS cp ON cp.pid  = c.categories_id WHERE cp.pid IS NOT NULL AND cp.pgroup != 'shop_" . $storeId
                . "'";
        }

        if ($ignoreDeactivated) {
            $query .= " AND c.categories_status != 0";
        }

        $result = $db->Execute($query);

        return $result->fields['cnt'];
    }

    /**
     * return the amount of all orders in the shop system
     *
     * @param string $beginDate in format Y-m-d H:i:s
     *
     * @return int
     */
    private function getOrderAmount($beginDate = null)
    {
        global $db;
        if (is_null($beginDate)) {
            $beginDate = 'now()';
        }
        $query  =
            "SELECT count(*) as cnt FROM " . TABLE_ORDERS . " WHERE date_purchased BETWEEN '{$beginDate}' AND now()";
        $result = $db->Execute($query);

        return $result->fields['cnt'];
    }

    /**
     * return the get Average cart score (acs)
     *
     * @return double
     */
    public function getAcs()
    {
        global $db;
        $query  = "SELECT ((SELECT SUM(" . TABLE_ORDERS_PRODUCTS . ".products_price)) DIV (SELECT COUNT(DISTINCT "
            . TABLE_ORDERS_PRODUCTS . ".products_id) )) as acs FROM " . TABLE_ORDERS_PRODUCTS . ";";
        $result = $db->Execute($query);

        return (!empty($result->fields['acs']))
            ? $result->fields['acs']
            : '';
    }

    /**
     * @return null
     */
    public function getUniqueVisits()
    {
        return null;
    }

    /**
     * @return null
     */
    public function getMobileVisits()
    {
        return null;
    }

    /**
     * returns the plugin version
     *
     * @return int
     */
    private function getPluginVersion()
    {
        return SHOPGATE_PLUGIN_VERSION;
    }

    /**
     * count the amount of all active product in the database
     *
     * @param      $groupPermission
     * @param      $storeId
     * @param bool $ignoreDeactivated
     *
     * @return mixed
     */
    private function getProductCount($groupPermission, $storeId, $ignoreDeactivated = false)
    {
        global $db;

        $query = "SELECT count(*) as cnt FROM " . TABLE_PRODUCTS . " AS p ";

        if ($groupPermission == 'blacklist') {
            $query .= "LEFT JOIN " . TABLE_PRODUCTS_PERMISSION
                . " AS pp ON pp.pid  = p.products_id WHERE pp.pid IS NULL OR pp.pgroup != 'shop_" . $storeId . "'";
        } else {
            $query .= "RIGHT JOIN " . TABLE_PRODUCTS_PERMISSION
                . " AS pp ON pp.pid = p.products_id WHERE pp.pid IS NOT NULL AND pp.pgroup !=  'shop_" . $storeId . "'";
        }

        if ($ignoreDeactivated) {
            $query .= " AND p.products_status != 0";
        }

        $result = $db->Execute($query);

        return $result->fields['cnt'];
    }

    /**
     * returns the shop system code defined by shopgate
     *
     * @return mixed
     */
    private function getShopsystemId()
    {
        return self::SHOPGATE_SHOP_TYPE;
    }

    /**
     * read the default currency from the database by the store id
     *
     * @param $storeId
     *
     * @return mixed
     */
    private function getDefaultCurrency($storeId)
    {
        global $db;
        $query  = 'SELECT config_value AS currency FROM ' . TABLE_CONFIGURATION . '_' . $storeId . ' AS c
				  	   where c.config_key = "' . self::SHOPGATE_DEFAULT_CURRENCY_KEY . '"';
        $result = $db->Execute($query);

        return $result->fields['currency'];
    }

    /**
     * returns the date minus the committed period
     *
     * @param string $interval
     *
     * @return date
     */
    private function getDate($interval = "-1 months")
    {
        return date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s") . $interval));
    }

    /**
     * send an curl Post request to shopgate
     *
     * @param $data array with post data
     *
     * @return bool return true if post was successful false if not
     */
    private function sendPostRequest($data)
    {
        $query = http_build_query($data);
        $curl  = curl_init();
        curl_setopt($curl, CURLOPT_URL, self::SHOPGATE_REQUEST_URL);
        curl_setopt($curl, CURLOPT_POST, count($data));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        if (!($result = curl_exec($curl))) {
            $error = curl_error($curl);
            $errNo = curl_errno($curl);

            return false;
        }

        curl_close($curl);

        return true;
    }

    /**
     * return the complete url to the current shop
     *
     * @return string
     */
    private function getUrl()
    {
        if (function_exists('apache_request_headers')) {
            $header = apache_request_headers();
            $host   = ((!empty($header['Referer']))
                ? $header['Referer']
                : $header['Host']);

            return $host;
        } else {
            if (isset($_SERVER)) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')
                    ? "https://"
                    : "http://";
                $host     = (!empty($_SERVER['HTTP_HOST']))
                    ? $_SERVER['HTTP_HOST']
                    : $_SERVER['HTTP_NAME'];
                $uri      = (!empty($_SERVER['REQUEST_URI']))
                    ? $_SERVER['REQUEST_URI']
                    : '';

                return ($protocol . $host . $uri);
            }
        }

        return '';
    }
}
