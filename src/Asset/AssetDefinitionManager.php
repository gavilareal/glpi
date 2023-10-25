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

use DirectoryIterator;
use Glpi\Asset\Capacity\CapacityInterface;
use ReflectionClass;

final class AssetDefinitionManager
{
    /**
     * Singleton instance
     */
    private static ?AssetDefinitionManager $instance = null;

    /**
     * Definitions cache.
     */
    private array $definitions_data;

    /**
     * Mapping between assets concrete classes and definitions.
     */
    private array $definition_mapping = [];

    /**
     * List of GLPI core capacities.
     * @var CapacityInterface[]
     */
    private array $core_capacities;

    /**
     * Singleton constructor
     */
    private function __construct()
    {
    }

    /**
     * Get singleton instance
     *
     * @return AssetDefinitionManager
     */
    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register assets concrete classes autoload.
     *
     * @return void
     */
    public function registerAssetsAutoload(): void
    {
        spl_autoload_register([$this, 'autoloadAssetClass']);
    }

    /**
     * Bootstrap asset classes.
     *
     * @return void
     */
    public function boostrapAssets(): void
    {
        $capacities = $this->getAvailableCapacities();

        foreach ($this->getDefinitions() as $definition) {
            if (!$definition->isActive()) {
                continue;
            }

            $concrete_class_name = $definition->getConcreteClassName();

            foreach ($capacities as $capacity) {
                if ($definition->hasCapacityEnabled($capacity)) {
                    $capacity->onClassBootstrap($concrete_class_name);
                }
            }
        }
    }

    /**
     * Autoload asset class, if requested class is a generic asset class.
     *
     * @param string $classname
     * @return void
     */
    public function autoloadAssetClass(string $classname): void
    {
        $asset_class_pattern = '/^Glpi\\\Asset\\\Asset(\d+)$/';
        if (preg_match($asset_class_pattern, $classname) !== 1) {
            return;
        }

        $definition_id = (int)preg_replace($asset_class_pattern, '$1', $classname);
        $definition    = $this->getDefinition($definition_id);

        if ($definition === null) {
            return;
        }

        $this->loadConcreteClass($definition);
    }

    /**
     * Get the classes names of all assets concrete classes.
     *
     * @param bool $with_namespace
     * @return array
     */
    public function getConcreteClassesNames(bool $with_namespace = true): array
    {
        $classes = [];

        foreach ($this->getDefinitions() as $definition) {
            if (!$definition->isActive()) {
                continue;
            }
            $classes[] = $definition->getConcreteClassName($with_namespace);
        }

        return $classes;
    }

    /**
     * Returns the definition that corresponds to an asset concrete class.
     *
     * @param string $classname
     * @return AssetDefinition|null
     */
    public function getDefinitionForConcreteClass(string $classname): ?AssetDefinition
    {
        return $this->definition_mapping[$classname] ?? null;
    }

    /**
     * Returns available capacities instance.
     *
     * @return CapacityInterface[]
     */
    public function getAvailableCapacities(): array
    {
        if (!isset($this->core_capacities)) {
            // Automatically build capacities list.
            // Would be better to do it with a DI auto-discovery feature, but it is not possible yet.
            $directory_iterator = new DirectoryIterator(__DIR__ . '/Capacity');
            /** @var \SplFileObject $file */
            foreach ($directory_iterator as $file) {
                $classname = $file->getExtension() === 'php'
                    ? '\\Glpi\\Asset\\Capacity\\' . $file->getBasename('.php')
                    : null;
                if (
                    $classname !== null
                    && class_exists($classname)
                    && is_subclass_of($classname, CapacityInterface::class)
                    && (new ReflectionClass($classname))->isAbstract() === false
                ) {
                    $this->core_capacities[] = new $classname();
                }
            }
        }

        return $this->core_capacities;
    }

    /**
     * Get the asset definition corresponding to given id.
     *
     * @param int $definition_id
     * @return AssetDefinition|null
     */
    private function getDefinition(int $definition_id): ?AssetDefinition
    {
        return $this->getDefinitions()[$definition_id] ?? null;
    }

    /**
     * Get all the asset definitions.
     *
     * @return AssetDefinition[]
     */
    private function getDefinitions(): array
    {
        if (!isset($this->definitions_data)) {
            $this->definitions_data = getAllDataFromTable(AssetDefinition::getTable());
        }

        $definitions = [];
        foreach ($this->definitions_data as $definition_id => $definition_data) {
            $definition = new AssetDefinition();
            $definition->getFromResultSet($definition_data);
            $definitions[$definition_id] = $definition;
        }

        return $definitions;
    }

    /**
     * Load asset concrete class.
     *
     * @param AssetDefinition $definition
     * @return void
     */
    private function loadConcreteClass(AssetDefinition $definition): void
    {
        eval(<<<PHP
namespace Glpi\Asset;
final class {$definition->getConcreteClassName(false)} extends Asset {}
PHP
        );
        $this->definition_mapping[$definition->getConcreteClassName()] = $definition;
    }
}
