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

defined('_VALID_CALL') || die('Direct Access is not allowed.');

class Shopgate_Installer_SchemaBuilderCustomers implements Shopgate_Installer_SchemaBuilder
{
    const DEFAULT_SCHEMA_NAME = 'shopgate_customers';

    /** @var string */
    private $schemaName;

    /**
     * @param string $prefix
     * @param string $schemaName
     */
    public function __construct($prefix, $schemaName = self::DEFAULT_SCHEMA_NAME)
    {
        $this->schemaName = $prefix . $schemaName;
    }

    /**
     * @param string $prefix
     * @param string $schemaName
     *
     * @return Shopgate_Installer_SchemaBuilder
     */
    public static function newInstance($prefix, $schemaName = self::DEFAULT_SCHEMA_NAME)
    {
        return new self($prefix, $schemaName);
    }

    public function build()
    {
        return new Shopgate_Installer_Schema(
            $this->schemaName,
            array(
                Shopgate_Installer_Schema_Field::newInstance('customer_id', 'INT(11)'),

                Shopgate_Installer_Schema_Field::newInstance('customer_token', 'VARCHAR(50)')
                    ->setNull(true)
                    ->setAfter('customer_id'),
            ),
            'customer_id'
        );
    }
}
