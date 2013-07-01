<?php

/**
 * @file
 * Contains Drupal\captcha\Tests\CaptchaSessionReuseAttackTestCase.
 *
 * Some tricks to debug:
 * drupal_debug($data) // from devel module
 * file_put_contents('tmp.simpletest.html', $this->drupalGetContent());
 */

namespace Drupal\captcha\Tests;

use Drupal\simpletest\WebTestBase;

class CaptchaSessionReuseAttackTestCase extends CaptchaBaseWebTestCase {

  public static function getInfo() {
    return array(
      'name' => t('CAPTCHA session reuse attack tests'),
      'description' => t('Testing of the protection against CAPTCHA session reuse attacks.'),
      'group' => t('CAPTCHA'),
    );
  }

  /**
   * Assert that the CAPTCHA session ID reuse attack was detected.
   */
  protected function assertCaptchaSessionIdReuseAttackDetection() {
    $this->assertText(t(self::CAPTCHA_SESSION_REUSE_ATTACK_ERROR_MESSAGE),
      'CAPTCHA session ID reuse attack should be detected.',
      'CAPTCHA');
    // There should be an error message about wrong response.
    $this->assertText(t(self::CAPTCHA_WRONG_RESPONSE_ERROR_MESSAGE),
      'CAPTCHA response should flagged as wrong.',
      'CAPTCHA');
  }

  function testCaptchaSessionReuseAttackDetectionOnCommentPreview() {
    // Create commentable node
    $node = $this->createNodeWithCommentsEnabled();
    // Set Test CAPTCHA on comment form.
    captcha_set_form_id_setting(self::COMMENT_FORM_ID, 'captcha/Math');
    config('captcha.settings')->set('captcha_persistence', CAPTCHA_PERSISTENCE_SKIP_ONCE_SUCCESSFUL_PER_FORM_INSTANCE)->save();

    // Log in as normal user.
    $this->drupalLogin($this->normal_user);

    // Go to comment form of commentable node.
    $this->drupalGet('comment/reply/' . $node->nid);
    $this->assertCaptchaPresence(TRUE);

    // Get CAPTCHA session ID and solution of the challenge.
    $captcha_sid = $this->getCaptchaSidFromForm();
    $captcha_token = $this->getCaptchaTokenFromForm();
    $solution = $this->getMathCaptchaSolutionFromForm();

    // Post the form with the solution.
    $edit = $this->getCommentFormValues();
    $edit['captcha_response'] = $solution;
    $this->drupalPost(NULL, $edit, t('Preview'));
    // Answer should be accepted and further CAPTCHA ommitted.
    $this->assertCaptchaResponseAccepted();
    $this->assertCaptchaPresence(FALSE);

    // Post a new comment, reusing the previous CAPTCHA session.
    $edit = $this->getCommentFormValues();
    $edit['captcha_sid'] = $captcha_sid;
    $edit['captcha_token'] = $captcha_token;
    $edit['captcha_response'] = $solution;
    $this->drupalPost('comment/reply/' . $node->nid, $edit, t('Preview'));
    // CAPTCHA session reuse attack should be detected.
    $this->assertCaptchaSessionIdReuseAttackDetection();
    // There should be a CAPTCHA.
    $this->assertCaptchaPresence(TRUE);

  }

