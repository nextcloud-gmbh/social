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

namespace OCA\Social\Service;


use daita\MySmallPhpTools\Exceptions\MalformedArrayException;
use Exception;
use OCA\Social\AP;
use OCA\Social\Db\StreamRequest;
use OCA\Social\Exceptions\InvalidOriginException;
use OCA\Social\Exceptions\InvalidResourceException;
use OCA\Social\Exceptions\ItemAlreadyExistsException;
use OCA\Social\Exceptions\ItemUnknownException;
use OCA\Social\Exceptions\RedundancyLimitException;
use OCA\Social\Exceptions\RequestContentException;
use OCA\Social\Exceptions\RequestNetworkException;
use OCA\Social\Exceptions\RequestResultNotJsonException;
use OCA\Social\Exceptions\RequestResultSizeException;
use OCA\Social\Exceptions\RequestServerException;
use OCA\Social\Exceptions\SocialAppConfigException;
use OCA\Social\Exceptions\StreamNotFoundException;
use OCA\Social\Exceptions\UnauthorizedFediverseException;
use OCA\Social\Model\ActivityPub\ACore;
use OCA\Social\Model\ActivityPub\Actor\Person;
use OCA\Social\Model\ActivityPub\Object\Document;
use OCA\Social\Model\ActivityPub\Object\Note;
use OCA\Social\Model\ActivityPub\Stream;
use OCA\Social\Model\InstancePath;


class StreamService {


	/** @var StreamRequest */
	private $streamRequest;

	/** @var ActivityService */
	private $activityService;

	/** @var AccountService */
	private $accountService;

	/** @var SignatureService */
	private $signatureService;

	/** @var StreamQueueService */
	private $streamQueueService;

	/** @var CacheActorService */
	private $cacheActorService;

	/** @var CurlService */
	private $curlService;

	/** @var ConfigService */
	private $configService;

	/** @var MiscService */
	private $miscService;


	/** @var Person */
	private $viewer = null;


	/**
	 * NoteService constructor.
	 *
	 * @param StreamRequest $streamRequest
	 * @param ActivityService $activityService
	 * @param AccountService $accountService
	 * @param SignatureService $signatureService
	 * @param StreamQueueService $streamQueueService
	 * @param CacheActorService $cacheActorService
	 * @param CurlService $curlService
	 * @param ConfigService $configService
	 * @param MiscService $miscService
	 */
	public function __construct(
		StreamRequest $streamRequest, ActivityService $activityService,
		AccountService $accountService, SignatureService $signatureService,
		StreamQueueService $streamQueueService, CacheActorService $cacheActorService,
		CurlService $curlService, ConfigService $configService, MiscService $miscService
	) {
		$this->streamRequest = $streamRequest;
		$this->activityService = $activityService;
		$this->accountService = $accountService;
		$this->signatureService = $signatureService;
		$this->streamQueueService = $streamQueueService;
		$this->cacheActorService = $cacheActorService;
		$this->curlService = $curlService;
		$this->configService = $configService;
		$this->miscService = $miscService;
	}


	/**
	 * @param Person $viewer
	 */
	public function setViewer(Person $viewer) {
		$this->viewer = $viewer;
		$this->streamRequest->setViewer($viewer);
	}


	/**
	 * @param ACore $stream
	 * @param Person $actor
	 * @param string $type
	 *
	 * @throws SocialAppConfigException
	 * @throws Exception
	 */
	public function assignItem(Acore &$stream, Person $actor, string $type) {
		$stream->setId($this->configService->generateId('@' . $actor->getPreferredUsername()));
		$stream->setPublished(date("c"));

		$this->setRecipient($stream, $actor, $type);
		$stream->setLocal(true);

		if ($stream instanceof Stream) {
			$this->assignStream($stream);
		}
	}


	/**
	 * @param Stream $stream
	 *
	 * @throws Exception
	 */
	public function assignStream(Stream &$stream) {
		$stream->convertPublished();
	}


