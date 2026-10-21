<?php

declare(strict_types=1);

namespace Drupal\redresshub_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * A heading above a row of blocks on the dashboard, without a frame.
 */
#[Block(
  id: 'redresshub_heading',
  admin_label: new TranslatableMarkup('Heading'),
  category: new TranslatableMarkup('RedressHub'),
)]
final class HeadingBlock extends BlockBase {

  public function defaultConfiguration(): array {
    return ['heading' => ''];
  }

  public function blockForm($form, FormStateInterface $form_state): array {
    $form['heading'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Heading'),
      '#default_value' => $this->configuration['heading'],
      '#required' => TRUE,
    ];
    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['heading'] = $form_state->getValue('heading');
  }

  public function build(): array {
    return [
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      '#markup' => '<h2 class="dashboard-heading">' . $this->t($this->configuration['heading']) . '</h2>',
      '#attached' => ['library' => ['redresshub_site/dashboard']],
    ];
  }

}
