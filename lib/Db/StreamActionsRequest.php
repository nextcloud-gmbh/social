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

use OCA\Social\Exceptions\StreamActionDoesNotExistException;
use OCA\Social\Model\StreamAction;

/**
 * Class StreamActionsRequest
 *
 * @package OCA\Social\Db
 */
class StreamActionsRequest extends StreamActionsRequestBuilder {
	/**
	 * Create a new Queue in the database.
	 */
	public function create(StreamAction $action): void {
		$qb = $this->getStreamActionInsertSql();

		$values = $action->getValues();
		$liked = $this->getBool(StreamAction::LIKED, $values, false);
		$boosted = $this->getBool(StreamAction::BOOSTED, $values, false);
		$replied = $this->getBool(StreamAction::REPLIED, $values, false);

		$qb->setValue('actor_id', $qb->createNamedParameter($action->getActorId()))
		   ->setValue('actor_id_prim', $qb->createNamedParameter($qb->prim($action->getActorId())))
		   ->setValue('stream_id', $qb->createNamedParameter($action->getStreamId()))
		   ->setValue('stream_id_prim', $qb->createNamedParameter($qb->prim($action->getStreamId())))
		   ->setValue('liked', $qb->createNamedParameter(($liked) ? 1 : 0))
		   ->setValue('boosted', $qb->createNamedParameter(($boosted) ? 1 : 0))
		   ->setValue('replied', $qb->createNamedParameter(($replied) ? 1 : 0));

		$qb->executeStatement();
	}


	public function update(StreamAction $action): int {
		$qb = $this->getStreamActionUpdateSql();

		// update entry/field in database, based only on affected action
		// to avoid race condition on 2 different actions
		foreach($action->getAffected() as $entry) {
			$field = match ($entry) {
				StreamAction::LIKED => 'liked',
				StreamAction::BOOSTED => 'boosted',
				StreamAction::REPLIED => 'replied',
				default => ''
			};

			if ($field !== '') {
				$qb->set($field, $qb->createNamedParameter(($action->getValueBool($entry)) ? 1 : 0));
			}
		}

		$qb->limitToActorIdPrim($qb->prim($action->getActorId()));
		$qb->limitToStreamIdPrim($qb->prim($action->getStreamId()));

		return $qb->executeStatement();
	}


	/**
	 * @throws StreamActionDoesNotExistException
	 */
	public function getAction(string $actorId, string $streamId): StreamAction {
		$qb = $this->getStreamActionSelectSql();
		$this->limitToActorId($qb, $actorId);
		$this->limitToStreamId($qb, $streamId);

		$cursor = $qb->executeQuery();
		$data = $cursor->fetch();
		if ($data === false) {
			throw new StreamActionDoesNotExistException();
		}
		$cursor->closeCursor();

		return $this->parseStreamActionsSelectSql($data);
	}

	public function delete(StreamAction $action): void {
		$qb = $this->getStreamActionDeleteSql();
		$this->limitToActorId($qb, $action->getActorId());
		$this->limitToStreamId($qb, $action->getStreamId());

		$qb->executeStatement();
	}
}
