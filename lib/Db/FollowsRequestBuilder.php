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

use OCA\Social\Exceptions\FollowNotFoundException;
use OCA\Social\Exceptions\InvalidResourceException;
use OCA\Social\Model\ActivityPub\Object\Follow;
use OCA\Social\Tools\Exceptions\RowNotFoundException;
use OCA\Social\Tools\Traits\TArrayTools;

/**
 * Class FollowsRequestBuilder
 *
 * @package OCA\Social\Db
 */
class FollowsRequestBuilder extends CoreRequestBuilder {
	use TArrayTools;


	/**
	 * Base of the Sql Insert request
	 *
	 * @return SocialQueryBuilder
	 */
	protected function getFollowsInsertSql(): SocialQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->insert(self::TABLE_FOLLOWS);

		return $qb;
	}


	/**
	 * Base of the Sql Update request
	 *
	 * @return SocialQueryBuilder
	 */
	protected function getFollowsUpdateSql(): SocialQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->update(self::TABLE_FOLLOWS);

		return $qb;
	}


	/**
	 * Base of the Sql Select request for Shares
	 *
	 * @return SocialQueryBuilder
	 */
	protected function getFollowsSelectSql(): SocialQueryBuilder {
		$qb = $this->getQueryBuilder();

		/** @noinspection PhpMethodParametersCountMismatchInspection */
		$qb->select(
			'f.id', 'f.type', 'f.actor_id', 'f.object_id', 'f.follow_id', 'f.accepted', 'f.creation'
		)
		   ->from(self::TABLE_FOLLOWS, 'f');

		$this->defaultSelectAlias = 'f';
		$qb->setDefaultSelectAlias('f');

		return $qb;
	}


	/**
	 * Base of the Sql Select request for Shares
	 *
	 * @return SocialQueryBuilder
	 */
	protected function countFollowsSelectSql(): SocialQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'count')
		   ->from(self::TABLE_FOLLOWS, 'f');

		$qb->setDefaultSelectAlias('f');
		$this->defaultSelectAlias = 'f';

		return $qb;
	}


	/**
	 * Base of the Sql Delete request
	 *
	 * @return SocialQueryBuilder
	 */
	protected function getFollowsDeleteSql(): SocialQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->delete(self::TABLE_FOLLOWS);

		return $qb;
	}


	/**
	 * @param SocialQueryBuilder $qb
	 *
	 * @return Follow
	 * @throws FollowNotFoundException
	 */
	protected function getFollowFromRequest(SocialQueryBuilder $qb): Follow {
		/** @var Follow $result */
		try {
			$result = $qb->getRow([$this, 'parseFollowsSelectSql']);
		} catch (RowNotFoundException $e) {
			throw new FollowNotFoundException($e->getMessage());
		}

		return $result;
	}

	/**
	 * @param SocialQueryBuilder $qb
	 *
	 * @return Follow[]
	 */
	public function getFollowsFromRequest(SocialQueryBuilder $qb): array {
		/** @var Follow[] $result */
		$result = $qb->getRows([$this, 'parseFollowsSelectSql']);

		return $result;
	}


	/**
	 * @param array $data
	 * @param SocialQueryBuilder $qb
	 *
	 * @return Follow
	 */
	public function parseFollowsSelectSql(array $data, SocialQueryBuilder $qb): Follow {
		$follow = new Follow();
		$follow->importFromDatabase($data);

		try {
			$actor = $qb->parseLeftJoinCacheActors($data, 'cacheactor_');
			$actor->setCompleteDetails(true);
			$this->assignDetails($actor, $data);

			$follow->setCompleteDetails(true);
			$follow->setActor($actor);

			return $follow;
		} catch (InvalidResourceException $e) {
		}

		try {
			$actor = $this->parseAccountsLeftJoin($data);
			$follow->setActor($actor);
		} catch (InvalidResourceException $e) {
		}

		return $follow;
	}
}
