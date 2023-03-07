<?php
use CRM_Nbrportalstuff_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Nbrportalstuff_Upgrader extends CRM_Nbrportalstuff_Upgrader_Base
{

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Add show on portal custom field upon install
   */
  public function install() {
    $this->createWithdrawFromPortal();
    $this->createDoNotUploadToPortal();
    // set all existing records to value ""
    CRM_Core_DAO::executeQuery("UPDATE civicrm_value_nbr_study_data SET nsd_prevent_upload_portal = %1", [1 => ["", "String"]]);
    CRM_Core_DAO::executeQuery("UPDATE civicrm_value_nihr_volunteer_general_observations SET nvgo_withdraw_portal = %1", [1 => ["", "String"]]);
    // insert for volunteers that do not have a record yet
    $this->insertDefaultWithdrawnIfNotExists();
  }

  /**
   * Add form processor on post install
   */
  public function postInstall() {
    // add Form Processor if required
    try {
      civicrm_api3("FormProcessorInstance", "import");
    } catch (CiviCRM_API3_Exception $ex) {
      Civi::log()->error(E::ts("Could not import the Form Processor in ") . __METHOD__ . E::ts(", error message: ")
        . $ex->getMessage() . E::ts(". Please import manually in the User Interface"));
    }
  }

  /**
   * Upgrade 1001 - fix show portal custom field and add form processor
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1001(): bool {
    $this->ctx->log->info('Applying update 1001 - fix show portal custom field');
    $newName = "nvgo_show_portal";
    try {
      $check = \Civi\Api4\CustomField::get()
        ->addSelect('id', 'column_name')
        ->addWhere('custom_group_id:name', '=', CRM_Nihrbackbone_BackboneConfig::singleton()->getVolunteerGeneralObservationsCustomGroup('name'))
        ->addWhere('name', '=', 'nbr_show_portal')
        ->execute();
      $customField = $check->first();
      if ($customField['id']) {
        $cfUpdate = "UPDATE civicrm_custom_field SET name = %1, column_name = %1 WHERE id = %2";
        $cfParams = [
          1 => [$newName, "String"],
          2 => [(int)$customField['id'], "Integer"]
        ];
        CRM_Core_DAO::executeQuery($cfUpdate, $cfParams);
        if ($customField['column_name'] != $newName) {
          CRM_Core_DAO::executeQuery("ALTER TABLE civicrm_value_nihr_volunteer_general_observations CHANGE " . $customField['column_name'] . " " . $newName . " TINYINT(4)");
        }
      }
    } catch (API_Exception $ex) {
    }
    // add Form Processor if required
    try {
      civicrm_api3("FormProcessorInstance", "import");
    } catch (CiviCRM_API3_Exception $ex) {
      Civi::log()->error(E::ts("Could not import the Form Processor in ") . __METHOD__ . E::ts(", error message: ")
        . $ex->getMessage() . E::ts(". Please import manually in the User Interface"));
    }
    return TRUE;
  }

  /**
   * Upgrade 1002 - remove radio field if needed and create checkbox field in general observations
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1002(): bool {
    $this->ctx->log->info('Applying update 1002 - remove radio field if needed and create checkbox field in general observations');
    try {
      $customFields = \Civi\Api4\CustomField::get()
        ->addSelect('id')
        ->addWhere('custom_group_id:name', '=', 'nihr_volunteer_general_observations')
        ->addWhere('name', '=', 'nvgo_show_portal')
        ->execute();
      $customField = $customFields->first();
      if ($customField['id']) {
        \Civi\Api4\CustomField::delete()
          ->addWhere('id', '=', $customField['id'])
          ->execute();
      }
    } catch (API_Exception $ex) {
    }
    $this->createWithdrawFromPortal();
    return TRUE;
  }

  /**
   * Upgrade 1003 - create checkbox field for do not upload to portal on study
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1003(): bool {
    $this->ctx->log->info('Applying update 1003 - create checkbox field for do not upload to portal on study');
    $this->createDoNotUploadToPortal();
    return TRUE;
  }

  /**
   * Upgrade 1004 - clean up for portal flags
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1004(): bool {
    $this->ctx->log->info('Applying update 1004 - clean up for portal flags');
    // make sure both custom field and column for show_portal are removed
    $this->removeShowPortalField();
    // make sure option values are set to "1"
    $query = "UPDATE civicrm_option_value SET value = %1 WHERE name = %2 OR name = %3";
    CRM_Core_DAO::executeQuery($query, [
      1 => ["1", "String"],
      2 => ["nbr_portal_withdrawn", "String"],
      3 => ["nbr_prevent_upload", "String"]
    ]);
    // make sure default_value for custom fields are set to NULL
    $query = "UPDATE civicrm_custom_field SET default_value = NULL WHERE name = %1 OR name = %2";
    CRM_Core_DAO::executeQuery($query, [
      1 => ["nsd_prevent_upload_portal", "String"],
      2 => ["nvgo_withdraw_portal", "String"]
    ]);
    // set all existing records to value ""
    CRM_Core_DAO::executeQuery("UPDATE civicrm_value_nbr_study_data SET nsd_prevent_upload_portal = %1", [1 => ["", "String"]]);
    CRM_Core_DAO::executeQuery("UPDATE civicrm_value_nihr_volunteer_general_observations SET nvgo_withdraw_portal = %1", [1 => ["", "String"]]);
    // insert for volunteers that do not have a record yet
    $this->insertDefaultWithdrawnIfNotExists();
    return TRUE;
  }

  /**
   * Upgrade 1005 - set study field prevent upload to portal default to true
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1005(): bool {
    $this->ctx->log->info('Applying update 1005 - tick box by default for study - do not upload to portal');
    try {
      \Civi\Api4\CustomField::update()
        ->addWhere('custom_group_id:name', '=', 'nbr_study_data')
        ->addWhere('name', '=', 'nsd_prevent_upload_portal')
        ->addValue('default_value', TRUE)
        ->setLimit(1)
        ->execute();
    }
    catch (API_Exception $ex) {
      Civi::log()->debug($ex->getMessage());
    }
    CRM_Core_DAO::executeQuery("UPDATE civicrm_value_nbr_study_data SET nsd_prevent_upload_portal = TRUE");
    return TRUE;
  }

  /**
   * Method to create withdraw from portal custom field if required
   *
   * @return void
   */
  private function createWithdrawFromPortal() {
    $customGroupName = "nihr_volunteer_general_observations";
    $customFieldName = "nvgo_withdraw_portal";
    try {
      $customFields = \Civi\Api4\CustomField::get()
        ->addSelect('*')
        ->addWhere('custom_group_id:name', '=', $customGroupName)
        ->addWhere('name', '=', $customFieldName)
        ->execute();
      $count = $customFields->count();
      if ($count == 0) {
        \Civi\Api4\CustomField::create()
          ->addValue('custom_group_id:name', $customGroupName)
          ->addValue('name', $customFieldName)
          ->addValue('column_name', $customFieldName)
          ->addValue('label', 'Withdraw from portal?')
          ->addValue('html_type', 'CheckBox')
          ->addValue('data_type', 'String')
          ->addValue('option_group_id', $this->findOrCreateWithdrawnOptionGroupId())
          ->addValue('is_active', TRUE)
          ->addValue('is_searchable', TRUE)
          ->addValue('in_selector', TRUE)
          ->execute();
      }
    } catch (API_Exception $ex) {
    }
  }

  /**
   * Method to get the ID of the withdrawn from portal option group or create if it did not exist
   *
   * @return int
   */
  private function findOrCreateWithdrawnOptionGroupId(): int {
    $optionName = 'nbr_portal_withdrawn';
    try {
      $optionGroups = \Civi\Api4\OptionGroup::get()
        ->addSelect('id')
        ->addWhere('name', '=', $optionName)
        ->execute();
      $optionGroupCount = $optionGroups->count();
      if ($optionGroupCount == 0) {
        try {
          $newGroups = \Civi\Api4\OptionGroup::create()
            ->addValue('name', $optionName)
            ->addValue('title', 'Withdrawn from NBR Portal')
            ->addValue('description', 'Option group for the flag withdrawn from portal, should only have 1 value')
            ->addValue('data_type', 'String')
            ->addValue('is_active', TRUE)
            ->addValue('is_reserved', TRUE)
            ->execute();
          $newGroup = $newGroups->first();
          if ($newGroup['id']) {
            $this->createWithdrawnOptionValues((int)$newGroup['id'], $optionName);
            return (int)$newGroup['id'];
          }
        } catch (API_Exception $ex) {
          Civi::log()->error(E::ts("Could not create option group ") . $optionName . E::ts(" in ") . __METHOD__
            . E::ts(", error message from API4 OptionGroup create: ") . $ex->getMessage());
        }
      } else {
        $optionGroup = $optionGroups->first();
        if ($optionGroup['id']) {
          $this->createWithdrawnOptionValues((int)$optionGroup['id'], $optionName);
          return (int)$optionGroup['id'];
        }
      }
    } catch (API_Exception $ex) {
      Civi::log()->error(E::ts("Could not get option group ") . $optionName . E::ts(" in ") . __METHOD__
        . E::ts(", error message from API4 OptionGroup get: ") . $ex->getMessage());
    }
    return FALSE;
  }

  /**
   * Method to create option value withdrawn from portal if it does not exist
   *
   * @param int $optionGroupId
   * @param string $optionName
   * @return void
   */
  private function createWithdrawnOptionValues(int $optionGroupId, string $optionName) {
    try {
      $optionValues = \Civi\Api4\OptionValue::get()
        ->addSelect('*')
        ->addWhere('option_group_id', '=', $optionGroupId)
        ->addWhere('name', '=', $optionName)
        ->execute();
      $optionValueCount = $optionValues->count();
      if ($optionValueCount == 0) {
        \Civi\Api4\OptionValue::create()
          ->addValue('option_group_id', $optionGroupId)
          ->addValue('label', 'Withdrawn')
          ->addValue('value', "1")
          ->addValue('name', $optionName)
          ->addValue('is_active', TRUE)
          ->addValue('is_reserved', TRUE)
          ->execute();
      }
    } catch (API_Exception $ex) {
    }
  }

  /**
   * Method to create do not upload to portal flag on study
   *
   * @return void
   */
  private function createDoNotUploadToPortal() {
    $customGroupName = "nbr_study_data";
    $customFieldName = "nsd_prevent_upload_portal";
    try {
      $customFields = \Civi\Api4\CustomField::get()
        ->addSelect('*')
        ->addWhere('custom_group_id:name', '=', $customGroupName)
        ->addWhere('name', '=', $customFieldName)
        ->execute();
      $count = $customFields->count();
      if ($count == 0) {
        \Civi\Api4\CustomField::create()
          ->addValue('custom_group_id:name', $customGroupName)
          ->addValue('name', $customFieldName)
          ->addValue('column_name', $customFieldName)
          ->addValue('label', 'Prevent upload to portal?')
          ->addValue('html_type', 'CheckBox')
          ->addValue('data_type', 'String')
          ->addValue('option_group_id', $this->findOrCreatePreventUploadOptionGroupId())
          ->addValue('is_active', TRUE)
          ->addValue('is_searchable', TRUE)
          ->addValue('default_value', TRUE)
          ->addValue('in_selector', TRUE)
          ->execute();
      }
    } catch (API_Exception $ex) {
    }
  }

  /**
   * Method to get the ID of the prevent upload to portal option group or create if it did not exist
   *
   * @return int
   */
  private function findOrCreatePreventUploadOptionGroupId(): int {
    $optionName = 'nbr_prevent_upload';
    try {
      $optionGroups = \Civi\Api4\OptionGroup::get()
        ->addSelect('id')
        ->addWhere('name', '=', $optionName)
        ->execute();
      $optionGroupCount = $optionGroups->count();
      if ($optionGroupCount == 0) {
        try {
          $newGroups = \Civi\Api4\OptionGroup::create()
            ->addValue('name', $optionName)
            ->addValue('title', 'Prevent upload to NBR Portal')
            ->addValue('description', 'Option group for the flag prevent upload portal, should only have 1 value')
            ->addValue('data_type', 'String')
            ->addValue('is_active', TRUE)
            ->addValue('is_reserved', TRUE)
            ->execute();
          $newGroup = $newGroups->first();
          if ($newGroup['id']) {
            $this->createPreventUploadOptionValues((int)$newGroup['id'], $optionName);
            return (int)$newGroup['id'];
          }
        } catch (API_Exception $ex) {
          Civi::log()->error(E::ts("Could not create option group ") . $optionName . E::ts(" in ") . __METHOD__
            . E::ts(", error message from API4 OptionGroup create: ") . $ex->getMessage());
        }
      } else {
        $optionGroup = $optionGroups->first();
        if ($optionGroup['id']) {
          $this->createPreventUploadOptionValues((int)$optionGroup['id'], $optionName);
          return (int)$optionGroup['id'];
        }
      }
    } catch (API_Exception $ex) {
      Civi::log()->error(E::ts("Could not get option group ") . $optionName . E::ts(" in ") . __METHOD__
        . E::ts(", error message from API4 OptionGroup get: ") . $ex->getMessage());
    }
    return FALSE;
  }

  /**
   * Method to create option value prevent upload to portal if it does not exist
   *
   * @param int $optionGroupId
   * @param string $optionName
   * @return void
   */
  private function createPreventUploadOptionValues(int $optionGroupId, string $optionName) {
    try {
      $optionValues = \Civi\Api4\OptionValue::get()
        ->addSelect('*')
        ->addWhere('option_group_id', '=', $optionGroupId)
        ->addWhere('name', '=', $optionName)
        ->execute();
      $optionValueCount = $optionValues->count();
      if ($optionValueCount == 0) {
        \Civi\Api4\OptionValue::create()
          ->addValue('option_group_id', $optionGroupId)
          ->addValue('label', 'Prevent upload')
          ->addValue('value', "1")
          ->addValue('name', $optionName)
          ->addValue('is_active', TRUE)
          ->addValue('is_reserved', TRUE)
          ->execute();
      }
    } catch (API_Exception $ex) {
    }
  }

  /**
   * Method to insert withdrawn with value "" for all volunteers that do not have a record yet
   *
   * @return void
   */
  private function insertDefaultWithdrawnIfNotExists() {
    $contactQry = "SELECT id FROM civicrm_contact WHERE contact_sub_type LIKE %1";
    $dao = CRM_Core_DAO::executeQuery($contactQry, [1 => ["%nihr_volunteer%", "String"]]);
    while ($dao->fetch()) {
      $checkQry = "SELECT COUNT(*) FROM civicrm_value_nihr_volunteer_general_observations WHERE entity_id = %1";
      $count = CRM_Core_DAO::singleValueQuery($checkQry, [1 => [$dao->id, "Integer"]]);
      if ($count == 0) {
        $insert = "INSERT INTO civicrm_value_nihr_volunteer_general_observations (entity_id, nvgo_withdraw_portal) VALUES(%1, %2)";
        CRM_Core_DAO::executeQuery($insert, [
          1 => [$dao->id, "Integer"],
          2 => ["", "String"],
        ]);
      }
    }
  }

  /**
   * Make sure the custom field for show portal is removed AND the column is removed from the table
   *
   * @return void
   */
  private function removeShowPortalField() {
    $fieldName = "nvgo_show_portal";
    try {
      $customFields = \Civi\Api4\CustomField::get()
        ->addSelect('id')
        ->addWhere('custom_group_id:name', '=', 'nihr_volunteer_general_observations')
        ->addWhere('name', '=', $fieldName)
        ->execute();
      $customField = $customFields->first();
      if ($customField['id']) {
        \Civi\Api4\CustomField::delete()
          ->addWhere('id', '=', $customField['id'])
          ->execute();
      }
    } catch (API_Exception $ex) {
    }
    if (CRM_Core_BAO_SchemaHandler::checkIfFieldExists("civicrm_value_nihr_volunteer_general_observations", $fieldName)) {
      CRM_Core_DAO::executeQuery("ALTER TABLE civicrm_value_nihr_volunteer_general_observations DROP COLUMN " . $fieldName);
    }
  }
}
