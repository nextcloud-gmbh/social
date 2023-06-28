<?php

declare(strict_types=1);


/**
 * Nextcloud - Social Support
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2018, Maxence Lange <maxence@artificial-owl.com>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\Social\Db;

/**
 * Class SocialFiltersQueryBuilder
 *
 * @package OCA\Social\Db
 */
class SocialFiltersQueryBuilder extends SocialLimitsQueryBuilder {
	/**
	 * @deprecated ?
	 */
	public function filterDuplicate() {
		if (!$this->hasViewer()) {
			return;
		}

		$viewer = $this->getViewer();
		$this->leftJoinFollowStatus('fs');

		$expr = $this->expr();
		$filter = $expr->orX();
		$filter->add($this->exprLimitToDBFieldInt('filter_duplicate', 0, 's'));

		$follower = $expr->andX();
		$follower->add($this->exprLimitToDBField('attributed_to_prim', $this->prim($viewer->getId()), false));
		//		$follower->add($expr->isNull('fs.id_prim'));
		$filter->add($follower);

		$this->andWhere($filter);
	}
}
