<?php

declare(strict_types=1);

namespace Drupal\redresshub_site\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\redresshub_site\Biblio;

/**
 * Publications: only the UGent Biblio ID is entered; the rest comes from
 * Biblio when saving, and is refreshed daily on cron.
 */
final class PublicationHooks {

  use StringTranslationTrait;

  /**
   * The fields filled in from Biblio, and the key in the Biblio record.
   */
  private const FIELDS = [
    'field_authors' => 'authors',
    'field_outlet' => 'outlet',
    'field_volume' => 'volume',
    'field_issue' => 'issue',
    'field_year' => 'year',
    'field_publication_status' => 'status',
    'field_doi' => 'doi',
    'field_publication_type' => 'type',
  ];

  /**
   * How many publications cron refreshes per run, and how often.
   */
  private const CRON_BATCH = 20;

  private const REFRESH_AFTER = 86400;

  public function __construct(
    private readonly Biblio $biblio,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MessengerInterface $messenger,
    private readonly TimeInterface $time,
  ) {}

  #[Hook('form_node_form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state): void {
    $node = $form_state->getFormObject()->getEntity();
    if ($node->bundle() !== 'publication' || !isset($form['field_biblio_id'])) {
      return;
    }
    $widget = &$form['field_biblio_id']['widget'][0]['value'];
    $widget['#description'] = $this->t('The ID of the publication in <a href=":biblio" target="_blank">UGent Biblio</a>, or its URL. The title, authors and the rest come from Biblio when you save, and are updated every day.', [':biblio' => 'https://biblio.ugent.be']);
    $form['#validate'][] = [self::class, 'validate'];
  }

  /**
   * Validates the Biblio ID: an ID or a Biblio URL, not added yet.
   */
  public static function validate(array &$form, FormStateInterface $form_state): void {
    $input = (string) ($form_state->getValue(['field_biblio_id', 0, 'value']) ?? '');
    $id = Biblio::idFrom($input);
    if ($id === NULL) {
      $form_state->setErrorByName('field_biblio_id', t('Enter the ID of the publication in UGent Biblio, or its URL.'));
      return;
    }
    $form_state->setValue(['field_biblio_id', 0, 'value'], $id);
    $node = $form_state->getFormObject()->getEntity();
    $query = \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'publication')->condition('field_biblio_id', $id);
    if (!$node->isNew()) {
      $query->condition('nid', $node->id(), '<>');
    }
    if ($existing = $query->range(0, 1)->execute()) {
      $other = \Drupal::entityTypeManager()->getStorage('node')->load(reset($existing));
      $form_state->setErrorByName('field_biblio_id', t('This publication has been added already: <a href=":url">@title</a>.', [':url' => $other->toUrl('edit-form')->toString(), '@title' => $other->label()]));
    }
  }

  #[Hook('node_presave')]
  public function presave(NodeInterface $node): void {
    if ($node->bundle() !== 'publication' || !$node->hasField('field_biblio_id')) {
      return;
    }
    $id = (string) Biblio::idFrom((string) $node->get('field_biblio_id')->value);
    $node->set('field_biblio_id', $id);
    // Cron fetched already; otherwise now, or the last copy from Biblio.
    $publication = $node->biblioPublication ?? $this->biblio->fetch($id);
    if ($publication) {
      $node->set('field_biblio_checked', $this->time->getRequestTime());
    }
    else {
      $publication = $this->biblio->stored($id);
      if (empty($node->biblioPublication)) {
        $this->messenger->addWarning($this->t('UGent Biblio could not be reached; the details of the publication are filled in later.'));
      }
    }
    if ($publication) {
      $node->setTitle(mb_substr($publication['title'] ?: "Biblio $id", 0, 255));
      foreach (self::FIELDS as $field => $key) {
        $value = $publication[$key] ?? '';
        $node->set($field, $field === 'field_year' ? ($value !== '' ? (int) $value : NULL) : $value);
      }
    }
    elseif ($node->label() === NULL || $node->label() === '') {
      $node->setTitle("Biblio $id");
    }
  }

  #[Hook('cron')]
  public function cron(): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $before = $this->time->getRequestTime() - self::REFRESH_AFTER;
    $query = $storage->getQuery()->accessCheck(FALSE)->condition('type', 'publication');
    $query->condition($query->orConditionGroup()->notExists('field_biblio_checked')->condition('field_biblio_checked', $before, '<'));
    $ids = $query->sort('field_biblio_checked')->range(0, self::CRON_BATCH)->execute();
    foreach ($storage->loadMultiple($ids) as $node) {
      $publication = $this->biblio->fetch((string) $node->get('field_biblio_id')->value);
      if (!$publication) {
        continue;
      }
      $node->biblioPublication = $publication;
      $node->setNewRevision(FALSE);
      $node->save();
    }
  }

  #[Hook('node_view')]
  public function view(array &$build, NodeInterface $node, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($node->bundle() !== 'publication' || !$node->hasField('field_biblio_id')) {
      return;
    }
    $publication = ['title' => $node->label()];
    foreach (self::FIELDS as $field => $key) {
      $publication[$key] = $field === 'field_authors'
        ? array_column($node->get($field)->getValue(), 'value')
        : (string) $node->get($field)->value;
    }
    $build['publication'] = [
      '#theme' => 'biblio_publication',
      '#publication' => $publication,
      '#url' => Biblio::url((string) $node->get('field_biblio_id')->value),
    ];
  }

}
