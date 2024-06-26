<?php

return [
  'name' => 'CaseType',
  'table' => 'civicrm_case_type',
  'class' => 'CRM_Case_DAO_CaseType',
  'getInfo' => fn() => [
    'title' => ts('Case Type'),
    'title_plural' => ts('Case Types'),
    'description' => ts('Case type definition'),
    'log' => TRUE,
    'add' => '4.5',
  ],
  'getIndices' => fn() => [
    'case_type_name' => [
      'fields' => [
        'name' => TRUE,
      ],
      'unique' => TRUE,
      'add' => '4.5',
    ],
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => ts('Case Type ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => ts('Autoincremented type id'),
      'add' => '4.5',
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'name' => [
      'title' => ts('Case Type Name'),
      'sql_type' => 'varchar(64)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => ts('Machine name for Case Type'),
      'add' => '4.5',
      'input_attrs' => [
        'maxlength' => 64,
      ],
    ],
    'title' => [
      'title' => ts('Case Type Title'),
      'sql_type' => 'varchar(64)',
      'input_type' => 'Text',
      'required' => TRUE,
      'localizable' => TRUE,
      'description' => ts('Natural language name for Case Type'),
      'add' => '4.5',
      'input_attrs' => [
        'maxlength' => 64,
      ],
    ],
    'description' => [
      'title' => ts('Case Type Description'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'localizable' => TRUE,
      'description' => ts('Description of the Case Type'),
      'add' => '4.5',
      'input_attrs' => [
        'maxlength' => 255,
      ],
    ],
    'is_active' => [
      'title' => ts('Case Type Is Active'),
      'sql_type' => 'boolean',
      'input_type' => 'CheckBox',
      'required' => TRUE,
      'description' => ts('Is this case type enabled?'),
      'add' => '4.5',
      'default' => TRUE,
      'input_attrs' => [
        'label' => ts('Enabled'),
      ],
    ],
    'is_reserved' => [
      'title' => ts('Case Type Is Reserved'),
      'sql_type' => 'boolean',
      'input_type' => 'CheckBox',
      'required' => TRUE,
      'description' => ts('Is this case type a predefined system type?'),
      'add' => '4.5',
      'default' => FALSE,
    ],
    'weight' => [
      'title' => ts('Order'),
      'sql_type' => 'int',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => ts('Ordering of the case types'),
      'add' => '4.5',
      'default' => 1,
    ],
    'definition' => [
      'title' => ts('Case Type Definition'),
      'sql_type' => 'blob',
      'input_type' => NULL,
      'description' => ts('xml definition of case type'),
      'add' => '4.5',
    ],
  ],
];
