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
    $this->createShowOnPortal();
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
   * Upgrade 1001 - fix show portal custom field
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
    return TRUE;
  }


  /**
   * Method to create show on portal custom field if required
   *
   * @return void
   */
  private function createShowOnPortal() {
    $customGroupName = "nihr_volunteer_general_observations";
    $customFieldName = "nvgo_show_portal";
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
          ->addValue('label', 'Show on portal?')
          ->addValue('html_type', 'Radio')
          ->addValue('data_type', 'Boolean')
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
