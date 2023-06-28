/**
 * @copyright Copyright (c) 2019 Cyrille Bollu <cyrpub@bollu.be>
 *
 * @author Cyrille Bollu <cyrpub@bollu.be>
 *
 * @license AGPL-3.0-or-later
 *
 * @file provides global account related methods
 *
 * @mixin
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

import serverData from './serverData.js'

export default {
	mixins: [
		serverData,
	],
	computed: {
		/** @return {string} the complete account name */
		profileAccount() {
			return (this.uid.indexOf('@') === -1) ? this.uid + '@' + this.hostname : this.uid
		},

		/** @return {import('../types/Mastodon.js').Account} detailed information about an account (account must be loaded in the store first) */
		accountInfo() {
			return this.$store.getters.getAccount(this.profileAccount)
		},

		/**
		 * Somewhat duplicate with accountInfo(), but needed (for some reason) to avoid glitches
		 * where components would first show "user not found" before display an account's account info
		 *
		 * @return {boolean}
		 */
		accountLoaded() {
			return this.$store.getters.accountLoaded(this.profileAccount) !== undefined
		},

		/** @return {boolean} */
		isLocal() {
			return !this.accountInfo.acct.includes('@')
		},
		/** @return {import('../types/Mastodon.js').Relationship} */
		relationship() {
			return this.$store.getters.getRelationshipWith(this.accountInfo.id)
		},
	},
}
