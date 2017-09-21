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
abstract class ShopgateVeytonHelperBase
{
    /**
     * @var ADOConnection
     */
    protected $db;

    /**
     * @var ShopgateLogger
     */
    private $logger;

    /**
     * @param ADOConnection  $db
     * @param ShopgateLogger $logger
     */
    public function __construct(ADOConnection $db, ShopgateLogger $logger = null)
    {
        $this->db     = $db;
        $this->logger = empty($logger)
            ? ShopgateLogger::getInstance()
            : $logger;
    }

    /**
     * uses an instance of the ShopgateLogger to log
     *
     * @param string $msg
     * @param string $type
     */
    protected function log($msg, $type = 'error')
    {
        $this->logger->log($msg, $type);
    }

    /**
     * @param string $version
     *
     * @return bool
     */
    public function assertMinimumVersion($version)
    {
        return version_compare(_SYSTEM_VERSION, $version, '>=');
    }
}
