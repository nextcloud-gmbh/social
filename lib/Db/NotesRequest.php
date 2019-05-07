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


use daita\MySmallPhpTools\Model\Cache;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Exception;
use OCA\Social\Exceptions\NoteNotFoundException;
use OCA\Social\Model\ActivityPub\ACore;
use OCA\Social\Model\ActivityPub\Actor\Person;
use OCA\Social\Model\ActivityPub\Object\Note;
use OCA\Social\Model\ActivityPub\Stream;
use OCA\Social\Service\ConfigService;
use OCA\Social\Service\MiscService;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class NotesRequest extends NotesRequestBuilder {


	/**
	 * NotesRequest constructor.
	 *
	 * @param IDBConnection $connection
	 * @param ConfigService $configService
	 * @param MiscService $miscService
	 */
	public function __construct(
		IDBConnection $connection, ConfigService $configService, MiscService $miscService
	) {
		parent::__construct($connection, $configService, $miscService);
	}


	/**
	 * @param Stream $stream
	 *
	 * @throws Exception
	 */
	public function save(Stream $stream) {
		$qb = $this->saveStream($stream);

		if ($stream->getType() === Note::TYPE) {
			/** @var Note $stream */
			$qb->setValue(
				'hashtags', $qb->createNamedParameter(json_encode($stream->getHashtags()))
			)
			   ->setValue(
				   'attachments', $qb->createNamedParameter(
				   json_encode($stream->getAttachments(), JSON_UNESCAPED_SLASHES)
			   )
			   );
		}

		$qb->execute();
	}



//
//
//	/**
//	 * Insert a new Note in the database.
//	 *
//	 * @param SocialAppNotification $notification
//	 *
//	 * @throws Exception
//	 */
//	public function saveNotification(SocialAppNotification $notification) {
//		$qb = $this->getNotesInsertSql();
//		$qb->setValue('id', $qb->createNamedParameter($notification->getId()))
//		   ->setValue('type', $qb->createNamedParameter($notification->getType()))
//		   ->setValue('to', $qb->createNamedParameter($notification->getTo()))
//		   ->setValue('to_array', $qb->createNamedParameter(''))
//		   ->setValue('cc', $qb->createNamedParameter(''))
//		   ->setValue('bcc', $qb->createNamedParameter(''))
//		   ->setValue('content', $qb->createNamedParameter(''))
//		   ->setValue('summary', $qb->createNamedParameter($notification->getSummary()))
//		   ->setValue('published', $qb->createNamedParameter($notification->getPublished()))
//		   ->setValue(
//			   'published_time',
//			   $qb->createNamedParameter(new DateTime('now'), IQueryBuilder::PARAM_DATE)
//		   )
//		   ->setValue('attributed_to', $qb->createNamedParameter($notification->getAttributedTo()))
//		   ->setValue('in_reply_to', $qb->createNamedParameter(''))
//		   ->setValue('source', $qb->createNamedParameter($notification->getSource()))
//		   ->setValue('instances', $qb->createNamedParameter(''))
//		   ->setValue('local', $qb->createNamedParameter(($notification->isLocal()) ? '1' : '0'))
//		   ->setValue(
//			   'creation',
//			   $qb->createNamedParameter(new DateTime('now'), IQueryBuilder::PARAM_DATE)
//		   );
//
//		$qb->execute();
//	}


	/**
	 * @param Stream $stream
	 * @param Cache $cache
	 */
	public function updateCache(Stream $stream, Cache $cache) {
		$qb = $this->getNotesUpdateSql();
		$qb->set('cache', $qb->createNamedParameter(json_encode($cache, JSON_UNESCAPED_SLASHES)));

		$this->limitToIdString($qb, $stream->getId());

		try {
			$qb->execute();
		} catch (UniqueConstraintViolationException $e) {
		}
	}


	/**
	 * @param string $id
	 * @param bool $asViewer
	 *
	 * @return Stream
	 * @throws NoteNotFoundException
	 */
	public function getNoteById(string $id, bool $asViewer = false): Stream {
		if ($id === '') {
			throw new NoteNotFoundException();
		};

		$qb = $this->getNotesSelectSql();
		$this->limitToIdString($qb, $id);
		$this->leftJoinCacheActors($qb, 'attributed_to');

		if ($asViewer) {
			$this->limitToViewer($qb);
			$this->leftJoinStreamAction($qb);
		}

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new NoteNotFoundException('Post not found');
		}

		try {
			/** @var Note $note */
			$note = $this->parseNotesSelectSql($data);
		} catch (Exception $e) {
			throw new NoteNotFoundException('Malformed Post');
		}

		return $note;
	}


	/**
	 * @param string $id
	 *
	 * @return Stream
	 * @throws NoteNotFoundException
	 * @throws Exception
	 */
	public function getNoteByActivityId(string $id): Stream {
		if ($id === '') {
			throw new NoteNotFoundException();
		};

		$qb = $this->getNotesSelectSql();
		$this->limitToActivityId($qb, $id);

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new NoteNotFoundException('Post not found');
		}

		return $this->parseNotesSelectSql($data);
	}


	/**
	 * @param Person $actor
	 * @param string $type
	 *
	 * @param string $objectId
	 *
	 * @return Stream
	 * @throws NoteNotFoundException
	 */
	public function getNoteByObjectId(Person $actor, string $type, string $objectId): Stream {
		if ($objectId === '') {
			throw new NoteNotFoundException('missing objectId');
		};

		$qb = $this->getNotesSelectSql();
		$this->limitToObjectId($qb, $objectId);
		$this->limitToType($qb, $type);
		$this->limitToAttributedTo($qb, $actor->getId());

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new NoteNotFoundException(
				'StreamByObjectId not found - ' . $actor->getId() . ' - ' . $type . ' - '
				. $objectId
			);
		}

		return $this->parseNotesSelectSql($data);
	}


	/**
	 * @param string $actorId
	 *
	 * @return int
	 */
	public function countNotesFromActorId(string $actorId): int {
		$qb = $this->countNotesSelectSql();
		$this->limitToAttributedTo($qb, $actorId);
		$this->limitToType($qb, Note::TYPE);

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		return $this->getInt('count', $data, 0);
	}


	/**
	 * Should returns:
	 *  * Own posts,
	 *  * Followed accounts
	 *
	 * @param Person $actor
	 * @param int $since
	 * @param int $limit
	 *
	 * @return Stream[]
	 * @throws Exception
	 */
	public function getStreamHome(Person $actor, int $since = 0, int $limit = 5): array {
		$qb = $this->getNotesSelectSql();

		$this->joinFollowing($qb, $actor);
//		$this->limitToType($qb, Note::TYPE);
		$this->limitPaginate($qb, $since, $limit);
		$this->leftJoinCacheActors($qb, 'attributed_to');
		$this->leftJoinStreamAction($qb);

		$notes = [];
		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			try {
				$notes[] = $this->parseNotesSelectSql($data);
			} catch (Exception $e) {
			}
		}
		$cursor->closeCursor();

		return $notes;
	}


	/**
	 * Should returns:
	 *  * Public/Unlisted/Followers-only post where current $actor is tagged,
	 *  - Events: (not yet)
	 *    - people liking or re-posting your posts (not yet)
	 *    - someone wants to follow you (not yet)
	 *    - someone is following you (not yet)
	 *
	 * @param Person $actor
	 * @param int $since
	 * @param int $limit
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getStreamNotifications(Person $actor, int $since = 0, int $limit = 5): array {
		$qb = $this->getNotesSelectSql();

		$this->limitPaginate($qb, $since, $limit);
		$this->limitToRecipient($qb, $actor->getId(), false);
		$this->leftJoinCacheActors($qb, 'attributed_to');
		$this->leftJoinStreamAction($qb);

		$notes = [];
		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			try {
				$notes[] = $this->parseNotesSelectSql($data);
			} catch (Exception $e) {
			}
		}
		$cursor->closeCursor();

		return $notes;
	}


	/**
	 * Should returns:
	 *  * public message from actorId.
	 *  - to followers-only if follower is logged. (not yet (check ?))
	 *
	 * @param string $actorId
	 * @param int $since
	 * @param int $limit
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getStreamAccount(string $actorId, int $since = 0, int $limit = 5): array {
		$qb = $this->getNotesSelectSql();
		$this->limitPaginate($qb, $since, $limit);
//		$this->limitToType($qb, Note::TYPE);

		$this->limitToAttributedTo($qb, $actorId);
		$this->leftJoinCacheActors($qb, 'attributed_to');
		$this->limitToRecipient($qb, ACore::CONTEXT_PUBLIC);
		$this->leftJoinStreamAction($qb);

		$notes = [];
		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			try {
				$notes[] = $this->parseNotesSelectSql($data);
			} catch (Exception $e) {
			}
		}
		$cursor->closeCursor();

		return $notes;
	}


	/**
	 * Should returns:
	 *  * Private message.
	 *  - group messages. (not yet)
	 *
	 * @param Person $actor
	 * @param int $since
	 * @param int $limit
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getStreamDirect(Person $actor, int $since = 0, int $limit = 5): array {
		$qb = $this->getNotesSelectSql();
		$this->limitPaginate($qb, $since, $limit);

//		$this->limitToType($qb, Note::TYPE);
		$this->limitToRecipient($qb, $actor->getId(), true);
		$this->filterToRecipient($qb, ACore::CONTEXT_PUBLIC);
		$this->filterToRecipient($qb, $actor->getFollowers());

		$this->leftJoinCacheActors($qb, 'attributed_to');

		$notes = [];
		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			try {
				$notes[] = $this->parseNotesSelectSql($data);
			} catch (Exception $e) {
			}
		}
		$cursor->closeCursor();

		return $notes;
	}


	/**
	 * Should returns:
	 *  * All local public/federated posts
	 *
	 * @param int $since
	 * @param int $limit
	 * @param bool $localOnly
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getStreamTimeline(int $since = 0, int $limit = 5, bool $localOnly = true
	): array {
		$qb = $this->getNotesSelectSql();
		$this->limitPaginate($qb, $since, $limit);
//		$this->limitToType($qb, Note::TYPE);

		if ($localOnly) {
			$this->limitToLocal($qb, true);
		}

		$this->leftJoinCacheActors($qb, 'attributed_to');
		$this->leftJoinStreamAction($qb);

		// TODO: to: = real public, cc: = unlisted !?
		$this->limitToRecipient($qb, ACore::CONTEXT_PUBLIC, true, ['to']);

		$notes = [];
		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			try {
				$notes[] = $this->parseNotesSelectSql($data);
			} catch (Exception $e) {
			}
		}
		$cursor->closeCursor();

		return $notes;
	}


	/**
	 * Should returns:
	 *  - All public post related to a tag (not yet)
	 *  - direct message related to a tag (not yet)
	 *  - message to followers related to a tag (not yet)
	 *
	 * @param Person $actor
	 * @param string $hashtag
	 * @param int $since
	 * @param int $limit
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getStreamTag(Person $actor, string $hashtag, int $since = 0, int $limit = 5
	): array {
		$qb = $this->getNotesSelectSql();

		$on = $this->exprJoinFollowing($qb, $actor);
		$on->add($this->exprLimitToRecipient($qb, ACore::CONTEXT_PUBLIC, false));
		$on->add($this->exprLimitToRecipient($qb, $actor->getId(), true));
		$qb->join($this->defaultSelectAlias, CoreRequestBuilder::TABLE_FOLLOWS, 'f', $on);

		$qb->andWhere($this->exprValueWithinJsonFormat($qb, 'hashtags', '' . $hashtag));

		$this->limitPaginate($qb, $since, $limit);
		$this->leftJoinCacheActors($qb, 'attributed_to');
		$this->leftJoinStreamAction($qb);

		$notes = [];
		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			$notes[] = $this->parseNotesSelectSql($data);
		}
		$cursor->closeCursor();

		return $notes;
	}


	/**
	 * @param int $since
	 *
	 * @return Note[]
	 * @throws Exception
	 */
	public function getNotesSince(int $since): array {
		$qb = $this->getNotesSelectSql();
		$this->limitToSince($qb, $since, 'published_time');
		$this->leftJoinStreamAction($qb);

		$notes = [];
		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			$notes[] = $this->parseNotesSelectSql($data);
		}
		$cursor->closeCursor();

		return $notes;
	}


	/**
	 * @param string $id
	 * @param string $type
	 */
	public function deleteNoteById(string $id, string $type = '') {
		$qb = $this->getNotesDeleteSql();

		$this->limitToIdString($qb, $id);
		if ($type !== '') {
			$this->limitToType($qb, $type);
		}

		$qb->execute();
	}


	/**
	 * @param string $actorId
	 */
	public function deleteByAuthor(string $actorId) {
		$qb = $this->getNotesDeleteSql();
		$this->limitToAttributedTo($qb, $actorId);

		$qb->execute();
	}


	/**
	 * Insert a new Note in the database.
	 *
	 * @param Stream $note
	 *
	 * @return IQueryBuilder
	 */
	public function saveStream(Stream $note): IQueryBuilder {

		try {
			$dTime = new DateTime();
			$dTime->setTimestamp($note->getPublishedTime());
		} catch (Exception $e) {
		}

		$cache = '[]';
		if ($note->gotCache()) {
			$cache = json_encode($note->getCache(), JSON_UNESCAPED_SLASHES);
		}

		$attributedTo = $note->getAttributedTo();
		if ($attributedTo === '' && $note->isLocal()) {
			$attributedTo = $note->getActor()
								 ->getId();
		}

		$qb = $this->getNotesInsertSql();
		$qb->setValue('id', $qb->createNamedParameter($note->getId()))
		   ->setValue('type', $qb->createNamedParameter($note->getType()))
		   ->setValue('to', $qb->createNamedParameter($note->getTo()))
		   ->setValue(
			   'to_array', $qb->createNamedParameter(
			   json_encode($note->getToArray(), JSON_UNESCAPED_SLASHES)
		   )
		   )
		   ->setValue(
			   'cc', $qb->createNamedParameter(
			   json_encode($note->getCcArray(), JSON_UNESCAPED_SLASHES)
		   )
		   )
		   ->setValue(
			   'bcc', $qb->createNamedParameter(
			   json_encode($note->getBccArray()), JSON_UNESCAPED_SLASHES
		   )
		   )
		   ->setValue('content', $qb->createNamedParameter($note->getContent()))
		   ->setValue('summary', $qb->createNamedParameter($note->getSummary()))
		   ->setValue('published', $qb->createNamedParameter($note->getPublished()))
		   ->setValue('attributed_to', $qb->createNamedParameter($attributedTo))
		   ->setValue('in_reply_to', $qb->createNamedParameter($note->getInReplyTo()))
		   ->setValue('source', $qb->createNamedParameter($note->getSource()))
		   ->setValue('object_id', $qb->createNamedParameter($note->getObjectId()))
		   ->setValue('cache', $qb->createNamedParameter($cache))
		   ->setValue(
			   'instances', $qb->createNamedParameter(
			   json_encode($note->getInstancePaths(), JSON_UNESCAPED_SLASHES)
		   )
		   )
		   ->setValue('local', $qb->createNamedParameter(($note->isLocal()) ? '1' : '0'));

		try {
			$dTime = new DateTime();
			$dTime->setTimestamp($note->getPublishedTime());
			$qb->setValue(
				'published_time', $qb->createNamedParameter($dTime, IQueryBuilder::PARAM_DATE)
			)
			   ->setValue(
				   'creation',
				   $qb->createNamedParameter(new DateTime('now'), IQueryBuilder::PARAM_DATE)
			   );
		} catch (Exception $e) {
		}

		$this->generatePrimaryKey($qb, $note->getId());

		return $qb;
	}

}

