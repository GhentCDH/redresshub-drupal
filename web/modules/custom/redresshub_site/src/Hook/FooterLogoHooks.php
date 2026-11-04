<?php

declare(strict_types=1);

namespace Drupal\redresshub_site\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\redresshub_site\Controller\FooterLogosController;

/**
 * The forms of the footer logos: simple, and back to the footer logos page.
 */
final class FooterLogoHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $type = FooterLogosController::MEDIA_TYPE;
    if (!in_array($form_id, ["media_{$type}_add_form", "media_{$type}_edit_form", "media_{$type}_delete_form"], TRUE)) {
      return;
    }
    $back = Url::fromRoute('redresshub_site.footer_logos');

    if ($form_id === "media_{$type}_delete_form") {
      $form['#title'] = $this->t('Remove the logo %name?', ['%name' => $form_state->getFormObject()->getEntity()->label()]);
      $form['description']['#markup'] = $this->t('It is no longer shown at the bottom of the pages. This cannot be undone.');
      $form['actions']['submit']['#value'] = $this->t('Remove');
      $form['actions']['submit']['#button_type'] = 'danger';
      $form['actions']['cancel']['#url'] = $back;
    }
    else {
      // No revisions and no technical details.
      foreach (['revision_information', 'revision', 'revision_log_message'] as $key) {
        if (isset($form[$key])) {
          $form[$key]['#access'] = FALSE;
        }
      }
      if (isset($form['name']['widget'][0]['value'])) {
        $form['name']['widget'][0]['value']['#description'] = $this->t('The name of the partner, e.g. "Ghent University".');
      }
      $form['actions']['cancel'] = [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#url' => $back,
        '#attributes' => ['class' => ['button']],
        '#weight' => 20,
      ];
    }
    foreach (array_keys($form['actions']) as $action) {
      if (isset($form['actions'][$action]['#submit'])) {
        $form['actions'][$action]['#submit'][] = [self::class, 'redirect'];
      }
    }
  }

  /**
   * Back to the footer logos page after saving or deleting.
   */
  public static function redirect(array $form, FormStateInterface $form_state): void {
    $form_state->setRedirect('redresshub_site.footer_logos');
  }

}
