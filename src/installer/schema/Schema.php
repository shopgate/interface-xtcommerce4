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

class Shopgate_Installer_Schema
{
    /** @var string */
    private $name;

    /** @var Shopgate_Installer_Schema_Field[] */
    private $fields;

    /** @var string */
    private $primaryKey;

    /** @var bool */
    private $dropOnCreation;

    /** @var string */
    private $engine;

    /** @var int */
    private $autoIncrementStart;

    /** @var string */
    private $charset;

    /**
     * @param string                            $name
     * @param Shopgate_Installer_Schema_Field[] $fields
     * @param string                            $primaryKey
     * @param bool                              $dropOnCreation
     * @param string                            $engine
     * @param int                               $autoIncrementStart
     * @param string                            $charset
     */
    public function __construct(
        $name,
        array $fields,
        $primaryKey,
        $dropOnCreation = false,
        $engine = 'MyISAM',
        $autoIncrementStart = 1,
        $charset = 'utf8'
    ) {
        $this->name               = $name;
        $this->fields             = $fields;
        $this->primaryKey         = $primaryKey;
        $this->dropOnCreation     = $dropOnCreation;
        $this->engine             = $engine;
        $this->autoIncrementStart = $autoIncrementStart;
        $this->charset            = $charset;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return Shopgate_Installer_Schema_Field[]
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @return bool
     */
    public function getDropOnCreation()
    {
        return $this->dropOnCreation;
    }

    /**
     * @return string
     */
    public function tableOptionsToString()
    {
        return "{$this->engineToString()} {$this->autoIncrementStartToString()} {$this->charsetToString()}";
    }

    /**
     * @return string
     */
    public function fieldsToString()
    {
        return implode(",\n", $this->fields) .
            (!empty($this->primaryKey)
                ? ", PRIMARY KEY(`{$this->primaryKey}`)"
                : ''
            );
    }

    /**
     * @return string
     */
    private function engineToString()
    {
        return (!empty($this->engine))
            ? "ENGINE {$this->engine}"
            : '';
    }

    /**
     * @return string
     */
    private function autoIncrementStartToString()
    {
        return (!empty($this->autoIncrementStart))
            ? "AUTO_INCREMENT={$this->autoIncrementStart}"
            : '';
    }

    /**
     * @return string
     */
    private function charsetToString()
    {
        return (!empty($this->charset))
            ? "DEFAULT CHARACTER SET={$this->charset}"
            : '';
    }
}
