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

namespace OCA\Social\Model\ActivityPub;

use DateTime;
use Exception;
use JsonSerializable;
use OCA\Social\AP;
use OCA\Social\Exceptions\InvalidResourceEntryException;
use OCA\Social\Exceptions\ItemAlreadyExistsException;
use OCA\Social\Exceptions\ItemUnknownException;
use OCA\Social\Model\ActivityPub\Actor\Person;
use OCA\Social\Model\ActivityPub\Object\Announce;
use OCA\Social\Model\ActivityPub\Object\Document;
use OCA\Social\Model\ActivityPub\Object\Follow;
use OCA\Social\Model\ActivityPub\Object\Image;
use OCA\Social\Model\ActivityPub\Object\Like;
use OCA\Social\Model\ActivityPub\Object\Mention;
use OCA\Social\Model\Client\MediaAttachment;
use OCA\Social\Model\StreamAction;
use OCA\Social\Tools\IQueryRow;
use OCA\Social\Tools\Model\Cache;
use OCA\Social\Tools\Model\CacheItem;
use OCA\Social\Traits\TDetails;
use OCP\IURLGenerator;
use OCP\Server;

/**
 * Class Stream
 *
 * @package OCA\Social\Model\ActivityPub
 */
class Stream extends ACore implements IQueryRow, JsonSerializable {
	use TDetails;


	public const TYPE = 'Stream';


	public const TYPE_PUBLIC = 'public';
	public const TYPE_UNLISTED = 'unlisted';
	public const TYPE_FOLLOWERS = 'followers';
	public const TYPE_DIRECT = 'direct';
	public const TYPE_ANNOUNCE = 'announce';

	private string $activityId = '';
	private string $content = '';
	private string $visibility = '';
	private string $spoilerText = '';
	private string $language = 'en';
	private string $attributedTo = '';
	private string $inReplyTo = '';
	private array $attachments = [];
	private array $mentions = [];
	private bool $sensitive = false;
	private string $conversation = '';
	private ?Cache $cache = null;
	private int $publishedTime = 0;
	private ?StreamAction $action = null;
	private string $timeline = '';
	private bool $filterDuplicate = false;

	/**
	 * Stream constructor.
	 *
	 * @param ?ACore $parent
	 */
	public function __construct(?ACore $parent = null) {
		parent::__construct($parent);
	}


	/**
	 * @return string
	 */
	public function getActivityId(): string {
		return $this->activityId;
	}

