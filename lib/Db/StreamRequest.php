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

use DateTime;
use Exception;
use OCA\Social\Exceptions\ItemUnknownException;
use OCA\Social\Exceptions\StreamNotFoundException;
use OCA\Social\Model\ActivityPub\ACore;
use OCA\Social\Model\ActivityPub\Internal\SocialAppNotification;
use OCA\Social\Model\ActivityPub\Object\Document;
use OCA\Social\Model\ActivityPub\Object\Note;
use OCA\Social\Model\ActivityPub\Stream;
use OCA\Social\Model\Client\Options\ProbeOptions;
use OCA\Social\Service\ConfigService;
use OCA\Social\Service\MiscService;
use OCA\Social\Tools\Exceptions\DateTimeException;
use OCA\Social\Tools\Model\Cache;
use OCP\DB\Exception as DBException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * Class StreamRequest
 *
 * @package OCA\Social\Db
 */
class StreamRequest extends StreamRequestBuilder {
	private const NID_LIMIT = 1000000;
	private StreamDestRequest $streamDestRequest;
	private StreamTagsRequest $streamTagsRequest;

	public function __construct(
		IDBConnection $connection, LoggerInterface $logger, IURLGenerator $urlGenerator,
		StreamDestRequest $streamDestRequest, StreamTagsRequest $streamTagsRequest,
		ConfigService $configService, MiscService $miscService
	) {
		parent::__construct($connection, $logger, $urlGenerator, $configService, $miscService);

		$this->streamDestRequest = $streamDestRequest;
		$this->streamTagsRequest = $streamTagsRequest;
	}

	public function save(Stream $stream): void {
		$qb = $this->saveStream($stream);
		if ($stream->getType() === Note::TYPE) {
			/** @var Note $stream */

			$attachments = [];
			foreach ($stream->getAttachments() as $item) {
				$attachments[] = $item->asLocal(); // get attachment ready for local
			}

			$qb->setValue('hashtags', $qb->createNamedParameter(json_encode($stream->getHashtags())))
			   ->setValue(
			   	'attachments', $qb->createNamedParameter(json_encode($attachments, JSON_UNESCAPED_SLASHES)
			   	)
			   );
		}

		try {
			$qb->executeStatement();

			$this->streamDestRequest->generateStreamDest($stream);
			$this->streamTagsRequest->generateStreamTags($stream);
		} catch (DBException $e) {
			if ($e->getReason() !== DBException::REASON_CONSTRAINT_VIOLATION) {
				$this->logger->error("Couldn't save stream: " . $e->getMessage(), [
					'exception' => $e,
				]);
			}
		}
	}

	public function update(Stream $stream, bool $generateDest = false): void {
		$qb = $this->getStreamUpdateSql();

		$qb->set('to', $qb->createNamedParameter($stream->getTo()));
		$qb->set(
			'cc', $qb->createNamedParameter(json_encode($stream->getCcArray(), JSON_UNESCAPED_SLASHES))
		);
		$qb->set(
			'to_array', $qb->createNamedParameter(json_encode($stream->getToArray(), JSON_UNESCAPED_SLASHES))
		);
		$qb->limitToIdPrim($qb->prim($stream->getId()));
		$qb->executeStatement();

		if ($generateDest) {
			$this->streamDestRequest->generateStreamDest($stream);
		}
	}


	public function updateDetails(Stream $stream): void {
		$qb = $this->getStreamUpdateSql();
		$qb->set('details', $qb->createNamedParameter(json_encode($stream->getDetailsAll())));
		$qb->limitToIdPrim($qb->prim($stream->getId()));
		$qb->executeStatement();
	}


	public function updateCache(Stream $stream, Cache $cache): void {
		$qb = $this->getStreamUpdateSql();
		$qb->set('cache', $qb->createNamedParameter(json_encode($cache, JSON_UNESCAPED_SLASHES)));

		$qb->limitToIdPrim($qb->prim($stream->getId()));

		$qb->executeStatement();
	}

