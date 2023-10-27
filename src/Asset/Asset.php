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
use Glpi\Application\View\TemplateRenderer;
use Glpi\Plugin\Hooks;
use Entity;
use Group;
use Location;
use Manufacturer;
use Plugin;
use Session;
use State;
use Toolbox;
use User;

abstract class Asset extends CommonDBTM
{
    /**
     * Search option ID used for the asset definition system criteria.
     */
    private const DEFINITION_SO_ID = 250;

    final public function __construct()
    {
        foreach ($this->getDefinition()->getEnabledCapacities() as $capacity) {
            $capacity->onObjectInstanciation($this);
        }
    }

    /**
     * Get the asset definition related to concrete class.
     *
     * @return AssetDefinition
     */
    public static function getDefinition(): AssetDefinition
    {
        $definition = AssetDefinitionManager::getInstance()->getDefinitionForConcreteClass(static::class);
        if ($definition === null) {
            throw new \LogicException('Asset definition has not been defined.');
        }
        return $definition;
    }

    public static function getTypeName($nb = 0)
    {
        return static::getDefinition()->getTranslatedName($nb);
    }

    public static function getIcon()
    {
        return static::getDefinition()->getAssetsIcon();
    }

    public static function getTable($classname = null)
    {
        if (is_a($classname ?? static::class, self::class, true)) {
            return parent::getTable(self::class);
        }
        return parent::getTable($classname);
    }

    public static function getSearchURL($full = true)
    {
        return Toolbox::getItemTypeSearchURL(self::class, $full)
            . '?'
            . AssetDefinition::getForeignKeyField()
            . '='
            . static::getDefinition()->getID();
    }

    public static function getFormURL($full = true)
    {
        return Toolbox::getItemTypeFormURL(self::class, $full)
            . '?'
            . AssetDefinition::getForeignKeyField()
            . '='
            . static::getDefinition()->getID();
    }

    public static function getFormURLWithID($id = 0, $full = true)
    {
        return Toolbox::getItemTypeFormURL(self::class, $full) . '?id=' . $id;
    }

    public static function getById(?int $id)
    {
        /** @var \DBmysql $DB */
        global $DB;

        if ($id === null) {
            return false;
        }

        $definition_request = [
            'INNER JOIN' => [
                self::getTable()  => [
                    'ON'  => [
                        self::getTable()            => AssetDefinition::getForeignKeyField(),
                        AssetDefinition::getTable() => AssetDefinition::getIndexName(),
                    ]
                ],
            ],
            'WHERE' => [
                self::getTableField(self::getIndexName()) => $id,
            ],
        ];
        $definition = new AssetDefinition();
        if (!$definition->getFromDBByRequest($definition_request)) {
            return false;
        }

        $asset_class = $definition->getConcreteClassName(true);
        $asset = new $asset_class();
        if (!$asset->getFromDB($id)) {
            return false;
        }

        return $asset;
    }

    public static function canView()
    {
        return static::hasGlobalRight(READ);
    }

    public static function canCreate()
    {
        return static::hasGlobalRight(CREATE);
    }

    public static function canUpdate()
    {
        return static::hasGlobalRight(UPDATE);
    }

    public static function canDelete()
    {
        return static::hasGlobalRight(DELETE);
    }

    public static function canPurge()
    {
        return static::hasGlobalRight(PURGE);
    }

    /**
     * Check if current user has the required global right.
     *
     * @param int $right
     * @return bool
     */
    private static function hasGlobalRight(int $right): bool
    {
        return static::getDefinition()->hasRightOnAssets($right);
    }

    public function canViewItem()
    {
        return $this->hasItemRight(READ) && parent::canViewItem();
    }

    public function canCreateItem()
    {
        return $this->hasItemRight(CREATE) && parent::canCreateItem();
    }

    public function canUpdateItem()
    {
        return $this->hasItemRight(UPDATE) && parent::canUpdateItem();
    }

    public function canDeleteItem()
    {
        return $this->hasItemRight(DELETE) && parent::canDeleteItem();
    }

    public function canPurgeItem()
    {
        return $this->hasItemRight(PURGE) && parent::canPurgeItem();
    }

    public function can($ID, $right, array &$input = null)
    {
        $ID = (int)$ID;

        if ($this->isNewID($ID)) {
            if (!isset($this->fields['id'])) {
                $this->getEmpty();
            }

            if (is_array($input)) {
                $this->input = $input;
            }

            // Rely only on `canCreateItem()` that will check rights based on asset definition.
            return $this->canCreateItem();
        }

        if ((!isset($this->fields['id']) || $this->fields['id'] != $ID) && !$this->getFromDB($ID)) {
            // Ensure the right item is loaded.
            return false;
        }
        $this->right = $right;

        Plugin::doHook(Hooks::ITEM_CAN, $this);
        if ($this->right !== $right) {
            return false;
        }
        $this->right = null;

        switch ($right) {
            case READ:
                // Rely only on `canViewItem()` that will check rights based on asset definition.
                return $this->canViewItem();

            case UPDATE:
                // Rely only on `canUpdateItem()` that will check rights based on asset definition.
                return $this->canUpdateItem();

            case DELETE:
                // Rely only on `canDeleteItem()` that will check rights based on asset definition.
                return $this->canDeleteItem();

            case PURGE:
                // Rely only on `canPurgeItem()` that will check rights based on asset definition.
                return $this->canPurgeItem();

            case CREATE:
                // Rely only on `canPurgeItem()` that will check rights based on asset definition.
                return $this->canCreateItem();

            case 'recursive':
                // Can make recursive if recursive access to entity
                return Session::haveAccessToEntity($this->getEntityID())
                    && Session::haveRecursiveAccessToEntity($this->getEntityID());
        }

        return false;
    }

