<?php

/**
 * Shared utility functions for CIB CiviEvent widgets.
 *
 * Included by cib-civievent-widget.php
 */

/**
 * Parse a shortcode boolean attribute (WordPress passes all values as strings).
 *
 * Uses array_key_exists so explicit "0" / "false" are not treated as missing.
 *
 * @param array  $atts    Shortcode attributes.
 * @param string $key     Attribute name (lowercase).
 * @param bool   $default Default when the key is absent.
 * @return bool
 */
function cib_civievent_parse_bool(array $atts, $key, $default = false)
{
    if (!array_key_exists($key, $atts)) {
        return $default;
    }
    return filter_var($atts[$key], FILTER_VALIDATE_BOOLEAN);
}

/**
 * Today's date in the WordPress site timezone (Y-m-d).
 *
 * @return string
 */
function cib_civievent_today_ymd()
{
    return function_exists("wp_date")
        ? wp_date("Y-m-d")
        : current_time("Y-m-d");
}

/**
 * Build a base APIv4 Event query for public widget listings.
 *
 * @param list<string> $select_fields Fields to select.
 * @param int          $event_type_id Optional event type filter (0 = all).
 * @return \Civi\Api4\Generic\DAOGetAction
 */
function cib_civievent_base_event_query(
    array $select_fields,
    $event_type_id = 0,
) {
    $query = \Civi\Api4\Event::get(false)
        ->addSelect(...$select_fields)
        ->addWhere("is_public", "=", true)
        ->addWhere("is_active", "=", true)
        ->addWhere("is_template", "=", false);
    if ($event_type_id) {
        $query->addWhere("event_type_id", "=", $event_type_id);
    }
    return $query;
}

/**
 * Fetch public events for the list/calendar widget.
 *
 * Upcoming-only mode returns the next $limit events from today onward.
 * When past events are included, the most recent past and nearest future events
 * are merged so the limit is not consumed by the oldest rows in the database.
 *
 * @param list<string> $select_fields Fields to select.
 * @param int          $limit         Maximum number of events.
 * @param bool         $upcoming_only When true, only events starting today or later.
 * @param int          $event_type_id Optional event type filter (0 = all).
 * @return list<array<string,mixed>>
 */
function cib_civievent_fetch_widget_events(
    array $select_fields,
    $limit,
    $upcoming_only,
    $event_type_id = 0,
) {
    $limit = max(1, (int) $limit);
    $today = cib_civievent_today_ymd();

    if ($upcoming_only) {
        return cib_civievent_base_event_query($select_fields, $event_type_id)
            ->addWhere("start_date", ">=", $today)
            ->addOrderBy("start_date", "ASC")
            ->setLimit($limit)
            ->execute()
            ->getArrayCopy();
    }

    $past_limit = (int) ceil($limit / 2);
    $future_limit = $limit - $past_limit;

    $past_events = cib_civievent_base_event_query(
        $select_fields,
        $event_type_id,
    )
        ->addWhere("start_date", "<", $today)
        ->addOrderBy("start_date", "DESC")
        ->setLimit($past_limit)
        ->execute()
        ->getArrayCopy();

    $future_events = cib_civievent_base_event_query(
        $select_fields,
        $event_type_id,
    )
        ->addWhere("start_date", ">=", $today)
        ->addOrderBy("start_date", "ASC")
        ->setLimit($future_limit)
        ->execute()
        ->getArrayCopy();

    $events = array_merge($past_events, $future_events);
    usort($events, static function (array $a, array $b): int {
        return strcmp(
            (string) ($a["start_date"] ?? ""),
            (string) ($b["start_date"] ?? ""),
        );
    });

    return $events;
}

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
 * Covers address fields (`location_*`), map (`map_*`), and Google Calendar URLs
 * (`gcal_url`, `calendar_links`), which embed venue text when the event shows location.
 *
 * @param string $template Smarty/Twig-style template source string.
 * @return bool
 */
