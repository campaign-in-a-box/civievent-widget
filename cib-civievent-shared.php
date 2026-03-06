<?php

/**
 * Shared utility functions for CIB CiviEvent widgets.
 *
 * Included by cib-civievent-widget.php
 */

/**
 * Fetch address data for an event's location block.
 *
 * Only queries CiviCRM when the event has "Show location" enabled.
 *
 * @param int $event_id CiviCRM event ID.
 * @return array Keys: address, city, state, country, postal, full. Empty array if no location.
 */
function cib_fetch_event_location($event_id)
{
    try {
        $ev = \Civi\Api4\Event::get(false)
            ->addSelect("loc_block_id", "is_show_location")
            ->addWhere("id", "=", intval($event_id))
            ->execute()
            ->first();
        if (empty($ev["is_show_location"]) || empty($ev["loc_block_id"])) {
            return [];
        }
        $loc_block = \Civi\Api4\LocBlock::get(false)
            ->addSelect("address_id")
            ->addWhere("id", "=", $ev["loc_block_id"])
            ->execute()
            ->first();
        if (empty($loc_block["address_id"])) {
            return [];
        }
        $addr = \Civi\Api4\Address::get(false)
            ->addSelect(
                "street_address",
                "supplemental_address_1",
                "city",
                "postal_code",
                "state_province_id.abbreviation",
                "country_id.name",
            )
            ->addWhere("id", "=", $loc_block["address_id"])
            ->execute()
            ->first();
        if (empty($addr)) {
            return [];
        }
    } catch (\CRM_Core_Exception $e) {
        return [];
    }

    $parts = array_filter([
        $addr["street_address"] ?? "",
        $addr["supplemental_address_1"] ?? "",
        $addr["city"] ?? "",
        $addr["state_province_id.abbreviation"] ?? "",
        $addr["postal_code"] ?? "",
        $addr["country_id.name"] ?? "",
    ]);

    return [
        "address" => $addr["street_address"] ?? "",
        "city" => $addr["city"] ?? "",
        "state" => $addr["state_province_id.abbreviation"] ?? "",
        "country" => $addr["country_id.name"] ?? "",
        "postal" => $addr["postal_code"] ?? "",
        "full" => implode(", ", $parts),
    ];
}

/**
 * Resolve a CiviCRM custom field label to its APIv4 select key (GroupName.field_name).
 *
 * @param string $label The custom field label (e.g. 'cibapp_Image_Link').
 * @return string APIv4 field key like 'CIB_Event.cibapp_Image_Link', or '' if not found.
 */
function cib_resolve_image_field($label)
{
    if (empty($label)) {
        return "";
    }
    try {
        $cf = \Civi\Api4\CustomField::get(false)
            ->addSelect("name", "custom_group_id.name")
            ->addWhere("label", "=", $label)
            ->execute()
            ->first();
        if (!empty($cf["name"]) && !empty($cf["custom_group_id.name"])) {
            return $cf["custom_group_id.name"] . "." . $cf["name"];
        }
    } catch (\CRM_Core_Exception $e) {
        // Field not found; return empty.
    }
    return "";
}

/**
 * Return true if $template uses any variable that requires a location API lookup.
 *
 * @param string $template Smarty template string.
 * @return bool
 */
