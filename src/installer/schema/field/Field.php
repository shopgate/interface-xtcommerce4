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

class Shopgate_Installer_Schema_Field
{
    /** @var string */
    private $name;

    /** @var string */
    private $dataType;

    /** @var string */
    private $default;

    /** @var bool */
    private $null;

    /** @var string */
    private $after;

    /** @var bool */
    private $autoIncrement;

    /**
     * @param string $name
     * @param string $dataType
     * @param string $default
     * @param bool   $null
     * @param string $after
     * @param bool   $autoIncrement
     */
    public function __construct($name, $dataType, $default = '', $null = false, $after = '', $autoIncrement = false)
    {
        $this->name          = $name;
        $this->dataType      = $dataType;
        $this->default       = $default;
        $this->null          = $null;
        $this->autoIncrement = $autoIncrement;
        $this->after         = $after;
    }

    /**
     * @param string $name
     * @param string $dataType
     *
     * @return Shopgate_Installer_Schema_Field
     */
    public static function newInstance($name, $dataType)
    {
        return new self($name, $dataType);
    }

    /**
     * @param bool $value
     *
     * @return $this
     */
    public function setAutoIncrement($value)
    {
        $this->autoIncrement = $value;

        return $this;
    }

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setDefault($value)
    {
        $this->default = $value;

        return $this;
    }

    /**
     * @param bool $value
     *
     * @return $this
     */
    public function setNull($value)
    {
        $this->null = $value;

        return $this;
    }

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setAfter($value)
    {
        $this->after = $value;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getAfter()
    {
        return $this->after;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return
            "`{$this->name}` {$this->dataType} {$this->defaultToString()} " .
            "{$this->nullToString()} {$this->autoIncrementToString()}";
    }

    /**
     * @return string
     */
    private function defaultToString()
    {
        return (!empty($this->default))
            ? "DEFAULT {$this->default}"
            : '';
    }

    /**
     * @return string
     */
    private function nullToString()
    {
        return ($this->null)
            ? 'NULL'
            : 'NOT NULL';
    }

    private function autoIncrementToString()
    {
        return ($this->autoIncrement)
            ? 'AUTO_INCREMENT'
            : '';
    }
}
