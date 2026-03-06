<?php

/*
Plugin Name: CIB CiviEvent Widget
Description: CIB CiviEvent Widget plugin displays public CiviCRM events in a widget.
Version: 5.0
Author: Campaign in a Box
Author URI: https://www.cibapp.net/
*/

require_once __DIR__ . "/cib-civievent-shared.php";

add_filter(
    "plugin_action_links_" . plugin_basename(__FILE__),
    "cib_add_changelog_link",
);
function cib_add_changelog_link($links)
{
    $links[] =
        '<a href="/wp-content/plugins/cib-civievent-widget/changelog.txt" target="_blank">Changelog</a>';
    return $links;
}

/*
 * Redirect single-event shortcode to the native CiviCRM event info page.
 *
 * [civievent_single_widget] is kept for backwards compatibility.
 * When ?eventID=X is present it redirects to /civicrm/event/info?reset=1&id=X.
 */
add_shortcode("civievent_single_widget", "civievent_single_widget_shortcode");
function civievent_single_widget_shortcode($atts, $content = null)
{
    if (!empty($_GET["eventID"]) && is_numeric($_GET["eventID"])) {
        wp_redirect(
            "/civicrm/event/info?reset=1&id=" . intval($_GET["eventID"]),
            301,
        );
        echo '<script>window.location.href = "/civicrm/event/info?reset=1&id=' .
            intval($_GET["eventID"]) .
            '";</script>';
        exit();
    }
    return "";
}

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

add_shortcode("civievent_widget", "civievent_widget_shortcode");
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
    if (!function_exists("civicrm_initialize")) {
        return "";
    }
    civicrm_initialize();

    $template = !empty($content)
        ? $content
        : file_get_contents(__DIR__ . "/templates/list.html");
    $atts = is_array($atts) ? $atts : [];
    $limit = isset($atts["limit"]) ? intval($atts["limit"]) : 5;
    $event_type_id = isset($atts["event_type_id"])
        ? intval($atts["event_type_id"])
        : 0;
    $image_field_label = !empty($atts["image_field"])
        ? sanitize_text_field($atts["image_field"])
        : "cibapp_Image_Link";

    // Resolve the custom image field to its APIv4 GroupName.field_name key.
    $image_field = cib_resolve_image_field($image_field_label);

    $select_fields = [
        "id",
        "title",
        "summary",
        "description",
        "start_date",
        "end_date",
        "is_online_registration",
        "registration_link_text",
        "registration_start_date",
        "registration_end_date",
        "is_show_location",
        "is_show_calendar_links",
        "is_map",
        "is_share",
        "loc_block_id",
    ];
    if ($image_field) {
        $select_fields[] = $image_field;
    }

    try {
        $query = \Civi\Api4\Event::get(false)
            ->addSelect(...$select_fields)
            ->addWhere("is_public", "=", true)
            ->addWhere("is_active", "=", true)
            ->addWhere("is_template", "=", false)
            ->addWhere("start_date", ">=", date("Y-m-d"))
            ->addOrderBy("start_date", "ASC")
            ->setLimit($limit);
        if ($event_type_id) {
            $query->addWhere("event_type_id", "=", $event_type_id);
        }
        $events = $query->execute()->getArrayCopy();
    } catch (\CRM_Core_Exception $e) {
        CRM_Core_Error::debug_log_message(
            "cib-civievent-widget: " . $e->getMessage(),
        );
        return "";
    }

    if (empty($events)) {
        return "";
    }

    $date_format = get_option("date_format");
    $time_format = get_option("time_format");

    wp_enqueue_style("civievent-widget-Stylesheet");

    $needs_location = cib_template_needs_location($template);

    $wtheme = sanitize_html_class(
        !empty($atts["wtheme"]) ? $atts["wtheme"] : "stripe",
    );
    $title = !empty($atts["title"]) ? esc_html($atts["title"]) : "";
    $event_page = !empty($atts["url"])
        ? $atts["url"]
        : "/civicrm/event/info?reset=1&id=";

    $html = '<div class="civievent-widget civievent-widget-' . $wtheme . '">';
    if ($title) {
        $html .= '<h2 class="title civievent-widget-title">' . $title . "</h2>";
    }
    $html .= '<div class="civievent-widget-list">';
    $index = 0;
    foreach ($events as $event) {
        $location = $needs_location
            ? cib_fetch_event_location($event["id"])
            : [];
        $html .= cib_apply_event_template(
            $template,
            $event,
            $index,
            $image_field,
            $date_format,
            $time_format,
            $location,
            $event_page,
        );
        $index++;
    }
    $html .= "</div></div>";

    return $html;
}
