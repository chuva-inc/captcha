<?php

/**
 * @file
 * Contains Drupal\captcha\Tests\CaptchaCacheTestCase.
 */

namespace Drupal\captcha\Tests;

use Drupal\captcha\Entity\CaptchaPoint;
use Drupal\Core\Url;

/**
 * Tests CAPTCHA caching on various pages.
 *
 * @group captcha
 */
class CaptchaCacheTestCase extends CaptchaBaseWebTestCase {

  public static $modules = ['block', 'image_captcha'];

  /**
   * Test the cache tags.
   */
  public function testCacheTags() {
    // Check caching without captcha as anonymous user.
    $this->drupalGet('user/login');
    $this->assertEqual($this->drupalGetHeader('x-drupal-cache'), 'MISS');
    $this->drupalGet('user/login');
    $this->assertEqual($this->drupalGetHeader('x-drupal-cache'), 'HIT');

    // Repeat the same after enabling captcha/Math.
    captcha_set_form_id_setting('user_login_form', 'captcha/Math');
    $this->drupalGet('user/login');
    $this->assertFalse($this->drupalGetHeader('x-drupal-cache'), 'Cache disabled');
    $sid = $this->getCaptchaSidFromForm();
    $this->drupalGet('user/login');
    $this->assertEquals($this->getCaptchaSidFromForm(), $sid);

    // Repeat the same after setting the captcha challange to captcha/Test.
    captcha_set_form_id_setting('user_login_form', 'captcha/Test');
    $this->drupalGet('user/login');
    $this->assertFalse($this->drupalGetHeader('x-drupal-cache'), 'Cache disabled');

    // Repeat the same after setting the captcha challange to captcha/Image.
    // @todo: Find out why image_captcha is broken
//    captcha_set_form_id_setting('user_login_form', 'captcha/Image');
//    $this->drupalGet('user/login');
//    $this->assertFalse($this->drupalGetHeader('x-drupal-cache'), 'Cache disabled');
  }

  /**
   * Helper function for checking CAPTCHA setting of a form.
   *
   * @param string $form_id
   *   The form_id of the form to investigate.
   * @param string $challenge_type
   *   What the challenge type should be:
   *   NULL, 'none', 'default' or something like 'captcha/Math'.
   */
  protected function assertCaptchaSetting($form_id, $challenge_type) {
    $result = captcha_get_form_id_setting(self::COMMENT_FORM_ID, TRUE);
    $this->assertEqual($result, $challenge_type,
      t('Check CAPTCHA setting for form: expected: @expected, received: @received.',
        array(
          '@expected' => var_export($challenge_type, TRUE),
          '@received' => var_export($result, TRUE),
        )),
        'CAPTCHA');
  }

}
