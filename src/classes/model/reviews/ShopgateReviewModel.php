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
class ShopgateReviewModel extends Shopgate_Model_Catalog_Review
{
    /**
     * Generates an array that can be added to the review CSV file with ShopgatePlugin::addReviewRow().
     *
     * @param  array [string, mixed] $defaultReviewRow An array as created by ShopgatePlugin::buildReviewCategoryRow().
     *
     * @return array[string, mixed] $defaultReviewRow with the default data overwritten with this model's data.
     */
    public function asCsvArray(array $defaultReviewRow)
    {
        $defaultReviewRow['update_review_id'] = $this->getUid();
        $defaultReviewRow['item_number']      = $this->getItemUid();
        $defaultReviewRow['score']            = $this->getScore();
        $defaultReviewRow['name']             = $this->getReviewerName();
        $defaultReviewRow['date']             = $this->getDate();
        $defaultReviewRow['title']            = $this->getTitle();
        $defaultReviewRow['text']             = $this->getText();

        return $defaultReviewRow;
    }
}
