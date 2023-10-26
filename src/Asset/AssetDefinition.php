<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2023 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

namespace Glpi\Asset;

use CommonDBTM;
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Asset\Capacity\CapacityInterface;
use Glpi\DBAL\QueryExpression;
use Glpi\DBAL\QueryFunction;
use Session;

final class AssetDefinition extends CommonDBTM
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0)
    {
        return _n('Asset definition', 'Asset definitions', $nb);
    }

    public static function getIcon()
    {
        return 'ti ti-database-cog';
    }

    public static function canCreate()
    {
        // required due to usage of `config` rightname
        return static::canUpdate();
    }

    public static function canPurge()
    {
        // required due to usage of `config` rightname
        return static::canUpdate();
    }

    public function defineTabs($options = [])
    {
        $tabs = [];

        $this->addDefaultFormTab($tabs);
        $this->addStandardTab(self::class, $tabs, $options);

        return $tabs;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof self) {
            return [
                1 => self::createTabEntry(__('Capacities'), 0, self::class, 'ti ti-adjustments'),
            ];
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof self) {
            $item->showCapacitiesForm();
        }
        return true;
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);
        TemplateRenderer::getInstance()->display('pages/admin/assetdefinition/main.html.twig', [
            'item'   => $this,
            'params' => $options,
        ]);
        return true;
    }

    /**
     * Display capacity form.
     *
     * @return void
     */
    private function showCapacitiesForm(): void
    {
        $capacities = AssetDefinitionManager::getInstance()->getAvailableCapacities();

        TemplateRenderer::getInstance()->display('pages/admin/assetdefinition/capacities.html.twig', [
            'item' => $this,
            'capacities' => $capacities,
        ]);
    }

    public function prepareInputForAdd($input)
    {
        if (!array_key_exists('capacities', $input)) {
            $input['capacities'] = []; // ensure default capacities value will be a valid array
        }
        return $this->prepareInput($input);
    }

    public function prepareInputForUpdate($input)
    {
        return $this->prepareInput($input);
    }

    /**
     * Prepare common input for and an update.
     *
     * @param array $input
     * @return array|bool
     */
    private function prepareInput(array $input): array|bool
    {
        if (array_key_exists('capacities', $input)) {
            $is_valid = true;
            if (!is_array($input['capacities'])) {
                $is_valid = false;
            } else {
                $available_capacities = array_map(
                    fn ($capacity) => $capacity::class,
                    AssetDefinitionManager::getInstance()->getAvailableCapacities()
                );
                foreach ($input['capacities'] as $classname) {
                    if (!in_array($classname, $available_capacities)) {
                        $is_valid = false;
                        break;
                    }
                }
            }
            if (!$is_valid) {
                Session::addMessageAfterRedirect(
                    sprintf(
                        __('The following field has an incorrect value: "%s".'),
                        __('Capacities')
                    ),
                    false,
                    ERROR
                );
                return false;
            }
            $input['capacities'] = json_encode($input['capacities']);
        }
        return $input;
    }

    public function post_updateItem($history = 1)
    {
        if (in_array('capacities', $this->updates)) {
            // When capabilities are removed, trigger the cleaning of data related to this capacity.
            $new_capacities = @json_decode($this->fields['capacities']);
            $old_capacities = @json_decode($this->oldvalues['capacities']);

            if (!is_array($new_capacities)) {
                // should not happen, do not trigger cleaning to prevent unexpected mass deletion of data
                trigger_error(sprintf('Invalid `capacities` value `%s`.', $this->fields['capacities']), E_USER_WARNING);
                return;
            }
            if (!is_array($old_capacities)) {
                // should not happen, do not trigger cleaning to prevent unexpected mass deletion of data
                trigger_error(sprintf('Invalid `capacities` value `%s`.', $this->oldvalues['capacities']), E_USER_WARNING);
                return;
            }

            $removed_capacities = array_diff($old_capacities, $new_capacities);
            foreach ($removed_capacities as $capacity_classname) {
                $capacity = AssetDefinitionManager::getInstance()->getCapacity($capacity_classname);
                if ($capacity === null) {
                    // can be null if provided by a plugin that is no longer active
                    continue;
                }
                $capacity->onCapacityDisabled($this->getConcreteClassName());
            }
        }
    }

    /**
     * Check if connected user has given right on assets from current definition.
     *
     * @param int $right
     * @return bool
     */
    public function hasRightOnAssets(int $right): bool
    {
        // TODO Fine-grain rights management.
        return true;
    }

    /**
     * Return translated name.
     *
     * @param int $count
     * @return string
     */
    public function getTranslatedName(int $count = 1): string
    {
        // TODO Return translated plural form.
        return $this->fields['name'];
    }

    /**
     * Return icon to use for assets.
     *
     * @return string
     */
    public function getAssetsIcon(): string
    {
        return $this->fields['icon'] ?: 'ti ti-box';
    }

    public function rawSearchOptions()
    {
        /** @var \DBmysql $DB */
        $search_options = parent::rawSearchOptions();

        $i = 1000;
        $tab[] = [
            'id'   => 'capacities',
            'name' => __('Capacities')
        ];
        foreach (AssetDefinitionManager::getInstance()->getAvailableCapacities() as $capacity) {
            $i++;

            // capacity is stored in a JSON array, so entry is surrounded by double quotes
            $search_string = json_encode($capacity::class);
            // Backslashes must be doubled in LIKE clause, according to MySQL documentation:
            // > To search for \, specify it as \\\\; this is because the backslashes are stripped
            // > once by the parser and again when the pattern match is made,
            // > leaving a single backslash to be matched against.
            $search_string = str_replace('\\', '\\\\', $search_string);

            $search_options[] = [
                'id'            => $i,
                'table'         => self::getTable(),
                'field'         => sprintf('_capacities_%s', $capacity::class),
                'name'          => $capacity->getLabel(),
                'computation'   => QueryFunction::if(
                    condition: ['capacities' => ['LIKE', '%' . $search_string . '%']],
                    true_expression: new QueryExpression('1'),
                    false_expression: new QueryExpression('0')
                ),
                'datatype'      => 'bool'
            ];
        }

        return $search_options;
    }

    /**
     * Get the definition's concerte asset class name.
     *
     * @param bool $with_namespace
     * @return string|null
     */
    public function getConcreteClassName(bool $with_namespace = true): string
    {
        return sprintf(
            ($with_namespace ? 'Glpi\\Asset\\' : '') . 'Asset%s',
            $this->getID()
        );
    }

    /**
     * Indicates whether the given capacity is enabled.
     *
     * @param CapacityInterface $capacity
     * @return bool
     */
    public function hasCapacityEnabled(CapacityInterface $capacity): bool
    {
        $enabled_capacities = @json_decode($this->fields['capacities']);
        if (!is_array($enabled_capacities)) {
            trigger_error(sprintf('Invalid `capacities` value `%s`.', $this->fields['capacities']), E_USER_WARNING);
            $this->fields['capacities'] = '[]'; // prevent warning to be triggered on each method call
            $enabled_capacities = [];
        }

        return in_array($capacity::class, $enabled_capacities);
    }

    /**
     * Get the list of enabled capacities.
     *
     * @return CapacityInterface[]
     */
    public function getEnabledCapacities(): array
    {
        $capacities = [];
        foreach (AssetDefinitionManager::getInstance()->getAvailableCapacities() as $capacity) {
            if ($this->hasCapacityEnabled($capacity)) {
                $capacities[] = $capacity;
            }
        }
        return $capacities;
    }
}
