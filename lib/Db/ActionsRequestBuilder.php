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

use OCA\Social\Exceptions\ActionDoesNotExistException;
use OCA\Social\Exceptions\InvalidResourceException;
use OCA\Social\Model\ActivityPub\ACore;
use OCA\Social\Tools\Exceptions\RowNotFoundException;
use OCA\Social\Tools\Traits\TArrayTools;

/**
 * Class ActionsRequestBuilder
 *
 * @package OCA\Social\Db
 */
class ActionsRequestBuilder extends CoreRequestBuilder {
	use TArrayTools;

	protected function getActionsInsertSql(): SocialQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->insert(self::TABLE_ACTIONS);

		return $qb;
	}


	/**
	 * Base of the Sql Update request
	 */
	protected function getActionsUpdateSql(): SocialQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->update(self::TABLE_ACTIONS);

		return $qb;
	}


	/**
	 * Base of the Sql Select request for Shares
	 */
	protected function getActionsSelectSql(): SocialQueryBuilder {
		$qb = $this->getQueryBuilder();

		/** @noinspection PhpMethodParametersCountMismatchInspection */
		$qb->select('a.id', 'a.type', 'a.actor_id', 'a.object_id', 'a.creation')
		   ->from(self::TABLE_ACTIONS, 'a');

		$this->defaultSelectAlias = 'a';
		$qb->setDefaultSelectAlias('a');

		return $qb;
	}


	/**
	 * Base of the Sql Select request for Shares
	 *
	 * @return SocialQueryBuilder
	 */
	protected function countActionsSelectSql(): SocialQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'count')
		   ->from(self::TABLE_ACTIONS, 'a');

		$this->defaultSelectAlias = 'a';
		$qb->setDefaultSelectAlias('a');

		return $qb;
	}


	/**
	 * Base of the Sql Delete request
	 *
	 * @return SocialQueryBuilder
	 */
	protected function getActionsDeleteSql(): SocialQueryBuilder {
		$qb = $this->getQueryBuilder();
		$qb->delete(self::TABLE_ACTIONS)
		   ->setDefaultSelectAlias('a');

		return $qb;
	}


	/**
	 * @param SocialQueryBuilder $qb
	 *
	 * @return ACore
	 * @throws ActionDoesNotExistException
	 */
	protected function getActionFromRequest(SocialQueryBuilder $qb): ACore {
		try {
			/** @var ACore $result */
			$result = $qb->getRow([$this, 'parseActionsSelectSql']);
		} catch (RowNotFoundException $e) {
			throw new ActionDoesNotExistException($e->getMessage());
		}

		return $result;
	}


	/**
	 * @param SocialQueryBuilder $qb
	 *
	 * @return ACore[]
	 */
	public function getActionsFromRequest(SocialQueryBuilder $qb): array {
		/** @var ACore[] $result */
		$result = $qb->getRows([$this, 'parseActionsSelectSql']);

		return $result;
	}


	/**
	 * @param array $data
	 * @param SocialQueryBuilder $qb
	 *
	 * @return ACore
	 */
	public function parseActionsSelectSql($data, SocialQueryBuilder $qb): ACore {
		$item = new ACore();
		$item->importFromDatabase($data);

		try {
			$actor = $qb->parseLeftJoinCacheActors($data, 'cacheactor_');
			$actor->setCompleteDetails(true);

			$item->setActor($actor);
		} catch (InvalidResourceException $e) {
		}

		return $item;
	}
}
