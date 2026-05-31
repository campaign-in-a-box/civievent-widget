<?php

/*
Plugin Name: CIB CiviEvent Widget
Description: CIB CiviEvent Widget plugin displays public CiviCRM events in a widget.
Version: 5.2.2
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
 *  - limit int The number of events (default: 100),
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
 *  - empty_message string text shown when there are no events (default: "No upcoming events.")
 *  - default_image string optional absolute URL used when the event has no Civi image field value.
 *  - default_images string optional several absolute URLs separated by | or newlines; one is chosen
 *    per event (stable hash by event id) when there is no Civi image. Ignores default_image when non-empty.
 *  - style string use "calendar-month" for a navigable monthly calendar with event popups
 *    (Smarty via CiviCRM).
 *  - upcoming_only bool when true (default), only events starting on or after today; set false
 *    (e.g. upcoming_only="0") to include past events — use with calendar-month to browse prior months.
 *    When false, up to half of limit are recent past events and the rest are upcoming (see limit).
 *
 * Most shortcode booleans default to false unless noted above.
 *
 * @return string The widget to drop into the post body.
 */

add_shortcode("civievent_widget", "civievent_widget_shortcode");
/**
 * [civievent_widget] shortcode: list (default) or `style="calendar-month"`.
 *
 * @param array  $atts    Shortcode attributes.
 * @param string $content Optional Smarty override for the full widget template (list or calendar-month).
 * @return string HTML.
 */
function civievent_widget_shortcode($atts, $content = null)
{
    if (!function_exists("civicrm_initialize")) {
        return "";
    }
    civicrm_initialize();

    $atts = is_array($atts) ? $atts : [];

    $style = isset($atts["style"]) ? sanitize_text_field($atts["style"]) : "";

    $default_tpl =
        __DIR__ .
        "/templates/" .
        ($style === "calendar-month" ? "calendar-month.html" : "list.html");
    $template_source = !empty($content)
        ? $content
        : (is_readable($default_tpl)
            ? file_get_contents($default_tpl)
            : "");

    $limit = isset($atts["limit"]) ? max(1, intval($atts["limit"])) : 100;
    $upcoming_only = cib_civievent_parse_bool($atts, "upcoming_only", true);

    $empty_message =
        isset($atts["empty_message"]) && $atts["empty_message"] !== ""
            ? sanitize_text_field($atts["empty_message"])
            : __("No upcoming events.", "cib-civievent-widget");
    $image_field_label = !empty($atts["image_field"])
        ? sanitize_text_field($atts["image_field"])
        : "cibapp_Image_Link";
    $event_type_id = isset($atts["event_type_id"])
        ? intval($atts["event_type_id"])
        : 0;
    $image_field = cib_resolve_image_field($image_field_label);
    $default_image_pool = cib_civievent_parse_default_image_pool($atts);

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
    if ($image_field !== "") {
        $select_fields[] = $image_field;
    }

    if (!$upcoming_only && $style === "calendar-month" && $limit < 500) {
        $limit = 500;
    }

    try {
        $civi_events = cib_civievent_fetch_widget_events(
            $select_fields,
            $limit,
            $upcoming_only,
            $event_type_id,
        );
    } catch (\CRM_Core_Exception $e) {
        CRM_Core_Error::debug_log_message(
            "cib-civievent-widget: " . $e->getMessage(),
        );
        return "";
    }

    $date_format = get_option("date_format");
    $time_format = get_option("time_format");

    wp_enqueue_style("civievent-widget-Stylesheet");

    $wtheme = sanitize_html_class(
        !empty($atts["wtheme"]) ? $atts["wtheme"] : "stripe",
    );

    $title = !empty($atts["title"]) ? $atts["title"] : "";

    $needs_location =
        $template_source !== "" &&
        cib_template_needs_location($template_source);
    $event_page = !empty($atts["url"])
        ? $atts["url"]
        : "/civicrm/event/info?reset=1&id=";

    $uid = wp_unique_id("civievent-cal-");

    // Enriched rows for Smarty; calendar-month uses `$payload_json` for the grid script.
    $events = [];
    $index = 0;
    foreach ($civi_events as $event) {
        $location = $needs_location
            ? cib_fetch_event_location($event["id"])
            : [];
        $events[] = cib_build_event_context(
            $event,
            $index,
            $image_field,
            $date_format,
            $time_format,
            $location,
            $event_page,
            $default_image_pool,
        );
        $index++;
    }

    $start_of_week = intval(get_option("start_of_week", "0"));
    $payload_json = wp_json_encode(
        [
            "uid" => (string) $uid,
            "events" => array_map(static function (array $e) {
                return [
                    "id" => (int) ($e["id"] ?? 0),
                    "start" => (string) ($e["start_date"] ?? ""),
                    "title" => (string) ($e["title"] ?? ""),
                ];
            }, $civi_events),
            "startOfWeek" => $start_of_week,
        ],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
    );

    $widget_context = [
        "uid" => $uid,
        "wtheme" => $wtheme,
        "title" => $title,
        "empty_message" => $empty_message,
        "events" => $events,
        "payload_json" => $payload_json,
    ];

    $smarty = CRM_Core_Smarty::singleton();
    return $smarty->fetchWith("string:" . $template_source, $widget_context);
}
