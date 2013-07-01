<?php

/**
 * @file
 * Contains Drupal\captcha\Tests\CaptchaTestCase.
 *
 * Some tricks to debug:
 * drupal_debug($data) // from devel module
 * file_put_contents('tmp.simpletest.html', $this->drupalGetContent());
 */

namespace Drupal\captcha\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\Core\Language\Language;

class CaptchaTestCase extends CaptchaBaseWebTestCase {

  public static function getInfo() {
    return array(
      'name' => t('General CAPTCHA functionality'),
      'description' => t('Testing of the basic CAPTCHA functionality.'),
      'group' => t('CAPTCHA'),
    );
  }

  /**
   * Testing the protection of the user log in form.
   */
  function testCaptchaOnLoginForm() {
    // Create user and test log in without CAPTCHA.
    $user = $this->drupalCreateUser();
    $this->drupalLogin($user);
    // Log out again.
    $this->drupalLogout();

    // Set a CAPTCHA on login form
    captcha_set_form_id_setting('user_login_form', 'captcha/Math');

    // Check if there is a CAPTCHA on the login form (look for the title).
    $this->drupalGet('user');
    $this->assertCaptchaPresence(TRUE);

    // Try to log in, which should fail.
    $edit = array(
      'name' => $user->name,
      'pass' => $user->pass_raw,
      'captcha_response' => '?',
    );
    $this->drupalPost('user', $edit, t('Log in'));
    // Check for error message.
    $this->assertText(t(self::CAPTCHA_WRONG_RESPONSE_ERROR_MESSAGE),
      'CAPTCHA should block user login form', 'CAPTCHA');

    // And make sure that user is not logged in: check for name and password fields on ?q=user
    $this->drupalGet('user');
    $this->assertField('name', t('Username field found.'), 'CAPTCHA');
    $this->assertField('pass', t('Password field found.'), 'CAPTCHA');

  }


  /**
   * Assert function for testing if comment posting works as it should.
   *
   * Creates node with comment writing enabled, tries to post comment
   * with given CAPTCHA response (caller should enable the desired
   * challenge on page node comment forms) and checks if the result is as expected.
   *
   * @param $captcha_response the response on the CAPTCHA
   * @param $should_pass boolean describing if the posting should pass or should be blocked
   * @param $message message to prefix to nested asserts
   */
  protected function assertCommentPosting($captcha_response, $should_pass, $message) {
    // Make sure comments on pages can be saved directely without preview.
    $this->container->get('state')->set('comment_preview_page', DRUPAL_OPTIONAL);

    // Create a node with comments enabled.
    $node = $this->createNodeWithCommentsEnabled();

    // Post comment on node.
    $edit = $this->getCommentFormValues();
    $comment_subject = $edit['subject'];
    $comment_body = $edit['comment_body[' . Language::LANGCODE_NOT_SPECIFIED . '][0][value]'];
    $edit['captcha_response'] = $captcha_response;
    $this->drupalPost('comment/reply/' . $node->nid, $edit, t('Save'));

    if ($should_pass) {
      // There should be no error message.
      $this->assertCaptchaResponseAccepted();
      // Get node page and check that comment shows up.
      $this->drupalGet('node/' . $node->nid);
      $this->assertText($comment_subject, $message .' Comment should show up on node page.', 'CAPTCHA');
      $this->assertText($comment_body, $message . ' Comment should show up on node page.', 'CAPTCHA');
    }
    else {
      // Check for error message.
      $this->assertText(t(self::CAPTCHA_WRONG_RESPONSE_ERROR_MESSAGE), $message .' Comment submission should be blocked.', 'CAPTCHA');
      // Get node page and check that comment is not present.
      $this->drupalGet('node/' . $node->nid);
      $this->assertNoText($comment_subject, $message .' Comment should not show up on node page.', 'CAPTCHA');
      $this->assertNoText($comment_body, $message . ' Comment should not show up on node page.', 'CAPTCHA');
    }
  }

