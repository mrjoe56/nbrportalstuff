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
 * Class NbrContactUpdate - update a contact with data from the portal
 *
 * @package Civi\Nbrportalstuff\Actions
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license AGPL-3.0
 */
class NbrContactUpdate extends AbstractAction {

  /**
   * @return SpecificationBag
   */
  public function getParameterSpecification() {
    $specs = new SpecificationBag();
    $specs->addSpecification(new Specification('nbr_contact_id', 'Integer', E::ts('Contact ID'), TRUE, NULL));
    $specs->addSpecification(new Specification('nbr_email', 'String', E::ts('E-mailaddress'), FALSE, NULL));
    $specs->addSpecification(new Specification('nbr_show_portal', 'Boolean', E::ts('Show data on portal?'), FALSE, TRUE));
    return $specs;
  }

  /**
   * @return SpecificationBag
   */
  public function getConfigurationSpecification() {
    return new SpecificationBag();
  }

  /**
   * Do the actual action - find the contact with ID and update the relevant data
   *
   * @param ParameterBagInterface $parameters
   * @param ParameterBagInterface $output
   * @throws ExecutionException
   */
  public function doAction(ParameterBagInterface $parameters, ParameterBagInterface $output) {
    $contactId = $parameters->getParameter('nbr_contact_id');
    if (!empty($contactId)) {
      $this->processUpdates($contactId, $parameters);
      $output->setParameter('nbr_contact_id', $contactId);
    }
    else {
      throw new ExecutionException(E::ts('When updating a contact the contact ID can not be empty.'));
    }
  }

  /**
   * Method to process the incoming portal updates
   *
   * @param int $contactId
   * @param ParameterBagInterface $parameters
   * @return void
   */
  private function processUpdates(int $contactId, ParameterBagInterface $parameters) {
    $factory = nbrportalstuff_get_factory();
    $showPortalField = $factory->getGeneralObservationsCustomGroupName() . "." . $factory->getShowPortalCustomFieldName();
    $showValue = $parameters->getParameter('nbr_show_portal');
    try {
      \Civi\Api4\Contact::update()
        ->addWhere('id', '=', $contactId)
        ->addValue($showPortalField, $showValue)
        ->execute();
    }
    catch (\API_Exception $ex) {
      \Civi::log()->error(E::ts("Error when trying to update contact in ") . __METHOD__ . E::ts(", error from API Contact update: ") . $ex->getMessage());
    }
    // update primary email if  there is one, else create
    $newEmail = $parameters->getParameter('nbr_email');
    if ($newEmail) {
      try {
        $emails = \Civi\Api4\Email::get()
          ->addSelect('id')
          ->addWhere('contact_id', '=', $contactId)
          ->addWhere('is_primary', '=', TRUE)
          ->execute();
        $emailCount = $emails->count();
        if ($emailCount == 0) {
          \Civi\Api4\Email::create()
            ->addValue('contact_id', $contactId)
            ->addValue('location_type_id', \CRM_Nihrbackbone_BackboneConfig::singleton()->getDefaultLocationTypeId())
            ->addValue('email', $newEmail)
            ->addValue('is_primary', TRUE)
            ->execute();
        }
        else {
          \Civi\Api4\Email::update()
            ->addWhere('contact_id', '=', $contactId)
            ->addWhere('is_primary', '=', TRUE)
            ->addValue('email', $newEmail)
            ->execute();
        }
      }
      catch (\API_Exception $ex) {
        \Civi::log()->error(E::ts("Could not update the primary email to ") . $newEmail . E::ts(" for contact with ID ")
          . $contactId . E::ts(" using API4 Email update in ") . __METHOD__ . E::ts(", error message: ") . $ex->getMessage());
      }
    }
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
      new Specification('nbr_contact_id', 'Integer', E::ts('Contact ID'), FALSE)
    ]);
  }

}
