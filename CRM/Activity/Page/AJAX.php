<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */

/**
 * This class contains all the function that are called using AJAX (jQuery)
 */
class CRM_Activity_Page_AJAX {

  public static function getCaseActivity() {
    CRM_Core_Page_AJAX::validateAjaxRequestMethod();
    // Should those params be passed through the validateParams method?
    $caseID = CRM_Utils_Type::validate($_GET['caseID'], 'Integer');
    $contactID = CRM_Utils_Type::validate($_GET['cid'], 'Integer');
    $userID = CRM_Utils_Type::validate($_GET['userID'], 'Integer');
    $context = CRM_Utils_Type::validate($_GET['context'] ?? NULL, 'String');

    $optionalParameters = [
      'source_contact_id' => 'Integer',
      'status_id' => 'Integer',
      'activity_deleted' => 'Boolean',
      'activity_type_id' => 'Integer',
      // "Date" validation fails because it expects only numbers with no hyphens
      'activity_date_low' => 'Alphanumeric',
      'activity_date_high' => 'Alphanumeric',
    ];

    $params = CRM_Core_Page_AJAX::defaultSortAndPagerParams();
    $params += CRM_Core_Page_AJAX::validateParams([], $optionalParameters);

    // get the activities related to given case
    $activities = CRM_Case_BAO_Case::getCaseActivity($caseID, $params, $contactID, $context, $userID);

    CRM_Utils_JSON::output($activities);
  }

  public static function getCaseGlobalRelationships() {
    CRM_Core_Page_AJAX::validateAjaxRequestMethod();
    $params = CRM_Core_Page_AJAX::defaultSortAndPagerParams();

    // get the activities related to given case
    $globalGroupInfo = [];

    // get the total row count
    CRM_Case_BAO_Case::getGlobalContacts($globalGroupInfo, NULL, FALSE, TRUE, NULL, NULL);
    // limit the rows
    $relGlobal = CRM_Case_BAO_Case::getGlobalContacts($globalGroupInfo, $params['sortBy'] ?? NULL, $showLinks = TRUE, FALSE, $params['offset'], $params['rp']);

    $relationships = [];
    // after sort we can update username fields to be a url
    foreach ($relGlobal as $key => $value) {
      $relationship = [];
      $relationship['sort_name'] = $value['sort_name'];
      $relationship['phone'] = $value['phone'];
      $relationship['email'] = $value['email'];

      array_push($relationships, $relationship);
    }

    $globalRelationshipsDT = [];
    $globalRelationshipsDT['data'] = $relationships;
    $globalRelationshipsDT['recordsTotal'] = count($relationships);
    $globalRelationshipsDT['recordsFiltered'] = count($relationships);

    CRM_Utils_JSON::output($globalRelationshipsDT);
  }

  public static function getCaseClientRelationships() {
    CRM_Core_Page_AJAX::validateAjaxRequestMethod();
    $caseID = CRM_Utils_Type::escape($_GET['caseID'], 'Integer');
    $contactID = CRM_Utils_Type::escape($_GET['cid'], 'Integer');

    $params = CRM_Core_Page_AJAX::defaultSortAndPagerParams();

    // Retrieve ALL client relationships
    $relClient = CRM_Contact_BAO_Relationship::getRelationship($contactID,
      CRM_Contact_BAO_Relationship::CURRENT,
      0, 0, 0, NULL, NULL, FALSE
    );

    $caseRelationships = CRM_Case_BAO_Case::getCaseRoles($contactID, $caseID, NULL, FALSE);

    // Now build 'Other Relationships' array by removing relationships that are already listed under Case Roles
    // so they don't show up twice.
    $clientRelationships = [];
    foreach ($relClient as $r) {
      if (!array_key_exists($r['id'], $caseRelationships)) {
        $clientRelationships[] = $r;
      }
    }

    // sort clientRelationships array using jquery call params
    $sortArray = [];
    if (!empty($params['_raw_values']['sort'])) {
      foreach ($clientRelationships as $key => $row) {
        $sortArray[$key] = $row[$params['_raw_values']['sort'][0]];
      }
      $sort_type = "SORT_" . strtoupper($params['_raw_values']['order'][0]);
      array_multisort($sortArray, constant($sort_type), $clientRelationships);
    }

    $relationships = [];
    // after sort we can update username fields to be a url
    foreach ($clientRelationships as $key => $value) {
      $relationship = [];
      $relationship['relation'] = $value['relation'];
      $relationship['name'] = '<a href=' . CRM_Utils_System::url('civicrm/contact/view',
          'action=view&reset=1&cid=' . $clientRelationships[$key]['cid']) . '>' . $clientRelationships[$key]['name'] . '</a>';
      $relationship['phone'] = $value['phone'];
      $relationship['email'] = $value['email'];

      array_push($relationships, $relationship);
    }

    $clientRelationshipsDT = [];
    $clientRelationshipsDT['data'] = $relationships;
    $clientRelationshipsDT['recordsTotal'] = count($relationships);
    $clientRelationshipsDT['recordsFiltered'] = count($relationships);

    CRM_Utils_JSON::output($clientRelationshipsDT);
  }

