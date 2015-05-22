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

  function setUp() {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser(array('administer blocks'));
    $this->drupalLogin($this->adminUser);
    $this->drupalPlaceBlock('user_login_block', array('id' => 'login'));
    $this->drupalLogout($this->adminUser);
  }

  /**
   * Test the cache tags.
   */
  public function testCacheTags() {
    // Check caching without captcha as anonymous user.
    $this->drupalGet('');
    $this->assertEqual($this->drupalGetHeader('x-drupal-cache'), 'MISS');
    $this->drupalGet('');
    $this->assertEqual($this->drupalGetHeader('x-drupal-cache'), 'HIT');

    // Repeat the same after enabling captcha/Math.
    captcha_set_form_id_setting('user_login_form', 'captcha/Math');
    $this->drupalGet('');
    $sid = $this->getCaptchaSidFromForm();
    $math_challenge = (string) $this->xpath('//span[@class="field-prefix"]')[0];
    $this->assertFalse($this->drupalGetHeader('x-drupal-cache'), 'Cache is disabled');
    $this->drupalGet('');
    $this->assertNotEqual($sid, $this->getCaptchaSidFromForm());
    $this->assertNotEqual($math_challenge, (string) $this->xpath('//span[@class="field-prefix"]')[0]);

    // Repeat the same after setting the captcha challange to captcha/Test.
    captcha_set_form_id_setting('user_login_form', 'captcha/Test');
    $this->drupalGet('');
    $sid = $this->getCaptchaSidFromForm();
    $this->assertFalse($this->drupalGetHeader('x-drupal-cache'), 'Cache is disabled');
    $this->drupalGet('');
    $this->assertNotEqual($sid, $this->getCaptchaSidFromForm());

    // Repeat the same after setting the captcha challange to captcha/Image.
    captcha_set_form_id_setting('user_login_form', 'image_captcha/Image');
    $this->drupalGet('');
    $image_path = (string) $this->xpath('//img[2]//@src')[0];
    $this->assertFalse($this->drupalGetHeader('x-drupal-cache'), 'Cache disabled');
    // Check that we get a new image when vising the page again.
    $this->drupalGet('');
    $this->assertNotEqual($image_path, (string) $this->xpath('//img[2]//@src')[0]);
    // Check image caching.
    $this->drupalGet($image_path);
    $this->assertResponse(200);
    // Do another request for ImageGenerator.
    // If caching is enabled, this will break.
    $this->drupalGet($image_path);
    $this->assertResponse(200);
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
