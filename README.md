CAPTCHA module for Drupal
---------------------------
[![Build Status](https://travis-ci.org/chuva-inc/captcha.svg?branch=8.x-1.x)](https://travis-ci.org/chuva-inc/captcha)
[![Code Climate](https://codeclimate.com/github/chuva-inc/captcha/badges/gpa.svg)](https://codeclimate.com/github/chuva-inc/captcha)

Description
------------
captcha.module is the basic CAPTCHA module, offering general CAPTCHA
administration and a simple maths challenge.

Sub module 
---------
image_captcha.module offers an image based challenge.

Installation:
--------------
	1. Extract the tar.gz into your 'modules' or directory and copy to modules folder.
	2. Go to "Extend" after successfully login into admin.
	3. Enable the module at 'administer >> modules'.	   	

Dependencies
------------
  The basic CAPTCHA module has no dependencies, nothing special is required.

Conflicts/known issues
-----------------------
  CAPTCHA and page caching do not work together currently.
  However, the CAPTCHA module does support the Drupal core page
  caching mechanism: it just disables the caching of the pages
  where it has to put its challenges.
  If you use other caching mechanisms, it is possible that CAPTCHA's
  won't work, and you get error messages like 'CAPTCHA validation
  error: unknown CAPTCHA session ID'.

Configuration
--------------
  The configuration page is at admin/config/people/captcha,
  where you can configure the CAPTCHA module
  and enable challenges for the desired forms.
  You can also tweak the image CAPTCHA to your liking.

Uninstallation
--------------
1. Disable the module from 'administer >> modules'.
2. Uninstall the module