	/**
	 * @param ACore $stream
	 * @param Person $actor
	 * @param string $type
	 */
	private function setRecipient(ACore $stream, Person $actor, string $type) {
		switch ($type) {
			case Stream::TYPE_UNLISTED:
				$stream->setTo($actor->getFollowers());
				$stream->addInstancePath(
					new InstancePath(
						$actor->getFollowers(), InstancePath::TYPE_FOLLOWERS,
						InstancePath::PRIORITY_LOW
					)
				);
				$stream->addCc(ACore::CONTEXT_PUBLIC);
				break;

			case Stream::TYPE_FOLLOWERS:
				$stream->setTo($actor->getFollowers());
				$stream->addInstancePath(
					new InstancePath(
						$actor->getFollowers(), InstancePath::TYPE_FOLLOWERS,
						InstancePath::PRIORITY_LOW
					)
				);
				break;

			case Stream::TYPE_ANNOUNCE:
				$stream->addInstancePath(
					new InstancePath(
						$actor->getFollowers(), InstancePath::TYPE_FOLLOWERS,
						InstancePath::PRIORITY_LOW
					)
				);
				$stream->addCc($actor->getFollowers());
				break;

			case Stream::TYPE_DIRECT:
				break;

			default:
				$stream->setTo(ACore::CONTEXT_PUBLIC);
				$stream->addCc($actor->getFollowers());
				$stream->addInstancePath(
					new InstancePath(
						$actor->getFollowers(), InstancePath::TYPE_FOLLOWERS,
						InstancePath::PRIORITY_LOW
					)
				);
				break;
		}
	}


	/**
	 * @param $stream
	 */
	public function detectType(Stream $stream) {
		if (in_array(ACore::CONTEXT_PUBLIC, $stream->getToAll())) {
			$stream->setTimeline(Stream::TYPE_PUBLIC);

			return;
		}

		if (in_array(ACore::CONTEXT_PUBLIC, $stream->getCcArray())) {
			$stream->setType(Stream::TYPE_UNLISTED);

			return;
		}

		try {
			$actor = $this->cacheActorService->getFromId($stream->getAttributedTo());
			echo json_encode($actor) . "\n";
		} catch (Exception $e) {
			return;
		}

	}


	/**
	 * @param Stream $stream
	 * @param string $type
	 * @param string $account
	 */
	public function addRecipient(Stream $stream, string $type, string $account) {
		if ($account === '') {
			return;
		}

		try {
			$actor = $this->cacheActorService->getFromAccount($account);
		} catch (Exception $e) {
			return;
		}

		$instancePath = new InstancePath(
			$actor->getInbox(), InstancePath::TYPE_INBOX, InstancePath::PRIORITY_MEDIUM
		);
		if ($type === Stream::TYPE_DIRECT) {
			$instancePath->setPriority(InstancePath::PRIORITY_HIGH);
			$stream->addToArray($actor->getId());
			$stream->setHiddenOnTimeline(true);
		} else {
			$stream->addCc($actor->getId());
		}

		$stream->addTag(
			[
				'type' => 'Mention',
				'href' => $actor->getId(),
				'name' => '@' . $account
			]
		);

		$stream->addInstancePath($instancePath);
	}


	/**
	 * @param Note $note
	 * @param string $hashtag
	 */
	public function addHashtag(Note $note, string $hashtag) {
		try {
			$note->addTag(
				[
					'type' => 'Hashtag',
					'href' => $this->configService->getSocialUrl() . 'tag/' . strtolower(
							$hashtag
						),
					'name' => '#' . $hashtag
				]
			);
		} catch (SocialAppConfigException $e) {
		}
	}


	/**
	 * @param Stream $stream
	 * @param string $type
	 * @param array $accounts
	 */
	public function addRecipients(Stream $stream, string $type, array $accounts) {
		foreach ($accounts as $account) {
			$this->addRecipient($stream, $type, $account);
		}
	}


	/**
	 * @param Note $note
	 * @param array $hashtags
	 */
	public function addHashtags(Note $note, array $hashtags) {
		$note->setHashtags($hashtags);
		foreach ($hashtags as $hashtag) {
			$this->addHashtag($note, $hashtag);
		}
	}


