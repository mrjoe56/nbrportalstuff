<?php
/**
 * Copyright (C) 2022  Erik Hommel (erik.hommel@civicoop.org)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
namespace Civi\Nbrportalstuff\Symfony\Component\DependencyInjection;

use Symfony\Component\DependencyInjection\Definition;

class DefinitionAdapter extends Definition {

  /**
   * Returns a definition class.
   * We have to set is private to false on the definition class
   * with newer versions of civicrm (especially with civicrm and drupal 9)
   * with older versions of civicrm the setPrivate method is not available so we cannot set it.
   *
   * @param null $class
   * @param array $arguments
   * @return \Symfony\Component\DependencyInjection\Definition
   */
  public static function createDefinitionClass($class = NULL, array $arguments = []) {
    $definition = new Definition($class, $arguments);
    $definition->setPublic(TRUE);
    if (method_exists(Definition::class, 'setPrivate')) {
      $definition->setPrivate(FALSE);
    }
    return $definition;
  }

  /**
   * Returns a definition class.
   * We have to set is private to false on the definition class
   * with newer versions of civicrm (especially with civicrm and drupal 9)
   * with older versions of civicrm the setPrivate method is not available so we cannot set it.
   *
   * @param null $class
   * @param array $arguments
   * @return \Symfony\Component\DependencyInjection\Definition
   */
  public static function createPrivateDefinitionClass($class = NULL, array $arguments = []) {
    $definition = new Definition($class, $arguments);
    $definition->setPublic(FALSE);
    if (method_exists(Definition::class, 'setPrivate')) {
      $definition->setPrivate(TRUE);
    }
    return $definition;
  }

}
