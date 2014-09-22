<?php

/**
 * @file
 * Contains \Drupal\captcha\Form\CaptchaExamplesForm.
 */

namespace Drupal\captcha\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the captcha settings form.
 */
class CaptchaExamplesForm extends ConfigFormBase {
  /**
   * The Drupal state storage service.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'));
  }

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
}
