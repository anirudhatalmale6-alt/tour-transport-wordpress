#!/bin/sh
# Seed the local demo with pages and four promotions.
set -e
cd /var/lib/freelancer/projects/40728457/wp
WP="php ../dl/wp-cli.phar"
IMG=/var/lib/freelancer/projects/40728457/demo-images

promo () {
  TITLE="$1"; EXCERPT="$2"; PRICE="$3"; NOTE="$4"; OLD="$5"; BADGE="$6"
  IMAGE="$7"; ORDER="$8"; FEATURED="$9"; END="${10}"

  ID=$($WP post create --post_type=tt_promotion --post_status=publish \
        --post_title="$TITLE" --post_excerpt="$EXCERPT" --menu_order="$ORDER" \
        --post_content="$EXCERPT" --porcelain)

  ATT=$($WP media import "$IMG/$IMAGE" --post_id="$ID" --featured_image --porcelain \
        --title="$TITLE" --alt="$TITLE")

  $WP post meta update "$ID" _ttp_price "$PRICE" >/dev/null
  $WP post meta update "$ID" _ttp_price_note "$NOTE" >/dev/null
  [ -n "$OLD" ] && $WP post meta update "$ID" _ttp_old_price "$OLD" >/dev/null
  [ -n "$BADGE" ] && $WP post meta update "$ID" _ttp_badge "$BADGE" >/dev/null
  [ -n "$END" ] && $WP post meta update "$ID" _ttp_end "$END" >/dev/null
  [ -n "$FEATURED" ] && $WP post meta update "$ID" _ttp_featured 1 >/dev/null
  $WP post meta update "$ID" _ttp_link_url "http://127.0.0.1:8974/bookings/" >/dev/null
  $WP post meta update "$ID" _ttp_link_text "Book this deal" >/dev/null
  echo "promo $ID  $TITLE (attachment $ATT)"
}

promo "Airport transfer - 20% off return trips" \
  "Book your arrival and departure together and we take 20% off the return leg. Flight tracking and a 60-minute free wait are included." \
  '$39' "per vehicle" '$49' "Save 20%" "airport-transfer.jpg" 1 yes "2026-12-31"

promo "Island day tour with lunch" \
  "A full day along the coast with a licensed guide, hotel pickup, and a seafood lunch at a harbour restaurant." \
  '$89' "per person" '' "Most popular" "island-day-tour.jpg" 2 yes ""

promo "SUV weekly hire" \
  "Seven days in a 5-seat SUV with unlimited mileage, full insurance and a second driver at no extra cost." \
  'From $320' "per week" '$395' "" "suv-weekly-hire.jpg" 3 "" "2026-11-30"

promo "Sunset city tour" \
  "Three hours through the old town and the viewpoint, finishing at the harbour for sunset. Small groups only." \
  '$45' "per person" '' "Evenings only" "sunset-city-tour.jpg" 4 "" ""

$WP post create --post_type=page --post_title="Promotions" --post_name=promotions \
  --post_status=publish --post_content='[tt_promotions columns="3"]' --porcelain
