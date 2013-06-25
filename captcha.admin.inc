<?php

/**
 * @file
 * Functionality and helper functions for CAPTCHA administration.
 */

 /**
 * Return an array with the available CAPTCHA types, for use as options array
 * for a select form elements.
 *
 * @param $add_special_options if true: also add a 'none' and 'default' option
 *
 * @return an associative array mapping "$module/$type" to
 *   "$type (from module $module)" with $module the module name implementing the CAPTCHA
 *   and $type the name of the CAPTCHA type.
 */
function _captcha_available_challenge_types($add_special_options=TRUE) {
  $captcha_types = array();
  if ($add_special_options) {
    $captcha_types['none'] = t('- No challenge -');
    $captcha_types['default'] = t('Default challenge type');
  }
  // We do our own version of Drupal's module_invoke_all() here because
  // we want to build an array with custom keys and values.
  foreach (module_implements('captcha') as $module) {
    $result = call_user_func_array($module . '_captcha', array('list'));
    if (is_array($result)) {
      foreach ($result as $type) {
        $captcha_types["$module/$type"] = t('@type (from module @module)', array('@type' => $type, '@module' => $module));
      }
    }
  }
  return $captcha_types;
}

/**
 * Central handler for CAPTCHA point administration (adding, disabling, deleting)
 */
function captcha_point_admin($captcha_point_form_id=NULL, $op=NULL) {
  module_load_include('inc', 'captcha');

  // if $captcha_point_form_id and action $op given: do the action
  if ($captcha_point_form_id) {
    switch ($op) {
      case 'disable':
        return drupal_get_form('captcha_point_disable_confirm', $captcha_point_form_id, FALSE);
      case 'delete':
        return drupal_get_form('captcha_point_disable_confirm', $captcha_point_form_id, TRUE);
    }
    // return edit form for CAPTCHA point
    return drupal_get_form('captcha_point_admin_form', $captcha_point_form_id);
  }
  // return add form for CAPTCHA point
  return drupal_get_form('captcha_point_admin_form');
}

function captcha_point_admin_form($form, $form_state, $captcha_point_form_id=NULL) {
  $form = array();
  $default_captcha_type = 'none';
  if (isset($captcha_point_form_id)) {
    // use given CAPTCHA point form_id
    $form['captcha_point_form_id'] = array(
      '#type' => 'textfield',
      '#title' => t('Form ID'),
      '#description' => t('The Drupal form_id of the form to add the CAPTCHA to.'),
      '#value' => check_plain($captcha_point_form_id),
      '#disabled' => TRUE,
    );
    $captcha_point = captcha_get_form_id_setting($captcha_point_form_id);
    if ($captcha_point) {
      $default_captcha_type = "{$captcha_point->module}/{$captcha_point->captcha_type}";
    }
  }
  else {
    // textfield for CAPTCHA point form_id
    $form['captcha_point_form_id'] = array(
      '#type' => 'textfield',
      '#title' => t('Form ID'),
      '#description' => t('The Drupal form_id of the form to add the CAPTCHA to.'),
    );
  }
  // select widget for CAPTCHA type
  $form['captcha_type'] = array(
    '#type' => 'select',
    '#title' => t('Challenge type'),
    '#description' => t('The CAPTCHA type to use for this form.'),
    '#default_value' => $default_captcha_type,
    '#options' => _captcha_available_challenge_types(),
  );
  // redirect to general CAPTCHA settings page after submission
  $form['#redirect'] = 'admin/config/people/captcha';
  // submit button
  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save'),
  );
  return $form;
}


/**
 * validation function for captcha_point_admin_form
 */
function captcha_point_admin_form_validate($form, $form_state) {
  if (!preg_match('/^[a-z0-9_]+$/', $form_state['values']['captcha_point_form_id'])) {
    form_set_error('captcha_point_form_id', t('Illegal form_id'));
  }
}


/**
 * Submit function for captcha_point_admin_form.
 */
