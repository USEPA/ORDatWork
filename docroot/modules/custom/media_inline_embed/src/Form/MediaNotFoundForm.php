<?php

namespace Drupal\media_inline_embed\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class MediaNotFoundForm extends ConfigFormBase {

  /**
   * @return array
   */
  protected function getEditableConfigNames() {
    return ['media_inline_embed.form'];
  }

  /**
   * @return string
   */
  public function getFormId() {
    return 'media_inline_embed_form';
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   *
   * @return array
   *
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('media_inline_embed.form');
    $form['BASE_URL'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ORD@Work Base Url'),
      '#default_value' => $config->get('BASE_URL'),
    ];
    $form['LOAD_BY_UUID_EXTENSION'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Url Extension for UUID media loading'),
      '#default_value' => $config->get('LOAD_BY_UUID_EXTENSION'),
    ];
    $form['LOAD_BY_MID_EXTENSION'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Url Extension for MID media loading'),
      '#default_value' => $config->get('LOAD_BY_MID_EXTENSION'),
    ];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('media_inline_embed.form')
      ->set('BASE_URL', $form_state->getValue('BASE_URL'))
      ->set('LOAD_BY_UUID_EXTENSIONL', $form_state->getValue('LOAD_BY_UUID_EXTENSION'))
      ->set('LOAD_BY_MID_EXTENSION', $form_state->getValue('LOAD_BY_MID_EXTENSION'))
      ->save();
  }
}
