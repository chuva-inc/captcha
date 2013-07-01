<?php

/**
 * @file
 * Contains Drupal\captcha\Tests\CaptchaBaseWebTestCase.
 *
 * Some tricks to debug:
 * drupal_debug($data) // from devel module
 * file_put_contents('tmp.simpletest.html', $this->drupalGetContent());
 */

// TODO: write test for CAPTCHAs on admin pages
// TODO: test for default challenge type
// TODO: test about placement (comment form, node forms, log in form, etc)
// TODO: test if captcha_cron does it work right
// TODO: test custom CAPTCHA validation stuff
// TODO: test if entry on status report (Already X blocked form submissions) works
// TODO: test space ignoring validation of image CAPTCHA
// TODO: refactor the 'comment_body[' . Language::LANGCODE_NOT_SPECIFIED . '][0][value]' stuff

namespace Drupal\captcha\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\Core\Language\Language;

/**
 * Base class for CAPTCHA tests.
 *
 * Provides common setup stuff and various helper functions
 */
abstract class CaptchaBaseWebTestCase extends WebTestBase {

// Some constants for better reuse.
const CAPTCHA_WRONG_RESPONSE_ERROR_MESSAGE =
  'The answer you entered for the CAPTCHA was not correct.';

const CAPTCHA_SESSION_REUSE_ATTACK_ERROR_MESSAGE =
  'CAPTCHA session reuse attack detected.';

const CAPTCHA_UNKNOWN_CSID_ERROR_MESSAGE =
  'CAPTCHA validation error: unknown CAPTCHA session ID. Contact the site administrator if this problem persists.';

  public static $modules = array('captcha', 'comment');
  protected $profile = 'standard';

  /**
   * User with various administrative permissions.
   * @var Drupal user
   */
  protected $admin_user;

  /**
   * Normal visitor with limited permissions
   * @var Drupal user;
   */
  protected $normal_user;

  /**
   * Form ID of comment form on standard (page) node
   * @var string
   */
  const COMMENT_FORM_ID = 'comment_node_page_comment_form';

  /**
   * Drupal path of the (general) CAPTCHA admin page
   */
  const CAPTCHA_ADMIN_PATH = 'admin/config/people/captcha';


  function setUp() {
    // Load two modules: the captcha module itself and the comment module for testing anonymous comments.
    parent::setUp();
    module_load_include('inc', 'captcha');

    // Create a normal user.
    $permissions = array(
      'access comments', 'post comments', 'skip comment approval',
      'access content', 'create page content', 'edit own page content',
    );
    $this->normal_user = $this->drupalCreateUser($permissions);

    // Create an admin user.
    $permissions[] = 'administer CAPTCHA settings';
    $permissions[] = 'skip CAPTCHA';
    $permissions[] = 'administer permissions';
    $permissions[] = 'administer content types';
    $this->admin_user = $this->drupalCreateUser($permissions);

    // Put comments on page nodes on a separate page (default in D7: below post).
    variable_set('comment_form_location_page', COMMENT_FORM_SEPARATE_PAGE);

  }

  /**
   * Assert that the response is accepted:
   * no "unknown CSID" message, no "CSID reuse attack detection" message,
   * no "wrong answer" message.
   */
  protected function assertCaptchaResponseAccepted() {
    // There should be no error message about unknown CAPTCHA session ID.
    $this->assertNoText(t(self::CAPTCHA_UNKNOWN_CSID_ERROR_MESSAGE),
      'CAPTCHA response should be accepted (known CSID).',
      'CAPTCHA');
    // There should be no error message about CSID reuse attack.
    $this->assertNoText(t(self::CAPTCHA_SESSION_REUSE_ATTACK_ERROR_MESSAGE),
      'CAPTCHA response should be accepted (no CAPTCHA session reuse attack detection).',
      'CAPTCHA');
    // There should be no error message about wrong response.
    $this->assertNoText(t(self::CAPTCHA_WRONG_RESPONSE_ERROR_MESSAGE),
      'CAPTCHA response should be accepted (correct response).',
      'CAPTCHA');
  }

  /**
   * Assert that there is a CAPTCHA on the form or not.
   * @param bool $presence whether there should be a CAPTCHA or not.
   */
  protected function assertCaptchaPresence($presence) {
    if ($presence) {
      $this->assertText(_captcha_get_description(),
        'There should be a CAPTCHA on the form.', 'CAPTCHA');
    }
    else {
      $this->assertNoText(_captcha_get_description(),
        'There should be no CAPTCHA on the form.', 'CAPTCHA');
    }
  }

  /**
   * Helper function to create a node with comments enabled.
   *
   * @return
   *   Created node object.
   */
  protected function createNodeWithCommentsEnabled($type='page') {
    $node_settings = array(
      'type' => $type,
      'comment' => COMMENT_NODE_OPEN,
    );
    $node = $this->drupalCreateNode($node_settings);
    return $node;
  }

  /**
   * Helper function to generate a form values array for comment forms
   */
  protected function getCommentFormValues() {
    $edit = array(
      'subject' => 'comment_subject ' . $this->randomName(32),
      'comment_body[' . Language::LANGCODE_NOT_SPECIFIED . '][0][value]' => 'comment_body ' . $this->randomName(256),
    );
    return $edit;
  }

  /**
   * Helper function to generate a form values array for node forms
   */
  protected function getNodeFormValues() {
    $edit = array(
      'title' => 'node_title ' . $this->randomName(32),
      'body[' . Language::LANGCODE_NOT_SPECIFIED . '][0][value]' => 'node_body ' . $this->randomName(256),
    );
    return $edit;
  }


  /**
   * Get the CAPTCHA session id from the current form in the browser.
   */
  protected function getCaptchaSidFromForm() {
    $elements = $this->xpath('//input[@name="captcha_sid"]');
    $captcha_sid = (int) $elements[0]['value'];
    return $captcha_sid;
  }

  /**
   * Get the CAPTCHA token from the current form in the browser.
   */
  protected function getCaptchaTokenFromForm() {
    $elements = $this->xpath('//input[@name="captcha_token"]');
    $captcha_token = (int) $elements[0]['value'];
    return $captcha_token;
  }

  /**
   * Get the solution of the math CAPTCHA from the current form in the browser.
   */
  protected function getMathCaptchaSolutionFromForm() {
    // Get the math challenge.
    $elements = $this->xpath('//div[@class="form-item form-type-textfield form-item-captcha-response"]/span[@class="field-prefix"]');
    $challenge = (string) $elements[0];
    // Extract terms and operator from challenge.
    $matches = array();
    $ret = preg_match('/\\s*(\\d+)\\s*(-|\\+)\\s*(\\d+)\\s*=\\s*/', $challenge, $matches);
    // Solve the challenge
    $a = (int) $matches[1];
    $b = (int) $matches[3];
    $solution = $matches[2] == '-' ? $a - $b : $a + $b;
    return $solution;
  }

  /**
   * Helper function to allow comment posting for anonymous users.
   */
  protected function allowCommentPostingForAnonymousVisitors() {
    // Enable anonymous comments.
    user_role_grant_permissions(DRUPAL_ANONYMOUS_RID, array(
      'access comments',
      'post comments',
      'skip comment approval',
    ));
  }

}