  public static function getCaseRoles() {
    CRM_Core_Page_AJAX::validateAjaxRequestMethod();
    $caseID = CRM_Utils_Type::escape($_GET['caseID'], 'Integer');
    $contactID = CRM_Utils_Type::escape($_GET['cid'], 'Integer');

    $params = CRM_Core_Page_AJAX::defaultSortAndPagerParams();

    $caseRelationships = CRM_Case_BAO_Case::getCaseRoles($contactID, $caseID, NULL, FALSE);
    $caseTypeName = CRM_Case_BAO_Case::getCaseType($caseID, 'name');
    $xmlProcessor = new CRM_Case_XMLProcessor_Process();
    $caseRoles = $xmlProcessor->get($caseTypeName, 'CaseRoles');

    $hasAccessToAllCases = CRM_Core_Permission::check('access all cases and activities');

    $managerRoleId = $xmlProcessor->getCaseManagerRoleId($caseTypeName);

    foreach ($caseRelationships as $key => $value) {
      // This role has been filled
      unset($caseRoles[$value['relation_type'] . '_' . $value['relationship_direction']]);
      // mark original case relationships record to use on setting edit links below
      $caseRelationships[$key]['source'] = 'caseRel';
    }

    $caseRoles['client'] = CRM_Case_BAO_Case::getContactNames($caseID);

    // move/transform caseRoles array data to caseRelationships
    // for sorting and display
    // CRM-14466 added cid to the non-client array to avoid php notice
    foreach ($caseRoles as $id => $value) {
      if ($id != "client") {
        $rel = [];
        $rel['relation'] = $value;
        $rel['relation_type'] = $id;
        $rel['sort_name'] = '(not assigned)';
        $rel['phone'] = '';
        $rel['email'] = '';
        $rel['is_active'] = '';
        $rel['end_date'] = '';
        $rel['source'] = 'caseRoles';
        $caseRelationships[] = $rel;
      }
      else {
        foreach ($value as $clientRole) {
          $relClient = [];
          $relClient['relation'] = ts('Client');
          $relClient['sort_name'] = $clientRole['sort_name'];
          $relClient['phone'] = $clientRole['phone'];
          $relClient['email'] = $clientRole['email'];
          $relClient['cid'] = $clientRole['contact_id'];
          $relClient['end_date'] = '';
          $relClient['source'] = 'contact';
          $caseRelationships[] = $relClient;
        }
      }
    }

    // sort clientRelationships array using jquery call params
    $sortArray = [];
    if (!empty($params['_raw_values']['sort'])) {
      foreach ($caseRelationships as $key => $row) {
        $sortArray[$key] = $row[$params['_raw_values']['sort'][0]];
      }
      $sort_type = "SORT_" . strtoupper($params['_raw_values']['order'][0]);
      array_multisort($sortArray, constant($sort_type), $caseRelationships);
    }

    $relationships = [];

    // set user name, email and edit columns links
    foreach ($caseRelationships as $key => &$row) {
      // add disabled class if role is inactive
      if (isset($row['is_active'])) {
        if ($row['is_active'] == '0') {
          $row['DT_RowClass'] = 'disabled';
        }
      }
      $typeLabel = $row['relation'];
      // Add "<br />(Case Manager)" to label
      if (!empty($row['relation_type']) && !empty($row['relationship_direction']) && $row['relation_type'] . '_' . $row['relationship_direction'] == $managerRoleId) {
        $row['relation'] .= '<br />' . '(' . ts('Case Manager') . ')';
      }
      // view user links
      if (!empty($row['cid'])) {
        $row['sort_name'] = '<a class="view-contact" title="' . ts('View Contact', ['escape' => 'htmlattribute']) . '" href=' . CRM_Utils_System::url('civicrm/contact/view',
            'action=view&reset=1&cid=' . $row['cid']) . '>' . $row['sort_name'] . '</a>';
      }
      // email column links/icon
      if ($row['email']) {
        $row['email'] = '<a class="crm-hover-button crm-popup" href="' . CRM_Utils_System::url('civicrm/case/email/add', 'reset=1&action=add&atype=3&cid=' . $row['cid']) . '&caseid=' . $caseID . '" title="' . ts('Send an Email', ['escape' => 'htmlattribute']) . '">' . CRM_Core_Page::crmIcon('fa-envelope') . '</a>';
      }

      // view end date if set
      if (!empty($row['end_date'])) {
        $row['end_date'] = CRM_Utils_Date::customFormat($row['end_date']);
        // add disabled class if end date is less than equal to current date.
        if (CRM_Utils_Date::overdue($row['end_date'])) {
          $row['DT_RowClass'] = 'disabled';
        }
      }
      else {
        $row['end_date'] = '';
      }

      // edit links
      $row['actions'] = '';
      if ($hasAccessToAllCases) {
        $contactType = empty($row['relation_type']) ? '' : (string) CRM_Core_DAO::getFieldValue('CRM_Contact_DAO_RelationshipType', $row['relation_type'], 'contact_type_b');
        $contactType = $contactType == 'Contact' ? '' : $contactType;
        switch ($row['source']) {
          case 'caseRel':
            if (empty($row['end_date'])) {
              $row['actions'] = '<a href="#editCaseRoleDialog" title="' . ts('Reassign %1', [1 => $typeLabel, 'escape' => 'htmlattribute']) . '" class="crm-hover-button case-miniform" data-contact_type="' . $contactType . '" data-rel_type="' . $row['relation_type'] . '_' . $row['relationship_direction'] . '" data-cid="' . $row['cid'] . '" data-rel_id="' . $row['rel_id'] . '"data-key="' . CRM_Core_Key::get('civicrm/ajax/relation') . '">' .
                CRM_Core_Page::crmIcon('fa-pencil') .
                '</a>' .
                '<a href="#deleteCaseRoleDialog" title="' . ts('Remove %1', [1 => $typeLabel, 'escape' => 'htmlattribute']) . '" class="crm-hover-button case-miniform" data-contact_type="' . $contactType . '" data-rel_type="' . $row['relation_type'] . '_' . $row['relationship_direction'] . '" data-cid="' . $row['cid'] . '" data-key="' . CRM_Core_Key::get('civicrm/ajax/delcaserole') . '">' .
                CRM_Core_Page::crmIcon('fa-trash') .
                '</a>';

            }
            break;

          case 'caseRoles':
            $row['actions'] = '<a href="#editCaseRoleDialog" title="' . ts('Assign %1', [1 => $typeLabel, 'escape' => 'htmlattribute']) . '" class="crm-hover-button case-miniform" data-contact_type="' . $contactType . '" data-rel_type="' . $row['relation_type'] . '_a_b" data-key="' . CRM_Core_Key::get('civicrm/ajax/relation') . '">' .
              CRM_Core_Page::crmIcon('fa-pencil') .
              '</a>';
            break;
        }
      }
      unset($row['cid']);
      unset($row['relation_type']);
      unset($row['rel_id']);
      unset($row['client_id']);
      unset($row['source']);
      array_push($relationships, $row);
    }
    $params['total'] = count($relationships);

    $caseRelationshipsDT = [];
    $caseRelationshipsDT['data'] = $relationships;
    $caseRelationshipsDT['recordsTotal'] = $params['total'];
    $caseRelationshipsDT['recordsFiltered'] = $params['total'];

    CRM_Utils_JSON::output($caseRelationshipsDT);

  }