  function testCaptchaSessionReuseAttackDetectionOnNodeForm() {
    // Set CAPTCHA on page form.
    captcha_set_form_id_setting('page_node_form', 'captcha/Math');
    config('captcha.settings')->set('captcha_persistence', CAPTCHA_PERSISTENCE_SKIP_ONCE_SUCCESSFUL_PER_FORM_INSTANCE)->save();

    // Log in as normal user.
    $this->drupalLogin($this->normal_user);

    // Go to node add form.
    $this->drupalGet('node/add/page');
    $this->assertCaptchaPresence(TRUE);

    // Get CAPTCHA session ID and solution of the challenge.
    $captcha_sid = $this->getCaptchaSidFromForm();
    $captcha_token = $this->getCaptchaTokenFromForm();
    $solution = $this->getMathCaptchaSolutionFromForm();

    // Page settings to post, with correct CAPTCHA answer.
    $edit = $this->getNodeFormValues();
    $edit['captcha_response'] = $solution;
    // Preview the node
    $this->drupalPost(NULL, $edit, t('Preview'));
    // Answer should be accepted.
    $this->assertCaptchaResponseAccepted();
    // Check that there is no CAPTCHA after preview.
    $this->assertCaptchaPresence(FALSE);

    // Post a new comment, reusing the previous CAPTCHA session.
    $edit = $this->getNodeFormValues();
    $edit['captcha_sid'] = $captcha_sid;
    $edit['captcha_token'] = $captcha_token;
    $edit['captcha_response'] = $solution;
    $this->drupalPost('node/add/page', $edit, t('Preview'));
    // CAPTCHA session reuse attack should be detected.
    $this->assertCaptchaSessionIdReuseAttackDetection();
    // There should be a CAPTCHA.
    $this->assertCaptchaPresence(TRUE);

  }

  function testCaptchaSessionReuseAttackDetectionOnLoginForm() {
    // Set CAPTCHA on login form.
    captcha_set_form_id_setting('user_login_form', 'captcha/Math');
    config('captcha.settings')->set('captcha_persistence', CAPTCHA_PERSISTENCE_SKIP_ONCE_SUCCESSFUL_PER_FORM_INSTANCE)->save();

    // Go to log in form.
    $this->drupalGet('user');
    $this->assertCaptchaPresence(TRUE);

    // Get CAPTCHA session ID and solution of the challenge.
    $captcha_sid = $this->getCaptchaSidFromForm();
    $captcha_token = $this->getCaptchaTokenFromForm();
    $solution = $this->getMathCaptchaSolutionFromForm();

    // Log in through form.
    $edit = array(
      'name' => $this->normal_user->name,
      'pass' => $this->normal_user->pass_raw,
      'captcha_response' => $solution,
    );
    $this->drupalPost(NULL, $edit, t('Log in'));
    $this->assertCaptchaResponseAccepted();
    $this->assertCaptchaPresence(FALSE);
    // If a "log out" link appears on the page, it is almost certainly because
    // the login was successful.
    $pass = $this->assertLink(t('Log out'), 0, t('User %name successfully logged in.', array('%name' => $this->normal_user->name)), t('User login'));

    // Log out again.
    $this->drupalLogout();

    // Try to log in again, reusing the previous CAPTCHA session.
    $edit += array(
      'captcha_sid' => $captcha_sid,
      'captcha_token' => $captcha_token,
    );
    $this->drupalPost('user', $edit, t('Log in'));
    // CAPTCHA session reuse attack should be detected.
    $this->assertCaptchaSessionIdReuseAttackDetection();
    // There should be a CAPTCHA.
    $this->assertCaptchaPresence(TRUE);
  }


  public function testMultipleCaptchaProtectedFormsOnOnePage()
  {
    // Set Test CAPTCHA on comment form and login block
    captcha_set_form_id_setting(self::COMMENT_FORM_ID, 'captcha/Test');
    captcha_set_form_id_setting('user_login_form', 'captcha/Math');
    $this->allowCommentPostingForAnonymousVisitors();

    // Create a node with comments enabled.
    $node = $this->createNodeWithCommentsEnabled();

    // Preview comment with correct CAPTCHA answer.
    $edit = $this->getCommentFormValues();
    $comment_subject = $edit['subject'];
    $edit['captcha_response'] = 'Test 123';
    $this->drupalPost('comment/reply/' . $node->nid, $edit, t('Preview'));
    // Post should be accepted: no warnings,
    // no CAPTCHA reuse detection (which could be used by user log in block).
    $this->assertCaptchaResponseAccepted();
    $this->assertText($comment_subject);

  }

}