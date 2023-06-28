/**
 * @copyright Copyright (c) 2023 Louis Chmn <louis@chmn.me>
 *
 * @author Louis Chmn <louis@chmn.me>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

describe('Social app setup', () => {
	before(() => {
		cy.createRandomUser()
			.then((user) => {
				cy.login(user)
				cy.visit('/apps/social')
			})
	})

	it('See the welcome message', () => {
		cy.get('.social__welcome').should('contain', 'Nextcloud becomes part of the federated social networks!')
		cy.get('.social__welcome').find('.icon-close').click()
		cy.get('.social__welcome').should('not.exist')
		cy.reload()
	})

	it('See the home section in the sidebar', () => {
		cy.get('.app-navigation').contains('Home').click()
		cy.get('.app-social .empty-content').should('be.visible')
	})

	it('See the empty content illustration of Direct messages', () => {
		cy.get('.app-navigation').contains('Direct messages').click()
		cy.get('.app-social .empty-content').should('be.visible').contains('No direct messages found')
	})

	it('See the empty content illustration of Profile', () => {
		cy.intercept({ times: 1, method: 'GET', url: '**/apps/social/api/v1/accounts/*/statuses?*' }).as('accountStatuses')

		cy.get('.app-navigation').contains('Profile').click()
		cy.wait("@accountStatuses")

		cy.get('.app-social .empty-content__title').scrollIntoView()
		cy.get('.app-social .empty-content').should('be.visible').contains('You have not tooted yet')
	})
})