  public static function convertToCaseActivity() {
    CRM_Core_Page_AJAX::validateAjaxRequestMethod();
    $params = ['caseID', 'activityID', 'contactID', 'newSubject', 'targetContactIds', 'mode'];
    $vals = [];
    foreach ($params as $param) {
      $vals[$param] = $_POST[$param] ?? NULL;
    }

    CRM_Utils_JSON::output(self::_convertToCaseActivity($vals));
  }

  /**
   * @param array $params
   *
   * @return array
   */
  public static function _convertToCaseActivity($params) {
    if (!$params['activityID'] || !$params['caseID']) {
      return (['error_msg' => 'required params missing.']);
    }

    $otherActivity = new CRM_Activity_DAO_Activity();
    $otherActivity->id = $params['activityID'];
    if (!$otherActivity->find(TRUE)) {
      return (['error_msg' => 'activity record is missing.']);
    }
    $actDateTime = $otherActivity->activity_date_time;

    // Create new activity record.
    $mainActivity = new CRM_Activity_DAO_Activity();
    $mainActVals = [];
    CRM_Core_DAO::storeValues($otherActivity, $mainActVals);

    // Get new activity subject.
    if (!empty($params['newSubject'])) {
      $mainActVals['subject'] = $params['newSubject'];
    }

    $mainActivity->copyValues($mainActVals);
    $mainActivity->id = NULL;
    $mainActivity->activity_date_time = $actDateTime;
    // Make sure this is current revision.
    $mainActivity->is_current_revision = TRUE;
    $mainActivity->original_id = $mainActivity->parent_id = NULL;

    $mainActivity->save();
    $mainActivityId = $mainActivity->id;
    CRM_Activity_BAO_Activity::logActivityAction($mainActivity);

    // Mark previous activity as deleted. If it was a non-case activity
    // then just change the subject.
    if (in_array($params['mode'], ['move', 'file'])) {
      $caseActivity = new CRM_Case_DAO_CaseActivity();
      $caseActivity->case_id = $params['caseID'];
      $caseActivity->activity_id = $otherActivity->id;
      if ($params['mode'] == 'move' || $caseActivity->find(TRUE)) {
        $otherActivity->is_deleted = 1;
      }
      else {
        $otherActivity->subject = ts('(Filed on case %1)', [
          1 => $params['caseID'],
        ]) . ' ' . $otherActivity->subject;
      }
      $otherActivity->save();

    }

    $targetContacts = [];
    if (!empty($params['targetContactIds'])) {
      $targetContacts = array_unique(explode(',', $params['targetContactIds']));
    }

    $activityContacts = CRM_Activity_BAO_ActivityContact::buildOptions('record_type_id', 'validate');
    $sourceID = CRM_Utils_Array::key('Activity Source', $activityContacts);
    $assigneeID = CRM_Utils_Array::key('Activity Assignees', $activityContacts);
    $targetID = CRM_Utils_Array::key('Activity Targets', $activityContacts);

    $sourceContactID = CRM_Activity_BAO_Activity::getSourceContactID($params['activityID']);
    $src_params = [
      'activity_id' => $mainActivityId,
      'contact_id' => $sourceContactID,
      'record_type_id' => $sourceID,
    ];
    CRM_Activity_BAO_ActivityContact::create($src_params);

    foreach ($targetContacts as $key => $value) {
      $targ_params = [
        'activity_id' => $mainActivityId,
        'contact_id' => $value,
        'record_type_id' => $targetID,
      ];
      CRM_Activity_BAO_ActivityContact::create($targ_params);
    }

    //CRM-21114 retrieve assignee contacts from original case; allow overriding from params
    $assigneeContacts = CRM_Activity_BAO_ActivityContact::retrieveContactIdsByActivityId($params['activityID'], $assigneeID);
    if (!empty($params['assigneeContactIds'])) {
      $assigneeContacts = array_unique(explode(',', $params['assigneeContactIds']));
    }
    foreach ($assigneeContacts as $value) {
      $assigneeParams = [
        'activity_id' => $mainActivityId,
        'contact_id' => $value,
        'record_type_id' => $assigneeID,
      ];
      CRM_Activity_BAO_ActivityContact::create($assigneeParams);
    }

    // Attach newly created activity to case.
    $caseActivity = new CRM_Case_DAO_CaseActivity();
    $caseActivity->case_id = $params['caseID'];
    $caseActivity->activity_id = $mainActivityId;
    $caseActivity->save();
    $error_msg = $caseActivity->_lastError;

    $params['mainActivityId'] = $mainActivityId;
    CRM_Activity_BAO_Activity::copyExtendedActivityData($params);
    CRM_Utils_Hook::post('create', 'CaseActivity', $caseActivity->id, $caseActivity);

    return (['error_msg' => $error_msg, 'newId' => $mainActivity->id]);
  }

