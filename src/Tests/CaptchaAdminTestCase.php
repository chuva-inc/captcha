<?php

/**
 * @file
 * Contains Drupal\captcha\Tests\CaptchaAdminTestCase.
 *
 * @TODO rewrite this test. Move links tests (appearance, access,etc to unit tests), etc.
 */

namespace Drupal\captcha\Tests;

use Drupal\captcha\Entity\CaptchaPoint;
use stdClass;

/**
 * Tests CAPTCHA admin settings.
 *
 * @group captcha
 */
class CaptchaAdminTestCase extends CaptchaBaseWebTestCase {

  /**
   * Test access to the admin pages.
   */
  public function testAdminAccess() {
    $this->drupalLogin($this->normalUser);
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH);
    // @TODO do we need this ?
    // file_put_contents('tmp.simpletest.html', $this->drupalGetContent());
    $this->assertText(t('Access denied'), 'Normal users should not be able to access the CAPTCHA admin pages', 'CAPTCHA');

    $this->drupalLogin($this->adminUser);
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH);
    $this->assertNoText(t('Access denied'), 'Admin users should be able to access the CAPTCHA admin pages', 'CAPTCHA');
  }

  /**
   * Test the CAPTCHA point setting getter/setter.
   */
  public function testCaptchaPointSettingGetterAndSetter() {
    $comment_form_id = self::COMMENT_FORM_ID;
    captcha_set_form_id_setting($comment_form_id, 'none');
    /* @var CaptchaPoint $result */
    $result = captcha_get_form_id_setting($comment_form_id);
    $this->assertNotNull($result, 'Setting and getting CAPTCHA point: none', 'CAPTCHA');
    // $this->assertNull($result->module, 'Setting and getting
    // CAPTCHA point: none', 'CAPTCHA');
    $this->assertNull($result->getCaptchaType(), 'Setting and getting CAPTCHA point: none', 'CAPTCHA');
    $result = captcha_get_form_id_setting($comment_form_id, TRUE);
    $this->assertEqual($result, 'none', 'Setting and symbolic getting CAPTCHA point: "none"', 'CAPTCHA');

    // Set to 'default'
    captcha_set_form_id_setting($comment_form_id, 'default');
    $this->config('captcha.settings')->set('default_challenge', 'foo/bar')->save();
    $result = captcha_get_form_id_setting($comment_form_id);
    $this->assertNotNull($result, 'Setting and getting CAPTCHA point: default', 'CAPTCHA');
    // $this->assertEqual($result->module, 'foo', 'Setting and getting
    // CAPTCHA point: default', 'CAPTCHA');
    $this->assertEqual($result->getCaptchaType(), 'bar', 'Setting and getting CAPTCHA point: default', 'CAPTCHA');
    $result = captcha_get_form_id_setting($comment_form_id, TRUE);
    $this->assertEqual($result, 'default', 'Setting and symbolic getting CAPTCHA point: "default"', 'CAPTCHA');

    // Set to 'baz/boo'.
    captcha_set_form_id_setting($comment_form_id, 'baz/boo');
    $result = captcha_get_form_id_setting($comment_form_id);
    $this->assertNotNull($result, 'Setting and getting CAPTCHA point: baz/boo', 'CAPTCHA');
    $this->assertEqual($result->getCaptchaType(), 'boo', 'Setting and getting CAPTCHA point: baz/boo', 'CAPTCHA');
    $result = captcha_get_form_id_setting($comment_form_id, TRUE);
    $this->assertEqual($result, 'baz/boo', 'Setting and symbolic getting CAPTCHA point: "baz/boo"', 'CAPTCHA');

    // Set to NULL (which should delete the CAPTCHA point setting entry).
    captcha_set_form_id_setting($comment_form_id, NULL);
    $result = captcha_get_form_id_setting($comment_form_id);
    $this->assertNull($result, 'Setting and getting CAPTCHA point: NULL', 'CAPTCHA');
    $result = captcha_get_form_id_setting($comment_form_id, TRUE);
    $this->assertNull($result, 'Setting and symbolic getting CAPTCHA point: NULL', 'CAPTCHA');

    // Set with object.
    $captcha_type = new stdClass();
    $captcha_type->module = 'baba';
    $captcha_type->captcha_type = 'fofo';
    captcha_set_form_id_setting($comment_form_id, $captcha_type);

    $result = captcha_get_form_id_setting($comment_form_id);
    $this->assertNotNull($result, 'Setting and getting CAPTCHA point: baba/fofo', 'CAPTCHA');
    // $this->assertEqual($result->module, 'baba', 'Setting and getting
    // CAPTCHA point: baba/fofo', 'CAPTCHA');
    $this->assertEqual($result->getCaptchaType(), 'fofo', 'Setting and getting CAPTCHA point: baba/fofo', 'CAPTCHA');
    $result = captcha_get_form_id_setting($comment_form_id, TRUE);
    $this->assertEqual($result, 'baba/fofo', 'Setting and symbolic getting CAPTCHA point: "baba/fofo"', 'CAPTCHA');
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

  /**
   * Testing of the CAPTCHA administration links.
   */
  public function testCaptchaAdminLinks() {
    $this->drupalLogin($this->adminUser);

    // Enable CAPTCHA administration links.
    $edit = array(
      'administration_mode' => TRUE,
    );

    $this->drupalPostForm(self::CAPTCHA_ADMIN_PATH, $edit, t('Save configuration'));

    // Create a node with comments enabled.
    $node = $this->drupalCreateNode();

    // Go to node page.
    $this->drupalGet('node/' . $node->id());

    // Click the add new comment link.
    $this->clickLink(t('Add new comment'));
    $add_comment_url = $this->getUrl();

    // Remove fragment part from comment URL to avoid
    // problems with later asserts.
    $add_comment_url = strtok($add_comment_url, "#");

    // Click the CAPTCHA admin link to enable a challenge.
    $this->clickLink(t('Place a CAPTCHA here for untrusted users.'));

    // Enable Math CAPTCHA.
    $edit = array('captchaType' => 'captcha/Math');
    $this->drupalPostForm($this->getUrl(), $edit, t('Save'));

    // Check if returned to original comment form.
    $this->assertUrl($add_comment_url, array(),
      'After setting CAPTCHA with CAPTCHA admin links: should return to original form.', 'CAPTCHA');

    // Check if CAPTCHA was successfully enabled
    // (on CAPTCHA admin links fieldset).
    $this->assertText(t('CAPTCHA: challenge "@type" enabled', array('@type' => 'Math')),
      'Enable a challenge through the CAPTCHA admin links', 'CAPTCHA');

    // Check if CAPTCHA was successfully enabled (through API).
    $this->assertCaptchaSetting(self::COMMENT_FORM_ID, 'captcha/Math');

    // Edit challenge type through CAPTCHA admin links.
    $this->clickLink(t('change'));

    // Enable Math CAPTCHA.
    $edit = array('captchaType' => 'default');
    $this->drupalPostForm($this->getUrl(), $edit, t('Save'));

    // Check if returned to original comment form.
    $this->assertEqual($add_comment_url, $this->getUrl(),
      'After editing challenge type CAPTCHA admin links: should return to original form.', 'CAPTCHA');

    // Check if CAPTCHA was successfully changed
    // (on CAPTCHA admin links fieldset).
    // This is actually the same as the previous setting because
    // the captcha/Math is the default for the default challenge.
    // TODO Make sure the edit is a real change.
    $this->assertText(t('CAPTCHA: challenge "@type" enabled', array('@type' => 'Math')),
      'Enable a challenge through the CAPTCHA admin links', 'CAPTCHA');
    // Check if CAPTCHA was successfully edited (through API).
    $this->assertCaptchaSetting(self::COMMENT_FORM_ID, 'default');

    // Disable challenge through CAPTCHA admin links.
    $this->clickLink(t('disable'));
    $this->drupalPostForm($this->getUrl(), array(), 'Disable');

    // Check if returned to original comment form.
    $this->assertEqual($add_comment_url, $this->getUrl(),
      'After disabling challenge with CAPTCHA admin links: should return to original form.', 'CAPTCHA');

    // Check if CAPTCHA was successfully disabled
    // (on CAPTCHA admin links fieldset).
    $this->assertText(t('CAPTCHA: no challenge enabled'),
      'Disable challenge through the CAPTCHA admin links', 'CAPTCHA');

    // Check if CAPTCHA was successfully disabled (through API).
    $this->assertCaptchaSetting(self::COMMENT_FORM_ID, 'none');
  }

  /**
   * Test untrusted user posting.
   */
  public function testUntrustedUserPosting() {
    // Set CAPTCHA on comment form.
    captcha_set_form_id_setting(self::COMMENT_FORM_ID, 'captcha/Math');

    // Create a node with comments enabled.
    $node = $this->drupalCreateNode();

    // Log in as normal (untrusted) user.
    $this->drupalLogin($this->normalUser);

    // Go to node page and click the "add comment" link.
    $this->drupalGet('node/' . $node->id());
    $this->clickLink(t('Add new comment'));
    $add_comment_url = $this->getUrl();

    // Check if CAPTCHA is visible on form.
    $this->assertCaptchaPresence(TRUE);
    // Try to post a comment with wrong answer.
    $edit = $this->getCommentFormValues();
    $edit['captcha_response'] = 'xx';
    $this->drupalPostForm($add_comment_url, $edit, t('Preview'));
    $this->assertText(self::CAPTCHA_WRONG_RESPONSE_ERROR_MESSAGE,
      'wrong CAPTCHA should block form submission.', 'CAPTCHA');
  }

  /**
   * Test XSS vulnerability on CAPTCHA description.
   */
  public function testXssOnCaptchaDescription() {
    // Set CAPTCHA on user register form.
    captcha_set_form_id_setting('user_register', 'captcha/Math');

    // Put Javascript snippet in CAPTCHA description.
    $this->drupalLogin($this->adminUser);
    $xss = '<script type="text/javascript">alert("xss")</script>';
    $edit = array('description' => $xss);
    $this->drupalPostForm(self::CAPTCHA_ADMIN_PATH, $edit, 'Save configuration');

    // Visit user register form and check if Javascript snippet is there.
    $this->drupalLogout();
    $this->drupalGet('user/register');
    $this->assertNoRaw($xss, 'Javascript should not be allowed in CAPTCHA description.', 'CAPTCHA');
  }

  /**
   * Test the CAPTCHA placement clearing.
   */
  public function testCaptchaPlacementCacheClearing() {
    // Set CAPTCHA on user register form.
    captcha_set_form_id_setting('user_register_form', 'captcha/Math');
    // Visit user register form to fill the CAPTCHA placement cache.
    $this->drupalGet('user/register');
    // Check if there is CAPTCHA placement cache.
    $placement_map = $this->container->get('cache.default')->get('captcha_placement_map_cache');
    $this->assertNotNull($placement_map, 'CAPTCHA placement cache should be set.');
    // Clear the cache.
    $this->drupalLogin($this->adminUser);
    $this->drupalPostForm(self::CAPTCHA_ADMIN_PATH, array(), t('Clear the CAPTCHA placement cache'));
    // Check that the placement cache is unset.
    $placement_map = $this->container->get('cache.default')->get('captcha_placement_map_cache');
    $this->assertFalse($placement_map, 'CAPTCHA placement cache should be unset after cache clear.');
  }

  /**
   * Helper function to get CAPTCHA point setting straight from the database.
   *
   * @param string $form_id
   *   Form machine ID.
   *
   * @return stdClass object
   *    Object with mysql query result.
   */
  protected function getCaptchaPointSettingFromDatabase($form_id) {
    $ids = \Drupal::entityQuery('captcha_point')
      ->condition('formId', $form_id)
      ->execute();
    return $ids ? CaptchaPoint::load(reset($ids)) : NULL;
  }

  /**
   * Method for testing the CAPTCHA point administration.
   */
  public function testCaptchaPointAdministration() {
    // Generate CAPTCHA point data:
    // Drupal form ID should consist of lowercase alphanumerics and underscore).
    $captcha_point_form_id = 'form_' . strtolower($this->randomMachineName(32));
    // The Math CAPTCHA by the CAPTCHA module is always available,
    // so let's use it.
    $captcha_point_module = 'captcha';
    $captcha_point_type = 'Math';

    // Log in as admin.
    $this->drupalLogin($this->adminUser);

    // Set CAPTCHA point through admin/user/captcha/captcha/captcha_point.
    $form_values = array(
      'formId' => $captcha_point_form_id,
      'captchaType' => $captcha_point_module . '/' . $captcha_point_type,
    );
    $this->drupalPostForm(self::CAPTCHA_ADMIN_PATH . '/captcha/captcha_point/add', $form_values, t('Save'));
    $this->assertText(t('Saved CAPTCHA point settings.'),
      'Saving of CAPTCHA point settings');

    // Check in database.
    $result = $this->getCaptchaPointSettingFromDatabase($captcha_point_form_id);
    $this->assertEqual($result->module, $captcha_point_module,
      'Enabled CAPTCHA point should have module set');
    $this->assertEqual($result->captcha_type, $captcha_point_type,
      'Enabled CAPTCHA point should have type set');

    // Disable CAPTCHA point again.
    $this->drupalPostForm(self::CAPTCHA_ADMIN_PATH . '/captcha/captcha_point/' . $captcha_point_form_id . '/disable', array(), t('Disable'));
    $this->assertRaw(t('Disabled CAPTCHA for form %form_id.', array('%form_id' => $captcha_point_form_id)), 'Disabling of CAPTCHA point');

    // Check in database.
    $result = $this->getCaptchaPointSettingFromDatabase($captcha_point_form_id);
    $this->assertNull($result->module,
      'Disabled CAPTCHA point should have NULL as module');
    $this->assertNull($result->captcha_type,
      'Disabled CAPTCHA point should have NULL as type');

    // Set CAPTCHA point via admin/user/captcha/captcha/captcha_point/$form_id.
    $form_values = array(
      'captchaType' => $captcha_point_module . '/' . $captcha_point_type,
    );
    $this->drupalPostForm(self::CAPTCHA_ADMIN_PATH . '/captcha/captcha_point/' . $captcha_point_form_id, $form_values, t('Save'));
    $this->assertText(t('Saved CAPTCHA point settings.'),
      'Saving of CAPTCHA point settings');

    // Check in database.
    $result = $this->getCaptchaPointSettingFromDatabase($captcha_point_form_id);
    $this->assertEqual($result->module, $captcha_point_module,
      'Enabled CAPTCHA point should have module set');
    $this->assertEqual($result->captcha_type, $captcha_point_type,
      'Enabled CAPTCHA point should have type set');

    // Delete CAPTCHA point.
    $this->drupalPostForm(self::CAPTCHA_ADMIN_PATH . '/captcha/captcha_point/' . $captcha_point_form_id . '/delete', array(), t('Delete'));
    $this->assertRaw(t('Deleted CAPTCHA for form %form_id.', array('%form_id' => $captcha_point_form_id)),
      'Deleting of CAPTCHA point');

    $result = $this->getCaptchaPointSettingFromDatabase($captcha_point_form_id);
    $this->assertFalse($result, 'Deleted CAPTCHA point should be in database');
  }

  /**
   * Method for testing the CAPTCHA point administration.
   */
  public function testCaptchaPointAdministrationByNonAdmin() {
    // First add a CAPTCHA point (as admin).
    $this->drupalLogin($this->adminUser);
    $captcha_point_form_id = 'form_' . strtolower($this->randomMachineName(32));
    $captcha_point_module = 'captcha';
    $captcha_point_type = 'Math';
    $form_values = array(
      'formId' => $captcha_point_form_id,
      'captchaType' => $captcha_point_module . '/' . $captcha_point_type,
    );
    $this->drupalPostForm(self::CAPTCHA_ADMIN_PATH . '/captcha/captcha_point/', $form_values, 'Save');
    $this->assertText(t('Saved CAPTCHA point settings.'),
      'Saving of CAPTCHA point settings');

    // Switch from admin to non-admin.
    $this->drupalLogin($this->normalUser);

    // Try to set CAPTCHA point
    // through admin/user/captcha/captcha/captcha_point.
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH . '/captcha/captcha_point');
    $this->assertText(t('You are not authorized to access this page.'),
      'Non admin should not be able to set a CAPTCHA point');

    // Try to set CAPTCHA point through
    // admin/user/captcha/captcha/captcha_point/$form_id.
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH . '/captcha/captcha_point/' . 'form_' . strtolower($this->randomMachineName(32)));
    $this->assertText(t('You are not authorized to access this page.'),
      'Non admin should not be able to set a CAPTCHA point');

    // Try to disable the CAPTCHA point.
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH . '/captcha/captcha_point/' . $captcha_point_form_id . '/disable');
    $this->assertText(t('You are not authorized to access this page.'),
      'Non admin should not be able to disable a CAPTCHA point');

    // Try to delete the CAPTCHA point.
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH . '/captcha/captcha_point/' . $captcha_point_form_id . '/delete');
    $this->assertText(t('You are not authorized to access this page.'),
      'Non admin should not be able to delete a CAPTCHA point');

    // Switch from nonadmin to admin again.
    $this->drupalLogin($this->adminUser);

    // Check if original CAPTCHA point still exists in database.
    $result = $this->getCaptchaPointSettingFromDatabase($captcha_point_form_id);
    $this->assertEqual($result->module, $captcha_point_module,
      'Enabled CAPTCHA point should still have module set');
    $this->assertEqual($result->captcha_type, $captcha_point_type,
      'Enabled CAPTCHA point should still have type set');

    // Delete CAPTCHA point.
    $this->drupalPostForm(self::CAPTCHA_ADMIN_PATH . '/captcha/captcha_point/' . $captcha_point_form_id . '/delete', array(), 'Delete');
    $this->assertRaw(t('Deleted CAPTCHA for form %form_id.', array('%form_id' => $captcha_point_form_id)),
      'Deleting of CAPTCHA point');
  }
}
