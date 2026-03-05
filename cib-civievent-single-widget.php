<?php

/*
 * Deliver ONE SINGLE event as a shortcode.
 *
 * @param array $atts The shortcode attributes provided
 * Available attributes include:
 *  - title string
 *    The widget title (default automatically fills with the event title),
 *  - wtheme string
 *    The widget theme (default: "standard"),
 *  - divider string
 *    The location field delimiter (default comma),
 *  - city bool
 *    1 = display event city,
 *  - state string
 *    display event state/province:
 *   	 'abbreviate' - abbreviation
 *   	 'full' - full name
 *   	 'none' (default) - display nothing
 *  - country bool
 *    1 = display event country,
 *  - offset int
 *    The number of events to skip (default: 0).
 *  - event_type_id int
 *    filter to a single event type.
 *
 * All booleans default to false; any value makes them true.
 *
 * @return string The widget to drop into the post body.
 */

// Default template for [civievent_single_widget] without inner content.
// Reproduces the built-in single-event layout so the shortcode uses a single rendering code path.
define(
  'CIB_CIVIEVENT_SINGLE_DEFAULT_TEMPLATE',
  "<h2 class='title civievent-single-widget-title'>{\$event.title}</h2>" .
    '{if $event.date_start}<div class="civievent-widget-single-date">' .
    '{$event.date_start} &bull; {$event.time_start}' .
    '{if $event.date_end} &mdash; {$event.date_end} {$event.time_end}{/if}' .
    '</div>{/if}' .
    '{if $event.summary}<div class="civievent-widget-single-summary">{$event.summary}</div>{/if}' .
    "<div class='civievent-widget-single-image'><a href='{\$event.url}'>{\$event.image_tag}</a></div>" .
    "<div class='civievent-widget-spacer'>&nbsp;</div>" .
    '{if $event.description}<div class="civievent-widget-single-summary">{$event.description}</div>{/if}' .
    '{if $event.location_full}<div class="civievent-widget-event-location"><strong>Location:</strong> {$event.location_full}</div>{/if}' .
    "<div class='civievent-widget-button-section'>" .
    '  {$event.register_buttons}' .
    "  <a href='/events/' class='button btn'><span>See all events</span></a>" .
    '  {$event.calendar_links}' .
    '  {if $event.map_link}<span class="civievent-widget-map">{$event.map_link}</span>{/if}' .
    '  </div>' .
    "<div class='civievent-widget-spacer'>&nbsp;</div>" .
    '{if $event.social_links}' . 
    "  <div class='civievent-widget-social' role='alert'>" .
    '  <h3>Help spread the word</h3>' .
    "  <p>Please help us and let your friends, colleagues and followers know about " .
    "  <strong>{\$event.title}</strong></p>" .
    '  {$event.social_links}' .
    '  </div>' .
    '{/if}',
);

add_shortcode('civievent_single_widget', 'civievent_single_widget_shortcode');

/**
 * Render a single event using a user-supplied {event.*} template.
 *
 * Called when the [civievent_single_widget]...[/civievent_single_widget] shortcode
 * has inner content.  Uses the same token set as civievent_widget_render_template().
 *
 * @param array  $atts     Shortcode attributes (offset, event_type_id, …).
 * @param string $template Template string containing {event.*} tokens.
 * @return string Rendered HTML.
 */
function civievent_single_widget_shortcode($atts, $content = null)
{
  if (!function_exists('civicrm_initialize')) {
    return '';
  }
  civicrm_initialize();

  $template = !empty($content) ? $content : CIB_CIVIEVENT_SINGLE_DEFAULT_TEMPLATE;
  $atts = is_array($atts) ? $atts : [];
  $offset = isset($atts['offset']) ? intval($atts['offset']) : 0;
  $event_type_id = isset($atts['event_type_id']) ? intval($atts['event_type_id']) : 0;

  // Resolve the custom image field.
  $image_field = '';
  try {
    $cf = civicrm_api3('custom_field', 'get', ['label' => 'cibapp_Image_Link']);
    if (!empty($cf['id'])) {
      $image_field = 'custom_' . $cf['id'];
    }
  } catch (CiviCRM_API3_Exception $e) {
    // No image field configured; carry on without it.
  }

  // Fetch either the specific event (if ?eventID= is in the URL) or the next upcoming one.
  try {
    if (!empty($_GET['eventID']) && is_numeric($_GET['eventID'])) {
      $event = civicrm_api3('Event', 'getsingle', ['id' => intval($_GET['eventID'])]);
    } else {
      $event_args = [
        'sequential' => 1,
        'is_active' => 1,
        'is_public' => 1,
        'is_template' => 0,
        'start_date' => ['>=' => date('Y-m-d')],
        'options' => [
          'limit' => 1,
          'sort' => 'start_date ASC',
          'offset' => $offset,
        ],
      ];
      if ($event_type_id) {
        $event_args['event_type_id'] = $event_type_id;
      }
      $event = civicrm_api3('Event', 'getsingle', $event_args);
    }
  } catch (CiviCRM_API3_Exception $e) {
    error_log('cib-civievent-shortcode: ' . $e->getMessage());
    return '';
  }

  if (empty($event['title'])) {
    return '';
  }

  $date_format = get_option('date_format');
  $time_format = get_option('time_format');

  wp_enqueue_style('civievent-widget-Stylesheet');

  $location = cib_template_needs_location($template) ? cib_fetch_event_location($event['id']) : [];

  // Support og: metatags (matches create_widget() with metatags=yes).
  $metatags = !isset($atts['metatags']) || $atts['metatags'] !== 'no';
  if ($metatags && !empty($_GET['eventID']) && is_numeric($_GET['eventID'])) {
    if ($image_field) {
      $event['customValue'] = intval(substr($image_field, 7)); // strip 'custom_'
    }
    set_transient('cib_civievent_single_event_' . intval($_GET['eventID']), $event, 5);
    add_action('wp_head', 'civievent_add_dynamic_og_image');
  }

  $wtheme = sanitize_html_class(!empty($atts['wtheme']) ? $atts['wtheme'] : 'standard');
  $event_page = !empty($atts['url']) ? $atts['url'] : '/events/event-single/';
  return '<div class="civievent-widget civievent-widget-single-' . $wtheme . '">' . cib_apply_event_template($template, $event, 0, $image_field, $date_format, $time_format, $location, $event_page) . '</div>';
}


function civievent_add_dynamic_og_image()
{
  // only add for certain pages, othewise get out
  $theID = '';
  if (!empty($_GET['eventID']) && $_GET['eventID'] != '') {
    if (!is_numeric($_GET['eventID'])) {
      // naughty people
      error_log('CiviCRM API Error: eventID not numeric:' . $_GET['eventID']);
      return 'CiviCRM API Error: eventID not numeric:' . $_GET['eventID'];
    }
    // we know it's ok
    $theID = $_GET['eventID'];

    // get the transient save in the DB
    $event = get_transient('cib_civievent_single_event_' . $theID);

    // the custom value is made up by CRM of "custom_" and the EVENT id.
    $customValue = 'custom_' . $event['customValue'];
    $image_url = $event[$customValue];

    // get rid of stuff in the summary so we can POST it
    $summary = str_replace("\n", ' ', $event['summary']);
    $summary = str_replace("\r", ' ', $summary);
    $summary = str_replace('\m', ' ', $summary);

    // send the meta stuff to the header
    echo "<meta property='og:image' content='" . esc_attr($image_url) . "' />\n";
    echo "<meta property='og:description' content='" . esc_attr($summary) . "'>\n";
    echo "<meta name='description' content='" . esc_attr($summary) . "'>\n";
  }
}
