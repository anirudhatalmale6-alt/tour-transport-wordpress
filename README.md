# Tour & Transport — WordPress plugins

Two self-contained plugins built for a tour and transport agency site. Neither
depends on a particular theme or page builder, so they drop into any WordPress
install and keep working if the theme is changed later.

| Plugin | What it does |
| --- | --- |
| `tt-bookings` | Three booking forms (Airport Transfer, Tour, Hire a Vehicle), each storing the request in wp-admin, confirming it to the customer by email and forwarding it to the agency inbox. |
| `tt-promotions` | A Promotions section in wp-admin — image, short text, price — that feeds the Promotions page and a sidebar widget automatically. |

Requires WordPress 6.0+ and PHP 7.4+.

## Install

Upload each folder to `wp-content/plugins/` (or install the ZIP through
**Plugins → Add New → Upload Plugin**) and activate it.

## Tour & Transport Bookings

### Putting a form on a page

| Shortcode | Result |
| --- | --- |
| `[tt_booking_forms]` | All three forms behind tabs. |
| `[tt_booking_form type="airport"]` | Airport Transfer only. |
| `[tt_booking_form type="tour"]` | Tour only. |
| `[tt_booking_form type="hire"]` | Hire a Vehicle only. |

`[tt_booking_forms]` also accepts `types="airport,tour"` to limit which tabs
appear, and `default="tour"` to choose the one opened first.

### What each form asks

Defined in one place — `includes/class-ttb-fields.php`. Adding, removing or
re-labelling a question is a single edit there; the form, the validation, the
admin screen, both emails and the CSV export all follow automatically.

* **Airport Transfer** — direction, pickup and drop-off, date, time, flight
  number, return leg, passengers, suitcases, child seats, vehicle class.
* **Tour** — tour or destination, date, duration, start time, adults, children,
  guide language, hotel pickup, dietary requirements.
* **Hire a Vehicle** — vehicle type, with or without driver, pickup and return
  date/time, locations, passengers, driver age and licence country (self-drive
  only), extras.

All three then ask for name, email, phone, country and free-text notes.

### Settings

**Bookings → Settings**

* Which inbox receives requests (several addresses allowed).
* The `From` address, company name, phone, brand colour and email logo.
* Subject lines, with `{reference}`, `{type}`, `{name}` and `{company}` tags.
* The consent checkbox wording — blank removes it.
* A switch per form, so one can be taken offline without editing any page.

**Bookings → Export & Test** downloads submissions as CSV and sends a sample
confirmation so deliverability can be checked before launch.

### Deliverability

The plugin sends through `wp_mail()`, so it uses whatever the site is configured
with. For mail that reliably reaches an inbox rather than a spam folder:

1. Send through authenticated SMTP, not PHP `mail()`.
2. Keep the `From` address on the site's own domain. The settings screen warns
   when it is not — a `From` on someone else's domain is treated as forged.
3. Publish SPF and DKIM records for that domain, and a DMARC record.

The customer's address goes in `Reply-To` on the agency email, so replying from
the inbox answers the customer directly.

### Spam and abuse handling

* A hidden honeypot field.
* A time trap — anything submitted within three seconds is rejected.
* Five submissions per IP per ten minutes, adjustable via the `ttb_rate_limit`
  filter.
* A nonce on every form.

### Without JavaScript

The form posts normally to `admin-post.php` and the visitor is redirected back
with either the reference or the errors, with what they typed still in place.
JavaScript only adds the tabs, conditional fields, inline validation and the
AJAX submit.

## Tour & Transport Promotions

### Adding a deal

**Promotions → Add deal.** Title, short description in the editor, a "Deal
image", then price, price note, a crossed-out previous price, a badge, the
validity window, the button link and text, and a "Sidebar highlight" tick.

A deal appears from its start date and hides itself the day after its end date.
The "Order" field under Page Attributes controls the position.

### Displaying them

| Where | How |
| --- | --- |
| Promotions page | `[tt_promotions columns="3"]` — `columns` 1–4, `limit` optional |
| Sidebar | **Appearance → Widgets → Promotions Highlight** |
| Anywhere else | `[tt_promotions_highlight limit="2"]` |

The widget falls back to the newest live deals when nothing is flagged as a
highlight, so it is never an empty box. Deal images get the deal title as alt
text when the media library has none.

**Promotions → How to use** repeats all of this inside the dashboard.

## Development notes

The `dev/` folder holds the local harness used to build this: a WordPress
install script, a seed script, the Playwright end-to-end suite and the
screenshot script. It is not needed in production.

The scripts carry the absolute paths of the machine they were written on — set
`ROOT` and `BASE` at the top of each before running them elsewhere.

```sh
python3 dev/e2e.py
```

It covers client-side validation, all three submissions, the conditional
fields, the honeypot, the rate limit, the no-JavaScript path, what is stored in
the admin, and the contents of both emails.
