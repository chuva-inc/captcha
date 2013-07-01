<?php

/**
 * @file
 * Contains Drupal\captcha\Tests\CaptchaPersistenceTestCase.
 *
 * Some tricks to debug:
 * drupal_debug($data) // from devel module
 * file_put_contents('tmp.simpletest.html', $this->drupalGetContent());
 */

namespace Drupal\captcha\Tests;

use Drupal\simpletest\WebTestBase;

class CaptchaPersistenceTestCase extends CaptchaBaseWebTestCase {

  public static function getInfo() {
    return array(
      'name' => t('CAPTCHA persistence functionality'),
      'description' => t('Testing of the CAPTCHA persistence functionality.'),
      'group' => t('CAPTCHA'),
    );
  }

  /**
   * Set up the persistence and CAPTCHA settings.
   * @param int $persistence the persistence value.
   */
  private function setUpPersistence($persistence) {
    // Log in as admin
    $this->drupalLogin($this->admin_user);
    // Set persistence.
    $edit = array('captcha_persistence' => $persistence);
    $this->drupalPost(self::CAPTCHA_ADMIN_PATH, $edit, 'Save configuration');
    // Log admin out.
    $this->drupalLogout();

    // Set the Test123 CAPTCHA on user register and comment form.
    // We have to do this with the function captcha_set_form_id_setting()
    // (because the CATCHA admin form does not show the Test123 option).
    // We also have to do this after all usage of the CAPTCHA admin form
    // (because posting the CAPTCHA admin form would set the CAPTCHA to 'none').
    captcha_set_form_id_setting('user_login_form', 'captcha/Test');
    $this->drupalGet('user');
    $this->assertCaptchaPresence(TRUE);
    captcha_set_form_id_setting('user_register_form', 'captcha/Test');
    $this->drupalGet('user/register');
    $this->assertCaptchaPresence(TRUE);
  }

  protected function assertPreservedCsid($captcha_sid_initial) {
    $captcha_sid = $this->getCaptchaSidFromForm();
    $this->assertEqual($captcha_sid_initial, $captcha_sid,
      "CAPTCHA session ID should be preserved (expected: $captcha_sid_initial, found: $captcha_sid).");
  }

  protected function assertDifferentCsid($captcha_sid_initial) {
    $captcha_sid = $this->getCaptchaSidFromForm();
    $this->assertNotEqual($captcha_sid_initial, $captcha_sid,
      "CAPTCHA session ID should be different.");
  }

  function testPersistenceAlways(){
    // Set up of persistence and CAPTCHAs.
    $this->setUpPersistence(CAPTCHA_PERSISTENCE_SHOW_ALWAYS);

    // Go to login form and check if there is a CAPTCHA on the login form (look for the title).
    $this->drupalGet('user');
    $this->assertCaptchaPresence(TRUE);
    $captcha_sid_initial = $this->getCaptchaSidFromForm();

    // Try to with wrong user name and password, but correct CAPTCHA.
    $edit = array(
      'name' => 'foobar',
      'pass' => 'bazlaz',
      'captcha_response' => 'Test 123',
    );
    $this->drupalPost(NULL, $edit, t('Log in'));
    // Check that there was no error message for the CAPTCHA.
    $this->assertCaptchaResponseAccepted();

    // Name and password were wrong, we should get an updated form with a fresh CAPTCHA.
    $this->assertCaptchaPresence(TRUE);
    $this->assertPreservedCsid($captcha_sid_initial);

    // Post from again.
    $this->drupalPost(NULL, $edit, t('Log in'));
    // Check that there was no error message for the CAPTCHA.
    $this->assertCaptchaResponseAccepted();
    $this->assertPreservedCsid($captcha_sid_initial);

  }

