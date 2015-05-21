<?php

/**
 * @file
 * Contains Drupal\captcha\Tests\CaptchaCacheTestCase.
 *
 * @TODO rewrite this test. Move links tests (appearance, access,etc to unit tests), etc.
 */

namespace Drupal\captcha\Tests;

use Drupal\captcha\Entity\CaptchaPoint;
use Drupal\Core\Url;

/**
 * Tests CAPTCHA admin settings.
 *
 * @group captcha
 */
class CaptchaCacheTestCase extends CaptchaBaseWebTestCase {

  /**
   * Test the cache tags.
   */
  public function testCacheTags() {
    // Check caching without captcha as anonymous user.
    $this->drupalGet('user/login');
    $this->assertTrue($this->drupalGetHeader('x-drupal-cache') == 'MISS', 'Cache MISS');
    $this->drupalGet('user/login');
    $this->assertTrue($this->drupalGetHeader('x-drupal-cache') == 'HIT', 'Cache HIT');

    // Repeat the same after enabling captcha/Math.
    captcha_set_form_id_setting('user_login_form', 'captcha/Math');
    $this->drupalGet('user/login');
    $this->assertFalse($this->drupalGetHeader('x-drupal-cache'), 'Cache MISS');
    $this->drupalGet('user/login');
    $this->assertFalse($this->drupalGetHeader('x-drupal-cache'), 'Cache MISS');

    // Repeat the same after setting the captcha challange to captcha/Test.
    captcha_set_form_id_setting('user_login_form', 'captcha/Test');
    $this->drupalGet('user/login');
    $this->assertFalse($this->drupalGetHeader('x-drupal-cache'), 'Cache MISS');
    $this->drupalGet('user/login');
    $this->assertFalse($this->drupalGetHeader('x-drupal-cache'), 'Cache MISS');

    // Repeat the same after setting the captcha challange to captcha/Test.
    captcha_set_form_id_setting('user_login_form', 'captcha/Image');
    $this->drupalGet('user/login');
    $this->assertFalse($this->drupalGetHeader('x-drupal-cache'), 'Cache MISS');
    $this->drupalGet('user/login');
    $this->assertFalse($this->drupalGetHeader('x-drupal-cache'), 'Cache MISS');
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
