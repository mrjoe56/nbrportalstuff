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
 * Class NbrCreateContact - update a contact with data from the portal
 *
 * @package Civi\Nbrportalstuff\Actions
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license AGPL-3.0
 */
class NbrCreateContact extends AbstractAction {

    /**
     * @return SpecificationBag
     */
    public function getParameterSpecification() {
        $specs = new SpecificationBag();
        $specs->addSpecification(new Specification('first_name', 'String', E::ts('First name'), TRUE, ""));
        $specs->addSpecification(new Specification('last_name', 'String', E::ts('Last Name'), TRUE, ""));
        $specs->addSpecification(new Specification('birth_date', 'Date', E::ts('Birth Date'), TRUE, ""));
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
            $this->processUpdates( $parameters);
    }

    /**
     * Method to process the incoming portal updates
     *
     * @param int $contactId
     * @param ParameterBagInterface $parameters
     * @return void
     */
    private function processUpdates(ParameterBagInterface $parameters) {
        $factory = nbrportalstuff_get_factory();

//        $showPortalField = $factory->getGeneralObservationsCustomGroupName() . "." . $factory->getShowPortalCustomFieldName();
        $firstName = $parameters->getParameter('first_name');
        $lastName = $parameters->getParameter('last_name');
        \Civi::log()->info("Date of birth is ".$parameters->getParameter('birth_date'));

        $dateOfBirth = $parameters->getParameter('birth_date');
        $values=[];
        $values["first_name"]= $firstName;
        $values["birth_date"]= $lastName;

        $values["birth_date"]= $dateOfBirth;
        \Civi::log()->info("Date of birth is ".$dateOfBirth);

        $contact = civicrm_api4('Contact', 'get', [
            'where' => [
                ['first_name', '=', $firstName],
                ['last_name', '=', $lastName],
                ['birth_date', '=', $dateOfBirth],


            ]]);
        $contactCount= $contact->count();
        if($contactCount>0){
            \Civi::log()->info("Contact found! Need to return!");
            throw new ExecutionException(E::ts('Contact already exists with  the name '. $firstName.' '.$lastName ));
        }

        else{
            try {
                $newContact = civicrm_api4('Contact', 'create', [
                    'values' => $values,
                ]);
//                \Civi::log()->info("Created contact".$newContact);
                $contact=$newContact->first();
                $contactId= $contact['id'];
                \Civi::log()->info("Created contact. Id is ".$contactId);



            }
            catch (\API_Exception $ex) {
                \Civi::log()->error(E::ts("Error when trying to update contact in ") . __METHOD__ . E::ts(", error from API Contact update: ") . $ex->getMessage());
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
//            new Specification('contact_id', 'Integer', E::ts('Contact ID'), FALSE)
        ]);
    }

}