	/**
	 * @param Note $note
	 * @param Document[] $documents
	 */
	public function addAttachments(Note $note, array $documents) {
		$note->setAttachments($documents);
	}


	/**
	 * @param Note $note
	 * @param string $replyTo
	 *
	 * @throws InvalidOriginException
	 * @throws InvalidResourceException
	 * @throws ItemUnknownException
	 * @throws MalformedArrayException
	 * @throws RedundancyLimitException
	 * @throws RequestContentException
	 * @throws RequestNetworkException
	 * @throws RequestResultNotJsonException
	 * @throws RequestResultSizeException
	 * @throws RequestServerException
	 * @throws SocialAppConfigException
	 * @throws StreamNotFoundException
	 * @throws UnauthorizedFediverseException
	 */
	public function replyTo(Note $note, string $replyTo) {
		if ($replyTo === '') {
			return;
		}

		$author = $this->getAuthorFromPostId($replyTo);
		$note->setInReplyTo($replyTo);
		// TODO - type can be NOT public !
		$note->addInstancePath(
			new InstancePath(
				$author->getSharedInbox(), InstancePath::TYPE_INBOX, InstancePath::PRIORITY_HIGH
			)
		);
	}


	/**
	 * @param Stream $item
	 * @param string $type
	 *
	 * @throws Exception
	 */
	public function deleteLocalItem(Stream $item, string $type = '') {
		if (!$item->isLocal()) {
			return;
		}

		$item->setActorId($item->getAttributedTo());
		$this->activityService->deleteActivity($item);
		$this->streamRequest->deleteById($item->getId(), $type);
	}


	/**
	 * @param string $id
	 * @param bool $asViewer
	 * @param bool $retrieve
	 *
	 * @return Stream
	 * @throws StreamNotFoundException
	 * @throws Exception
	 */
	public function getStreamById(string $id, bool $asViewer = false, bool $retrieve = false): Stream {
		try {
			return $this->streamRequest->getStreamById($id, $asViewer);
		} catch (StreamNotFoundException $e) {
			if (!$retrieve) {
				throw $e;
			}

			if ($asViewer) {
				try {
					$this->streamRequest->getStreamById($id, false);
					throw $e;
				} catch (StreamNotFoundException $e) {
				}
			}
		}

		return $this->retrieveStream($id);
	}


	/**
	 * @param string $id
	 *
	 * @return Stream
	 * @throws InvalidOriginException
	 * @throws InvalidResourceException
	 * @throws ItemUnknownException
	 * @throws MalformedArrayException
	 * @throws RedundancyLimitException
	 * @throws RequestContentException
	 * @throws RequestNetworkException
	 * @throws RequestResultNotJsonException
	 * @throws RequestResultSizeException
	 * @throws RequestServerException
	 * @throws SocialAppConfigException
	 * @throws UnauthorizedFediverseException
	 * @throws StreamNotFoundException
	 */
	public function retrieveStream(string $id) {
		$data = $this->curlService->retrieveObject($id);
		$object = AP::$activityPub->getItemFromData($data);

		$origin = parse_url($id, PHP_URL_HOST);
		$object->setOrigin($origin, SignatureService::ORIGIN_REQUEST, time());

		if ($object->getId() !== $id) {
			throw new InvalidOriginException(
				'StreamServiceStreamQueueService::getStreamById - objectId: ' . $object->getId() . ' - id: '
				. $id
			);
		}

		if ($object->getType() !== Note::TYPE
			// do we also retrieve Announce ?
			//|| $object->getType() !== Announce:TYPE
		) {
			throw new InvalidResourceException();
		}

		/** @var Stream $object */
		$this->cacheActorService->getFromId($object->getAttributedTo());

		$interface = AP::$activityPub->getInterfaceForItem($object);
		try {
			$interface->save($object);
		} catch (ItemAlreadyExistsException $e) {
		}


		return $this->streamRequest->getStreamById($id);
	}


