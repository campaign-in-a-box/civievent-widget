<?php

/*
Plugin Name: CIB CiviEvent Widget
Description: CIB CiviEvent Widget plugin displays public CiviCRM events in a widget.
Version: 4.5
Author: Campaign in a Box
Author URI: https://www.cibapp.net/
*/

require_once __DIR__ . '/cib-civievent-shared.php';
require_once __DIR__ . '/cib-civievent-single-widget.php';

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'cib_add_changelog_link');
function cib_add_changelog_link($links)
{
  $links[] = '<a href="/wp-content/plugins/cib-civievent-widget/changelog.txt" target="_blank">Changelog</a>';
  return $links;
}

add_action('widgets_init', function () {
  wp_register_style('civievent-widget-Stylesheet', plugins_url('cib-civievent-widget.css', __FILE__));
});

/**
 * Deliver the widget as a shortcode.
 *
 * @param array $atts The shortcode attributes provided
 * Available attributes include:
 *  - title string The widget title (default: "Upcoming Events"),
 *  - summary bool 1 = display the summary,
 *  - limit int The number of events (default: 5),
 *  - alllink bool 1 = display "view all",
 *  - wtheme string The widget theme (default: "stripe"),
 *  - divider string The location field delimiter (default comma),
 *  - city bool 1 = display event city,
 *  - state string display event state/province:
 *   	'abbreviate' - abbreviation
 *   	'full' - full name
 *   	'none' (default) - display nothing
 *  - country bool 1 = display event country,
 *  - admin_type string display type:
 *   	'simple' (default) - use settings above for title, summary, etc.
 *   	'custom' - use custom_display and custom_filter
 *  - custom_display string JSON of custom display options (see documentation).
 *  - custom_filter string JSON of custom filter options (see documentation).
 *  - event_type_id int filter the event listing to a single event type
 *
 * All booleans default to false; any value makes them true.
 *
 * @return string The widget to drop into the post body.
 */
// Default per-event template for [civievent_widget] without inner content.
// Reproduces the built-in "simple" layout so the shortcode uses a single rendering code path.
define(
  'CIB_CIVIEVENT_LIST_DEFAULT_TEMPLATE',
  "<div class='civievent-widget-event-row civievent-widget-event-{\$event.class} civievent-widget-event-{\$event.index}'>" .
    "<div class='civievent-widget-event-cell civievent-widget-event-cell-left'>" .
    '{if $event.date_start}' .
    '<div class="civievent-widget-event-start-date">Start:<br/>{$event.date_start}</div>' .
    '&nbsp;<div class="civievent-widget-event-start-time">{$event.time_start}</div><br/><br/>' .
    '{if $event.date_end}' .
    '<div class="civievent-widget-event-start-date">End:<br/>{$event.date_end}</div>' .
    ' <div class="civievent-widget-event-start-time">{$event.time_end}</div>' .
    '{elseif $event.time_end}' .
    ' <div class="civievent-widget-event-start-time">End:<br/>{$event.time_end}</div>' .
    '{/if}{/if}' .
    '</div>' .
    "<div class='civievent-widget-event-cell civievent-widget-event-cell-right'>" .
    "<div class='civievent-widget-event-title'><h2><a href='{\$event.url}'>{\$event.title}</a></h2></div>" .
    "<div class='civievent-widget-single-image'><a href='{\$event.url}'>{\$event.image_tag}</a></div>" .
    "{if \$event.summary}<div class='civievent-widget-event-summary'>{\$event.summary}</div>{/if}" .
    "<div class='civievent-widget-button-section'>" .
    '  {$event.register_buttons}' .
    '  <a href="{$event.url}" title="Read More" class="button btn"><span>Read More</span></a>' .
    '</div>' .
    '</div></div>' . "\n",
);

add_shortcode('civievent_widget', 'civievent_widget_shortcode');
/**
 * Render an event list using a user-supplied {event.*} template.
 *
 * Called when the [civievent_widget]...[/civievent_widget] shortcode has inner content.
 *
 * @param array  $atts     Shortcode attributes (limit, event_type_id, …).
 * @param string $template Per-event template string containing {event.*} tokens.
 * @return string Rendered HTML.
 */
function civievent_widget_shortcode($atts, $content = null)
{
  if (!function_exists('civicrm_initialize')) {
    return '';
  }
  civicrm_initialize();

  $template = !empty($content) ? $content : CIB_CIVIEVENT_LIST_DEFAULT_TEMPLATE;
  $atts = is_array($atts) ? $atts : [];
  $limit = isset($atts['limit']) ? intval($atts['limit']) : 5;
  $event_type_id = isset($atts['event_type_id']) ? intval($atts['event_type_id']) : 0;

  // Resolve the custom image field once.
  $image_field = '';
  try {
    $cf = civicrm_api3('custom_field', 'get', ['label' => 'cibapp_Image_Link']);
    if (!empty($cf['id'])) {
      $image_field = 'custom_' . $cf['id'];
    }
  } catch (CiviCRM_API3_Exception $e) {
    // No image field configured; carry on without it.
  }

  $return_fields = [
    'id',
    'title',
    'summary',
    'description',
    'start_date',
    'end_date',
    'is_online_registration',
    'registration_link_text',
    'registration_start_date',
    'registration_end_date',
  ];
  if ($image_field) {
    $return_fields[] = $image_field;
  }

  $params = [
    'is_public' => 1,
    'is_active' => 1,
    'start_date' => ['>=' => date('Y-m-d')],
    'return' => $return_fields,
    'options' => [
      'sort' => 'start_date ASC',
      'limit' => $limit,
    ],
  ];
  if ($event_type_id) {
    $params['event_type_id'] = $event_type_id;
  }

  try {
    $result = civicrm_api3('Event', 'get', $params);
  } catch (CiviCRM_API3_Exception $e) {
    CRM_Core_Error::debug_log_message('cib-civievent-widget: ' . $e->getMessage());
    return '';
  }

  if (empty($result['values'])) {
    return '';
  }

  $date_format = get_option('date_format');
  $time_format = get_option('time_format');

  wp_enqueue_style('civievent-widget-Stylesheet');

  $needs_location = cib_template_needs_location($template);

  $wtheme = sanitize_html_class(!empty($atts['wtheme']) ? $atts['wtheme'] : 'stripe');
  $title = !empty($atts['title']) ? esc_html($atts['title']) : '';
  $event_page = !empty($atts['url']) ? $atts['url'] : '/events/event-single/';

  $html = '<div class="civievent-widget civievent-widget-' . $wtheme . '">';
  if ($title) {
    $html .= '<h2 class="title civievent-widget-title">' . $title . '</h2>';
  }
  $html .= '<div class="civievent-widget-list">';
  $index = 0;
  foreach ($result['values'] as $event) {
    $location = $needs_location ? cib_fetch_event_location($event['id']) : [];
    $html .= cib_apply_event_template($template, $event, $index, $image_field, $date_format, $time_format, $location, $event_page);
    $index++;
  }
  $html .= '</div></div>';

  return $html;
}
