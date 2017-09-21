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
class ShopgateVeytonHelperCategory extends ShopgateVeytonHelperBase
{
    const DEFAULT_SEO_LINK_TYPE = 2;

    /**
     * @var language - a Veyton Language object
     */
    protected $language;

    /**
     * Overwritten constructor for additional injection purposes
     *
     * @param ADOConnection       $db
     * @param language            $language
     * @param ShopgateLogger|null $logger
     */
    public function __construct(ADOConnection $db, language $language, ShopgateLogger $logger = null)
    {
        $this->language = $language;
        parent::__construct($db, $logger);
    }

    /**
     * read category data from database
     *
     * @param int      $shopId    - the ID of the shop the categories should be fetched for.
     * @param bool     $blacklist - true to use the Veyton 'blacklist policy', false to use the Veyton 'whitelist
     *                            policy'.
     * @param int      $limit     - maximum number of categories to be retrieved.
     * @param int      $offset    - offset of the first category to be retrieved.
     * @param string[] $uids      - a list of category UIDs that should be retrieved.
     *
     * @return ADORecordSet
     * @internal param language $language A Veyton language object.
     */
    public function getCategories($shopId, $blacklist, $limit = null, $offset = null, array $uids = array())
    {
        $params = array();
        /** @noinspection PhpParamsInspection */
        $result = $this->db->GetOne(
            'SHOW COLUMNS FROM ' . TABLE_CATEGORIES_DESCRIPTION . ' LIKE \'categories_store_id\''
        );

        if (empty($result)) {
            $catStoreId = '';
        } else {
            $catStoreId = ' AND cd.categories_store_id = ?';
            $params[]   = $shopId;
        }
        $params[] = 'shop_' . $shopId;
        $params[] = $this->language->code;

        /** @noinspection SqlDialectInspection */
        $qry = '
                SELECT
                DISTINCT c.categories_id,
                c.parent_id,
                cd.categories_name,
                c.sort_order,
                c.categories_status,
                c.categories_image
                FROM ' . TABLE_CATEGORIES . ' c
                JOIN ' . TABLE_CATEGORIES_DESCRIPTION . ' cd ON (c.categories_id = cd.categories_id' . $catStoreId . ')
                LEFT JOIN ' . TABLE_CATEGORIES_PERMISSION . ' cm ON (cm.pid = c.categories_id AND cm.pgroup = ?)
                WHERE cd.language_code = ?
        ';

        $qry .= ($blacklist)
            ? ' AND (cm.permission IS NULL OR cm.permission = 0)'
            : ' AND (cm.permission IS NOT NULL AND cm.permission = 1)';

        if (!empty($uids)) {
            $placeholders = array();
            foreach ($uids as $uid) {
                $placeholders[] = '?';
                $params[]       = $uid;
            }
            $qry .= ' AND c.categories_id IN (' . implode(', ', $placeholders) . ')';
        }

        $qry .= ' ORDER BY categories_id';

        if (($offset !== null) && ($limit !== null)) {
            $qry .= ' LIMIT ?, ?';
            $params[] = $offset;
            $params[] = $limit;
        }

        $qry .= ';';

        $this->log(
            'Fetching categories. SQL statement: ' . $qry . '\nParameters: ' . implode(', ', $params),
            ShopgateLogger::LOGTYPE_DEBUG
        );

        $stmt = $this->db->Prepare($qry);

        return $this->db->Execute($stmt, $params);
    }

