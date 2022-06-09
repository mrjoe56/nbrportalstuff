<?php
namespace Civi\Nbrportalstuff\Actions;

use \Civi\ActionProvider\Action\AbstractAction;
use Civi\ActionProvider\Exception\ExecutionException;
use \Civi\ActionProvider\Parameter\ParameterBagInterface;
use \Civi\ActionProvider\Parameter\SpecificationBag;
use \Civi\ActionProvider\Parameter\Specification;

use Civi\Core\Lock\NullLock;
use Civi\FormProcessor\API\Exception;
use CRM_Nbrportalstuff_ExtensionUtil as E;

/**
 * Class NbrVolunteerUpdate - update a volunteer with data from the portal
 *
 * @package Civi\Nbrportalstuff\Actions
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license AGPL-3.0
 */
class NbrVolunteerUpdate extends AbstractAction {

  /**
   * @return SpecificationBag
   */
  public function getParameterSpecification() {
    $specs = new SpecificationBag();
    $specs->addSpecification(new Specification('nbr_volunteer_id', 'Int', E::ts('Volunteer Contact ID'), TRUE, NULL));
    $specs->addSpecification(new Specification('nbr_volunteer_email', 'String', E::ts('Volunteer E-mailaddress'), FALSE, NULL));
    $specs->addSpecification(new Specification('nbr_volunteer_show_portal', 'Boolean', E::ts('Hide data on portal?'), FALSE, FALSE));
    return $specs;
  }

  /**
   * @return SpecificationBag
   */
  public function getConfigurationSpecification() {
    return new SpecificationBag();
  }

  /**
   * Do the actual action - find the volunteer with ID and update the relevant data
   *
   * @param ParameterBagInterface $parameters
   * @param ParameterBagInterface $output
   * @throws ExecutionException
   */
  public function doAction(ParameterBagInterface $parameters, ParameterBagInterface $output) {
    $volunteerId = $parameters->getParameter('nbr_volunteer_id');
    if (!empty($volunteerId)) {
      $this->processUpdates($volunteerId);
      $output->setParameter('volunteer_id', $volunteerId);
    }
    else {
      throw new ExecutionException(E::ts('When updating a volunteer the volunteer ID can not be empty.'));
    }
  }
  private function processUpdates(int $volunteerId) {
    // update show portal
    $factory = nbrportalstuff_get_factory();
    $showPortalField = $factory->getGeneralObservationsCustomGroupName() . "." . $factory->getShowPortalCustomFieldName();
    try {
      \Civi\Api4\Contact::update()
        ->addWhere('id', '=', $volunteerId)
        ->addValue('nihr_volunteer_general_observations.nbr_show_portal', 1)
        ->execute();
    }
    catch (\API_Exception $ex) {
      \Civi::log()->error(E::ts("Error when trying to update volunteer in ") . __METHOD__ . E::ts(", error from API Contact update: ") . $ex->getMessage());
    }
    // update email
  }

  /**
   * Returns the specification of the output parameters of this action.
   *
   * This function could be overriden by child classes.
   *
   * @return SpecificationBag
   */
  public function getOutputSpecification() {
    return new SpecificationBag([
      new Specification('volunteer_id', 'Integer', E::ts('Volunteer ID'), TRUE)
    ]);
  }

}
