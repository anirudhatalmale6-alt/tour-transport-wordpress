<?php
/**
 * Plugin Name: Local Test Helpers (development only)
 * Description: Lifts the per-IP booking rate limit so the automated test run can submit many forms from 127.0.0.1. NOT part of the deliverable.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'ttb_rate_limit',
	function () {
		// The test run sets this option low to prove the limit really bites.
		return (int) get_option( 'ttb_test_rate_limit', 500 );
	}
);
