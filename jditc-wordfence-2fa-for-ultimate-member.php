<?php
/**
 * Plugin Name: JDITC Wordfence 2FA for Ultimate Member
 * Description: Adds Wordfence 2FA compatibility to Ultimate Member login forms.
 * Version: 1.0
 * Author: Justdave IT Consulting LLC
 * Author URI: https://github.com/justdave
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Tested up to: 7.0
 *
 * @package JDITC\Wordfence_2FA_for_Ultimate_Member
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
		esc_url( 'https://github.com/justdave/jditc-wordfence-2fa-for-ultimate-member/issues' ),
		esc_html__( 'Bug Reports & Feature Requests', 'jditc-wordfence-2fa-for-ultimate-member' )
	);

	return $plugin_meta;
}

add_filter( 'plugin_row_meta', __NAMESPACE__ . '\\add_plugin_meta_links', 10, 2 );

require_once __DIR__ . '/classes/class-ultimatemember.php';

new Wordfence_2FA_for_Ultimate_Member\UltimateMember();
