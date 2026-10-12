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
  $form['redresshub']['map_url'] = [
    '#type' => 'url',
    '#title' => t('Map URL'),
    '#description' => t('The NodeGoat viewer embedded on the front page.'),
    '#default_value' => theme_get_setting('map_url'),
  ];
  $form['redresshub']['map_updated'] = [
    '#type' => 'textfield',
    '#title' => t('Map data note'),
    '#description' => t('Shown below the map, e.g. "Map data last updated April 7, 2026."'),
    '#default_value' => theme_get_setting('map_updated'),
  ];
  $form['redresshub']['contact_email'] = [
    '#type' => 'email',
    '#title' => t('Contact email'),
    '#description' => t('Shown in the footer.'),
    '#default_value' => theme_get_setting('contact_email'),
  ];
}