function cib_template_needs_location($template)
{
    foreach (['$event.location_', '$event.map_'] as $needle) {
        if (strpos($template, $needle) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Render a Smarty template string for one CiviCRM event.
 *
 * The template receives a single Smarty variable `$event` whose keys are:
 *
 *   Core fields
 *   $event.id                     – event ID
 *   $event.title                  – event title (HTML-escaped)
 *   $event.summary                – event summary (safe HTML allowed)
 *   $event.description            – event description (safe HTML allowed)
 *   $event.url                    – URL of the event detail page
 *   $event.register_url           – URL of the online registration page
 *   $event.registration_link_text – registration button label
 *   $event.image                  – image URL (empty string if no image)
 *   $event.image_tag              – ready-to-use <img> tag (empty string if no image)
 *   $event.date_start             – formatted start date
 *   $event.time_start             – formatted start time
 *   $event.date_end               – formatted end date (blank when same day as start)
 *   $event.time_end               – formatted end time
 *   $event.index                  – 0-based position in the event list
 *   $event.class                  – "even" or "odd"
 *
 *   Calendar
 *   $event.ical_url               – iCal / .ics download URL
 *   $event.gcal_url               – Google Calendar "add event" URL
 *   $event.calendar_links         – pre-built iCal + Google Calendar buttons
 *
 *   Social sharing
 *   $event.share_twitter          – X / Twitter share link
 *   $event.share_facebook         – Facebook share link
 *   $event.share_linkedin         – LinkedIn share link
 *   $event.share_email            – Email share link
 *   $event.social_links           – pre-built row with all four share links
 *
 *   Location & map  (only populated when $location is provided)
 *   $event.location_address       – street address
 *   $event.location_city          – city
 *   $event.location_state         – state / province abbreviation
 *   $event.location_country       – country name
 *   $event.location_postal        – postal / zip code
 *   $event.location_full          – full address on one line
 *   $event.map_url                – Google Maps search URL
 *   $event.map_link               – pre-built link to Google Maps
 *
 *   Layout helpers (pre-rendered HTML; empty when not applicable)
 *   $event.register_buttons       – Register + Read More buttons
 *
 * @param string $template    Smarty template string.
 * @param array  $event       CiviCRM event data array.
 * @param int    $index       0-based position in the event list.
 * @param string $image_field CiviCRM custom field key, e.g. "custom_7".
 * @param string $date_format PHP date() format string for dates.
 * @param string $time_format PHP date() format string for times.
 * @param array  $location    Location data from cib_fetch_event_location(); pass [] to skip.
 * @return string Rendered HTML for this event.
 */
function cib_apply_event_template(
    $template,
    $event,
    $index = 0,
    $image_field = "",
    $date_format = "",
    $time_format = "",
    $location = [],
    $event_page = "/civicrm/event/info?reset=1&id=",
) {
    $event_id = intval($event["id"]);
    $url = $event_page . $event_id;
    $reg_url = CRM_Utils_System::url(
        "civicrm/event/register",
        "reset=1&id=$event_id",
    );

    $date_format = $date_format ?: "d M Y";
    $time_format = $time_format ?: "g:i A";

    // ── Dates ────────────────────────────────────────────────────────────────────
    $date_start = $time_start = $date_end = $time_end = "";
    $gcal_start = $gcal_end = "";
    if (!empty($event["start_date"])) {
        $dt = new DateTime($event["start_date"]);
        $date_start = $dt->format($date_format);
        $time_start = $dt->format($time_format);
        $gcal_start = $gcal_end = $dt->format("Ymd\THis");
    }
    if (!empty($event["end_date"])) {
        $dt = new DateTime($event["end_date"]);
        $date_end = $dt->format($date_format);
        $time_end = $dt->format($time_format);
        $gcal_end = $dt->format("Ymd\THis");
        if ($date_end === $date_start) {
            $date_end = "";
        }
    }

    // ── Image ────────────────────────────────────────────────────────────────────
    $image_url =
        $image_field && !empty($event[$image_field])
            ? $event[$image_field]
            : "";
    $image_tag = $image_url
        ? '<img src="' .
            esc_url($image_url) .
            '" alt="' .
            esc_attr($event["title"] ?? "") .
            '" style="width:100%;height:100%;object-fit:cover;display:block;" />'
        : "";

    // ── Calendar ─────────────────────────────────────────────────────────────────
    $ical_url = $gcal_url = $calendar_links = "";
    if ($event["is_show_calendar_links"] ?? false) {
        $ical_url = CRM_Utils_System::url(
            "civicrm/event/ical",
            "reset=1&id=$event_id",
        );
        $gcal_params = [
            "action" => "TEMPLATE",
            "text" => $event["title"] ?? "",
            "dates" => $gcal_start . "/" . $gcal_end,
            "details" => wp_strip_all_tags($event["summary"] ?? ""),
        ];
        if ($event["is_show_location"] && !empty($location["full"])) {
            $gcal_params["location"] = $location["full"];
        }
        $gcal_url =
            "https://www.google.com/calendar/render?" .
            http_build_query($gcal_params);

        $calendar_links =
            '<a href="' .
            esc_url($ical_url) .
            '" class="button btn"><span>&#x1F4C5; Download iCal</span></a>' .
            '<a href="' .
            esc_url($gcal_url) .
            '" target="_blank" rel="noopener" class="button btn"><span>&#x1F4C6; Add to Google Calendar</span></a>';
    }

    // ── Social sharing ────────────────────────────────────────────────────────────
    $enc_url = rawurlencode($url);
    $enc_title = rawurlencode($event["title"] ?? "");
    $enc_text = rawurlencode(wp_strip_all_tags($event["summary"] ?? ""));

    $share_twitter =
        '<a href="https://x.com/intent/tweet?url=' .
        $enc_url .
        "&amp;text=" .
        $enc_title .
        '" target="_blank" rel="noopener" class="button btn"><span>X / Twitter</span></a>';
    $share_facebook =
        '<a href="https://www.facebook.com/sharer/sharer.php?u=' .
        $enc_url .
        '" target="_blank" rel="noopener" class="button btn"><span>Facebook</span></a>';
    $share_linkedin =
        '<a href="https://www.linkedin.com/shareArticle?mini=true&amp;url=' .
        $enc_url .
        "&amp;title=" .
        $enc_title .
        '" target="_blank" rel="noopener" class="button btn"><span>LinkedIn</span></a>';
    $share_email =
        '<a href="mailto:?subject=' .
        rawurlencode($event["title"] ?? "") .
        "&amp;body=" .
        $enc_text .
        "%0A" .
        $enc_url .
        '" class="button btn"><span>Email</span></a>';
    $social_links =
        $event["is_share"] ?? false
            ? $share_twitter . $share_facebook . $share_linkedin . $share_email
            : "";

    // ── Location & map ────────────────────────────────────────────────────────────
    $map_url = "";
    $map_link = "";
    if ($event["is_map"] ?? false) {
        if (!empty($location["full"])) {
            $map_url =
                "https://www.google.com/maps/search/?api=1&query=" .
                rawurlencode($location["full"]);
            $map_link =
                '<a href="' .
                esc_url($map_url) .
                '" target="_blank" rel="noopener" class="button btn"><span>&#x1F4CD; Map</span></a>';
        }
    }

    // ── Register buttons (replicates regFix()) ────────────────────────────────────
    $register_buttons = "";
    if (
        !empty($event["is_online_registration"]) &&
        (empty($event["registration_start_date"]) ||
            strtotime($event["registration_start_date"]) <= time()) &&
        (empty($event["registration_end_date"]) ||
            strtotime($event["registration_end_date"]) > time())
    ) {
        $link_text = esc_html($event["registration_link_text"] ?? "Register");
        $register_buttons .=
            " <a href='" .
            esc_url($reg_url) .
            "' title='Register Now' class='button btn'><span>" .
            $link_text .
            "</span></a>";
    }

    // ── Render via CiviCRM's Smarty instance ─────────────────────────────────────
    $event_data = [
        "id" => $event_id,
        "title" => esc_html($event["title"] ?? ""),
        "summary" => wp_kses_post($event["summary"] ?? ""),
        "description" => wp_kses_post($event["description"] ?? ""),
        "url" => esc_url($url),
        "register_url" => !empty($event["is_online_registration"])
            ? esc_url($reg_url)
            : "",
        "registration_link_text" => esc_html(
            $event["registration_link_text"] ?? "Register",
        ),
        "image" => esc_url($image_url),
        "image_tag" => $image_tag,
        "date_start" => esc_html($date_start),
        "time_start" => esc_html($time_start),
        "date_end" => esc_html($date_end),
        "time_end" => esc_html($time_end),
        "index" => $index,
        "class" => $index % 2 === 0 ? "even" : "odd",
        "ical_url" => esc_url($ical_url),
        "gcal_url" => esc_url($gcal_url),
        "calendar_links" => $calendar_links,
        "share_twitter" => $share_twitter,
        "share_facebook" => $share_facebook,
        "share_linkedin" => $share_linkedin,
        "share_email" => $share_email,
        "social_links" => $social_links,
        "location_address" =>
            $event["is_show_location"] ?? false
                ? esc_html($location["address"] ?? "")
                : "",
        "location_city" =>
            $event["is_show_location"] ?? false
                ? esc_html($location["city"] ?? "")
                : "",
        "location_state" =>
            $event["is_show_location"] ?? false
                ? esc_html($location["state"] ?? "")
                : "",
        "location_country" =>
            $event["is_show_location"] ?? false
                ? esc_html($location["country"] ?? "")
                : "",
        "location_postal" =>
            $event["is_show_location"] ?? false
                ? esc_html($location["postal"] ?? "")
                : "",
        "location_full" =>
            $event["is_show_location"] ?? false
                ? esc_html($location["full"] ?? "")
                : "",
        "map_url" => esc_url($map_url),
        "map_link" => $map_link,
        "register_buttons" => $register_buttons,
    ];

    $smarty = CRM_Core_Smarty::singleton();
    $smarty->assign("event", $event_data);
    $html = $smarty->fetch("string:" . $template);
    $smarty->clearAssign("event");
    return $html;
}
