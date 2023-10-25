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

namespace Glpi\Asset\Capacity;

use Glpi\Asset\Asset;

/**
 * Abstract capacity that provides an empty implementation of some `\Glpi\Asset\Capacity\CapacityInterface` methods
 * that can legitimately be effectless.
 */
abstract class AbstractCapacity implements CapacityInterface
{
    /**
     * Constructor.
     *
     * Declared as final to ensure that constructor can be call without having to pass any parameter.
     */
    final public function __construct()
    {
    }

    public function onClassBootstrap(string $classname): void
    {
    }

    public function onObjectInstanciation(Asset $object): void
    {
    }

    public function onCapacityDisabled(string $classname): void
    {
    }
}
