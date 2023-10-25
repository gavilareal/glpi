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

use Document_Item;
use Infocom;
use Log;

enum AssetCapacity
{
    case Document;
    case Infocom;
    case Log;

    /**
     * Get the `$CFG_GLPI` keys in which the concrete class must be added when corresponding capacity is enabled.
     *
     * @return string[]
     */
    public function typeConfigKeys(): array
    {
        return match ($this) {
            AssetCapacity::Document => ['document_types'],
            AssetCapacity::Infocom => ['infocom_types'],
            default => [],
        };
    }

    /**
     * Get the itemtype to use in the tab that must be added when corresponding capacity is enabled.
     * Will return null when there is no tab related to the capacity.
     *
     * @return array{itemtype: class-string, order: int}[]
     */
    public function tabs(): array
    {
        return match ($this) {
            AssetCapacity::Document => [['itemtype' => Document_Item::class, 'order' => 50]],
            AssetCapacity::Infocom => [['itemtype' => Infocom::class, 'order' => 40]],
            AssetCapacity::Log => [['itemtype' => Log::class, 'order' => 100]],
            default => [],
        };
    }
}
