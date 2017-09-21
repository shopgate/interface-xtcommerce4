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

/**
 * Class responsible for handling custom field data
 * of Shopgate object
 */
class ShopgateCustomFieldModel
{
    /** @var ADOConnection $db */
    protected $db;

    /**
     * Constructor that initializes the
     * global var and makes it local
     */
    public function __construct()
    {
        global $db;
        $this->db = $db;
    }

    /**
     * Checks whether the table has the column
     * names to save to.
     *
     * @param ShopgateOrder|ShopgateAddress|ShopgateCustomer $object
     * @param string                                         $table - table to check against
     *
     * @return array
     */
    public function getCustomFieldsMap($object, $table = TABLE_ORDERS)
    {
        $result = array();
        $qry    = "SHOW COLUMNS FROM {$table}";

        $qryResult = $this->db->Execute($qry);

        $columns = array();
        while (!$qryResult->EOF) {
            $columns[$qryResult->fields['Field']] = 1;
            $qryResult->MoveNext();
        }

        foreach ($object->getCustomFields() as $customField) {
            if (isset($columns[$customField->getInternalFieldName()])) {
                $result[$customField->getInternalFieldName()] = $customField->getValue();
            }
        }

        return $result;
    }

    /**
     * generates a html string from the custom field data
     *
     * @param ShopgateOrder|ShopgateAddress|ShopgateCustomer $customFields
     * @param array                                          $customFieldBlacklist
     *
     * @return string
     */
    public function buildCustomFieldsHtml($customFields, array $customFieldBlacklist = array())
    {
        $result = '';
        $style
                = ' .sg-custom-field-table { margin-top: 10px; border: 0px solid black; border-spacing: 0px; }'
            . ' .sg-custom-field-cell { padding: 1px 10px 1px 0px; }';

        $customFieldRows = array();
        foreach ($customFields->getCustomFields() as $customField) {
            // skip blacklisted entries
            if (array_key_exists($customField->getInternalFieldName(), $customFieldBlacklist)) {
                continue;
            }

            // convert to date or time values
            $customFieldValueString = $customField->getValue();

            $fieldList    = array(
                'YYYY' => 'year',
                'mm'   => 'month',
                'dd'   => 'day',
                'T'    => 'splitChar',
                'HH'   => 'hours',
                'ii'   => 'minutes',
                'ss'   => 'seconds',
            );
            $formatString =
                "((?P<YYYY>[0-9]{4})-(?P<mm>[0-9]{2})-(?P<dd>[0-9]{2}))?(?P<T>[ T])?((?P<HH>[0-9]{2}):(?P<ii>[0-9]{2})(:(?P<ss>[0-9]{2}))?)?";
            foreach ($fieldList as $fieldFormat => $fieldName) {
                str_replace("<{$fieldFormat}>", "<$fieldName>", $formatString);
            }

            $matches = array();
            preg_match("/{$formatString}/", $customFieldValueString, $matches);
            $timeData = (object)$matches;

            foreach ($fieldList as $fieldName) {
                if (!isset($timeData->{$fieldName})) {
                    $timeData->{$fieldName} = null;
                }
            }

            $format = null;
            if ($timeData->year && $timeData->month && $timeData->day && !$timeData->splitChar) {
                $format = TEXT_XT_SHOPGATE_DATE_FORMAT;
            } elseif ($timeData->year && $timeData->month && $timeData->day && $timeData->splitChar && $timeData->hours
                && $timeData->minutes
                && $timeData->seconds
            ) {
                $format = TEXT_XT_SHOPGATE_DATE_FORMAT . ' H:i:s';
            } elseif ($timeData->hours && $timeData->minutes && is_null($timeData->seconds)) {
                $format = 'H:i';
            } elseif ($timeData->hours && $timeData->minutes && !is_null($timeData->seconds)) {
                $format = 'H:i:s';
            }
            if ($format) {
                $customFieldValueString = date($format, strtotime($customFieldValueString));
            }

            $customFieldRows[] = sprintf(
                '<td class="sg-custom-field-cell">%s:</td><td class="sg-custom-field-cell">%s</td>',
                $customField->getLabel(),
                $customFieldValueString
            );
        }
        if (!empty($customFieldRows)) {
            $tableContent = '<tr>' . implode('</tr><tr>', $customFieldRows) . '</tr>';
            $result       = sprintf(
                '<style>%s</style><table class="sg-custom-field-table">%s</table>',
                $style,
                $tableContent
            );
        }

        return $result;
    }
}
