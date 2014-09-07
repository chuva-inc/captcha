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
   * Image Captcha config storage.
   *
   * @var Config
   */
  protected $config;

  /**
   * Watchdog logger channel for captcha.
   *
   * @var LoggerChannelInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct(Config $config, LoggerChannelInterface $logger) {
    $this->config = $config;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')->get('image_captcha.settings'),
      $container->get('logger.factory')->get('captcha')
    );
  }

  /**
   * Main method that throw ImageResponse object to generate image.
   *
   * @return CaptchaImageResponse
   */
  public function image() {
    return new CaptchaImageResponse($this->config, $this->logger);
  }

}
