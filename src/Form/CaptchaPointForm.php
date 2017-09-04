<?php

namespace Drupal\captcha\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Entity Form to edit CAPTCHA points.
 */
class CaptchaPointForm extends EntityForm {

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * CaptchaPointForm constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    module_load_include('inc', 'captcha', 'captcha.admin');

    /* @var CaptchaPointInterface $captchaPoint */
    $captcha_point = $this->entity;

    // Support to set a default form_id through a query argument.
    $request = $this->requestStack->getCurrentRequest();
    if ($captcha_point->isNew() && !$captcha_point->id() && $request->query->has('form_id')) {
      $captcha_point->set('formId', $request->query->get('form_id'));
      $captcha_point->set('label', $request->query->get('form_id'));
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Form ID'),
      '#default_value' => $captcha_point->label(),
      '#required' => TRUE,
    ];

    $form['formId'] = [
      '#type' => 'machine_name',
      '#default_value' => $captcha_point->id(),
      '#machine_name' => [
        'exists' => 'captcha_point_load',
      ],
      '#disable' => !$captcha_point->isNew(),
      '#required' => TRUE,
    ];

    // Select widget for CAPTCHA type.
    $form['captchaType'] = [
      '#type' => 'select',
      '#title' => $this->t('Challenge type'),
      '#description' => $this->t('The CAPTCHA type to use for this form.'),
      '#default_value' => ($captcha_point->getCaptchaType() ?: $this->config('captcha.settings')
        ->get('default_challenge')),
      '#options' => _captcha_available_challenge_types(),
    ];

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
      drupal_set_message($this->t('Captcha Point for %form_id form was created.', [
        '%form_id' => $captcha_point->getFormId(),
      ]));
    }
    else {
      drupal_set_message($this->t('Captcha Point for %form_id form was updated.', [
        '%form_id' => $captcha_point->getFormId(),
      ]));
    }
    $form_state->setRedirect('captcha_point.list');
  }

}
