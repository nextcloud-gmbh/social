/*
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

/*
 * This file provides global methods for using the serverData structure
 * the serverData structure's data are initialy found in a DOM element
 *  provided by the server.
 *
 * It may contain the following information:
 *	- account (only in 'OStatus.vue'): The account that the user wants to follow
 *	- cliUrl:
 *	- cloudAddress:
 *	- firstrun:
 *	- isAdmin:
 * 	- local (only in 'OStatus.vue'): The local part of the account that the user wants to follow
 *	- public: False when the page is accessed by an authenticated user. True otherwise.
 *	- setup:
 */

export default {
	computed: {
		serverData: function() {
			return this.$store.getters.getServerData
		},
		hostname() {
			const url = document.createElement('a')
			url.setAttribute('href', this.serverData.cloudAddress)
			return url.hostname
		}
	}
}
