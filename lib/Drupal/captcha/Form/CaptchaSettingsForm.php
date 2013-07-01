<?php

/**
 * @file
 * Contains \Drupal\captcha\Form\CaptchaSettingsForm.
 */

namespace Drupal\captcha\Form;

use Drupal\Core\ControllerInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\Context\ContextInterface;
use Drupal\system\SystemConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the captcha settings form.
 */
class CaptchaSettingsForm extends SystemConfigFormBase {

  /**
   * The Drupal state storage service.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $state;

  /**
   * Constructs a \Drupal\user\SystemConfigFormBase object.
   *
   */
  public function __construct(ConfigFactory $config_factory, ContextInterface $context, KeyValueStoreInterface $state) {
    $this->configFactory = $config_factory;
    $this->configFactory->enterContext($context);
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'), $container->get('config.context.free'), $container->get('state'));
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::getFormID().
   */
  public function getFormID() {
    return 'captcha_settings';
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::buildForm().
   */
  public function buildForm(array $form, array &$form_state) {
    $config = $this->configFactory->get('captcha.settings');
    module_load_include('inc','captcha');
    module_load_include('inc','captcha','captcha.admin');

    // Use javascript for some added usability on admin form.
    drupal_add_library('captcha','captcha.js');

  // Configuration of which forms to protect, with what challenge.
  $form['captcha_form_protection'] = array(
    '#type' => 'details',
    '#title' => t('Form protection'),
    '#description' => t("Select the challenge type you want for each of the listed forms (identified by their so called <em>form_id</em>'s). You can easily add arbitrary forms with the textfield at the bottom of the table or with the help of the option <em>Add CAPTCHA administration links to forms</em> below."),
  );
  $form['captcha_form_protection']['captcha_default_challenge'] = array(
    '#type' => 'select',
    '#title' => t('Default challenge type'),
    '#description' => t('Select the default challenge type for CAPTCHAs. This can be overriden for each form if desired.'),
    '#options' => _captcha_available_challenge_types(FALSE),
    '#default_value' => $config->get('captcha_default_challenge'),
  );
  // List known form_ids.
  $form['captcha_form_protection']['captcha_form_id_overview'] = array(
    '#header' => array('form_id', t('Challenge type'), t('Operations')),
    '#type' => 'table',
  );
  $captcha_type_options = _captcha_available_challenge_types();
  $result = db_select('captcha_points', 'cp')->fields('cp')->orderBy('form_id')->execute();
  foreach ($result as $captcha_point) {
    $form['captcha_form_protection']['captcha_form_id_overview'][$captcha_point->form_id]['form_id'] = array(
      '#markup' => $captcha_point->form_id,
    );
    // Select widget for CAPTCHA type.
    if (isset($captcha_point->module) && $captcha_point->module) {
      $captcha_type = $captcha_point->module . '/' . $captcha_point->captcha_type;
    }
    elseif (isset($captcha_point->captcha_type) && ($captcha_point->captcha_type == 'default')) {
      $captcha_type = 'default';
    }
    else {
      $captcha_type = 'none';
    }
    $form['captcha_form_protection']['captcha_form_id_overview'][$captcha_point->form_id]['captcha_type'] = array(
      '#type' => 'select',
      '#default_value' => $captcha_type,
      '#options' => $captcha_type_options,
    );
    // Additional operations.
    $form['captcha_form_protection']['captcha_form_id_overview'][$captcha_point->form_id]['operations'] = array(
      '#markup' => implode(", ", array(
        l(t('delete'), "admin/config/people/captcha/captcha/captcha_point/{$captcha_point->form_id}/delete"),
      ))
    );
  }

  // Form items for new form_id.
  $form['captcha_form_protection']['captcha_form_id_overview']['captcha_new_captcha_point'] = array();
  // Textfield for form_id.
  $form['captcha_form_protection']['captcha_form_id_overview']['captcha_new_captcha_point']['form_id'] = array(
    '#type' => 'textfield',
    '#size' => 16,
  );
  // Select widget for CAPTCHA type.
  $form['captcha_form_protection']['captcha_form_id_overview']['captcha_new_captcha_point']['captcha_type'] = array(
    '#type' => 'select',
    '#default_value' => 'none',
    '#options' => $captcha_type_options,
  );
  // Empty Cell
  $form['captcha_form_protection']['captcha_form_id_overview']['captcha_new_captcha_point']['operations'] = array();

  // Field for the CAPTCHA administration mode.
  $form['captcha_form_protection']['captcha_administration_mode'] = array(
    '#type' => 'checkbox',
    '#title' => t('Add CAPTCHA administration links to forms'),
    '#default_value' => $config->get('captcha_administration_mode'),
    '#description' => t('This option makes it easy to manage CAPTCHA settings on forms. When enabled, users with the <em>administer CAPTCHA settings</em> permission will see a fieldset with CAPTCHA administration links on all forms, except on administrative pages.'),
  );
  // Field for the CAPTCHAs on admin pages.
  $form['captcha_form_protection']['captcha_allow_on_admin_pages'] = array(
    '#type' => 'checkbox',
    '#title' => t('Allow CAPTCHAs and CAPTCHA administration links on administrative pages'),
    '#default_value' => $config->get('captcha_allow_on_admin_pages'),
    '#description' => t("This option makes it possible to add CAPTCHAs to forms on administrative pages. CAPTCHAs are disabled by default on administrative pages (which shouldn't be accessible to untrusted users normally) to avoid the related overhead. In some situations, e.g. in the case of demo sites, it can be usefull to allow CAPTCHAs on administrative pages."),
  );

  // Button for clearing the CAPTCHA placement cache.
  // Based on Drupal core's "Clear all caches" (performance settings page).
  $form['captcha_form_protection']['captcha_placement_caching'] = array(
    '#type' => 'item',
    '#title' => t('CAPTCHA placement caching'),
    '#description' => t('For efficiency, the positions of the CAPTCHA elements in each of the configured forms are cached. Most of the time, the structure of a form does not change and it would be a waste to recalculate the positions every time. Occasionally however, the form structure can change (e.g. during site building) and clearing the CAPTCHA placement cache can be required to fix the CAPTCHA placement.'),
  );
  $form['captcha_form_protection']['captcha_placement_caching']['captcha_placement_cache_clear'] = array(
    '#type' => 'submit',
    '#value' => t('Clear the CAPTCHA placement cache'),
    '#submit' => array(array($this, 'captcha_clear_captcha_placement_cache_submit')),
  );

  // Configuration option for adding a CAPTCHA description.
  $form['captcha_add_captcha_description'] = array(
    '#type' => 'checkbox',
    '#title' => t('Add a description to the CAPTCHA'),
    '#description' => t('Add a configurable description to explain the purpose of the CAPTCHA to the visitor.'),
    '#default_value' => $config->get('captcha_add_captcha_description'),
  );
  // Textfield(s) for the CAPTCHA description.
  if (module_exists('locale')) {
    $langs = locale_language_list();
    $form['captcha_descriptions'] = array(
      '#type' => 'details',
      '#title' => t('CAPTCHA description'),
      '#description' => t('Configurable description of the CAPTCHA. An empty entry will reset the description to default.'),
      '#attributes' => array('id' => 'edit-captcha-description-wrapper'),
    );
    foreach ($langs as $lang_code => $lang_name) {
      $form['captcha_descriptions']["captcha_description_$lang_code"] = array(
        '#type' => 'textfield',
        '#title' => t('For language %lang_name (code %lang_code)', array('%lang_name' => $lang_name, '%lang_code' => $lang_code)),
        '#default_value' => _captcha_get_description($lang_code),
        '#maxlength' => 256,
      );
    }
  }
  else {
    $form['captcha_description'] = array(
      '#type' => 'textfield',
      '#title' => t('Challenge description'),
      '#description' => t('Configurable description of the CAPTCHA. An empty entry will reset the description to default.'),
      '#default_value' => _captcha_get_description(),
      '#maxlength' => 256,
      '#attributes' => array('id' => 'edit-captcha-description-wrapper'),
    );
  }

  // Option for case sensitive/insensitive validation of the responses.
  $form['captcha_default_validation'] = array(
    '#type' => 'radios',
    '#title' => t('Default CAPTCHA validation'),
    '#description' => t('Define how the response should be processed by default. Note that the modules that provide the actual challenges can override or ignore this.'),
    '#options' => array(
      CAPTCHA_DEFAULT_VALIDATION_CASE_SENSITIVE => t('Case sensitive validation: the response has to exactly match the solution.'),
      CAPTCHA_DEFAULT_VALIDATION_CASE_INSENSITIVE => t('Case insensitive validation: lowercase/uppercase errors are ignored.'),
    ),
    '#default_value' => $config->get('captcha_default_validation'),
  );

  // Field for CAPTCHA persistence.
  // TODO for D7: Rethink/simplify the explanation and UI strings.
  $form['captcha_persistence'] = array(
    '#type' => 'radios',
    '#title' => t('Persistence'),
    '#default_value' => $config->get('captcha_persistence'),
    '#options' => array(
      CAPTCHA_PERSISTENCE_SHOW_ALWAYS =>
        t('Always add a challenge.'),
      CAPTCHA_PERSISTENCE_SKIP_ONCE_SUCCESSFUL_PER_FORM_INSTANCE =>
        t('Omit challenges in a multi-step/preview workflow once the user successfully responds to a challenge.'),
      CAPTCHA_PERSISTENCE_SKIP_ONCE_SUCCESSFUL_PER_FORM_TYPE =>
        t('Omit challenges on a form type once the user successfully responds to a challenge on a form of that type.'),
      CAPTCHA_PERSISTENCE_SKIP_ONCE_SUCCESSFUL =>
        t('Omit challenges on all forms once the user successfully responds to any challenge on the site.'),
    ),
    '#description' => t('Define if challenges should be omitted during the rest of a session once the user successfully responds to a challenge.'),
  );

  // Enable wrong response counter.
  $form['captcha_enable_stats'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enable statistics'),
    '#description' => t('Keep CAPTCHA related counters in the <a href="!statusreport">status report</a>. Note that this comes with a performance penalty as updating the counters results in clearing the variable cache.', array('!statusreport' => url('admin/reports/status'))),
    '#default_value' => $config->get('captcha_enable_stats'),
  );

  // Option for logging wrong responses.
  $form['captcha_log_wrong_responses'] = array(
    '#type' => 'checkbox',
    '#title' => t('Log wrong responses'),
    '#description' => t('Report information about wrong responses to the <a href="!dblog">log</a>.', array('!dblog' => url('admin/reports/dblog'))),
    '#default_value' => $config->get('captcha_log_wrong_responses'),
  );

  // Submit button.
  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save configuration'),
  );

    return parent::buildForm($form, $form_state);
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface:validateForm()
   */
  public function validateForm(array &$form, array &$form_state) {
    $form_id = $form_state['values']['captcha_form_id_overview']['captcha_new_captcha_point']['form_id'];
    if (!preg_match('/^[a-z0-9_]*$/', $form_id)) {
      form_set_error('captcha_form_id_overview][captcha_new_captcha_point][form_id', t('Illegal form_id'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface:submitForm()
   *
   * @see book_remove_button_submit()
   */
  public function submitForm(array &$form, array &$form_state) {
    $config = $this->configFactory->get('captcha.settings');
    $config->set('captcha_administration_mode', $form_state['values']['captcha_administration_mode']);
    $config->set('captcha_allow_on_admin_pages', $form_state['values']['captcha_allow_on_admin_pages']);
    $config->set('captcha_default_challenge', $form_state['values']['captcha_default_challenge']);

    // Process CAPTCHA points
    if (isset($form_state['values']['captcha_form_id_overview']['captcha_captcha_points'])) {
      foreach ($form_state['values']['captcha_form_id_overview']['captcha_captcha_points'] as $captcha_point_form_id => $data) {
        captcha_set_form_id_setting($captcha_point_form_id, $data['captcha_type']);
      }
    }

    // Add new CAPTCHA point?
    $captcha_point_form_id = $form_state['values']['captcha_form_id_overview']['captcha_new_captcha_point']['form_id'];
    if (!empty($captcha_point_form_id)) {
      $captcha_type = $form_state['values']['captcha_form_id_overview']['captcha_new_captcha_point']['captcha_type'];
      captcha_set_form_id_setting($captcha_point_form_id, $captcha_type);
      drupal_set_message(t('Added CAPTCHA point.'), 'status');
    }

    // CAPTCHA description stuff.
    $config->set('captcha_add_captcha_description', $form_state['values']['captcha_add_captcha_description']);
    // Save (or reset) the CAPTCHA descriptions.
    if (module_exists('locale')) {
      $langs = locale_language_list();
      foreach ($langs as $lang_code => $lang_name) {
        $description = $form_state['values']["captcha_description_$lang_code"];
        if ($description) {
          $config->set("captcha_description_$lang_code", $description);
        }
        else {
          variable_del("captcha_description_$lang_code");
          drupal_set_message(t('Reset of CAPTCHA description for language %language.', array('%language' => $lang_name)), 'status');
        }
      }
    }
    else {
      $description = $form_state['values']['captcha_description'];
      if ($description) {
        $config->set('captcha_description', $description);
      }
      else {
        $config->set('captcha_description','');
        drupal_set_message(t('Reset of CAPTCHA description.'), 'status');
      }
    }

    $config->set('captcha_default_validation', $form_state['values']['captcha_default_validation']);
    $config->set('captcha_persistence', $form_state['values']['captcha_persistence']);
    $config->set('captcha_enable_stats', $form_state['values']['captcha_enable_stats']);
    $config->set('captcha_log_wrong_responses', $form_state['values']['captcha_log_wrong_responses']);
    $config->save();
    drupal_set_message(t('The CAPTCHA settings have been saved.'), 'status');

    parent::SubmitForm($form, $form_state);
  }

 /**
  * Submit callback; clear CAPTCHA placement cache.
  */
  public function captcha_clear_captcha_placement_cache_submit($form, $form_state) {
    $this->state->delete('captcha_placement_map_cache');
    drupal_set_message(t('Cleared the CAPTCHA placement cache.'));
  }
}