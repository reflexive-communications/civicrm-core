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
 * Class api_v3_GroupContactTest
 * @group headless
 */
class api_v3_GroupContactTest extends CiviUnitTestCase {

  /**
   * @var int
   */
  protected $contactID;

  /**
   * @var int
   */
  protected $contactID1;

  /**
   * @var array
   */
  protected $groups;

  /**
   * @var int
   */
  protected $groupID1;

  /**
   * @var int
   */
  protected $groupID2;

  /**
   * Set up for group contact tests.
   *
   * @todo set up calls function that doesn't work @ the moment
   */
  public function setUp(): void {
    parent::setUp();
    $this->contactID = $this->individualCreate();

    $this->groupID1 = $this->groupCreate();

    $this->callAPISuccess('group_contact', 'create', [
      'contact_id' => $this->contactID,
      'group_id' => $this->groupID1,
    ]);

    $this->groupID2 = $this->groupCreate([
      'name' => 'Test Group 2',
      'domain_id' => 1,
      'title' => 'New Test Group2 Created',
      'description' => 'New Test Group2 Created',
      'is_active' => 1,
      'visibility' => 'User and User Admin Only',
    ]);

    $this->groups = [
      $this->groupID1 => [
        'title' => 'New Test Group Created',
        'visibility' => 'Public Pages',
        'in_method' => 'API',
      ],
      $this->groupID2 => [
        'title' => 'New Test Group2 Created',
        'visibility' => 'User and User Admin Only',
        'in_method' => 'API',
      ],
    ];
  }

  /**
   * Cleanup after test.
   */
  public function tearDown(): void {
    $this->quickCleanup(['civicrm_group', 'civicrm_group_contact', 'civicrm_subscription_history']);
    parent::tearDown();
  }

  /**
   * Test GroupContact.get by ID.
   */
  public function testGet(): void {
    $params = [
      'contact_id' => $this->contactID,
    ];
    $result = $this->callAPISuccess('group_contact', 'get', $params);
    foreach ($result['values'] as $v) {
      $this->assertEquals($v['title'], $this->groups[$v['group_id']]['title']);
      $this->assertEquals($v['visibility'], $this->groups[$v['group_id']]['visibility']);
      $this->assertEquals($v['in_method'], $this->groups[$v['group_id']]['in_method']);
    }
  }

  public function testGetGroupID(): void {
    $params = [
      'group_id' => $this->groupID1,
      'api.group.get' => 1,
      'sequential' => 1,
    ];
    $result = $this->callAPISuccess('group_contact', 'get', $params);
    foreach ($result['values'][0]['api.group.get']['values'] as $values) {
      $key = $values['id'];
      $this->assertEquals($values['title'], $this->groups[$key]['title']);
      $this->assertEquals($values['visibility'], $this->groups[$key]['visibility']);
    }
  }

  /**
   * Test group contact create.
   */
  public function testCreate(): void {
    $this->contactID1 = $this->individualCreate([
      'first_name' => 'Amiteshwar',
      'middle_name' => 'L.',
      'last_name' => 'Prasad',
      'prefix_id' => 3,
      'suffix_id' => 3,
      'email' => 'amiteshwar.prasad@civicrm.org',
      'contact_type' => 'Individual',
    ]);
    $params = [
      'contact_id' => $this->contactID,
      'contact_id.2' => $this->contactID1,
      'group_id' => $this->groupID1,
    ];

    $result = $this->callAPISuccess('GroupContact', 'create', $params);
    $this->assertEquals(1, $result['not_added']);
    $this->assertEquals(1, $result['added']);
    $this->assertEquals(2, $result['total_count']);
  }

  /**
   * CRM-16945 duplicate groups are showing up when contacts are hard-added to child groups or smart groups.
   *
   * Fix documented in
   *
   * Test illustrates this (& ensures once fixed it will stay fixed).
   */
  public function testAccurateCountWithSmartGroups(): void {
    $childGroupID = $this->groupCreate([
      'name' => 'Child group',
      'domain_id' => 1,
      'title' => 'Child group',
      'description' => 'Child group',
      'is_active' => 1,
      'parents' => $this->groupID1,
      'visibility' => 'User and User Admin Only',
    ]);

    $params = [
      'name' => 'Individuals',
      'title' => 'Individuals',
      'is_active' => 1,
      'parents' => $this->groupID1,
      'formValues' => ['contact_type' => 'Goat'],
    ];
    $smartGroup2 = CRM_Contact_BAO_Group::createSmartGroup($params);

    $this->callAPISuccess('GroupContact', 'create', ['contact_id' => $this->contactID, 'status' => 'Added', 'group_id' => $this->groupID2]);
    $this->callAPISuccess('GroupContact', 'create', ['contact_id' => $this->contactID, 'status' => 'Added', 'group_id' => $smartGroup2->id]);
    $this->callAPISuccess('GroupContact', 'create', ['contact_id' => $this->contactID, 'status' => 'Added', 'group_id' => $childGroupID]);
    $groups = $this->callAPISuccess('GroupContact', 'get', ['contact_id' => $this->contactID]);

    // Although the contact is actually hard-added to 4 groups the smart groups are conventionally not returned by the api or displayed
    // on the main part of the groups tab on the contact (which calls the same function. So, 3 groups is an OK number to return.
    // However, as of writing this test 4 groups are returned (indexed by group_contact_id, but more seriously 3/4 of those have the group id 1
    // so 2 on them have group ids that do not match the group contact id they have been keyed by.
    foreach ($groups['values'] as $groupContactID => $groupContactRecord) {
      $this->assertEquals($groupContactRecord['group_id'], CRM_Core_DAO::singleValueQuery("SELECT group_id FROM civicrm_group_contact WHERE id = $groupContactID"), 'Group contact record mis-returned for id ' . $groupContactID);
    }
    $this->assertEquals(3, $groups['count']);

  }

}
