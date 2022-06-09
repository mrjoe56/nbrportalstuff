<?php
use CRM_Nbrportalstuff_ExtensionUtil as E;

/**
 * Class for NBR Portal to CiviCRM connector service
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license AGPL-3.0
 */
namespace Civi\Nbrportalstuff;

class NbrPortalFactory {

  /**
   * @var CRM_Nbrportalstuff_NbrPortalService
   */
  protected static $singleton;
  private $_ngoCgName = NULL;
  private $_ngoShowPortalCfName = NULL;

  /**
   * CRM_Nbrportalstuff_NbrPortalService constructor.
   */
  public function __construct() {
    if (!self::$singleton) {
      self::$singleton = $this;
    }
  }

  /**
   * @return CRM_Nbrportalstuff_NbrPortalService
   */
  public static function getInstance() {
    if (!self::$singleton) {
      self::$singleton = new CRM_Nbrportalstuff_NbrPortalService();
    }
    return self::$singleton;
  }

  /**
   * @param string $name
   */
  public function setGeneralObservationsCustomGroupName($name) {
    $this->_ngoCgName = $name;
  }

  /**
   * @return null
   */
  public function getGeneralObservationsCustomGroupName() {
    return $this->_ngoCgName;
  }

  /**
   * @param string $name
   */
  public function setShowPortalCustomFieldName($name) {
    $this->_ngoShowPortalCfName = $name;
  }

  /**
   * @return null
   */
  public function getShowPortalCustomFieldName() {
    return $this->_ngoShowPortalCfName;
  }

}
