<?php
/**
 * Plugin Name: Wordfence 2FA for Ultimate Member
 * Description: Adds Wordfence 2FA compatibility to Ultimate Member login forms.
 * Version: 0.1.0
 * Author: David D. Miller
 * Author URI: https://github.com/justdave
 * License: GPLv2+
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

require_once __DIR__ . '/classes/class-ultimatemember.php';

new Wordfence_2FA_for_Ultimate_Member\UltimateMember();
