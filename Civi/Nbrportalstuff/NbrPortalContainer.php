<?php
use CRM_Nbrportalstuff_ExtensionUtil as E;

/**
 * @author Erik Hommel <erik.hommel@civicoop.org>
 * @license AGPL-3.0
 */
namespace Civi\Nbrportalstuff;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class NbrPortalContainer implements CompilerPassInterface {

  /**
   * You can modify the container here before it is dumped to PHP code.
   */
  public function process(ContainerBuilder $container) {
    $definition = new Definition('Civi_Nbrportalstuff_NbrPortalFactory');
    $definition->setFactory(['Civi_Nbrportalstuff_NbrPortalFactory', 'getInstance']);
    $definition->setCustomData($definition);
    $definition->setPublic(TRUE);
    $container->setDefinition('nbrportalstuff', $definition);
    if ($container->hasDefinition('action_provider')) {
      $actionProviderDefinition = $container->getDefinition('action_provider');
      $actionProviderDefinition->addMethodCall('addAction',
        ['NbrVolunteerUpdate', 'Civi\Nbrportalstuff\Actions\NbrVolunteerUpdate', E::ts('Update Volunteer Data'), []]);
    }
  }
  /**
   * Method to set the custom group (and fields) properties
   *
   * @param $definition
   */
  private function setCustomData(&$definition) {
    $customGroupName = \CRM_Nihrbackbone_BackboneConfig::singleton()->getVolunteerGeneralObservationsCustomGroup('name');
    if ($customGroupName) {
      $definition->addMethodCall('setGeneralObservationsCustomGroupName', [$customGroupName]);
      $definition->addMethodCall('setShowPortalCustomFieldName', ["nvgo_show_portal"]);
    }
  }

}