    /**
     * Check if current user has the required right on current item.
     *
     * @param int $right
     * @return bool
     */
    private function hasItemRight(int $right): bool
    {
        $definition_id = $this->isNewItem()
            ? ($this->input[AssetDefinition::getForeignKeyField()] ?? 0)
            : ($this->fields[AssetDefinition::getForeignKeyField()] ?? 0);
        $definition = new AssetDefinition();
        if ($definition_id === 0 || !$definition->getFromDB($definition_id)) {
            return false;
        }

        return $definition->hasRightOnAssets($right);
    }

    public function rawSearchOptions()
    {
        $search_options = parent::rawSearchOptions();

        $search_options = array_merge($search_options, Location::rawSearchOptionsToAdd());

        // TODO 4 for type

        // TODO 40 for model

        $search_options[] = [
            'id'                 => '31',
            'table'              => State::getTable(),
            'field'              => 'completename',
            'name'               => __('Status'),
            'datatype'           => 'dropdown',
            // TODO 'condition' to filter values
        ];

        $search_options[] = [
            'id'                 => '5',
            'table'              => $this->getTable(),
            'field'              => 'serial',
            'name'               => __('Serial number'),
            'datatype'           => 'string',
        ];

        $search_options[] = [
            'id'                 => '6',
            'table'              => $this->getTable(),
            'field'              => 'otherserial',
            'name'               => __('Inventory number'),
            'datatype'           => 'string',
        ];

        $search_options[] = [
            'id'                 => '16',
            'table'              => $this->getTable(),
            'field'              => 'comment',
            'name'               => __('Comments'),
            'datatype'           => 'text'
        ];

        $search_options[] = [
            'id'                 => '7',
            'table'              => $this->getTable(),
            'field'              => 'contact',
            'name'               => __('Alternate username'),
            'datatype'           => 'string',
        ];

        $search_options[] = [
            'id'                 => '8',
            'table'              => $this->getTable(),
            'field'              => 'contact_num',
            'name'               => __('Alternate username number'),
            'datatype'           => 'string',
        ];

        $search_options[] = [
            'id'                 => '70',
            'table'              => User::getTable(),
            'field'              => 'name',
            'name'               => User::getTypeName(1),
            'datatype'           => 'dropdown',
            'right'              => 'all'
        ];

        $search_options[] = [
            'id'                 => '71',
            'table'              => Group::getTable(),
            'field'              => 'completename',
            'name'               => Group::getTypeName(1),
            'condition'          => ['is_itemgroup' => 1],
            'datatype'           => 'dropdown'
        ];

        $search_options[] = [
            'id'                 => '19',
            'table'              => $this->getTable(),
            'field'              => 'date_mod',
            'name'               => __('Last update'),
            'datatype'           => 'datetime',
            'massiveaction'      => false
        ];

        $search_options[] = [
            'id'                 => '121',
            'table'              => $this->getTable(),
            'field'              => 'date_creation',
            'name'               => __('Creation date'),
            'datatype'           => 'datetime',
            'massiveaction'      => false
        ];


        $search_options[] = [
            'id'                 => '23',
            'table'              => Manufacturer::getTable(),
            'field'              => 'name',
            'name'               => Manufacturer::getTypeName(1),
            'datatype'           => 'dropdown'
        ];

        $search_options[] = [
            'id'                 => '24',
            'table'              => User::getTable(),
            'field'              => 'name',
            'linkfield'          => 'users_id_tech',
            'name'               => __('Technician in charge of the hardware'),
            'datatype'           => 'dropdown',
            'right'              => 'own_ticket'
        ];

        $search_options[] = [
            'id'                 => '49',
            'table'              => Group::getTable(),
            'field'              => 'completename',
            'linkfield'          => 'groups_id_tech',
            'name'               => __('Group in charge of the hardware'),
            'condition'          => ['is_assign' => 1],
            'datatype'           => 'dropdown'
        ];

        // TODO 65 for template

        $search_options[] = [
            'id'                 => '80',
            'table'              => Entity::getTable(),
            'field'              => 'completename',
            'name'               => Entity::getTypeName(1),
            'datatype'           => 'dropdown'
        ];

        $search_options[] = [
            'id'                 => self::DEFINITION_SO_ID,
            'table'              => $this->getTable(),
            'field'              => AssetDefinition::getForeignKeyField(),
            'name'               => AssetDefinition::getTypeName(),
            'massiveaction'      => false,
            'nosearch'           => true,
            'nodisplay'          => true,
        ];

        // TODO Search options for capacities

        foreach ($search_options as &$search_option) {
            if (
                is_array($search_option)
                && array_key_exists('table', $search_option)
                && $search_option['table'] === $this->getTable()
            ) {
                // Search class could not be able to retrieve the concrete class when using `getItemTypeForTable()`,
                // so we have to define an `itemtype` here.
                $search_option['itemtype'] = static::class;
            }
        }

        return $search_options;
    }

    public static function getSystemSQLCriteria(): array
    {
        // Keep only items from current definition must be shown.
        return [
            AssetDefinition::getForeignKeyField() => static::getDefinition()->getID(),
        ];
    }

    public static function getSystemSearchCriteria(): array
    {
        // In search pages, only items from current definition must be shown.
        return [
            [
                'field'      => self::DEFINITION_SO_ID,
                'searchtype' => 'equals',
                'value'      => static::getDefinition()->getID()
            ]
        ];
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);
        TemplateRenderer::getInstance()->display('pages/assets/asset.html.twig', [
            'item'   => $this,
            'params' => $options,
        ]);
        return true;
    }
}
