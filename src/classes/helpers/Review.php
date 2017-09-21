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
class ShopgateVeytonHelperReview extends ShopgateVeytonHelperBase
{
    /**
     * read review data from the database
     *
     * @param int      $limit  Maximum number of categories to be retrieved.
     * @param int      $offset Offset of the first category to be retrieved.
     * @param string[] $uids   A list of category UIDs that should be retrieved.
     *
     * @return ADORecordSet
     */
    public function getReviews($limit = null, $offset = null, array $uids = array())
    {
        $qry = 'SELECT
                pr.review_rating,
                pr.review_id,
                pr.review_date,
                pr.review_text,
                pr.review_title,
                pr.products_id,
                ca.customers_firstname as firstname,
                ca.customers_lastname as lastname
            FROM ' . TABLE_PRODUCTS_REVIEWS . ' pr
            LEFT JOIN ' . TABLE_CUSTOMERS_ADDRESSES . ' ca
            ON (pr.customers_id = ca.customers_id)
            WHERE pr.review_status = 1';

        $params = array();

        if (!empty($uids)) {
            $placeholders = array();
            foreach ($uids as $uid) {
                $placeholders[] = '?';
                $params[]       = $uid;
            }
            $qry .= ' AND pr.review_id IN (' . implode(', ', $placeholders) . ')';
        }

        $qry .= ' ORDER BY pr.review_id';

        if (($offset !== null) && ($limit !== null)) {
            $qry .= ' LIMIT ?, ?';
            $params[] = $offset;
            $params[] = $limit;
        }

        $qry .= ';';

        $this->log(
            'Fetching reviews. SQL statement: ' . $qry . "\nParameters: " . implode(', ', $params),
            ShopgateLogger::LOGTYPE_DEBUG
        );

        $stmt = $this->db->Prepare($qry);

        return $this->db->Execute($stmt, $params);
    }

    /**
     * create a ShopgateReviewModel object and fill it with information from one review row
     *
     * @param [string, mixed] $reviewData The review data as fetched from the Veyton database.
     *
     * @return ShopgateReviewModel
     */
    public function generateReviewFromDatabaseRow(array $reviewData)
    {
        $reviewModel = new ShopgateReviewModel();

        $reviewModel->setUid($reviewData['review_id']);
        $reviewModel->setItemUid($reviewData['products_id']);
        $reviewModel->setScore($reviewData['review_rating'] * 2);
        $reviewModel->setReviewerName($reviewData['firstname'] . " " . $reviewData['lastname']);
        $reviewModel->setDate($reviewData['review_date']);
        $reviewModel->setTitle($reviewData['review_title']);
        $reviewModel->setText(stripcslashes($reviewData['review_text']));

        return $reviewModel;
    }
}
