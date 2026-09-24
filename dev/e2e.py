"""End-to-end test of the three booking forms, with and without JavaScript."""
import glob
import subprocess
import sys
from playwright.sync_api import sync_playwright

BASE = "http://127.0.0.1:8974"
ROOT = "/var/lib/freelancer/projects/40728457"
SHOTS = ROOT + "/shots"
WP = ["php", ROOT + "/dl/wp-cli.phar", "--path=" + ROOT + "/wp"]
failures = []


def check(label, condition, detail=""):
    print(("PASS  " if condition else "FAIL  ") + label + ("  " + detail if detail else ""))
    if not condition:
        failures.append(label)


def fill_airport(page):
    page.click("[data-ttb-panel='airport'] .ttb-option:has-text('Both ways (return)')")
    page.fill("[data-ttb-panel='airport'] [name='ttb[pickup_location]']", "Changi Airport, Terminal 3")
    page.fill("[data-ttb-panel='airport'] [name='ttb[dropoff_location]']", "Marina Bay Hotel, 10 Bayfront Ave")
    page.fill("[data-ttb-panel='airport'] [name='ttb[pickup_date]']", "2026-11-14")
    page.fill("[data-ttb-panel='airport'] [name='ttb[pickup_time]']", "14:30")
    page.fill("[data-ttb-panel='airport'] [name='ttb[flight_number]']", "SQ 322")
    page.fill("[data-ttb-panel='airport'] [name='ttb[return_date]']", "2026-11-21T08:00")
    page.fill("[data-ttb-panel='airport'] [name='ttb[passengers]']", "3")
    page.fill("[data-ttb-panel='airport'] [name='ttb[luggage]']", "4")
    page.fill("[data-ttb-panel='airport'] [name='ttb[child_seats]']", "1")
    page.select_option("[data-ttb-panel='airport'] [name='ttb[vehicle_class]']", "van")
    page.fill("[data-ttb-panel='airport'] [name='ttb[full_name]']", "Amelia Hart")
    page.fill("[data-ttb-panel='airport'] [name='ttb[email]']", "amelia.hart@example.com")
    page.fill("[data-ttb-panel='airport'] [name='ttb[phone]']", "+44 7700 900123")
    page.fill("[data-ttb-panel='airport'] [name='ttb[country]']", "United Kingdom")
    page.fill("[data-ttb-panel='airport'] [name='ttb[notes]']", "One oversized suitcase, plus a folding wheelchair.")
    page.check("[data-ttb-panel='airport'] [name='ttb_consent']")


def fill_tour(page):
    p = "[data-ttb-panel='tour'] "
    page.fill(p + "[name='ttb[tour_name]']", "Northern lakes and waterfalls")
    page.fill(p + "[name='ttb[tour_date]']", "2026-12-02")
    page.select_option(p + "[name='ttb[duration]']", "full-day")
    page.fill(p + "[name='ttb[start_time]']", "08:00")
    page.fill(p + "[name='ttb[adults]']", "4")
    page.fill(p + "[name='ttb[children]']", "2")
    page.fill(p + "[name='ttb[language]']", "English")
    page.fill(p + "[name='ttb[hotel]']", "Grand Riverside Hotel, room 412")
    page.select_option(p + "[name='ttb[pickup_required]']", "yes")
    page.fill(p + "[name='ttb[dietary]']", "Two vegetarians")
    page.fill(p + "[name='ttb[full_name]']", "Daniel Okafor")
    page.fill(p + "[name='ttb[email]']", "daniel.okafor@example.com")
    page.fill(p + "[name='ttb[phone]']", "+234 802 555 0144")
    page.fill(p + "[name='ttb[country]']", "Nigeria")
    page.fill(p + "[name='ttb[notes]']", "Celebrating an anniversary.")
    page.check(p + "[name='ttb_consent']")


def fill_hire(page):
    p = "[data-ttb-panel='hire'] "
    page.select_option(p + "[name='ttb[vehicle_type]']", "suv")
    page.click(p + ".ttb-option:has-text('Self-drive')")
    page.fill(p + "[name='ttb[pickup_date]']", "2026-10-20T09:00")
    page.fill(p + "[name='ttb[return_date]']", "2026-10-27T18:00")
    page.fill(p + "[name='ttb[pickup_location]']", "City depot, 4 Harbour Road")
    page.fill(p + "[name='ttb[dropoff_location]']", "Airport return desk")
    page.fill(p + "[name='ttb[passengers]']", "5")
    page.fill(p + "[name='ttb[driver_age]']", "34")
    page.fill(p + "[name='ttb[licence_country]']", "Germany")
    page.check(p + ".ttb-option:has-text('GPS / navigation') input")
    page.check(p + ".ttb-option:has-text('Child seat') input")
    page.fill(p + "[name='ttb[full_name]']", "Lena Fischer")
    page.fill(p + "[name='ttb[email]']", "lena.fischer@example.com")
    page.fill(p + "[name='ttb[phone]']", "+49 151 23456789")
    page.fill(p + "[name='ttb[country]']", "Germany")
    page.check(p + "[name='ttb_consent']")


