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
class ShopgateCategoryModel extends Shopgate_Model_Catalog_Category
{
    /**
     * Generates an array that can be added to the category CSV file with ShopgatePlugin::addCategoryRow().
     *
     * @param array [string,mixed] $defaultCategoryRow An array as created by ShopgatePlugin::buildDefaultCategoryRow().
     *
     * @return array[string,mixed] $defaultCategoryRow with the default data overwritten with this model's data.
     */
    public function asCsvArray(array $defaultCategoryRow)
    {
        $defaultCategoryRow['category_number'] = $this->getUid();
        $defaultCategoryRow['parent_id']       = $this->getParentUid();
        $defaultCategoryRow['category_name']   = $this->getName();
        $defaultCategoryRow['order_index']     = $this->getSortOrder();
        $defaultCategoryRow['is_active']       = $this->getIsActive();
        $defaultCategoryRow['url_deeplink']    = $this->getDeeplink();

        if ($this->getImage() instanceof Shopgate_Model_Media_Image) {
            $defaultCategoryRow['url_image'] = $this->getImage()->getUrl();
        }

        return $defaultCategoryRow;
    }
}