  /**
   * Get activities for the contact.
   *
   * @throws \CRM_Core_Exception
   */
  public static function getContactActivity() {
    $requiredParameters = [
      'cid' => 'Integer',
    ];

    $optionalParameters = [
      'context' => 'String',
      'activity_type_id' => 'Integer',
      'activity_type_exclude_id' => 'Integer',
      'activity_status_id' => 'String',
      'activity_date_time_relative' => 'String',
      'activity_date_time_low' => 'String',
      'activity_date_time_high' => 'String',
    ];

    $params = CRM_Core_Page_AJAX::defaultSortAndPagerParams();
    $params += CRM_Core_Page_AJAX::validateParams($requiredParameters, $optionalParameters);
    // $params will be modified later on, need to save original filters
    $filterParams = $params;

    // To be consistent, the cid parameter should be renamed to contact_id in
    // the template file, see templates/CRM/Activity/Selector/Selector.tpl
    $params['contact_id'] = $params['cid'];
    unset($params['cid']);

    // get the contact activities
    $activities = CRM_Activity_BAO_Activity::getContactActivitySelector($params);

    foreach ($activities['data'] as $key => $value) {
      // Check if recurring activity.
      if (!empty($value['is_recurring_activity'])) {
        $repeat = $value['is_recurring_activity'];
        $activities['data'][$key]['activity_type'] .= '<br/><span class="bold">' . ts('Repeating (%1 of %2)', [1 => $repeat[0], 2 => $repeat[1]]) . '</span>';
      }
    }

    // store the activity filter preference CRM-11761
    if (Civi::settings()->get('preserve_activity_tab_filter') && ($userID = CRM_Core_Session::getLoggedInContactID())) {
      $activityFilter = [];
      unset($optionalParameters['context']);
      foreach ($optionalParameters as $searchField => $dataType) {
        $formSearchField = $searchField;
        if ($searchField === 'activity_type_id') {
          $formSearchField = 'activity_type_filter_id';
        }
        elseif ($searchField === 'activity_type_exclude_id') {
          $formSearchField = 'activity_type_exclude_filter_id';
        }
        if (!empty($filterParams[$searchField])) {
          $activityFilter[$formSearchField] = $filterParams[$searchField];
          if (in_array($searchField, ['activity_date_time_low', 'activity_date_time_high'])) {
            $activityFilter['activity_date_time_relative'] = 0;
          }
          elseif ($searchField === 'activity_status_id') {
            $activityFilter['status_id'] = explode(',', $activityFilter[$searchField]);
          }
        }
      }

      Civi::contactSettings()->set('activity_tab_filter', $activityFilter);
    }

    CRM_Utils_JSON::output($activities);
  }

}