    /**
     * Returns a list of SEO-URLs, indexed by the category ID.
     *
     * In case SEO URLs are deactivated in the shop an empty list is returned.
     *
     * @param int $linkType - link type in database
     *
     * @return array(int => string) An array with the category IDs as indices and the SEO links as values.
     */
    public function getCategorySeoUrls($linkType = self::DEFAULT_SEO_LINK_TYPE)
    {
        if (_SYSTEM_MOD_REWRITE == 'false') {
            return array();
        }

        $this->log('Start Categories SEO-URL...', ShopgateLogger::LOGTYPE_DEBUG);
        /** @noinspection SqlDialectInspection */
        $qry =
            'SELECT
                seo.url_text,
                seo.link_id,
                seo.link_type
            FROM ' . TABLE_SEO_URL . ' AS seo
            WHERE seo.language_code = ?
            AND seo.link_type = ?;';

        $params = array($this->language->code, $linkType);
        $stmt   = $this->db->Prepare($qry);

        $seoUrlsResult = $this->db->Execute($stmt, $params);

        $seoUrls = array();
        while (!empty($seoUrlsResult) && !$seoUrlsResult->EOF) {
            $seoUrl = $seoUrlsResult->fields;

            $seoUrls[$seoUrl['link_id']] =
                trim(_SYSTEM_BASE_HTTP . _SRV_WEB, '/') .
                '/' . $seoUrl['url_text'] .
                (_SYSTEM_SEO_FILE_TYPE !== ''
                    ? '.' . _SYSTEM_SEO_FILE_TYPE
                    : ''
                );

            $seoUrlsResult->MoveNext();
        }

        return $seoUrls;
    }

    /**
     * @return int - the highest sort order index any of the categories in the shop has.
     */
    public function getMaximumOrderIndex()
    {
        /** @noinspection SqlDialectInspection */
        /** @noinspection PhpParamsInspection */
        return (int)$this->db->GetOne('SELECT MAX(sort_order) FROM ' . TABLE_CATEGORIES);
    }

    /**
     * generate category data as needed for the export
     *
     * @param array(string, mixed) $categoryData         - the category data as fetched from the Veyton database.
     * @param array(int, string)   $seoUrlsByCategoryIds - an array with the category IDs as indices and the SEO links
     *                             as values.
     * @param int                  $maxOrderIndex        - the highest sort order index any of the categories in the
     *                                                   shop has.
     * @param string               $webPrefix            - the prefix that needs to be added to image URLs
     *
     * @return ShopgateCategoryModel
     */
    public function generateCategoryFromDatabaseRow(
        array $categoryData,
        array $seoUrlsByCategoryIds,
        $maxOrderIndex,
        $webPrefix
    ) {
        $deeplink = !empty($seoUrlsByCategoryIds[$categoryData['categories_id']])
            ? $seoUrlsByCategoryIds[$categoryData['categories_id']]
            : _SYSTEM_BASE_HTTP . _SRV_WEB . 'index.php?page=categorie&cat=' . $categoryData['categories_id'];

        $categoryModel = new ShopgateCategoryModel();
        $categoryModel->setUid($categoryData['categories_id']);
        $categoryModel->setParentUid(
            empty($categoryData['parent_id'])
                ? null
                : $categoryData['parent_id']
        );
        $categoryModel->setName($categoryData['categories_name']);
        $categoryModel->setSortOrder($maxOrderIndex - $categoryData['sort_order']);
        $categoryModel->setIsActive($categoryData['categories_status']);
        $categoryModel->setDeeplink($deeplink);

        if (!empty($categoryData['categories_image'])) {
            $image = new Shopgate_Model_Media_Image();
            $image->setUrl($webPrefix . $categoryData['categories_image']);
            $categoryModel->setImage($image);
        }

        return $categoryModel;
    }

    /**
     * Generates a category for the 'xt_special_products' plugin.
     *
     * This does not check if the plugin is installed. It will always try to generate the category object.
     *
     * @return ShopgateCategoryModel
     */
    public function generateSpecialProductsCategory()
    {
        $this->log('Adding "xt_special_products" category.', ShopgateLogger::LOGTYPE_DEBUG);
        $categoryModel = new ShopgateCategoryModel();
        $categoryModel->setUid('xt_special_products');
        $categoryModel->setParentUid(null);
        $categoryModel->setName(TEXT_SPECIAL_PRODUCTS);
        $categoryModel->setDeeplink(_SYSTEM_BASE_HTTP . _SRV_WEB . $this->language->code . '/xt_special_products');
        $categoryModel->setSortOrder(0);
        $categoryModel->setIsActive(1);

        return $categoryModel;
    }
}
