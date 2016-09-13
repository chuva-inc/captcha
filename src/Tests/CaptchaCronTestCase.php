<?php

namespace Drupal\captcha\Tests;

use Drupal\Core\Database\Database;

/**
 * Tests CAPTCHA cron.
 *
 * @group captcha
 */
class CaptchaCronTestCase extends CaptchaBaseWebTestCase {

  public $captcha_sessions;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Add removed session.
    $this->captcha_sessions['remove_sid'] = $this->addCaptchaSession(REQUEST_TIME - 1 - 60 * 60 * 24);
    // Add remain session.
    $this->captcha_sessions['remain_sid'] = $this->addCaptchaSession(REQUEST_TIME);
  }

  /**
   * Add test Captcha session data.
   */
  public function addCaptchaSession($request_time) {
    $admin_user = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($admin_user);

    // Initialize solution with random data.
    $solution = hash('sha256', mt_rand());

    // Insert an entry and thankfully receive the value
    // of the autoincrement field 'csid'.
    $connection = Database::getConnection();
    $captcha_sid = $connection->insert('captcha_sessions')
      ->fields([
        'uid' => 0,
        'sid' => session_id(),
        'ip_address' => \Drupal::request()->getClientIp(),
        'timestamp' => $request_time,
        'form_id' => 'CaptchaCronTestCase',
        'solution' => $solution,
        'status' => 1,
        'attempts' => 0,
      ])
      ->execute();
    return $captcha_sid;
  }

  /**
   * Test captcha cron.
   */
  public function testCron() {

    // Run captcha_cron().
    captcha_cron();

    $connection = Database::getConnection();
    $sids = $connection->select('captcha_sessions')
      ->fields('captcha_sessions', ['csid'])
      ->condition('csid', array_values($this->captcha_sessions), 'IN')
      ->execute()
      ->fetchCol('csid');

    // Test if Captcha cron appropriately removes sessions older than a day.
    $this->assertTrue(!in_array($this->captcha_sessions['remove_sid'], $sids), 'Captcha cron removes captcha session data older than 1 day.');

    // Test if Captcha cron appropriately keeps sessions younger than a day.
    $this->assertTrue(in_array($this->captcha_sessions['remain_sid'], $sids), 'Captcha cron does not remove captcha session data younger than 1 day.');
  }

}
