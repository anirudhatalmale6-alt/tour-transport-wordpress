"""Test the client's new requirements: destination cards and the grocery questions."""
import sys
from playwright.sync_api import sync_playwright

BASE = "http://127.0.0.1:8974"
SHOTS = "/var/lib/freelancer/projects/40728457/shots"
failures = []


def check(label, ok, detail=""):
    print(("PASS  " if ok else "FAIL  ") + label + ("  " + detail if detail else ""))
    if not ok:
        failures.append(label)


with sync_playwright() as pw:
    browser = pw.chromium.launch()
    ctx = browser.new_context(viewport={"width": 1280, "height": 860})
    page = ctx.new_page()

    # ---------- Destination cards ----------
    page.goto(BASE + "/tours/", wait_until="networkidle")
    page.mouse.move(2, 2)
    cards = page.locator(".ttp-dest__card")
    check("five destination cards render", cards.count() == 5, f"{cards.count()} cards")

    titles = page.locator(".ttp-dest__title").all_inner_texts()
    check("all five places are listed",
          set(titles) == {"Blue Mountains", "The Entrance", "Wollongong", "Orange", "Hunter Valley"},
          ", ".join(titles))

    imgs = page.locator(".ttp-dest__img")
    check("every card has an image", imgs.count() == 5, f"{imgs.count()} images")

    # The image must actually load, not just exist in the markup.
    loaded = page.evaluate(
        "Array.from(document.querySelectorAll('.ttp-dest__img'))"
        ".every(i => i.complete && i.naturalWidth > 0)"
    )
    check("every destination image actually loads", loaded)
    page.screenshot(path=f"{SHOTS}/30-destinations.png")

    # ---------- The journey: click a card, land on the right form ----------
    page.locator(".ttp-dest__card", has_text="Hunter Valley").click()
    page.wait_for_load_state("networkidle")
    page.wait_for_timeout(900)

    check("card leads to the booking page", "/bookings/" in page.url, page.url)

    tour_open = page.locator("[data-ttb-tab='tour']").get_attribute("aria-selected")
    check("the Tour tab is the one open", tour_open == "true", f"aria-selected={tour_open}")

    prefilled = page.input_value("[data-ttb-panel='tour'] [name='ttb[tour_name]']")
    check("destination is pre-filled in the form", prefilled == "Hunter Valley", repr(prefilled))
    page.screenshot(path=f"{SHOTS}/31-prefilled-tour.png")

    # ---------- Grocery questions ----------
    page.goto(BASE + "/bookings/", wait_until="networkidle")
    page.wait_for_timeout(700)

    milk = page.locator("[data-ttb-panel='airport'] [name='ttb[milk]']")
    bread = page.locator("[data-ttb-panel='airport'] [name='ttb[bread]']")

    # Default is "Airport to my accommodation" - they are heading home, so show.
    check("groceries show on the airport-to-home leg", milk.is_visible() and bread.is_visible())

    page.click("[data-ttb-panel='airport'] .ttb-option:has-text('My accommodation to the airport')")
    page.wait_for_timeout(500)
    check("groceries hide when heading TO the airport",
          milk.is_hidden() and bread.is_hidden())
    heading = page.locator("[data-ttb-panel='airport'] .ttb-section", has_text="Groceries for your arrival")
    check("the grocery heading hides with its fields", heading.is_hidden())

    page.click("[data-ttb-panel='airport'] .ttb-option:has-text('Both ways (return)')")
    page.wait_for_timeout(500)
    check("groceries show again on a return trip", milk.is_visible() and bread.is_visible())

    check("milk offers 2L and 3L",
          [o.strip() for o in milk.locator("option").all_inner_texts()] == ["No thanks", "2 litres", "3 litres"],
          str(milk.locator("option").all_inner_texts()))
    check("bread offers white and wholemeal",
          [o.strip() for o in bread.locator("option").all_inner_texts()] == ["No thanks", "White", "Wholemeal"],
          str(bread.locator("option").all_inner_texts()))
    page.screenshot(path=f"{SHOTS}/32-groceries.png")

    # ---------- A real submission carrying the groceries ----------
    p = "[data-ttb-panel='airport'] "
    page.fill(p + "[name='ttb[pickup_location]']", "Sydney Airport T1")
    page.fill(p + "[name='ttb[dropoff_location]']", "12 Bourke Street, Sydney")
    page.fill(p + "[name='ttb[pickup_date]']", "2026-11-18")
    page.fill(p + "[name='ttb[pickup_time]']", "16:45")
    page.fill(p + "[name='ttb[return_date]']", "2026-11-25T07:30")
    page.fill(p + "[name='ttb[passengers]']", "2")
    page.select_option(p + "[name='ttb[milk]']", "3l")
    page.select_option(p + "[name='ttb[bread]']", "wholemeal")
    page.fill(p + "[name='ttb[full_name]']", "Grocery Tester")
    page.fill(p + "[name='ttb[email]']", "grocery@example.com")
    page.fill(p + "[name='ttb[phone]']", "+61 400 000 111")
    page.check(p + "[name='ttb_consent']")
    page.wait_for_timeout(3500)
    page.click(p + ".ttb-submit")
    page.wait_for_selector(p + "[data-ttb-success]:not([hidden])", timeout=20000)
    ref = page.locator(p + "[data-ttb-success]").inner_text()
    check("booking with groceries submits", "AT-" in ref, ref.replace("\n", " ")[:80])

    # ---------- A trip TO the airport must not carry groceries ----------
    page.goto(BASE + "/bookings/", wait_until="networkidle")
    page.click(p + ".ttb-option:has-text('My accommodation to the airport')")
    page.wait_for_timeout(400)
    page.fill(p + "[name='ttb[pickup_location]']", "12 Bourke Street, Sydney")
    page.fill(p + "[name='ttb[dropoff_location]']", "Sydney Airport T1")
    page.fill(p + "[name='ttb[pickup_date]']", "2026-11-19")
    page.fill(p + "[name='ttb[pickup_time]']", "05:15")
    page.fill(p + "[name='ttb[passengers]']", "1")
    page.fill(p + "[name='ttb[full_name]']", "Departure Tester")
    page.fill(p + "[name='ttb[email]']", "departure@example.com")
    page.fill(p + "[name='ttb[phone]']", "+61 400 000 222")
    page.check(p + "[name='ttb_consent']")
    page.wait_for_timeout(3500)
    page.click(p + ".ttb-submit")
    page.wait_for_selector(p + "[data-ttb-success]:not([hidden])", timeout=20000)
    check("departure booking submits", "AT-" in page.locator(p + "[data-ttb-success]").inner_text())

    browser.close()

# ---------- What reached the emails ----------
import glob
mails = sorted(glob.glob("/var/lib/freelancer/projects/40728457/mail-sink/*.html"))
grocery = "\n".join(open(m, encoding="utf-8").read() for m in mails
                    if "grocery@example.com" in open(m, encoding="utf-8").read())
departure = "\n".join(open(m, encoding="utf-8").read() for m in mails
                      if "departure@example.com" in open(m, encoding="utf-8").read())

check("milk reaches the emails as words", "3 litres" in grocery, "")
check("bread reaches the emails as words", "Wholemeal" in grocery, "")
check("a departure booking carries NO grocery lines",
      "Milk" not in departure and "Bread" not in departure, "")

print()
print("FAILURES:", failures if failures else "none")
sys.exit(1 if failures else 0)
