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
class ShopgateVeytonHelperItemTest extends \PHPUnit_Framework_TestCase
{
    /** @var ShopgateVeytonHelperItem|PHPUnit_Framework_MockObject_MockObject */
    private $subjectUnderTest;

    public function setUp()
    {
        $this->subjectUnderTest = $this->getMockBuilder('ShopgateVeytonHelperItem')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();
    }

    /**
     * @param string $type
     * @param array  $itemData
     * @param string $expectedDescription
     *
     * @dataProvider descriptionProvider
     * @covers       ShopgateVeytonHelperItem::getDescriptionToProduct
     */
    public function testFetchingDescriptionsForProducts($type, $itemData, $expectedDescription)
    {
        $result = $this->subjectUnderTest->getDescriptionToProduct($type, $itemData);

        $this->assertEquals($expectedDescription, $result);
    }

    /**
     * @return array
     */
    public function descriptionProvider()
    {
        $longDescription  = 'long';
        $shortDescription = 'short';
        $glue             = '<br/><br/>';

        $fakeItem = array(
            'products_description'       => $longDescription,
            'products_short_description' => $shortDescription,
        );

        return array(
            'export default description'        => array('', $fakeItem, $longDescription),
            'export LONG description'           => array(
                ShopgateConfigVeyton::EXPORT_DESCRIPTION,
                $fakeItem,
                $longDescription,
            ),
            'export SHORT description'          => array(
                ShopgateConfigVeyton::EXPORT_SHORTDESCRIPTION,
                $fakeItem,
                $shortDescription,
            ),
            'export LONG and SHORT description' => array(
                ShopgateConfigVeyton::EXPORT_DESCRIPTION_SHORTDESCRIPTION,
                $fakeItem,
                $longDescription . $glue . $shortDescription,
            ),
            'export SHORT and LONG description' => array(
                ShopgateConfigVeyton::EXPORT_SHORTDESCRIPTION_DESCRIPTION,
                $fakeItem,
                $shortDescription . $glue . $longDescription,
            ),
        );
    }
}
