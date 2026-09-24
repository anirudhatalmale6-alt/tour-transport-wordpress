"""Capture the screenshots sent to the client."""
import glob
import os
import subprocess
from playwright.sync_api import sync_playwright

BASE = "http://127.0.0.1:8974"
ROOT = "/var/lib/freelancer/projects/40728457"
OUT = ROOT + "/shots"
WP = ["php", ROOT + "/dl/wp-cli.phar", "--path=" + ROOT + "/wp"]
os.makedirs(OUT, exist_ok=True)


def shot(page, name, path=None, scroll=0, wait=600, click=None):
    if path:
        page.goto(BASE + path, wait_until="networkidle")
    if click:
        page.click(click)
    # Park the pointer so no card is caught mid-hover.
    page.mouse.move(2, 2)
    page.wait_for_timeout(wait)
    if scroll:
        page.evaluate(f"window.scrollTo(0,{scroll})")
        page.wait_for_timeout(400)
    page.screenshot(path=f"{OUT}/{name}.png")
    print("saved", name)


def booking_id(kind):
    out = subprocess.run(
        WP + ["post", "list", "--post_type=tt_booking", "--format=ids"],
        capture_output=True, text=True, check=True).stdout.split()
    for pid in out:
        t = subprocess.run(WP + ["post", "meta", "get", pid, "_ttb_type"],
                           capture_output=True, text=True).stdout.strip()
        if t == kind:
            return pid
    return out[0]


def email_files():
    """Return (customer file, agency file) for the airport booking."""
    customer = agency = None
    for f in sorted(glob.glob(ROOT + "/mail-sink/*.html")):
        body = open(f, encoding="utf-8").read()
        if "amelia.hart@example.com" not in body:
            continue
        if "New booking request" in body and agency is None:
            agency = f
        elif "Your reference" in body and "New booking request" not in body and customer is None:
            customer = f
    return customer, agency


with sync_playwright() as pw:
    browser = pw.chromium.launch()

    # ---------- Public pages, desktop ----------
    ctx = browser.new_context(viewport={"width": 1280, "height": 800})
    page = ctx.new_page()

    shot(page, "01-bookings-airport", "/bookings/", scroll=170)
    shot(page, "02-bookings-tour", click="[data-ttb-tab='tour']", scroll=170)
    shot(page, "03-bookings-hire", click="[data-ttb-tab='hire']", scroll=170)
    shot(page, "04-promotions", "/promotions/", scroll=150)
    shot(page, "05-sidebar-highlight", "/sidebar-preview/", scroll=120)
    ctx.close()

    # ---------- Public pages, mobile ----------
    ctx = browser.new_context(viewport={"width": 390, "height": 780},
                              device_scale_factor=2, is_mobile=True, has_touch=True)
    page = ctx.new_page()
    shot(page, "06-mobile-bookings", "/bookings/", scroll=110)
    shot(page, "07-mobile-promotions", "/promotions/", scroll=140)
    ctx.close()

    # ---------- Tablet ----------
    ctx = browser.new_context(viewport={"width": 834, "height": 900})
    page = ctx.new_page()
    shot(page, "08-tablet-bookings", "/bookings/", scroll=200)
    shot(page, "09-tablet-promotions", "/promotions/", scroll=170)
    ctx.close()

    # ---------- Emails ----------
    customer, agency = email_files()
    ctx = browser.new_context(viewport={"width": 760, "height": 940})
    page = ctx.new_page()
    if customer:
        page.goto("file://" + customer, wait_until="load")
        shot(page, "10-email-customer")
        page.evaluate("window.scrollTo(0,520)")
        page.wait_for_timeout(300)
        page.screenshot(path=f"{OUT}/11-email-customer-detail.png")
        print("saved 11-email-customer-detail")
    if agency:
        page.goto("file://" + agency, wait_until="load")
        shot(page, "12-email-agency")
    ctx.close()

    # ---------- Admin ----------
    ctx = browser.new_context(viewport={"width": 1280, "height": 860})
    page = ctx.new_page()
    page.goto(BASE + "/wp-login.php", wait_until="networkidle")
    page.fill("#user_login", "admin")
    page.fill("#user_pass", "admin123")
    page.click("#wp-submit")
    page.wait_for_load_state("networkidle")

    shot(page, "13-admin-bookings-list", "/wp-admin/edit.php?post_type=tt_booking")
    shot(page, "14-admin-booking-detail",
         f"/wp-admin/post.php?post={booking_id('hire')}&action=edit", scroll=120)
    shot(page, "15-admin-booking-settings",
         "/wp-admin/edit.php?post_type=tt_booking&page=ttb-settings")
    shot(page, "16-admin-booking-settings-lower",
         "/wp-admin/edit.php?post_type=tt_booking&page=ttb-settings", scroll=520)
    shot(page, "17-admin-tools",
         "/wp-admin/edit.php?post_type=tt_booking&page=ttb-tools")
    shot(page, "18-admin-promotions-list", "/wp-admin/edit.php?post_type=tt_promotion")

    promo = subprocess.run(
        WP + ["post", "list", "--post_type=tt_promotion", "--format=ids"],
        capture_output=True, text=True, check=True).stdout.split()[0]
    shot(page, "19-admin-deal-editor", f"/wp-admin/post.php?post={promo}&action=edit",
         scroll=260, wait=1800)
    shot(page, "20-admin-promotions-help",
         "/wp-admin/edit.php?post_type=tt_promotion&page=ttp-help")
    ctx.close()

    browser.close()

print("done")