  /*
   * Testing the case sensistive/insensitive validation.
   */
  function testCaseInsensitiveValidation() {
    $config = config('captcha.settings');
    // Set Test CAPTCHA on comment form
    captcha_set_form_id_setting(self::COMMENT_FORM_ID, 'captcha/Test');

    // Log in as normal user.
    $this->drupalLogin($this->normal_user);

    // Test case sensitive posting.
    $config->set('captcha_default_validation', CAPTCHA_DEFAULT_VALIDATION_CASE_SENSITIVE);
    $config->save();

    $this->assertCommentPosting('Test 123', TRUE, 'Case sensitive validation of right casing.');
    $this->assertCommentPosting('test 123', FALSE, 'Case sensitive validation of wrong casing.');
    $this->assertCommentPosting('TEST 123', FALSE, 'Case sensitive validation of wrong casing.');

    // Test case insensitive posting (the default)
    $config->set('captcha_default_validation', CAPTCHA_DEFAULT_VALIDATION_CASE_INSENSITIVE);
    $config->save();

    $this->assertCommentPosting('Test 123', TRUE, 'Case insensitive validation of right casing.');
    $this->assertCommentPosting('test 123', TRUE, 'Case insensitive validation of wrong casing.');
    $this->assertCommentPosting('TEST 123', TRUE, 'Case insensitive validation of wrong casing.');
  }

  /**
   * Test if the CAPTCHA description is only shown if there are challenge widgets to show.
   * For example, when a comment is previewed with correct CAPTCHA answer,
   * a challenge is generated and added to the form but removed in the pre_render phase.
   * The CAPTCHA description should not show up either.
   *
   * \see testCaptchaSessionReuseOnNodeForms()
   */
  function testCaptchaDescriptionAfterCommentPreview() {
    // Set Test CAPTCHA on comment form.
    captcha_set_form_id_setting(self::COMMENT_FORM_ID, 'captcha/Test');

    // Log in as normal user.
    $this->drupalLogin($this->normal_user);

    // Create a node with comments enabled.
    $node = $this->createNodeWithCommentsEnabled();

    // Preview comment with correct CAPTCHA answer.
    $edit = $this->getCommentFormValues();
    $edit['captcha_response'] = 'Test 123';
    $this->drupalPost('comment/reply/' . $node->nid, $edit, t('Preview'));

    // Check that there is no CAPTCHA after preview.
    $this->assertCaptchaPresence(FALSE);
  }

  /**
   * Test if the CAPTCHA session ID is reused when previewing nodes:
   * node preview after correct response should not show CAPTCHA anymore.
   * The preview functionality of comments and nodes works slightly different under the hood.
   * CAPTCHA module should be able to handle both.
   *
   * \see testCaptchaDescriptionAfterCommentPreview()
   */
  function testCaptchaSessionReuseOnNodeForms() {
    // Set Test CAPTCHA on page form.
    captcha_set_form_id_setting('page_node_form', 'captcha/Test');

    // Log in as normal user.
    $this->drupalLogin($this->normal_user);

    // Page settings to post, with correct CAPTCHA answer.
    $edit = $this->getNodeFormValues();
    $edit['captcha_response'] = 'Test 123';
    // Preview the node
    $this->drupalPost('node/add/page', $edit, t('Preview'));

    // Check that there is no CAPTCHA after preview.
    $this->assertCaptchaPresence(FALSE);
  }


  /**
   * CAPTCHA should also be put on admin pages even if visitor
   * has no access
   */
  function testCaptchaOnLoginBlockOnAdminPagesIssue893810() {
    // Set a CAPTCHA on login block form
    captcha_set_form_id_setting('user_login_form', 'captcha/Math');

    // Check if there is a CAPTCHA on home page.
    $this->drupalGet('node');
    $this->assertCaptchaPresence(TRUE);

    // Check there is a CAPTCHA on "forbidden" admin pages
    $this->drupalGet('admin');
    $this->assertCaptchaPresence(TRUE);
  }

}