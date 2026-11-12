<?php
/**
 * Plugin Name: JDITC Add Wordfence 2FA to Ultimate Member
 * Description: Adds Wordfence 2FA compatibility to Ultimate Member login forms.
 * Version: 1.1.4-dev
 * Author: Justdave IT Consulting LLC
 * Author URI: https://justdaveitconsulting.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Tested up to: 7.1
 *
 * @package JDITC\Add_Wordfence_2FA_to_Ultimate_Member
 */

/*
 * Copyright (C) 2026 Justdave IT Consulting LLC
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

namespace JDITC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'JDITC_W2FA_UM_PATH' ) ) {
	define( 'JDITC_W2FA_UM_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'JDITC_W2FA_UM_URL' ) ) {
	define( 'JDITC_W2FA_UM_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Add plugin meta links on the Installed Plugins page.
 *
 * @param string[] $plugin_meta Existing plugin meta links.
 * @param string   $plugin_file Relative path to the plugin entry file.
 * @return string[]
 */
function add_plugin_meta_links( $plugin_meta, $plugin_file ) {
	if ( plugin_basename( __FILE__ ) !== $plugin_file ) {
		return $plugin_meta;
	}

	$plugin_meta[] = sprintf(
		'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
		esc_url( 'https://github.com/justdave/jditc-add-wordfence-2fa-to-ultimate-member/issues' ),
		esc_html__( 'Bug Reports & Feature Requests', 'jditc-add-wordfence-2fa-to-ultimate-member' )
	);

	return $plugin_meta;
}

add_filter( 'plugin_row_meta', __NAMESPACE__ . '\\add_plugin_meta_links', 10, 2 );

/**
 * Make Cloudflare Turnstile skip token reset during the Wordfence 2FA flow.
 *
 * The Wordfence 2FA integration with Ultimate Member uses a two-step form submission:
 * first to check credentials with Wordfence, then to complete the login after 2FA verification.
 * Turnstile's automatic token reset after form submission can interfere with this flow,
 * causing the second submission to fail with "Please enter your password". This filter
 * tells Turnstile to skip reset on Ultimate Member login forms that are handling 2FA.
 *
 * @param string $selectors Comma-separated list of form selectors to skip.
 * @return string
 */
function add_turnstile_skip_forms( $selectors ) {
	if ( ! is_string( $selectors ) ) {
		$selectors = '';
	}

	$um_form_selector = 'form[data-mode="login"]';
	if ( $selectors ) {
		$selectors .= ', ' . $um_form_selector;
	} else {
		$selectors = $um_form_selector;
	}

	return $selectors;
}
add_filter( 'cfturnstile_token_refresh_skip_forms', __NAMESPACE__ . '\\add_turnstile_skip_forms' );

require_once __DIR__ . '/classes/class-ultimatemember.php';

new Add_Wordfence_2FA_to_Ultimate_Member\UltimateMember();
