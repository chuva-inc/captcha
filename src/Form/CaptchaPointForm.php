<?php

/**
 * @file
 * Contains \Drupal\captcha\Form\CaptchaPointForm.
 */

namespace Drupal\captcha\Form;

use Drupal\captcha\Entity\CaptchaPoint;
use Drupal\Core\Entity\EntityForm;
use Drupal\captcha\CaptchaPointInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class CaptchaPointForm.
 *
 * @package Drupal\captcha\Form
 */
class CaptchaPointForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    module_load_include('inc', 'captcha', 'captcha.admin');

    /* @var CaptchaPointInterface $captchaPoint */
    $captcha_point = $this->entity;

    // Support to set a default form_id through a query argument.
    $request = \Drupal::request();
    if ($captcha_point->isNew() && !$captcha_point->id() && $request->query->has('form_id')) {
      $captcha_point->set('formId', $request->query->get('form_id'));
      $captcha_point->set('label', $request->query->get('form_id'));
    }

    $form['label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Form ID'),
      '#default_value' => $captcha_point->label(),
    );

    $form['formId'] = array(
      '#type' => 'machine_name',
      '#default_value' => $captcha_point->id(),
      '#machine_name' => array(
        'exists' => 'captcha_point_load',
      ),
      '#disable' => !$captcha_point->isNew(),
    );

    // Select widget for CAPTCHA type.
    $form['captchaType'] = array(
      '#type' => 'select',
      '#title' => t('Challenge type'),
      '#description' => t('The CAPTCHA type to use for this form.'),
      '#default_value' => ($captcha_point->getCaptchaType() ?: $this->config('captcha.settings')->get('default_challenge')),
      '#options' => _captcha_available_challenge_types(),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /* @var CaptchaPoint $captcha_point */
    $captcha_point = $this->entity;
    $status = $captcha_point->save();

    if ($status == SAVED_NEW) {
      drupal_set_message($this->t('Captcha Point for %label form was created.', array(
        '%label' => $captcha_point->getFormId(),
      )));
    }
    else {
      drupal_set_message($this->t('Captcha Point for %label form was updated.', array(
        '%label' => $captcha_point->getFormId(),
      )));
    }
    $form_state->setRedirect('captcha_point.list');
  }

}
