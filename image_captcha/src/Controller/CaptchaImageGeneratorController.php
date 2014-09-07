<?php

/**
 * @file
 * Contains CAPTCHA image response class.
 */

namespace Drupal\image_captcha\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\image_captcha\Response\CaptchaImageResponse;
use Drupal\Core\Config\Config;

class CaptchaImageGeneratorController implements ContainerInjectionInterface {

  /**
   * @var Config
   */
  protected $config;

  /**
   * @var LoggerChannelInterface
   */
  protected $logger;

  /**
   * @var resource
   */
  protected $image;

  /**
   * {@inheritdoc}
   */
  public function __construct(Config $config, LoggerChannelInterface $logger) {
    $this->config = $config;
    $this->logger = $logger;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')->get('image_captcha.settings'),
      $container->get('logger.factory')->get('captcha')
    );
  }

    public function image() {
    return new CaptchaImageResponse($this->config, $this->logger);
  }
}
