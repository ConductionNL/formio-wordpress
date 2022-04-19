<?php

namespace OWC\Formio\Classes;

use OWC\Formio\Foundation\Plugin;

class FormioEndpoint
{
  /** @var Plugin */
  protected $plugin;

  public function __construct(Plugin $plugin)
  {
    $this->plugin = $plugin;
    $this->load_hooks();
  }

  private function load_hooks(): void
  {
    add_action('rest_api_init', function () {
      register_rest_route('conductionnl/v1', '/author/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'my_awesome_func',
      ));
    });
  }

  /**
   * Grab latest post title by an author!
   *
   * @param array $data Options for the function.
   * @return string|null Post title for the latest,  * or null if none.
   */
  public function my_awesome_func($data)
  {
    $posts = get_posts(array(
      'author' => $data['id'],
    ));

    if (empty($posts)) {
      return null;
    }

    return $posts[0]->post_title;
  }
}