	public function updateAttachments(Document $document): void {
		$qb = $this->getStreamSelectSql();
		$qb->limitToIdPrim($qb->prim($document->getParentId()));

		$cursor = $qb->executeQuery();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			return;
		}

		$new = $this->updateAttachmentInList($document, $this->getArray('attachments', $data, []));
		$qb = $this->getStreamUpdateSql();
		$qb->set('attachments', $qb->createNamedParameter(json_encode($new, JSON_UNESCAPED_SLASHES)));
		$qb->limitToIdPrim($qb->prim($document->getParentId()));

		$qb->executeStatement();
	}

	/**
	 * @return Document[]
	 */
	private function updateAttachmentInList(Document $document, array $attachments): array {
		$new = [];
		foreach ($attachments as $attachment) {
			$tmp = new Document();
			$tmp->importFromDatabase($attachment);
			if ($tmp->getId() === $document->getId()) {
				$new[] = $document;
			} else {
				$new[] = $tmp;
			}
		}

		return $new;
	}


	public function updateAttributedTo(string $itemId, string $to): void {
		$qb = $this->getStreamUpdateSql();
		$qb->set('attributed_to', $qb->createNamedParameter($to));
		$qb->set('attributed_to_prim', $qb->createNamedParameter($qb->prim($to)));

		$qb->limitToIdPrim($qb->prim($itemId));

		$qb->executeStatement();
	}


	/**
	 * @param string $type
	 *
	 * @return Stream[]
	 */
	public function getAll(string $type = ''): array {
		$qb = $this->getStreamSelectSql();

		if ($type !== '') {
			$qb->limitToType($type);
		}

		return $this->getStreamsFromRequest($qb);
	}


	/**
	 * @param string $id
	 * @param bool $asViewer
	 * @param int $format
	 *
	 * @return Stream
	 * @throws StreamNotFoundException
	 */
	public function getStreamById(
		string $id,
		bool $asViewer = false,
		int $format = ACore::FORMAT_ACTIVITYPUB
	): Stream {
		if ($id === '') {
			throw new StreamNotFoundException();
		};

		$qb = $this->getStreamSelectSql($format);
		$qb->limitToIdPrim($qb->prim($id));
		$qb->linkToCacheActors('ca', 's.attributed_to_prim');

		if ($asViewer) {
			$qb->limitToViewer('sd', 'f', true, true);
			$qb->leftJoinStreamAction('sa');
		}

		try {
			return $this->getStreamFromRequest($qb);
		} catch (ItemUnknownException $e) {
			throw new StreamNotFoundException('Malformed Stream');
		} catch (StreamNotFoundException $e) {
			throw new StreamNotFoundException('Stream not found');
		}
	}


	/**
	 * @param string $id
	 * @param bool $asViewer
	 * @param int $format
	 *
	 * @return Stream
	 * @throws StreamNotFoundException
	 */
	public function getStreamByNid(int $nid): Stream {
		$qb = $this->getStreamSelectSql(ACore::FORMAT_LOCAL);
		$qb->limitToNid($nid);
		$qb->linkToCacheActors('ca', 's.attributed_to_prim');

		$qb->limitToViewer('sd', 'f', true, true);
		$qb->leftJoinStreamAction('sa');

		return $this->getStreamFromRequest($qb);
	}


	/**
	 * @param string $idPrim
	 *
	 * @return Stream
	 * @throws StreamNotFoundException
	 */
	public function getStream(string $idPrim): Stream {
		$qb = $this->getStreamSelectSql();
		$qb->limitToIdPrim($idPrim);

		return $this->getStreamFromRequest($qb);
	}

	/**
	 * @param string $id
	 * @param int $since
	 * @param int $limit
	 * @param bool $asViewer
	 *
	 * @return Stream[]
	 * @throws StreamNotFoundException
	 * @throws DateTimeException
	 */
	public function getRepliesByParentId(string $id, int $since = 0, int $limit = 5, bool $asViewer = false
	): array {
		if ($id === '') {
			throw new StreamNotFoundException();
		};

		$qb = $this->getStreamSelectSql();
		$qb->limitToInReplyTo($id);
		$qb->limitPaginate($since, $limit);

		$qb->linkToCacheActors('ca', 's.attributed_to_prim');
		if ($asViewer) {
			$qb->limitToViewer('sd', 'f', true);
			$qb->leftJoinStreamAction();
		}

		return $this->getStreamsFromRequest($qb);
	}


	/**
	 * @param string $id
	 *
	 * @return Stream
	 * @throws StreamNotFoundException
	 * @throws Exception
	 */
	public function getStreamByActivityId(string $id): Stream {
		if ($id === '') {
			throw new StreamNotFoundException();
		};

		$qb = $this->getStreamSelectSql();
		$qb->limitToActivityId($id);

		return $this->getStreamFromRequest($qb);
	}


	/**
	 * @param string $objectId
	 * @param string $type
	 * @param string $subType
	 *
	 * @return Stream
	 * @throws StreamNotFoundException
	 */
	public function getStreamByObjectId(string $objectId, string $type, string $subType = ''
	): Stream {
		if ($objectId === '') {
			throw new StreamNotFoundException('missing objectId');
		};

		$qb = $this->getStreamSelectSql();
		$qb->limitToObjectId($objectId);
		$qb->limitToType($type);
		$qb->limitToSubType($subType);

		return $this->getStreamFromRequest($qb);
	}


	/**
	 * @param string $id
	 *
	 * @return int
	 */
	public function countRepliesTo(string $id): int {
		$qb = $this->countNotesSelectSql();
		$qb->limitToInReplyTo($id, true);

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		return $this->getInt('count', $data, 0);
	}


	/**
	 * @param string $actorId
	 *
	 * @return int
	 */
	public function countNotesFromActorId(string $actorId): int {
		$qb = $this->countNotesSelectSql();
		$qb->limitToAttributedTo($actorId, true);
		$qb->limitToType(Note::TYPE);

		$qb->selectDestFollowing('sd', '');
		$qb->innerJoinStreamDest('recipient', 'id_prim', 'sd', 's');
		$qb->limitToDest(ACore::CONTEXT_PUBLIC, 'recipient', '', 'sd');

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		return $this->getInt('count', $data, 0);
	}


	/**
	 * @param string $actorId
	 *
	 * @return Stream
	 * @throws StreamNotFoundException
	 */
	public function lastNoteFromActorId(string $actorId): Stream {
		$qb = $this->getStreamSelectSql();
		$qb->limitToAttributedTo($actorId, true);
		$qb->limitToType(Note::TYPE);

		$qb->selectDestFollowing('sd', '');
		$qb->innerJoinStreamDest('recipient', 'id_prim', 'sd', 's');
		$qb->limitToDest(ACore::CONTEXT_PUBLIC, 'recipient', '', 'sd');

		$qb->orderBy('id', 'desc');
		$qb->setMaxResults(1);

		return $this->getStreamFromRequest($qb);
	}

	/**
	 * @param ProbeOptions $options
	 *
	 * @return Stream[]
	 */
	public function getTimeline(ProbeOptions $options): array {
		switch (strtolower($options->getProbe())) {
			case ProbeOptions::ACCOUNT:
				$result = $this->getTimelineAccount($options);
				break;
			case ProbeOptions::HOME:
				$result = $this->getTimelineHome($options);
				break;
			case ProbeOptions::DIRECT:
				$result = $this->getTimelineDirect($options);
				break;
			case ProbeOptions::FAVOURITES:
				$result = $this->getTimelineFavourites($options);
				break;
			case ProbeOptions::HASHTAG:
				$result = $this->getTimelineHashtag($options);
				break;
			case ProbeOptions::NOTIFICATIONS:
				$options->setFormat(ACore::FORMAT_NOTIFICATION);
				$result = $this->getTimelineNotifications($options);
				break;
			case ProbeOptions::PUBLIC:
				$result = $this->getTimelinePublic($options);
				break;
			default:
				return [];
		}

		if ($options->isInverted()) {
			// in case we inverted the order during the request, we revert the results
			$result = array_reverse($result);
		}

		return $result;
	}

	/**
	 * Should return:
	 *  * Own posts,
	 *  * Followed accounts
	 *
	 * @param ProbeOptions $options
	 *
	 * @return Stream[]
	 */
	private function getTimelineHome(ProbeOptions $options): array {
		$qb = $this->getStreamSelectSql($options->getFormat());

		$qb->filterType(SocialAppNotification::TYPE);
		$qb->paginate($options);

		$qb->limitToViewer('sd', 'f', false);
		$this->timelineHomeLinkCacheActor($qb, 'ca', 'f');

		$qb->leftJoinStreamAction('sa');
		$qb->leftJoinObjectStatus();
		$qb->filterDuplicate();

		return $this->getStreamsFromRequest($qb);
	}


	/**
	 * Should return:
	 *  * Private message.
	 *  - group messages. (not yet)
	 *
	 * @param ProbeOptions $options
	 *
	 * @return Stream[]
	 */
	private function getTimelineDirect(ProbeOptions $options): array {
		$qb = $this->getStreamSelectSql($options->getFormat());

		$qb->filterType(SocialAppNotification::TYPE);
		$qb->paginate($options);

		$qb->linkToCacheActors('ca', 's.attributed_to_prim');

		$viewer = $qb->getViewer();
		$qb->selectDestFollowing('sd', '');
		$qb->limitToDest($viewer->getId(), 'dm', '', 'sd');

		return $this->getStreamsFromRequest($qb);
	}


	/**
	 * Should returns:
	 *  - public message from actorId.
	 *  - followers-only if logged and follower.
	 *
	 * @param ProbeOptions $options
	 *
	 * @return Stream[]
	 */
	private function getTimelineAccount(ProbeOptions $options): array {
		$qb = $this->getStreamSelectSql($options->getFormat());

		$qb->limitToType(Note::TYPE);
		$qb->paginate($options);

		$actorId = $options->getAccountId();
		if ($actorId === '') {
			return [];
		}

		$qb->limitToAttributedTo($actorId, true);

		$qb->selectDestFollowing('sd', '');
		$qb->innerJoinStreamDest('recipient', 'id_prim', 'sd', 's');
		$accountIsViewer = ($qb->hasViewer() && $qb->getViewer()->getId() === $actorId);
		$qb->limitToDest($accountIsViewer ? '' : ACore::CONTEXT_PUBLIC, 'recipient', '', 'sd');

		$qb->linkToCacheActors('ca', 's.attributed_to_prim');
		$qb->leftJoinStreamAction();

		return $this->getStreamsFromRequest($qb);
	}


	/**
	 * @param ProbeOptions $options
	 *
	 * @return Stream[]
	 */
	private function getTimelineFavourites(ProbeOptions $options): array {
		$qb = $this->getStreamSelectSql($options->getFormat());
		$actor = $qb->getViewer();
		$expr = $qb->expr();

		$qb->limitToType(Note::TYPE);
		$qb->paginate($options);
		$qb->linkToCacheActors('ca', 's.attributed_to_prim');

		$qb->selectStreamActions('sa');
		$qb->andWhere($expr->eq('sa.stream_id_prim', 's.id_prim'));
		$qb->andWhere($expr->eq('sa.actor_id_prim', $qb->createNamedParameter($qb->prim($actor->getId()))));
		$qb->andWhere($expr->eq('sa.liked', $qb->createNamedParameter(1)));

		return $this->getStreamsFromRequest($qb);
	}


	/**
	 * @param ProbeOptions $options
	 *
	 * @return Stream[]
	 */
	private function getTimelineHashtag(ProbeOptions $options): array {
		$qb = $this->getStreamSelectSql($options->getFormat());
		$qb->limitToType(Note::TYPE);
		$qb->paginate($options);

		$expr = $qb->expr();
		$qb->linkToCacheActors('ca', 's.attributed_to_prim');
		$qb->linkToStreamTags('st', 's.id_prim');
		$qb->andWhere($qb->exprLimitToDBField('hashtag', $options->getArgument(), true, false, 'st'));

		$qb->limitToViewer('sd', 'f', true);
		$qb->andWhere($expr->eq('s.attributed_to_prim', 'ca.id_prim'));

		$qb->leftJoinStreamAction('sa');

		return $this->getStreamsFromRequest($qb);
	}


	/**
	 * @param ProbeOptions $options
	 *
	 * @return Stream[]
	 */
	private function getTimelineNotifications(ProbeOptions $options): array {
		$qb = $this->getStreamSelectSql($options->getFormat());
		$actor = $qb->getViewer();

		$qb->limitToType(SocialAppNotification::TYPE);
		$qb->paginate($options);

		$qb->selectDestFollowing('sd', '');
		$qb->limitToDest($actor->getId(), 'notif', '', 'sd');
		$qb->linkToCacheActors('ca', 's.attributed_to_prim');
		$qb->leftJoinStreamAction();
		$qb->leftJoinObjectStatus();

		return $this->getStreamsFromRequest($qb);
	}


	/**
	 * Should return:
	 *  * Own posts,
	 *  * Followed accounts
	 *
	 * @param int $since
	 * @param int $limit
	 * @param int $format
	 *
	 * @return Stream[]
	 * @throws DateTimeException
	 * @deprecated - use getTimelineHome()
	 */
	public function getTimelineHome_dep(
		int $since = 0, int $limit = 5, int $format = Stream::FORMAT_ACTIVITYPUB
	): array {
		$qb = $this->getStreamSelectSql($format);

		$qb->filterType(SocialAppNotification::TYPE);
		$qb->limitPaginate($since, $limit);

		$qb->limitToViewer('sd', 'f', false);
		$this->timelineHomeLinkCacheActor($qb, 'ca', 'f');

		$qb->leftJoinStreamAction('sa');
		$qb->filterDuplicate();

		return $this->getStreamsFromRequest($qb);
	}


	/**
	 * Should return:
	 *  * Public/Unlisted/Followers-only post where current $actor is tagged,
	 *  - Events: (not yet)
	 *    - people liking or re-posting your posts (not yet)
	 *    - someone wants to follow you (not yet)
	 *    - someone is following you (not yet)
	 *
	 * @param int $since
	 * @param int $limit
	 *
	 * @return Stream[]
	 * @throws DateTimeException
	 * @deprecated
	 */
	public function getTimelineNotifications_dep(int $since = 0, int $limit = 5): array {
		$qb = $this->getStreamSelectSql();

		$actor = $qb->getViewer();

		$qb->limitPaginate($since, $limit);

		$qb->selectDestFollowing('sd', '');
		$qb->limitToDest($actor->getId(), 'notif', '', 'sd');
		$qb->limitToType(SocialAppNotification::TYPE);

		$qb->linkToCacheActors('ca', 's.attributed_to_prim');
		$qb->leftJoinStreamAction();

		return $this->getStreamsFromRequest($qb);
	}


	/**
	 * Should return:
	 *  * public message from actorId.
	 *  - to followers-only if follower is logged. (not yet (check ?))
	 *
	 * @param string $actorId
	 * @param int $since
	 * @param int $limit
	 *
	 * @return Stream[]
	 * @throws DateTimeException
	 */
	public function getTimelineAccount_dep(string $actorId, int $since = 0, int $limit = 5): array {
		$qb = $this->getStreamSelectSql();
		$qb->limitPaginate($since, $limit);

		$qb->limitToAttributedTo($actorId);

		$qb->selectDestFollowing('sd', '');
		$qb->innerJoinStreamDest('recipient', 'id_prim', 'sd', 's');
		$accountIsViewer = ($qb->hasViewer() && $qb->getViewer()->getId() === $actorId);
		$qb->limitToDest($accountIsViewer ? '' : ACore::CONTEXT_PUBLIC, 'recipient', '', 'sd');

		$qb->linkToCacheActors('ca', 's.attributed_to_prim');
		$qb->leftJoinStreamAction();

		return $this->getStreamsFromRequest($qb);
	}


	/**
	 * Should return:
	 *  * Private message.
	 *  - group messages. (not yet)
	 *
	 * @param int $since
	 * @param int $limit
	 *
	 * @return Stream[]
	 * @throws DateTimeException
	 */
	public function getTimelineDirect_dep(int $since = 0, int $limit = 5): array {
		$qb = $this->getStreamSelectSql();

		$qb->filterType(SocialAppNotification::TYPE);
		$qb->limitPaginate($since, $limit);

		$qb->linkToCacheActors('ca', 's.attributed_to_prim');

		$viewer = $qb->getViewer();
		$qb->selectDestFollowing('sd', '');
		$qb->limitToDest($viewer->getId(), 'dm', '', 'sd');

		return $this->getStreamsFromRequest($qb);
	}

	/**
	 * Should return:
	 *  * All local public/federated posts
	 *
	 * @param ProbeOptions $options
	 *
	 * @return Stream[]
	 */
	private function getTimelinePublic(ProbeOptions $options): array {
		$qb = $this->getStreamSelectSql($options->getFormat());
		$qb->paginate($options);

		if ($options->isLocal()) {
			$qb->limitToLocal(true);
		}
		$qb->limitToType(Note::TYPE);

		$qb->linkToCacheActors('ca', 's.attributed_to_prim');
		$qb->leftJoinStreamAction();

		$qb->selectDestFollowing('sd', '');
		$qb->innerJoinStreamDest('recipient', 'id_prim', 'sd', 's');
		$qb->limitToDest(ACore::CONTEXT_PUBLIC, 'recipient', 'to', 'sd');

		return $this->getStreamsFromRequest($qb);
	}


	/**
	 * Should returns:
	 *  * All local public/federated posts
	 *
	 * @param int $since
	 * @param int $limit
	 * @param bool $localOnly
	 *
	 * @return Stream[]
	 * @throws DateTimeException
	 * @deprecated - use getTimelinePublic()
	 */
	public function getTimelineGlobal_dep(int $since = 0, int $limit = 5, bool $localOnly = true
	): array {
		$qb = $this->getStreamSelectSql();
		$qb->limitPaginate($since, $limit);

		$qb->limitToLocal($localOnly);
		$qb->limitToType(Note::TYPE);

		$qb->linkToCacheActors('ca', 's.attributed_to_prim');
		$qb->leftJoinStreamAction();

		$qb->selectDestFollowing('sd', '');
		$qb->innerJoinStreamDest('recipient', 'id_prim', 'sd', 's');
		$qb->limitToDest(ACore::CONTEXT_PUBLIC, 'recipient', 'to', 'sd');

		return $this->getStreamsFromRequest($qb);
	}


	/**
	 * Should returns:
	 *  * All liked posts
	 *
	 * @param int $since
	 * @param int $limit
	 *
	 * @return Stream[]
	 * @throws DateTimeException
	 */
	public function getTimelineLiked(int $since = 0, int $limit = 5): array {
		$qb = $this->getStreamSelectSql();
		if (!$qb->hasViewer()) {
			return [];
		}

		$actor = $qb->getViewer();

		$qb->limitToType(Note::TYPE);
		$qb->limitPaginate($since, $limit);

		$expr = $qb->expr();
		$qb->linkToCacheActors('ca', 's.attributed_to_prim');

		$qb->selectStreamActions('sa');
		$qb->andWhere($expr->eq('sa.stream_id_prim', 's.id_prim'));
		$qb->andWhere($expr->eq('sa.actor_id_prim', $qb->createNamedParameter($qb->prim($actor->getId()))));
		$qb->andWhere($expr->eq('sa.liked', $qb->createNamedParameter(1)));

		return $this->getStreamsFromRequest($qb);
	}


	/**
	 * Should return:
	 *  - All public post related to a tag (not yet)
	 *  - direct message related to a tag (not yet)
	 *  - message to followers related to a tag (not yet)
	 *
	 * @param string $hashtag
	 * @param int $since
	 * @param int $limit
	 *
	 * @return Stream[]
	 * @throws DateTimeException
	 */
	public function getTimelineTag(string $hashtag, int $since = 0, int $limit = 5): array {
		$qb = $this->getStreamSelectSql();

		$expr = $qb->expr();
		$qb->linkToCacheActors('ca', 's.attributed_to_prim');
		$qb->linkToStreamTags('st', 's.id_prim');
		$qb->limitPaginate($since, $limit);

		$qb->andWhere($qb->exprLimitToDBField('type', Note::TYPE));
		$qb->andWhere($qb->exprLimitToDBField('hashtag', $hashtag, true, false, 'st'));

		$qb->limitToViewer('sd', 'f', true);
		$qb->andWhere($expr->eq('s.attributed_to_prim', 'ca.id_prim'));

		$qb->leftJoinStreamAction('sa');

		return $this->getStreamsFromRequest($qb);
	}


	/**
	 * @param int $since
	 *
	 * @return Stream[]
	 * @throws DateTimeException
	 */
	public function getNoteSince(int $since): array {
		$qb = $this->getStreamSelectSql();
		$qb->limitToSince($since, 'published_time');
		$qb->limitToType(Note::TYPE);
		$qb->leftJoinStreamAction();

		return $this->getStreamsFromRequest($qb);
	}


	/**
	 * @param string $id
	 * @param string $type
	 */
	public function deleteById(string $id, string $type = '') {
		$qb = $this->getStreamDeleteSql();
		$qb->limitToIdPrim($qb->prim($id));

		if ($type !== '') {
			$qb->limitToType($type);
		}

		$qb->execute();
	}


	/**
	 * @param string $actorId
	 */
	public function deleteByAuthor(string $actorId) {
		$qb = $this->getStreamDeleteSql();
		$qb->limitToAttributedTo($actorId, true);

		$qb->execute();
	}


	/**
	 * @param string $actorId
	 */
	public function updateAuthor(string $actorId, string $newId) {
		$qb = $this->getStreamUpdateSql();
		$qb->set('attributed_to', $qb->createNamedParameter($newId))
		   ->set('attributed_to_prim', $qb->createNamedParameter($qb->prim($newId)));
		$qb->limitToAttributedTo($actorId, true);

		$qb->executeStatement();
	}


	/**
	 * Insert a new Stream in the database.
	 *
	 * @param Stream $stream
	 *
	 * @return IQueryBuilder
	 */
	public function saveStream(Stream $stream): IQueryBuilder {
		try {
			$dTime = new DateTime();
			$dTime->setTimestamp($stream->getPublishedTime());
		} catch (Exception $e) {
		}

		$cache = '[]';
		if ($stream->hasCache()) {
			$cache = json_encode($stream->getCache(), JSON_UNESCAPED_SLASHES);
		}

		$attributedTo = $stream->getAttributedTo();
		if ($attributedTo === '' && $stream->isLocal()) {
			$attributedTo = $stream->getActor()
								   ->getId();
		}

		if ($stream->getNid() === 0) {
			$stream->setNid($stream->getPublishedTime() * self::NID_LIMIT + rand(1, self::NID_LIMIT));
		}

		$qb = $this->getStreamInsertSql();
		$qb->setValue('nid', $qb->createNamedParameter($stream->getNid()))
		   ->setValue('id', $qb->createNamedParameter($stream->getId()))
		   ->setValue('visibility', $qb->createNamedParameter($stream->getVisibility()))
		   ->setValue('type', $qb->createNamedParameter($stream->getType()))
		   ->setValue('subtype', $qb->createNamedParameter($stream->getSubType()))
		   ->setValue('to', $qb->createNamedParameter($stream->getTo()))
		   ->setValue(
		   	'to_array', $qb->createNamedParameter(
		   		json_encode($stream->getToArray(), JSON_UNESCAPED_SLASHES)
		   	)
		   )
		   ->setValue(
		   	'cc', $qb->createNamedParameter(
		   		json_encode($stream->getCcArray(), JSON_UNESCAPED_SLASHES)
		   	)
		   )
		   ->setValue(
		   	'bcc', $qb->createNamedParameter(
		   		json_encode($stream->getBccArray(), JSON_UNESCAPED_SLASHES)
		   	)
		   )
		   ->setValue('content', $qb->createNamedParameter($stream->getContent()))
		   ->setValue('summary', $qb->createNamedParameter($stream->getSummary()))
		   ->setValue('published', $qb->createNamedParameter($stream->getPublished()))
		   ->setValue('attributed_to', $qb->createNamedParameter($attributedTo))
		   ->setValue('attributed_to_prim', $qb->createNamedParameter($qb->prim($attributedTo)))
		   ->setValue('in_reply_to', $qb->createNamedParameter($stream->getInReplyTo()))
		   ->setValue('in_reply_to_prim', $qb->createNamedParameter($qb->prim($stream->getInReplyTo())))
		   ->setValue('source', $qb->createNamedParameter($stream->getSource()))
		   ->setValue('activity_id', $qb->createNamedParameter($stream->getActivityId()))
		   ->setValue('object_id', $qb->createNamedParameter($stream->getObjectId()))
		   ->setValue('object_id_prim', $qb->createNamedParameter($qb->prim($stream->getObjectId())))
		   ->setValue('details', $qb->createNamedParameter(json_encode($stream->getDetailsAll())))
		   ->setValue('cache', $qb->createNamedParameter($cache))
		   ->setValue(
		   	'filter_duplicate',
		   	$qb->createNamedParameter(($stream->isFilterDuplicate()) ? '1' : '0')
		   )
		   ->setValue(
		   	'instances', $qb->createNamedParameter(
		   		json_encode($stream->getInstancePaths(), JSON_UNESCAPED_SLASHES)
		   	)
		   )
		   ->setValue('local', $qb->createNamedParameter(($stream->isLocal()) ? '1' : '0'));

		try {
			$dTime = new DateTime();
			$dTime->setTimestamp($stream->getPublishedTime());
			$qb->setValue(
				'published_time', $qb->createNamedParameter($dTime, IQueryBuilder::PARAM_DATE)
			)
			   ->setValue(
			   	'creation',
			   	$qb->createNamedParameter(new DateTime('now'), IQueryBuilder::PARAM_DATE)
			   );
		} catch (Exception $e) {
		}

		$qb->generatePrimaryKey($stream->getId(), 'id_prim');

		return $qb;
	}


	public function getRelatedToActor(string $actorId) {
	}


	/**
	 * @param string $id
	 *
	 * @return array
	 */
	public function getDescendants(string $id): array {
		$qb = $this->getStreamSelectSql(ACore::FORMAT_LOCAL);

		$qb->filterType(SocialAppNotification::TYPE);
		$qb->limitToViewer('sd', 'f', true);
		$qb->limitToInReplyTo($id, true);

		$qb->linkToCacheActors('ca', 's.attributed_to_prim');
		//$qb->filterDuplicate();

		return $this->getStreamsFromRequest($qb);
	}
}