	/**
	 * @param string $id
	 * @param int $since
	 * @param int $limit
	 * @param bool $asViewer
	 *
	 * @return Stream[]
	 * @throws StreamNotFoundException
	 */
	public function getRepliesByParentId(string $id, int $since = 0, int $limit = 5, bool $asViewer = false
	): array {
		return $this->streamRequest->getRepliesByParentId($id, $since, $limit, $asViewer);
	}


	/**
	 * @param Person $actor
	 * @param int $since
	 * @param int $limit
	 *
	 * @return Note[]
	 * @throws Exception
	 */
	public function getStreamHome(int $since = 0, int $limit = 5): array {
		return $this->streamRequest->getTimelineHome($since, $limit);
	}


	/**
	 * @param Person $actor
	 * @param int $since
	 * @param int $limit
	 *
	 * @return Note[]
	 * @throws Exception
	 */
	public function getStreamNotifications(Person $actor, int $since = 0, int $limit = 5): array {
		return $this->streamRequest->getTimelineNotifications($actor, $since, $limit);
	}


	/**
	 * @param string $actorId
	 * @param int $since
	 * @param int $limit
	 *
	 * @return Note[]
	 * @throws Exception
	 */
	public function getStreamAccount(string $actorId, int $since = 0, int $limit = 5): array {
		return $this->streamRequest->getTimelineAccount($actorId, $since, $limit);
	}


	/**
	 * @param Person $actor
	 * @param int $since
	 * @param int $limit
	 *
	 * @return Note[]
	 * @throws Exception
	 */
	public function getStreamDirect(int $since = 0, int $limit = 5): array {
		return $this->streamRequest->getTimelineDirect($since, $limit);
	}


	/**
	 * @param int $since
	 * @param int $limit
	 *
	 * @return Note[]
	 * @throws Exception
	 */
	public function getStreamLocalTimeline(int $since = 0, int $limit = 5): array {
		return $this->streamRequest->getTimelineGlobal($since, $limit, true);
	}


	/**
	 * @param Person $actor
	 * @param string $hashtag
	 * @param int $since
	 * @param int $limit
	 *
	 * @return Note[]
	 * @throws Exception
	 */
	public function getStreamLocalTag(string $hashtag, int $since = 0, int $limit = 5): array {
		return $this->streamRequest->getTimelineTag($hashtag, $since, $limit);
	}


	/**
	 * @param int $since
	 * @param int $limit
	 *
	 * @return Note[]
	 */
	public function getStreamInternalTimeline(int $since = 0, int $limit = 5): array {
		// TODO - admin should be able to provide a list of 'friendly/internal' instance of ActivityPub
		return [];
	}


	/**
	 *
	 * @param int $since
	 * @param int $limit
	 *
	 * @return Note[]
	 * @throws Exception
	 */
	public function getStreamGlobalTimeline(int $since = 0, int $limit = 5): array {
		return $this->streamRequest->getTimelineGlobal($since, $limit, false);
	}


	/**
	 *
	 * @param int $since
	 * @param int $limit
	 *
	 * @return Note[]
	 * @throws Exception
	 */
	public function getStreamLiked(int $since = 0, int $limit = 5): array {
		return $this->streamRequest->getTimelineLiked($since, $limit);
	}


	/**
	 * @param $noteId
	 *
	 * @return Person
	 * @throws InvalidOriginException
	 * @throws InvalidResourceException
	 * @throws MalformedArrayException
	 * @throws StreamNotFoundException
	 * @throws RedundancyLimitException
	 * @throws SocialAppConfigException
	 * @throws ItemUnknownException
	 * @throws RequestContentException
	 * @throws RequestNetworkException
	 * @throws RequestResultSizeException
	 * @throws RequestServerException
	 * @throws RequestResultNotJsonException
	 * @throws UnauthorizedFediverseException
	 */
	public function getAuthorFromPostId($noteId) {
		$note = $this->streamRequest->getStreamById($noteId);

		return $this->cacheActorService->getFromId($note->getAttributedTo());
	}


}

