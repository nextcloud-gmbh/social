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

use OCA\Social\Exceptions\InstanceDoesNotExistException;
use OCA\Social\Model\ActivityPub\ACore;
use OCA\Social\Model\Instance;
use OCA\Social\Tools\Traits\TArrayTools;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Class InstancesRequest
 *
 * @package OCA\Social\Db
 */
class InstancesRequest extends InstancesRequestBuilder {
	use TArrayTools;


	/**
	 * @param Instance $instance
	 * TODO: store instance in db
	 */
	public function save(Instance $instance) {
		//		$now = new DateTime('now');
		//		$instance->setCreation($now->getTimestamp());

		$qb = $this->getInstanceInsertSql();
		$qb->setValue('uri', $qb->createNamedParameter($instance->getUri()))
			->setValue('local', $qb->createNamedParameter($instance->isLocal()), IQueryBuilder::PARAM_BOOL)
			->setValue('title', $qb->createNamedParameter($instance->getTitle()))
			->setValue('version', $qb->createNamedParameter($instance->getVersion()))
			->setValue('short_description', $qb->createNamedParameter($instance->getShortDescription()))
			->setValue('description', $qb->createNamedParameter($instance->getDescription()))
			->setValue('email', $qb->createNamedParameter($instance->getEmail()))
			->setValue('urls', $qb->createNamedParameter(json_encode($instance->getUrls())))
			->setValue('stats', $qb->createNamedParameter(json_encode($instance->getStats())))
			->setValue('usage', $qb->createNamedParameter(json_encode($instance->getUsage())))
			->setValue('image', $qb->createNamedParameter($instance->getImage()))
			->setValue('languages', $qb->createNamedParameter(json_encode($instance->getLanguages())))
			->setValue('account_prim', $qb->createNamedParameter($instance->getAccountPrim() ? $qb->prim($instance->getAccountPrim()) : null));
		$qb->executeStatement();
	}


	/**
	 * @param int $format
	 *
	 * @return Instance
	 * @throws InstanceDoesNotExistException
	 */
	public function getLocal(int $format = ACore::FORMAT_ACTIVITYPUB): Instance {
		$qb = $this->getInstanceSelectSql($format);
		$qb->linkToCacheActors('ca', 'account_prim', false);
		$qb->limitToDBFieldInt('local', 1);
		$qb->leftJoinCacheDocuments('icon_id', 'ca');

		return $this->getInstanceFromRequest($qb);
	}
}