	/**
	 * @param string $activityId
	 *
	 * @return Stream
	 */
	public function setActivityId(string $activityId): Stream {
		$this->activityId = $activityId;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getContent(): string {
		return $this->content;
	}


	/**
	 * @param string $content
	 *
	 * @return Stream
	 */
	public function setContent(string $content): Stream {
		$this->content = $content;

		return $this;
	}

	/**
	 * @param string $visibility
	 *
	 * @return Stream
	 */
	public function setVisibility(string $visibility): self {
		$this->visibility = $visibility;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getVisibility(): string {
		return $this->visibility;
	}


	/**
	 * @return string
	 */
	public function getSpoilerText(): string {
		return $this->spoilerText;
	}

	/**
	 * @param string $text
	 *
	 * @return Stream
	 */
	public function setSpoilerText(string $text): self {
		$this->spoilerText = $text;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getLanguage(): string {
		return $this->language;
	}

	/**
	 * @param string $language
	 *
	 * @return $this
	 */
	public function setLanguage(string $language): self {
		$this->language = $language;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getAttributedTo(): string {
		return $this->attributedTo;
	}

	/**
	 * @param string $attributedTo
	 *
	 * @return Stream
	 */
	public function setAttributedTo(string $attributedTo): Stream {
		$this->attributedTo = $attributedTo;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getInReplyTo(): string {
		return $this->inReplyTo;
	}

	/**
	 * @param string $inReplyTo
	 *
	 * @return Stream
	 */
	public function setInReplyTo(string $inReplyTo): Stream {
		$this->inReplyTo = $inReplyTo;

		return $this;
	}


	/**
	 * @return MediaAttachment[]
	 */
	public function getAttachments(): array {
		return $this->attachments;
	}

	/**
	 * @param MediaAttachment[] $attachments
	 *
	 * @return self
	 */
	public function setAttachments(array $attachments): self {
		$this->attachments = $attachments;

		return $this;
	}


	public function getMentions(): array {
		return $this->mentions;
	}

	public function setMentions(array $mentions): self {
		$this->mentions = $mentions;

		return $this;
	}


	/**
	 * @return bool
	 */
	public function isSensitive(): bool {
		return $this->sensitive;
	}

	/**
	 * @param bool $sensitive
	 *
	 * @return Stream
	 */
	public function setSensitive(bool $sensitive): Stream {
		$this->sensitive = $sensitive;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getConversation(): string {
		return $this->conversation;
	}

	/**
	 * @param string $conversation
	 *
	 * @return Stream
	 */
	public function setConversation(string $conversation): Stream {
		$this->conversation = $conversation;

		return $this;
	}


	/**
	 * @return int
	 */
	public function getPublishedTime(): int {
		return $this->publishedTime;
	}

	/**
	 * @param int $time
	 *
	 * @return Stream
	 */
	public function setPublishedTime(int $time): Stream {
		$this->publishedTime = $time;

		return $this;
	}

	/**
	 */
	public function convertPublished() {
		try {
			$dTime = new DateTime($this->getPublished());
			$this->setPublishedTime($dTime->getTimestamp());
		} catch (Exception $e) {
		}
	}


	/**
	 * @return bool
	 */
	public function hasCache(): bool {
		return ($this->cache !== null);
	}

	/**
	 * @return Cache
	 */
	public function getCache(): ?Cache {
		return $this->cache;
	}

	/**
	 * @param Cache $cache
	 *
	 * @return Stream
	 */
	public function setCache(Cache $cache): Stream {
		$this->cache = $cache;

		return $this;
	}


	public function addCacheItem(string $url): Stream {
		$cacheItem = new CacheItem($url);

		if (!$this->hasCache()) {
			$this->setCache(new Cache());
		}

		$this->getCache()
			 ->addItem($cacheItem);

		return $this;
	}

	public function getAction(): ?StreamAction {
		return $this->action;
	}

	public function setAction(StreamAction $action): Stream {
		$this->action = $action;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function hasAction(): bool {
		return ($this->action !== null);
	}


	/**
	 * @return string
	 */
	public function getTimeline(): string {
		return $this->timeline;
	}

	/**
	 * @param string $timeline
	 *
	 * @return Stream
	 */
	public function setTimeline(string $timeline): self {
		$this->timeline = $timeline;

		return $this;
	}


	/**
	 * @return bool
	 */
	public function isFilterDuplicate(): bool {
		return $this->filterDuplicate;
	}

	/**
	 * @param bool $filterDuplicate
	 *
	 * @return Stream
	 */
	public function setFilterDuplicate(bool $filterDuplicate): Stream {
		$this->filterDuplicate = $filterDuplicate;

		return $this;
	}


	/**
	 * @param array $data
	 */
	public function import(array $data) {
		parent::import($data);

		$this->setInReplyTo($this->validate(self::AS_ID, 'inReplyTo', $data, ''));
		$this->setAttributedTo($this->validate(self::AS_ID, 'attributedTo', $data, ''));
		$this->setSensitive($this->getBool('sensitive', $data, false));
		$this->setObjectId($this->get('object', $data, ''));
		$this->setConversation($this->validate(self::AS_ID, 'conversation', $data, ''));
		$this->setContent($this->get('content', $data, ''));
		try {
			$this->importAttachments($this->getArray('attachment', $data, []));
		} catch (ItemAlreadyExistsException $e) {
		}
		$this->convertPublished();
	}


	/**
	 * @throws ItemAlreadyExistsException
	 */
	public function importAttachments(array $list): void {
		$urlGenerator = Server::get(IURLGenerator::class);

		$new = [];
		foreach ($list as $item) {
			try {
				/** @var Document $attachment */
				$attachment = AP::$activityPub->getItemFromData($item, $this);
			} catch (Exception $e) {
				continue;
			}

			if ($attachment->getType() !== Document::TYPE
				&& $attachment->getType() !== Image::TYPE) {
				continue;
			}

			try {
				$attachment->setUrl(
					$this->validateEntryString(ACore::AS_URL, $attachment->getUrl())
				);
			} catch (InvalidResourceEntryException $e) {
				continue;
			}

			if ($attachment->getUrl() === '') {
				continue;
			}

			try {
				$interface = AP::$activityPub->getInterfaceFromType($attachment->getType());
			} catch (ItemUnknownException $e) {
				continue;
			}

			$interface->save($attachment);
			$new[] = $attachment->convertToMediaAttachment($urlGenerator);
		}

		$this->setAttachments($new);
	}


	/**
	 * @param array $data
	 */
	public function importFromDatabase(array $data) {
		parent::importFromDatabase($data);

		try {
			$dTime = new DateTime($this->get('published_time', $data, 'yesterday'));
			$this->setPublishedTime($dTime->getTimestamp());
		} catch (Exception $e) {
		}

		$this->setActivityId($this->validate(self::AS_ID, 'activity_id', $data, ''));
		$this->setContent($this->validate(self::AS_CONTENT, 'content', $data, ''));
		$this->setObjectId($this->validate(self::AS_ID, 'object_id', $data, ''));
		$this->setAttributedTo($this->validate(self::AS_ID, 'attributed_to', $data, ''));
		$this->setInReplyTo($this->validate(self::AS_ID, 'in_reply_to', $data));
		$this->setDetailsAll($this->getArray('details', $data, []));
		$this->setFilterDuplicate($this->getBool('filter_duplicate', $data, false));
		$this->setAttachments($this->getArray('attachments', $data, []));
		$this->setMentions($this->getDetails('mentions'));
		$this->setVisibility($this->get('visibility', $data));

		$cache = new Cache();
		$cache->import($this->getArray('cache', $data, []));
		$this->setCache($cache);
	}

	public function importFromLocal(array $data) {
		parent::importFromLocal($data);

		$this->setId($this->get('url', $data));
		$this->setUrl($this->get('url', $data));
		$this->setLocal($this->getBool('local', $data));
		$this->setContent($this->get('content', $data));
		$this->setSensitive($this->getBool('sensitive', $data));
		$this->setSpoilerText($this->get('spoiler_text', $data));
		$this->setVisibility($this->get('visibility', $data));
		$this->setLanguage($this->get('language', $data));

		$action = new StreamAction();
		$action->updateValueBool(StreamAction::LIKED, $this->getBool('favourited', $data));
		$action->updateValueBool(StreamAction::BOOSTED, $this->getBool('reblogged', $data));
		$this->setAction($action);

		try {
			$dTime = new DateTime($this->get('created_at', $data, 'yesterday'));
			$this->setPublishedTime($dTime->getTimestamp());
		} catch (Exception $e) {
		}

//		"in_reply_to_id" => null,
//			"in_reply_to_account_id" => null,
//			'replies_count' => 0,
//			'reblogs_count' => 0,
//			'favourites_count' => 0,
//			'muted' => false,
//			'bookmarked' => false,
//			"reblog" => null,
//			'noindex' => false

		$attachments = [];
		foreach ($this->getArray('media_attachments', $data) as $dataAttachment) {
			$attachment = new MediaAttachment();
			$attachment->import($dataAttachment);
			$attachments[] = $attachment;
		}
		$this->setAttachments($attachments);

		$this->setMentions($this->getArray('mentions', $data));

		// import from cache with new format !
		$actor = new Person();
		$actor->importFromLocal($this->getArray('account', $data));
		$actor->setExportFormat(ACore::FORMAT_LOCAL);
		$this->setActor($actor);
		//		$this->setCompleteDetails(true);
	}


	/**
	 * @return array
	 */
	public function exportAsActivityPub(): array {
		$result = array_merge(
			parent::exportAsActivityPub(),
			[
				'content' => $this->getContent(),
				'attributedTo' => ($this->getAttributedTo() !== '') ? $this->getUrlSocial()
																	  . $this->getAttributedTo() : '',
				'inReplyTo' => $this->getInReplyTo(),
				'sensitive' => $this->isSensitive(),
				'conversation' => $this->getConversation()
			]
		);

		// TODO: use exportFormat
		if ($this->isCompleteDetails()) {
			$result = array_merge(
				$result,
				[
					'details' => $this->getDetailsAll(),
					'action' => ($this->hasAction()) ? $this->getAction() : [],
					'cache' => ($this->hasCache()) ? $this->getCache() : '',
					'publishedTime' => $this->getPublishedTime()
				]
			);
		}

		$this->cleanArray($result);

		return $result;
	}


	/**
	 * @return array
	 */
	public function exportAsLocal(): array {
		$actions = ($this->hasAction()) ? $this->getAction()->getValues() : [];
		$favorited = false;
		$reblogged = false;
		foreach ($actions as $action => $value) {
			if ($value) {
				switch ($action) {
					case StreamAction::BOOSTED:
						$reblogged = true;
						break;
					case StreamAction::LIKED:
						$favorited = true;
						break;
				}
			}
		}
		$result = [
			"local" => $this->isLocal(),
			"content" => $this->getContent(),
			"sensitive" => $this->isSensitive(),
			"spoiler_text" => $this->getSpoilerText(),
			'visibility' => $this->getVisibility(),
			"language" => $this->getLanguage(),
			"in_reply_to_id" => null,
			"in_reply_to_account_id" => null,
			'mentions' => $this->getMentions(),
			'replies_count' => $this->getDetailInt('replies'),
			'reblogs_count' => $this->getDetailInt('boosts'),
			'favourites_count' => $this->getDetailInt('likes'),
			'favourited' => $favorited,
			'reblogged' => $reblogged,
			'muted' => false,
			'bookmarked' => false,
			'uri' => $this->getId(),
			'url' => $this->getId(),
			"reblog" => null,
			'media_attachments' => $this->getAttachments(),
			"created_at" => date('Y-m-d\TH:i:s', $this->getPublishedTime()) . '.000Z',
			'noindex' => false
		];

		// TODO - store created_at full string with milliseconds ?
		if ($this->hasActor()) {
			$actor = $this->getActor();
			$result['account'] = $actor->exportAsLocal();
		}

		return array_merge(parent::exportAsLocal(), $result);
	}


	public function exportAsNotification(): array {
		// TODO - implements:
		// status = Someone you enabled notifications for has posted a status
		// follow_request = Someone requested to follow you
		// poll = A poll you have voted in or created has ended
		// update = A status you boosted with has been edited
		switch ($this->getSubType()) {
			case Like::TYPE:
				$type = 'favourite';
				break;
			case Announce::TYPE:
				$type = 'reblog';
				break;
			case Mention::TYPE:
				$type = 'mention';
				break;
			case Follow::TYPE:
				$type = 'follow';
				break;
			default:
				$type = '';
		}

		$result = [
			'id' => (string)$this->getNid(),
			'type' => $type,
			'created_at' => date('Y-m-d\TH:i:s', $this->getPublishedTime()) . '.000Z',
			'status' => $this->getObject(),
		];

		if ($this->hasActor()) {
			$actor = $this->getActor();
			$result['account'] = $actor->exportAsLocal();
		}

		return array_merge(parent::exportAsNotification(), $result);
	}


	public function jsonSerialize(): array {
		$result = parent::jsonSerialize();

		//		$result['media_attachments'] = $this->getAttachments();
		$result['attachment'] = $this->getAttachments();

		return $result;
	}
}
