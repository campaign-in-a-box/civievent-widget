=== CiviEvent Widget ===
Contributors: Campaign/Cause-in-a-Box (CIB)
Tags: civicrm, events, event, nonprofit, crm, calendar
Requires at least: 5.0
Tested up to: 6.9
License: AGPLv3 or later
License URI: http://www.gnu.org/licenses/agpl-3.0.html

Display widgets for CiviCRM events: the next public event or a whole list.
Embed widgets as shortcodes, too!

== Description ==

This plugin was initially written by "Andie" from AGHStrategies (https://aghstrategies.com/). It has not been updated for 7 years. We had to fix a few issues with the formatting/php and add another feature, so we cloned it.

You can use the CiviEvent widget to add two types of widgets for upcoming public events from CiviCRM.  There's no limit to the number of widgets you can add of either type.  You can include the widgets in the sidebar like normal, or you can include them via shortcodes in the body of your posts.

= CiviEvent List Widget =

This widget is a basic, flexible listing of upcoming events that are marked as public.  You have options to customize the appearance and number of events.  There is the option to add the event's city, state, and/or country to the listing if "Show location" is enabled on the event.

= Single CiviEvent Widget =

This widget displays a single public event from CiviCRM.  By default, it will display the first event from the current day or the future, or you can set an offset to skip one or more and display the second or third upcoming event.  You may display the location if "Show location" is enabled on the event.

= Template Shortcodes =

Both shortcodes support a **template mode**: place your own HTML between the opening and closing tags and use [Smarty](https://smarty.net/) `{$event.*}` variables to insert event data.  No server-side files need to be edited.  Because the engine is CiviCRM's built-in Smarty instance, you can use the full Smarty syntax — `{if}`, `{foreach}`, custom modifiers, etc.

**Available variables:**

*Core fields*

| Variable | Description |
|---|---|
| `{$event.id}` | Event ID |
| `{$event.title}` | Event title (HTML-escaped) |
| `{$event.summary}` | Event summary (HTML allowed) |
| `{$event.description}` | Full event description (HTML allowed) |
| `{$event.register_url}` | URL of the online registration page |
| `{$event.registration_link_text}` | Registration button label from CiviCRM |
| `{$event.image}` | Image URL (empty if no image set) |
| `{$event.image_tag}` | Ready-to-use `<img>` tag (empty if no image) |
| `{$event.date_start}` | Formatted start date |
| `{$event.time_start}` | Formatted start time |
| `{$event.date_end}` | Formatted end date (blank when same day as start) |
| `{$event.time_end}` | Formatted end time |
| `{$event.index}` | 0-based position in the list (list widget only) |
| `{$event.class}` | `"even"` or `"odd"` (list widget only) |
| `{$event.register_buttons}` | Register + Read More buttons, respecting registration open/close dates; empty if registration is closed or not enabled |

*Calendar links*

| Variable | Description |
|---|---|
| `{$event.ical_url}` | iCal / .ics download URL |
| `{$event.gcal_url}` | Google Calendar "add event" URL (includes location when available) |
| `{$event.calendar_links}` | Pre-built iCal + Google Calendar buttons |

*Social sharing*

| Variable | Description |
|---|---|
| `{$event.share_twitter}` | X / Twitter share link |
| `{$event.share_facebook}` | Facebook share link |
| `{$event.share_linkedin}` | LinkedIn share link |
| `{$event.share_email}` | Email share link |
| `{$event.social_links}` | Pre-built row with all four share links |

*Location & map* — only populated when the event has "Show location" enabled in CiviCRM

| Variable | Description |
|---|---|
| `{$event.location_address}` | Street address |
| `{$event.location_city}` | City |
| `{$event.location_state}` | State / province abbreviation |
| `{$event.location_country}` | Country name |
| `{$event.location_postal}` | Postal / zip code |
| `{$event.location_full}` | Full address on one line |
| `{$event.map_url}` | Google Maps search URL |
| `{$event.map_link}` | Pre-built link to Google Maps |

**Event list — card layout (works great in Elementor / Astra):**

Paste this into an Elementor Shortcode widget.  The template uses `var(--ast-global-color-0)` so it automatically picks up your Astra primary colour; the fallback `#4169e1` keeps it sensible on any other theme.

```
[civievent_widget url="/event/event-single/"]
<div style="background:#fff;border-radius:12px;box-shadow:0 2px 16px rgba(0,0,0,.08);overflow:hidden;margin-bottom:24px;display:flex;flex-wrap:wrap;">
  <div style="flex:0 0 200px;min-height:150px;background:#e8e8e8;overflow:hidden;">
    <a href="{$event.url}" style="display:block;height:100%;">{$event.image_tag}</a>
  </div>
  <div style="flex:1;min-width:220px;padding:20px 24px;display:flex;flex-direction:column;gap:12px;">
    <div>
      <p style="margin:0 0 4px;font-size:.78em;font-weight:700;color:var(--ast-global-color-0,#4169e1);text-transform:uppercase;letter-spacing:.06em;">
        {$event.date_start} &bull; {$event.time_start}
        {if $event.time_end}
          - {$event.time_end}
        {/if}
      </p>
      <h3 style="margin:0 0 6px;font-size:1.1em;line-height:1.3;">
        <a href="{$event.url}" style="color:#1a1a1a;text-decoration:none;">{$event.title}</a>
      </h3>
      {if $event.location_full}<p style="margin:0 0 4px;font-size:.88em;color:#666;">{$event.location_full}</p>{/if}
      {if $event.summary}<p style="margin:0;font-size:.9em;color:#555;line-height:1.55;">{$event.summary}</p>{/if}
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      {if $event.register_url}<a href="{$event.register_url}" style="display:inline-block;padding:8px 20px;background:var(--ast-global-color-0,#4169e1);color:#fff;border-radius:6px;text-decoration:none;font-size:.85em;font-weight:600;">{$event.registration_link_text}</a>{/if}
      <a href="{$event.url}" style="display:inline-block;padding:8px 20px;border:1.5px solid var(--ast-global-color-0,#4169e1);color:var(--ast-global-color-0,#4169e1);border-radius:6px;text-decoration:none;font-size:.85em;font-weight:600;">More info</a>
      {$event.map_link}
      {$event.calendar_links}
    </div>
  </div>
</div>
[/civievent_widget]
```

**Single event hero (works great in Elementor / Astra):**

Paste this into an Elementor Shortcode widget on your event detail page.

```
[civievent_single_widget url="/event/event-single/"]
<div style="border-radius:16px;overflow:hidden;background:#fff;box-shadow:0 4px 28px rgba(0,0,0,.1);">
  {if $event.image_tag}
  <div style="position:relative;height:280px;overflow:hidden;background:#ddd;">
    <a href="{$event.url}" style="display:block;height:100%;">{$event.image_tag}</a>
    <div style="position:absolute;top:16px;left:16px;background:var(--ast-global-color-0,#4169e1);color:#fff;padding:7px 16px;border-radius:30px;font-size:.82em;font-weight:700;letter-spacing:.03em;">
      {$event.date_start} &bull; {$event.time_start}
      {if $event.time_end}
        - {$event.time_end}
      {/if}
    </div>
  </div>
  {/if}
  <div style="padding:28px 32px;display:flex;flex-direction:column;gap:20px;">
    <div>
      <h2 style="margin:0 0 6px;font-size:1.7em;line-height:1.25;">
        <a href="{$event.url}" style="color:#1a1a1a;text-decoration:none;">{$event.title}</a>
      </h2>
      <p style="margin:0 0 10px;font-size:.88em;color:var(--ast-global-color-0,#4169e1);font-weight:600;">
        {$event.date_start} &bull; {$event.time_start}{if $event.date_end} &mdash; {$event.date_end} {$event.time_end}{/if}
      </p>
      {if $event.location_full}<p style="margin:0 0 6px;font-size:.9em;color:#555;">{$event.location_full}</p>{/if}
      {if $event.summary}<p style="margin:0 0 10px;color:#555;font-size:1em;line-height:1.65;">{$event.summary}</p>{/if}
      {if $event.description}<p style="margin:0;color:#555;font-size:1em;line-height:1.65;">{$event.description}</p>{/if}
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      {if $event.register_url}<a href="{$event.register_url}" style="display:inline-block;padding:12px 28px;background:var(--ast-global-color-0,#4169e1);color:#fff;border-radius:8px;text-decoration:none;font-size:.95em;font-weight:700;">{$event.registration_link_text}</a>{/if}
      <a href="/events/" style="display:inline-block;padding:12px 28px;border:2px solid var(--ast-global-color-0,#4169e1);color:var(--ast-global-color-0,#4169e1);border-radius:8px;text-decoration:none;font-size:.95em;font-weight:700;">See all events</a>
      {$event.map_link}
    </div>
    <div>
      <p style="margin:0 0 8px;font-size:.85em;font-weight:600;color:#555;">Add to calendar:</p>
      {$event.calendar_links}
    </div>
    <div>
      <p style="margin:0 0 8px;font-size:.85em;font-weight:600;color:#555;">Share this event:</p>
      {$event.social_links}
    </div>
  </div>
</div>
[/civievent_single_widget]
```

When no inner template is provided the widgets use a built-in default template that reproduces the original layout.

= Shortcodes (built-in layout) =

Both widgets are also available without a template.  Use the `[civievent_widget]` shortcode for the events listing and the `[civievent_single_widget]` shortcode for the single next (or offset) event.  The available parameters are as follows:

- "title="Your Title""
  The widget title (default: "Upcoming Events" for the list widget, or the event's title for the single widget).
- "summary=1"
  Display the event summary.  Omit the parameter or set it to 0 to hide the summary.
  (List widget only.)
- "limit=5"
  Display the specified number of events (default: 5).
  (List widget only.)*
- "alllink=1"
  Display "view all" with a link to the page with a full list of public events.  Omit the parameter or set it to 0 to hide the link.
  (List widget only.)*
- "wtheme="mytheme""
   The widget theme (a class added to the widget div).
   Set a new one and handle it in your theme's CSS.
   (Default for list widget: "stripe", with "divider" as an alternative.  Default for single widget: "standard".)
- "divider="
   The location field delimiter (default: comma followed by a space).
- "city=1"
  Display the event's city.
  Omit the parameter or set it to 0 to hide the city.
- "state=abbreviate"
  Display the event's state/province.
  Default is "none", which will display nothing about the state or province.
  Display options are "abbreviate" for the state/province abbreviation or "full" for the full name.
- "country=1"
  Display the event's country.
  Omit the parameter or set it to 0 to hide the country.
- "offset=2"
  Skip the given number of events before displaying the next one (default: 0).
  (Single widget only.)
- "admin_type=simple"
  Whether to use the "simple" (default) or "custom" display options (as appear in the widget settings).
  The `custom_display` and `custom_filter` parameters only function alongside `admin_type="custom"`.
  The `summary`, `alllink`, `divider`, `city`, `state`, and `country` parameters only function when `admin_type="simple"`
  (or reverting to the default)
  (List widget only.)*
- "custom_display='{"event_title_infolink":{"title":0,"prefix":null,"suffix":null,"wrapper":1},"description":{"title":1,"prefix":null,"suffix":null,"wrapper":1}}'"
  Custom options for displaying results when `admin_type="custom"`.
  The value should be an object written in JSON.
  Each property name should be a field to display, and the property value should be an object with the following properties:
    `title` (1 or 0: whether to display the field name)
    `prefix` (`null` or a string with markup to precede the field)
    `suffix` (`null` or a string with markup to follow the field)
    `wrapper` (1 or 0: whether to wrap the field with the default wrapper elements.
  You may configure a widget using the standard widget interface, click "Show JSON", and copy the JSON into this parameter.
  If `custom_display` is missing, the listing will revert to displaying in the "simple" mode despite the `admin_type` value.
  (List widget only.)
- "custom_filter='{"start_date": {">=": "2015-12-16"}, "is_public": 1, "options": {"sort": "start_date ASC"}}'"
  Custom options for filtering results when `admin_type="custom"`.
  The value should be an object written in JSON.
  The object should be a valid set of parameters for the CiviCRM API.
  The default is to list all public events starting on today's date or later, sorted by start date ascending.
  (List widget only.)
- "event_type_id=3"
  (default: show all event).
  Display Event with event type id 3
  (only work with admin_type="simple".)
- "metatags=yes"
  (default: yes).
  add open graph information to the head of the HTML, including "meta name=description" and "meta property=og:XYZ"
  (Single widget only.)

== Installation ==

1. Upload `civievent-widget` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to the 'Widgets' page in WordPress to add and configure one or more widgets.
1. Insert shortcodes into posts or pages as appropriate.

== Frequently Asked Questions ==

= What's CiviCRM? =

CiviCRM is the leading open-source constituent relationship management (CRM) system for nonprofits.  This plugin *is not* CiviCRM, but you can read all about and download CiviCRM at http://civicrm.org.  Free to download, free to install and use, free to share, and free to modify, CiviCRM is a great solution for not-for-profit and charitable organizations looking to track donors, event participants, case clients, members, and more.

= Why does this plugin exist? =

CiviCRM provides full pages of info on single events, plus a poorly-documented page listing all public upcoming events, but there's no simple widget for listing the events in the WordPress sidebar or as a shortcode that doesn't overwhelm your page content.

= Why are my widget's links not working right? =

Go into CiviCRM and visit the Manage Events page in the Events menu.  Check out the event links there--most likely they are identical to what the widget provides.  If the widget's links cause you trouble, you probably have fundamental problems with your CiviCRM installation: the widgets just use CiviCRM to provide links.

= What's all this about themes? =

You might want to have different CiviEvent widgets on your site look different.  Setting the "theme" in the widget settings or the shortcode doesn't pick a different site theme, but it adds a class to your widget.  Using one of the built-in theme options will provide a straightforward display, or you can create your own: just type something new as the widget theme and then add CSS in your site's theme to handle it.  The plugin was built from the perspective that while the widget should look reasonable out-of-the-box, most sites who care strongly about the widget's appearance will already be implementing a lot of custom CSS.  There's no need for the widget to come with a lot of heavy-handed theming.

= How does the Custom API Filter work? =

You can write a bit of JSON to filter your results for the CiviEvent List Widget in Custom mode.  This uses the syntax for the CiviCRM API.  For example, to only include events with online registration enabled, enter `{"is_online_registration": 1}` in the Custom API Filter field.  By default, results have the `event_start_date` greater than or equal to today and have `is_public` equal to 1.  You can override these.

You can also adjust the limit, sort, or offset by adding items under `options`.  For example, `{"is_online_registration": 1, "options": {"sort": "title ASC", "limit": 3, "offset": 4}}` will display the fifth, sixth, and seventh events in order of title.

**Note:** CiviCRM's API takes JSON arrays in some cases.  A JSON array is denoted by square brackets.  A shortcode is denoted by square brackets.  If you use the `custom_filter` shortcode parameter to set a custom API filter, you'll have trouble if you use square brackets for arrays.  As a workaround, write arrays as objects with sequentially numbered properties: `{"0": "First Thing", "1": "Second Thing"}` instead of `["First Thing","Second Thing"]`.


== Changelog ==

Please see the file changelog.txt.