with sync_playwright() as pw:
    browser = pw.chromium.launch()

    # ---------- 1. Validation blocks an empty submit ----------
    ctx = browser.new_context(viewport={"width": 1280, "height": 900})
    page = ctx.new_page()
    page.goto(BASE + "/bookings/", wait_until="networkidle")
    page.click("[data-ttb-panel='airport'] .ttb-submit")
    page.wait_for_timeout(500)
    errors = page.locator("[data-ttb-panel='airport'] .ttb-field.has-error").count()
    check("empty submit is blocked client-side", errors >= 5, f"{errors} fields flagged")
    page.screenshot(path=f"{SHOTS}/05-validation.png")

    # ---------- 2. Bad email is rejected ----------
    page.fill("[data-ttb-panel='airport'] [name='ttb[email]']", "not-an-email")
    page.click("[data-ttb-panel='airport'] .ttb-submit")
    page.wait_for_timeout(300)
    msg = page.locator("[data-ttb-panel='airport'] [name='ttb[email]']").evaluate(
        "el => el.closest('.ttb-field').querySelector('.ttb-field__error').textContent.trim()"
    )
    check("invalid email is rejected", "valid email" in msg, msg)

    # ---------- 3. Airport transfer, full submit ----------
    page.goto(BASE + "/bookings/", wait_until="networkidle")
    fill_airport(page)
    page.wait_for_timeout(3500)  # clear the anti-bot time trap
    page.screenshot(path=f"{SHOTS}/06-airport-filled.png")
    page.click("[data-ttb-panel='airport'] .ttb-submit")
    page.wait_for_selector("[data-ttb-panel='airport'] [data-ttb-success]:not([hidden])", timeout=15000)
    text = page.locator("[data-ttb-panel='airport'] [data-ttb-success]").inner_text()
    check("airport transfer submitted", "AT-" in text, text.replace("\n", " ")[:90])
    page.screenshot(path=f"{SHOTS}/07-airport-success.png")

    # ---------- 4. Tour ----------
    page.goto(BASE + "/bookings/", wait_until="networkidle")
    page.click("[data-ttb-tab='tour']")
    fill_tour(page)
    page.wait_for_timeout(3500)
    page.click("[data-ttb-panel='tour'] .ttb-submit")
    page.wait_for_selector("[data-ttb-panel='tour'] [data-ttb-success]:not([hidden])", timeout=15000)
    text = page.locator("[data-ttb-panel='tour'] [data-ttb-success]").inner_text()
    check("tour submitted", "TR-" in text, text.replace("\n", " ")[:90])

    # ---------- 5. Hire a vehicle, incl. conditional fields ----------
    page.goto(BASE + "/bookings/", wait_until="networkidle")
    page.click("[data-ttb-tab='hire']")
    hidden_before = page.locator("[data-ttb-panel='hire'] [data-ttb-showif]").first.is_hidden()
    check("conditional field hidden until its trigger is chosen", hidden_before)
    fill_hire(page)
    shown_after = page.locator("[data-ttb-panel='hire'] [data-ttb-showif]").first.is_visible()
    check("conditional field appears for self-drive", shown_after)
    page.wait_for_timeout(3500)
    page.screenshot(path=f"{SHOTS}/08-hire-filled.png")
    page.click("[data-ttb-panel='hire'] .ttb-submit")
    page.wait_for_selector("[data-ttb-panel='hire'] [data-ttb-success]:not([hidden])", timeout=15000)
    text = page.locator("[data-ttb-panel='hire'] [data-ttb-success]").inner_text()
    check("hire submitted", "HV-" in text, text.replace("\n", " ")[:90])

    # ---------- 6. Honeypot ----------
    page.goto(BASE + "/bookings/", wait_until="networkidle")
    fill_airport(page)
    page.eval_on_selector("[data-ttb-panel='airport'] [name='ttb_website']", "el => el.value = 'http://spam.example'")
    page.wait_for_timeout(3500)
    page.click("[data-ttb-panel='airport'] .ttb-submit")
    page.wait_for_selector("[data-ttb-panel='airport'] [data-ttb-error]:not([hidden])", timeout=15000)
    blocked = page.locator("[data-ttb-panel='airport'] [data-ttb-success]").is_hidden()
    check("honeypot submission is rejected", blocked)
    ctx.close()

    # ---------- 7. No-JavaScript fallback ----------
    ctx = browser.new_context(java_script_enabled=False, viewport={"width": 1280, "height": 900})
    page = ctx.new_page()
    page.goto(BASE + "/bookings/", wait_until="load")
    page.fill("[data-ttb-panel='airport'] [name='ttb[pickup_location]']", "Ferry terminal")
    page.fill("[data-ttb-panel='airport'] [name='ttb[dropoff_location]']", "Seaside Inn")
    page.fill("[data-ttb-panel='airport'] [name='ttb[pickup_date]']", "2026-11-30")
    page.fill("[data-ttb-panel='airport'] [name='ttb[pickup_time]']", "10:00")
    page.fill("[data-ttb-panel='airport'] [name='ttb[passengers]']", "2")
    page.fill("[data-ttb-panel='airport'] [name='ttb[full_name]']", "No Script Tester")
    page.fill("[data-ttb-panel='airport'] [name='ttb[email]']", "noscript@example.com")
    page.fill("[data-ttb-panel='airport'] [name='ttb[phone]']", "+1 555 0177")
    page.check("[data-ttb-panel='airport'] [name='ttb_consent']")
    page.wait_for_timeout(3500)
    page.click("[data-ttb-panel='airport'] .ttb-submit")
    page.wait_for_load_state("load")
    body = page.content()
    check("no-JavaScript submit succeeds", "AT-" in body and "ttb_ref=" in page.url, page.url[-60:])
    page.screenshot(path=f"{SHOTS}/09-nojs-success.png")

    # A missing required field must come back with the error, not a blank page.
    page.goto(BASE + "/bookings/", wait_until="load")
    page.fill("[data-ttb-panel='airport'] [name='ttb[full_name]']", "Missing Fields")
    page.wait_for_timeout(3500)
    page.click("[data-ttb-panel='airport'] .ttb-submit")
    page.wait_for_load_state("load")
    body = page.content()
    kept = page.input_value("[data-ttb-panel='airport'] [name='ttb[full_name]']")
    check("no-JavaScript validation returns errors", "is required" in body, "")
    check("no-JavaScript keeps what was typed", kept == "Missing Fields", kept)
    ctx.close()

    # ---------- 8. The rate limit really blocks ----------
    subprocess.run(WP + ["option", "update", "ttb_test_rate_limit", "1"], check=True,
                   capture_output=True)
    subprocess.run(WP + ["transient", "delete", "--all"], check=True, capture_output=True)

    ctx = browser.new_context(viewport={"width": 1280, "height": 900})
    page = ctx.new_page()
    outcomes = []
    for attempt in range(2):
        page.goto(BASE + "/bookings/", wait_until="networkidle")
        fill_airport(page)
        page.wait_for_timeout(3500)
        page.click("[data-ttb-panel='airport'] .ttb-submit")
        page.wait_for_timeout(2500)
        ok = page.locator("[data-ttb-panel='airport'] [data-ttb-success]").is_visible()
        outcomes.append(ok)
    check("rate limit allows the first request", outcomes[0] is True)
    check("rate limit blocks the second request", outcomes[1] is False)
    ctx.close()

    subprocess.run(WP + ["option", "update", "ttb_test_rate_limit", "500"], check=True,
                   capture_output=True)
    subprocess.run(WP + ["transient", "delete", "--all"], check=True, capture_output=True)

    browser.close()