  function testPersistencePerFormInstance(){
    // Set up of persistence and CAPTCHAs.
    $this->setUpPersistence(CAPTCHA_PERSISTENCE_SKIP_ONCE_SUCCESSFUL_PER_FORM_INSTANCE);

    // Go to login form and check if there is a CAPTCHA on the login form.
    $this->drupalGet('user');
    $this->assertCaptchaPresence(TRUE);
    $captcha_sid_initial = $this->getCaptchaSidFromForm();

    // Try to with wrong user name and password, but correct CAPTCHA.
    $edit = array(
      'name' => 'foobar',
      'pass' => 'bazlaz',
      'captcha_response' => 'Test 123',
    );
    $this->drupalPost(NULL, $edit, t('Log in'));
    // Check that there was no error message for the CAPTCHA.
    $this->assertCaptchaResponseAccepted();
    // There shouldn't be a CAPTCHA on the new form.
    $this->assertCaptchaPresence(FALSE);
    $this->assertPreservedCsid($captcha_sid_initial);

    // Start a new form instance/session
    $this->drupalGet('node');
    $this->drupalGet('user');
    $this->assertCaptchaPresence(TRUE);
    $this->assertDifferentCsid($captcha_sid_initial);

    // Check another form
    $this->drupalGet('user/register');
    $this->assertCaptchaPresence(TRUE);
    $this->assertDifferentCsid($captcha_sid_initial);

  }

  function testPersistencePerFormType(){
    // Set up of persistence and CAPTCHAs.
    $this->setUpPersistence(CAPTCHA_PERSISTENCE_SKIP_ONCE_SUCCESSFUL_PER_FORM_TYPE);

    // Go to login form and check if there is a CAPTCHA on the login form.
    $this->drupalGet('user');
    $this->assertCaptchaPresence(TRUE);
    $captcha_sid_initial = $this->getCaptchaSidFromForm();

    // Try to with wrong user name and password, but correct CAPTCHA.
    $edit = array(
      'name' => 'foobar',
      'pass' => 'bazlaz',
      'captcha_response' => 'Test 123',
    );
    $this->drupalPost(NULL, $edit, t('Log in'));
    // Check that there was no error message for the CAPTCHA.
    $this->assertCaptchaResponseAccepted();
    // There shouldn't be a CAPTCHA on the new form.
    $this->assertCaptchaPresence(FALSE);
    $this->assertPreservedCsid($captcha_sid_initial);

    // Start a new form instance/session
    $this->drupalGet('node');
    $this->drupalGet('user');
    $this->assertCaptchaPresence(FALSE);
    $this->assertDifferentCsid($captcha_sid_initial);

    // Check another form
    $this->drupalGet('user/register');
    $this->assertCaptchaPresence(TRUE);
    $this->assertDifferentCsid($captcha_sid_initial);
  }

  function testPersistenceOnlyOnce(){
    // Set up of persistence and CAPTCHAs.
    $this->setUpPersistence(CAPTCHA_PERSISTENCE_SKIP_ONCE_SUCCESSFUL);

    // Go to login form and check if there is a CAPTCHA on the login form.
    $this->drupalGet('user');
    $this->assertCaptchaPresence(TRUE);
    $captcha_sid_initial = $this->getCaptchaSidFromForm();

    // Try to with wrong user name and password, but correct CAPTCHA.
    $edit = array(
      'name' => 'foobar',
      'pass' => 'bazlaz',
      'captcha_response' => 'Test 123',
    );
    $this->drupalPost(NULL, $edit, t('Log in'));
    // Check that there was no error message for the CAPTCHA.
    $this->assertCaptchaResponseAccepted();
    // There shouldn't be a CAPTCHA on the new form.
    $this->assertCaptchaPresence(FALSE);
    $this->assertPreservedCsid($captcha_sid_initial);

    // Start a new form instance/session
    $this->drupalGet('node');
    $this->drupalGet('user');
    $this->assertCaptchaPresence(FALSE);
    $this->assertDifferentCsid($captcha_sid_initial);

    // Check another form
    $this->drupalGet('user/register');
    $this->assertCaptchaPresence(FALSE);
    $this->assertDifferentCsid($captcha_sid_initial);
  }

}