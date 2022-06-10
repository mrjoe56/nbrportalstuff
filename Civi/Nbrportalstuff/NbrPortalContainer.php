<?php

/**
 * @author Erik Hommel <erik.hommel@civicoop.org>
 * @license AGPL-3.0
 */
namespace Civi\Nbrportalstuff;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use CRM_Nbrportalstuff_ExtensionUtil as E;

class NbrPortalContainer implements CompilerPassInterface {

  /**
   * You can modify the container here before it is dumped to PHP code.
   */
  public function process(ContainerBuilder $container) {
    $definition = new Definition('CRM_Nbrportalstuff_NbrPortalFactory');
    $definition->setFactory(['CRM_Nbrportalstuff_NbrPortalFactory', 'getInstance']);
    $definition->addMethodCall('setGeneralObservationsCustomGroupName', ["nihr_volunteer_general_observations"]);
    $definition->addMethodCall('setShowPortalCustomFieldName', ["nvgo_show_portal"]);
    $definition->setPublic(TRUE);
    $container->setDefinition('nbrportalstuff', $definition);
    if ($container->hasDefinition('action_provider')) {
      $actionProviderDefinition = $container->getDefinition('action_provider');
      $actionProviderDefinition->addMethodCall('addAction',
        ['NbrContactUpdate', 'Civi\Nbrportalstuff\Actions\NbrContactUpdate', E::ts('Update NBR Contact Data'), []]);
    }
  }

}