# ---------- 9. What actually landed: stored bookings and emails ----------
stored = subprocess.run(
    WP + ["post", "list", "--post_type=tt_booking", "--format=count"],
    capture_output=True, text=True, check=True).stdout.strip()
check("bookings are stored in wp-admin", int(stored) >= 4, f"{stored} rows")

mails = sorted(glob.glob("/var/lib/freelancer/projects/40728457/mail-sink/*.html"))
check("two emails per booking were sent", len(mails) >= 8, f"{len(mails)} emails")

joined = "\n".join(open(m, encoding="utf-8").read() for m in mails)
check("customer confirmation carries the reference", "Your reference" in joined)
check("agency notification is addressed to the inbox", "New booking request" in joined)
check("reply-to points back at the customer",
      "Reply-To: Amelia Hart <amelia.hart@example.com>" in joined)
check("select values are shown as words, not stored codes",
      "Minivan - up to 8 passengers" in joined and "SUV / 4x4" in joined)
check("checkbox extras are listed by label", "GPS / navigation, Child seat" in joined)
check("dates are formatted for a human", "November 14, 2026" in joined)
check("times are formatted for a human", "2:30 pm" in joined)
check("a conditional field is captured once it is shown", "Licence issued in" in joined)

print()
print("FAILURES:", failures if failures else "none")
sys.exit(1 if failures else 0)
