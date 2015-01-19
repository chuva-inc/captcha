<?php

/**
 * @file
 * Contains \Drupal\captcha\Form\CaptchaExamplesForm.
 */

namespace Drupal\captcha\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Displays the captcha settings form.
 */
class CaptchaExamplesForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'captcha_examples';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    module_load_include('inc', 'captcha', 'captcha.admin');

    $module = $this->getRequest()->get('module');
    $challenge = $this->getRequest()->get('challenge');

    $form = array();
    if ($module && $challenge) {
      // Generate 10 example challenges.
      for ($i = 0; $i < 10; $i++) {
        $form["challenge_{$i}"] = _captcha_generate_example_challenge($module, $challenge);
      }
    }
    else {
      // Generate a list with examples of the available CAPTCHA types.
      $form['info'] = array(
        '#markup' => t('This page gives an overview of all available challenge types, generated with their current settings.'),
      );
      foreach (\Drupal::moduleHandler()->getImplementations('captcha') as $mkey => $module) {
        $challenges = call_user_func_array($module . '_captcha', array('list'));

        if ($challenges) {
          foreach ($challenges as $ckey => $challenge) {
            $form["captcha_{$mkey}_{$ckey}"] = array(
              '#type' => 'details',
              '#title' => t('Challenge %challenge by module %module', array('%challenge' => $challenge, '%module' => $module)),
              'challenge' => _captcha_generate_example_challenge($module, $challenge),
              'more_examples' => array(
                '#markup' => l(t('10 more examples of this challenge.'), "admin/config/people/captcha/examples/$module/$challenge"),
              ),
            );
          }
        }
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