function cib_template_needs_location($template)
{
    static $needles = [
        '$event.location_',
        '$event.map_',
        "event.location_",
        "event.map_",
        '{$event.location_',
        '{$event.map_',
        "gcal_url",
        "calendar_links",
    ];
    foreach ($needles as $needle) {
        if (strpos($template, $needle) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Parse default placeholder image URL(s) from shortcode attributes.
 *
 * - `default_image` — single absolute URL.
 * - `default_images` — several URLs separated by `|` or newlines (use when URLs contain commas).
 *
 * @param array $atts Shortcode attributes.
 * @return list<string> Sanitized URL list (may be empty).
 */
function cib_civievent_parse_default_image_pool(array $atts)
{
    $urls = [];
    if (!empty($atts["default_images"])) {
        $parts = preg_split(
            '/[\r\n|]+/',
            (string) $atts["default_images"],
            -1,
            PREG_SPLIT_NO_EMPTY,
        );
        foreach ($parts as $p) {
            $u = esc_url_raw(trim($p));
            if ($u !== "") {
                $urls[] = $u;
            }
        }
    }
    if ($urls === [] && !empty($atts["default_image"])) {
        $u = esc_url_raw(trim((string) $atts["default_image"]));
        if ($u !== "") {
            $urls[] = $u;
        }
    }
    $urls = array_values(array_unique($urls));

    /**
     * Filter the list of fallback image URLs when the event has no Civi image.
     *
     * @param list<string> $urls Sanitized URLs.
     * @param array        $atts Shortcode attributes.
     */
    return apply_filters("cib_civievent_default_image_pool", $urls, $atts);
}

/**
 * Build the `$event` context array for Smarty (core fields, calendar links, social, location, map,
 * register buttons). Use with `cib_civievent_smarty_fetch()` and assign under the `event` key
 * when rendering a per-event fragment.
 *
 * @param array       $event       CiviCRM event data array.
 * @param int         $index       0-based position in the event list.
 * @param string      $image_field CiviCRM custom field key, e.g. "custom_7".
 * @param string      $date_format PHP date() format string for dates.
 * @param string      $time_format PHP date() format string for times.
 * @param array       $location    Location data from cib_fetch_event_location(); pass [] to skip.
 * @param string      $event_page  Prefix for event info URLs.
 * @param list<string> $default_image_pool Fallback image URLs when Civi has none (stable pick per event).
 * @return array<string,mixed>
 */
function cib_build_event_context(
    $event,
    $index = 0,
    $image_field = "",
    $date_format = "",
    $time_format = "",
    $location = [],
    $event_page = "/civicrm/event/info?reset=1&id=",
    $default_image_pool = [],
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

    $show_location = !empty($event["is_show_location"]);

    // ── Image ────────────────────────────────────────────────────────────────────
    $civi_image =
        $image_field !== "" && !empty($event[$image_field])
            ? $event[$image_field]
            : "";
    $image_url = $civi_image !== "" ? $civi_image : "";
    $image_is_default = false;
    if ($image_url === "" && $default_image_pool !== []) {
        $n = count($default_image_pool);
        $pick =
            $n === 1
                ? 0
                : (int) (abs(
                    crc32((string) $event_id . "|" . (string) $index),
                ) % $n);
        $image_url = $default_image_pool[$pick];
        $image_is_default = true;
    }
    $image_tag = $image_url
        ? '<img src="' .
            esc_url($image_url) .
            '" alt="' .
            esc_attr($event["title"] ?? "") .
            '" style="width:100%;height:100%;object-fit:cover;display:block;"' .
            ($image_is_default
                ? ' class="civievent-widget-image-default"'
                : "") .
            " />"
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
        if ($show_location && !empty($location["full"])) {
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

    // ── Register link/buttons (same window as Civi regFix: online reg + date range) ─
    $now = time();
    $registration_window_ok =
        (empty($event["registration_start_date"]) ||
            strtotime((string) $event["registration_start_date"]) <= $now) &&
        (empty($event["registration_end_date"]) ||
            strtotime((string) $event["registration_end_date"]) > $now);
    $registration_open =
        !empty($event["is_online_registration"]) && $registration_window_ok;

    $register_buttons = "";
    if ($registration_open) {
        $link_text = esc_html($event["registration_link_text"] ?? "Register");
        $register_buttons .=
            " <a href='" .
            esc_url($reg_url) .
            "' title='Register Now' class='button btn'><span>" .
            $link_text .
            "</span></a>";
    }

    return [
        "id" => $event_id,
        "title" => esc_html($event["title"] ?? ""),
        "summary" => wp_kses_post($event["summary"] ?? ""),
        "description" => wp_kses_post($event["description"] ?? ""),
        "url" => esc_url($url),
        "register_url" => $registration_open ? esc_url($reg_url) : "",
        "registration_link_text" => esc_html(
            $event["registration_link_text"] ?? "Register",
        ),
        "image" => esc_url($image_url),
        "image_tag" => $image_tag,
        "image_is_default" => $image_is_default,
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
        "location_address" => $show_location
            ? esc_html($location["address"] ?? "")
            : "",
        "location_city" => $show_location
            ? esc_html($location["city"] ?? "")
            : "",
        "location_state" => $show_location
            ? esc_html($location["state"] ?? "")
            : "",
        "location_country" => $show_location
            ? esc_html($location["country"] ?? "")
            : "",
        "location_postal" => $show_location
            ? esc_html($location["postal"] ?? "")
            : "",
        "location_full" => $show_location
            ? esc_html($location["full"] ?? "")
            : "",
        "map_url" => esc_url($map_url),
        "map_link" => $map_link,
        "register_buttons" => $register_buttons,
    ];
}
