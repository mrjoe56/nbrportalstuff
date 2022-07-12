<?php
use CRM_Nbrportalstuff_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Nbrportalstuff_Upgrader extends CRM_Nbrportalstuff_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Add show on portal custom field upon install
   */
  public function install() {
    $this->createWithdrawFromPortal();
    $this->createDoNotUploadToPortal();
  }

  /**
   * Add form processor on post install
   */
  public function postInstall() {
    // add Form Processor if required
    try {
      civicrm_api3("FormProcessorInstance", "import");
    }
    catch (CiviCRM_API3_Exception $ex) {
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
      if ( $customField['id']) {
        $cfUpdate = "UPDATE civicrm_custom_field SET name = %1, column_name = %1 WHERE id = %2";
        $cfParams = [
          1 => [$newName, "String"],
          2 => [(int) $customField['id'], "Integer"]
        ];
        CRM_Core_DAO::executeQuery($cfUpdate, $cfParams);
        if ($customField['column_name'] != $newName) {
          CRM_Core_DAO::executeQuery("ALTER TABLE civicrm_value_nihr_volunteer_general_observations CHANGE " . $customField['column_name'] . " " .  $newName . " TINYINT(4)");
        }
      }
    }
    catch (API_Exception $ex) {
    }
    // add Form Processor if required
    try {
      civicrm_api3("FormProcessorInstance", "import");
    }
    catch (CiviCRM_API3_Exception $ex) {
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
    }
    catch (API_Exception $ex) {
    }
    $this->createWithdrawFromPortal();
    // make sure all values are set to 0 at start
    CRM_Core_DAO::executeQuery("UPDATE civicrm_value_nihr_volunteer_general_observations SET nvgo_withdraw_portal = NULL");
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
          ->addValue('default_value', TRUE)
          ->addValue('is_searchable', TRUE)
          ->addValue('in_selector', TRUE)
          ->execute();
      }
    }
    catch (API_Exception $ex) {
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
            $this->createWithdrawnOptionValue((int) $newGroup['id'], $optionName);
            return (int) $newGroup['id'];
          }
        }
        catch (API_Exception $ex) {
          Civi::log()->error(E::ts("Could not create option group ") . $optionName . E::ts(" in ") . __METHOD__
            . E::ts(", error message from API4 OptionGroup create: ") . $ex->getMessage());
        }
      }
      else {
        $optionGroup = $optionGroups->first();
        if ($optionGroup['id']) {
          $this->createWithdrawnOptionValue((int) $optionGroup['id'], $optionName);
          return (int) $optionGroup['id'];
        }
      }
    }
    catch (API_Exception $ex) {
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
  private function createWithdrawnOptionValue(int $optionGroupId, string $optionName) {
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
          ->addValue('value', $optionName)
          ->addValue('name', $optionName)
          ->addValue('is_active', TRUE)
          ->addValue('is_reserved', TRUE)
          ->execute();
      }
    }
    catch (API_Exception $ex) {
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
          ->addValue('default_value', TRUE)
          ->addValue('is_searchable', TRUE)
          ->addValue('in_selector', TRUE)
          ->execute();
      }
    }
    catch (API_Exception $ex) {
    }
    // make sure all values are set to 0 at start
    CRM_Core_DAO::executeQuery("UPDATE civicrm_value_nbr_study_data SET nsd_prevent_upload_portal = NULL");
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
            $this->createPreventUploadOptionValue((int) $newGroup['id'], $optionName);
            return (int) $newGroup['id'];
          }
        }
        catch (API_Exception $ex) {
          Civi::log()->error(E::ts("Could not create option group ") . $optionName . E::ts(" in ") . __METHOD__
            . E::ts(", error message from API4 OptionGroup create: ") . $ex->getMessage());
        }
      }
      else {
        $optionGroup = $optionGroups->first();
        if ($optionGroup['id']) {
          $this->createPreventUploadOptionValue((int) $optionGroup['id'], $optionName);
          return (int) $optionGroup['id'];
        }
      }
    }
    catch (API_Exception $ex) {
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
  private function createPreventUploadOptionValue(int $optionGroupId, string $optionName) {
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
          ->addValue('value', $optionName)
          ->addValue('name', $optionName)
          ->addValue('is_active', TRUE)
          ->addValue('is_reserved', TRUE)
          ->execute();
      }
    }
    catch (API_Exception $ex) {
    }
  }

  /**
   * Example: Run an external SQL script when the module is uninstalled.
   */
  // public function uninstall() {
  //  $this->executeSqlFile('sql/myuninstall.sql');
  // }

  /**
   * Example: Run a simple query when a module is enabled.
   */
  // public function enable() {
  //  CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 1 WHERE bar = "whiz"');
  // }

  /**
   * Example: Run a simple query when a module is disabled.
   */
  // public function disable() {
  //   CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 0 WHERE bar = "whiz"');
  // }


  /**
   * Example: Run an external SQL script.
   *
   * @return TRUE on success
   * @throws Exception
   */
  // public function upgrade_4201(): bool {
  //   $this->ctx->log->info('Applying update 4201');
  //   // this path is relative to the extension base dir
  //   $this->executeSqlFile('sql/upgrade_4201.sql');
  //   return TRUE;
  // }


  /**
   * Example: Run a slow upgrade process by breaking it up into smaller chunk.
   *
   * @return TRUE on success
   * @throws Exception
   */
  // public function upgrade_4202(): bool {
  //   $this->ctx->log->info('Planning update 4202'); // PEAR Log interface

  //   $this->addTask(E::ts('Process first step'), 'processPart1', $arg1, $arg2);
  //   $this->addTask(E::ts('Process second step'), 'processPart2', $arg3, $arg4);
  //   $this->addTask(E::ts('Process second step'), 'processPart3', $arg5);
  //   return TRUE;
  // }
  // public function processPart1($arg1, $arg2) { sleep(10); return TRUE; }
  // public function processPart2($arg3, $arg4) { sleep(10); return TRUE; }
  // public function processPart3($arg5) { sleep(10); return TRUE; }

  /**
   * Example: Run an upgrade with a query that touches many (potentially
   * millions) of records by breaking it up into smaller chunks.
   *
   * @return TRUE on success
   * @throws Exception
   */
  // public function upgrade_4203(): bool {
  //   $this->ctx->log->info('Planning update 4203'); // PEAR Log interface

  //   $minId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(min(id),0) FROM civicrm_contribution');
  //   $maxId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(max(id),0) FROM civicrm_contribution');
  //   for ($startId = $minId; $startId <= $maxId; $startId += self::BATCH_SIZE) {
  //     $endId = $startId + self::BATCH_SIZE - 1;
  //     $title = E::ts('Upgrade Batch (%1 => %2)', array(
  //       1 => $startId,
  //       2 => $endId,
  //     ));
  //     $sql = '
  //       UPDATE civicrm_contribution SET foobar = whiz(wonky()+wanker)
  //       WHERE id BETWEEN %1 and %2
  //     ';
  //     $params = array(
  //       1 => array($startId, 'Integer'),
  //       2 => array($endId, 'Integer'),
  //     );
  //     $this->addTask($title, 'executeSql', $sql, $params);
  //   }
  //   return TRUE;
  // }

}
