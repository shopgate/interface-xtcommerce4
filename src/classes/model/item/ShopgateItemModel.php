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
class ShopgateItemModel extends Shopgate_Model_Catalog_Product
{
    /**
     * @var ShopgateVeytonHelperCheckPlugin
     */
    protected $_checkPluginHelper;

    /**
     * @param ShopgateVeytonHelperCheckPlugin $helper
     */
    public function setCheckPluginHelper(ShopgateVeytonHelperCheckPlugin $helper)
    {
        $this->_checkPluginHelper = $helper;
    }

    /**
     * Generates an array that can be added to the item CSV file with ShopgatePlugin::addItemRow().
     *
     * @param array             $defaultItemRow An array as created by ShopgatePlugin::buildDefaultItemRow().
     * @param ShopgateItemModel $parent
     *
     * @return array            $defaultItemRow    with the default data overwritten with this model's data.
     */
    public function asCsvArray(array $defaultItemRow, $parent = null)
    {
        // general data
        $defaultItemRow['last_update']                        = $this->getLastUpdate();
        $defaultItemRow['item_number']                        = $this->getUid();
        $defaultItemRow['item_name']                          = $this->getName();
        $defaultItemRow['description']                        = $this->getDescription();
        $defaultItemRow['weight']                             = $this->getWeight();
        $defaultItemRow['url_deeplink']                       = $this->getDeeplink();
        $defaultItemRow['internal_order_info']                = $this->getInternalOrderInfo();
        $defaultItemRow['age_rating']                         = $this->getAgeRating();
        $defaultItemRow['is_highlight']                       = $this->getData('is_highlight');
        $defaultItemRow['highlight_order_index']              = $this->getData('is_highlight_order_index');
        $defaultItemRow['additional_shipping_costs_per_unit'] = $this->getShipping()->getAdditionalCostsPerUnit();

        $manufacturerModel = $this->getManufacturer();
        if ($manufacturerModel instanceof Shopgate_Model_Catalog_Manufacturer) {
            $defaultItemRow['manufacturer'] = $manufacturerModel->getTitle();
        }

        // tags
        $tags = array();
        foreach ($this->getTags() as $tag) {
            $tags[] = $tag->getValue();
        }
        $defaultItemRow['products_keywords'] = implode(',', $tags);

        // properties
        $properties = array();
        foreach ($this->getProperties() as $property) {
            $properties[] = $property->getLabel() . '=>' . $property->getValue();
        }
        $defaultItemRow['properties'] = implode('||', $properties);

        // images
        $images = array();
        foreach ($this->getImages() as $imageModel) {
            $images[] = $imageModel->getUrl();
        }
        $defaultItemRow['urls_images'] = implode('||', $images);

        // categories
        $categories = array();
        foreach ($this->getCategoryPaths() as $categoryModel) {
            $categories[] = $categoryModel->getUid() . '=>' . $categoryModel->getSortOrder();
        }
        $defaultItemRow['category_numbers'] = implode('||', $categories);

        // identifier
        foreach ($this->getIdentifiers() as $identifierModel) {
            switch ($identifierModel->getType()) {
                case 'SKU':
                    $defaultItemRow['item_number_public'] = $identifierModel->getValue();
                    break;
                case 'EAN':
                    $defaultItemRow['ean'] = $identifierModel->getValue();
                    break;
            }
        }

        // prices
        $defaultItemRow['unit_amount']     = $this->getPrice()->getSalePrice();
        $defaultItemRow['old_unit_amount'] = $this->getPrice()->getPrice();
        $defaultItemRow['basic_price']     = $this->getPrice()->getBasePrice();
        $defaultItemRow['tax_percent']     = $this->getTaxPercent();
        $defaultItemRow['currency']        = $this->getCurrency();

        // stock
        $defaultItemRow['use_stock']      = _STORE_STOCK_CHECK_BUY == "false"
            ? "1"
            : "0";
        $defaultItemRow['stock_quantity'] = $this->getStock()->getStockQuantity();
        $defaultItemRow['available_text'] = $this->getStock()->getAvailabilityText();

        // input / options
        $i       = 1;
        $options = $this->getInputs();
        if (is_array($options['options'])) {
            foreach ($options['options'] as $values) {
                $opts = array();
                foreach ($values["values"] as $value) {
                    $opts[] = $value["value_id"] . "=" . $value["value"]
                        . "=>" . $value["price_offset"];
                }

                $defaultItemRow["has_options"]              = "1";
                $defaultItemRow["option_" . $i]
                                                            =
                    $values["group"]["group_id"] . "="
                    . $values["group"]["group_name"];
                $defaultItemRow["option_" . $i . "_values"] = implode(
                    "||",
                    $opts
                );
                $i++;
            }
        }

        $i = 1;
        if (is_array($options['inputs'])) {
            foreach ($options['inputs'] as $ivalue) {
                $defaultItemRow["input_field_" . $i . "_type"]     = "text";
                $defaultItemRow["input_field_" . $i . "_number"]
                                                                   =
                    $ivalue["group_id"] . '_' . $ivalue["value_id"];
                $defaultItemRow["input_field_" . $i . "_label"]
                                                                   =
                    $ivalue["group_name"] . ': '
                    . $ivalue["value_name"];
                $defaultItemRow["input_field_" . $i . "_infotext"] = '';
                $defaultItemRow["input_field_" . $i . "_required"]
                                                                   = $ivalue["required"];
                $defaultItemRow["input_field_" . $i . "_add_amount"]
                                                                   = $ivalue["price_offset"];
                $defaultItemRow["has_input_fields"]                = "1";
                $i++;
            }
        }

        // children manipulation
        $children = $this->getData('children');
        if (!empty($children)) {
            $defaultItemRow['has_children'] = '1';
        }

        if (!is_null($parent)) {
            $defaultItemRow['parent_item_number'] = $parent->getUid();
        }

        $i               = 1;
        $attributeGroups = $this->getAttributeGroups();
        /** @var $attribute Shopgate_Model_Catalog_AttributeGroup */
        foreach ($attributeGroups as $attributeGroup) {
            $defaultItemRow['attribute_' . $i] = $attributeGroup->getLabel();
            $i++;
        }

        $i          = 1;
        $attributes = $this->getAttributes();
        /** @var $attribute Shopgate_Model_Catalog_Attribute */
        foreach ($attributes as $attribute) {
            $defaultItemRow['attribute_' . $i] = $attribute->getLabel();
            $i++;
        }

        return $defaultItemRow;
    }
}
