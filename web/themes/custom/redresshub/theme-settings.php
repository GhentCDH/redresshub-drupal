<?php

/**
 * @file
 * Theme settings for RedressHub.
 */

declare(strict_types=1);

use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_system_theme_settings_alter().
 */
function redresshub_form_system_theme_settings_alter(array &$form, FormStateInterface $form_state): void {
  $form['redresshub'] = [
    '#type' => 'details',
    '#title' => t('RedressHub'),
    '#open' => TRUE,
  ];
  $form['redresshub']['map_tiles'] = [
    '#type' => 'textfield',
    '#title' => t('Map tiles'),
    '#maxlength' => 512,
    '#description' => t('URL template of the base map of the explorer, with {z}, {x} and {y}. Stadia Maps (the default) works on localhost; on a public domain, register the domain in a (free) Stadia account.'),
    '#default_value' => theme_get_setting('map_tiles'),
  ];
  $form['redresshub']['map_attribution'] = [
    '#type' => 'textfield',
    '#title' => t('Map attribution'),
    '#maxlength' => 512,
    '#description' => t('Credits of the base map (HTML), shown in the corner of the map.'),
    '#default_value' => theme_get_setting('map_attribution'),
  ];
  $form['redresshub']['contact_email'] = [
    '#type' => 'email',
    '#title' => t('Contact email'),
    '#description' => t('Shown in the footer.'),
    '#default_value' => theme_get_setting('contact_email'),
  ];
}