function captcha_point_admin_form_submit($form, $form_state) {
  $captcha_point_form_id = $form_state['values']['captcha_point_form_id'];
  $captcha_type = $form_state['values']['captcha_type'];
  captcha_set_form_id_setting($captcha_point_form_id, $captcha_type);
  drupal_set_message(t('Saved CAPTCHA point settings.'), 'status');
}

/**
 * Confirm dialog for disabling/deleting a CAPTCHA point
 */
function captcha_point_disable_confirm($form, &$form_state, $captcha_point_form_id, $delete) {
  $form = array();
  $form['captcha_point_form_id'] = array(
    '#type' => 'value',
    '#value' => $captcha_point_form_id,
  );
  $form['captcha_point_delete'] = array(
    '#type' => 'value',
    '#value' => $delete,
  );
  if ($delete) {
    $message = t('Are you sure you want to delete the CAPTCHA for form_id %form_id?', array('%form_id' => $captcha_point_form_id));
    $yes = t('Delete');
  }
  else {
    $message = t('Are you sure you want to disable the CAPTCHA for form_id %form_id?', array('%form_id' => $captcha_point_form_id));
    $yes = t('Disable');
  }
  return confirm_form($form, $message, 'admin/config/people/captcha/captcha', '', $yes);
}

/**
 * Submission handler of CAPTCHA point disabling/deleting confirm_form.
 */
function captcha_point_disable_confirm_submit($form, &$form_state) {
  $captcha_point_form_id = $form_state['values']['captcha_point_form_id'];
  $delete = $form_state['values']['captcha_point_delete'];
  if ($delete) {
    captcha_set_form_id_setting($captcha_point_form_id, NULL);
    drupal_set_message(t('Deleted CAPTCHA for form %form_id.', array('%form_id' => $captcha_point_form_id)));
  }
  else {
    captcha_set_form_id_setting($captcha_point_form_id, 'none');
    drupal_set_message(t('Disabled CAPTCHA for form %form_id.', array('%form_id' => $captcha_point_form_id)));
  }
  $form_state['redirect'] = 'admin/config/people/captcha/captcha';
}

/**
 * Helper function for generating an example challenge.
 */
function _captcha_generate_example_challenge($module, $type) {
  return array(
    '#type' => 'captcha',
    '#captcha_type' => $module . '/' . $type,
    '#captcha_admin_mode' => TRUE,
  );
}

/**
 * Funtion for generating a page with CAPTCHA examples.
 *
 * If the arguments $module and $challenge are not set, generate a list with
 * examples of the available CAPTCHA types.
 * If $module and $challenge are set, generate 10 examples of the concerning
 * CAPTCHA.
 */
function captcha_examples($form, $form_state, $module, $challenge) {
  module_load_include('inc', 'captcha');

  $form = array();
  if ($module && $challenge) {
    // Generate 10 example challenges.
    for ($i=0; $i<10; $i++) {
      $form["challenge_{$i}"] = _captcha_generate_example_challenge($module, $challenge);
    }
  }
  else {
    // generate a list with examples of the available CAPTCHA types
    $form['info'] = array(
      '#markup' => t('This page gives an overview of all available challenge types, generated with their current settings.'),
    );
    foreach (module_implements('captcha') as $mkey => $module) {
      $challenges = call_user_func_array($module . '_captcha', array('list'));
      if ($challenges) {
        foreach ($challenges as $ckey => $challenge) {
          $form["captcha_{$mkey}_{$ckey}"] = array(
            '#type' => 'details',
            '#title' => t('Challenge %challenge by module %module', array('%challenge' => $challenge, '%module' => $module)),
            'challenge' => _captcha_generate_example_challenge($module, $challenge),
            'more_examples' => array(
              '#markup' => l(t('10 more examples of this challenge.'), "admin/config/people/captcha/captcha/examples/$module/$challenge"),
            ),
          );
        }
      }
    }
  }
  return $form;
}
