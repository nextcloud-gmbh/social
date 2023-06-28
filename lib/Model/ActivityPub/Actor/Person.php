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


namespace OCA\Social\Model\ActivityPub\Actor;

use DateTime;
use Exception;
use JsonSerializable;
use OCA\Social\AP;
use OCA\Social\Exceptions\InvalidOriginException;
use OCA\Social\Exceptions\ItemUnknownException;
use OCA\Social\Exceptions\SocialAppConfigException;
use OCA\Social\Exceptions\UrlCloudException;
use OCA\Social\Model\ActivityPub\ACore;
use OCA\Social\Model\ActivityPub\Object\Image;
use OCA\Social\Tools\IQueryRow;
use OCA\Social\Traits\TDetails;
use OCP\IURLGenerator;
use OCP\Server;

/**
 * Class Actor
 *
 * @package OCA\Social\Model\ActivityPub
 */
class Person extends ACore implements IQueryRow, JsonSerializable {
	use TDetails;


	public const TYPE = 'Person';


	public const LINK_VIEWER = 'viewer';
	public const LINK_REMOTE = 'remote';
	public const LINK_LOCAL = 'local';


	private string $userId = '';
	private string $name = '';
	private string $preferredUsername = '';
	private string $displayName = '';
	private string $description = '';
	private string $publicKey = '';
	private string $privateKey = '';
	private int $creation = 0;
	private int $deleted = 0;
	private string $account = '';
	private string $following = '';
	private string $followers = '';
	private string $inbox = '';
	private string $outbox = '';
	private string $sharedInbox = '';
	private string $featured = '';
	private string $avatar = '';
	private string $header = '';
	private bool $locked = false;
	private bool $bot = false;
	private bool $discoverable = false;
	private string $privacy = 'public';
	private bool $sensitive = false;
	private string $language = 'en';
	private int $avatarVersion = -1;
	private string $viewerLink = '';

	/**
	 * Person constructor.
	 *
	 * @param ACore $parent
	 */
	public function __construct($parent = null) {
		parent::__construct($parent);

		$this->setType(self::TYPE);
	}


	/**
	 * @return string
	 */
	public function getUserId(): string {
		return $this->userId;
	}

	/**
	 * @param string $userId
	 *
	 * @return Person
	 */
	public function setUserId(string $userId): self {
		$this->userId = $userId;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getPreferredUsername(): string {
		return $this->preferredUsername;
	}

	/**
	 * @param string $preferredUsername
	 *
	 * @return Person
	 */
	public function setPreferredUsername(string $preferredUsername): self {
		$this->preferredUsername = $preferredUsername;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getDisplayName(): string {
		if ($this->displayName === '') {
			return $this->getPreferredUsername();
		}

		return $this->displayName;
	}

	/**
	 * @param string $displayName
	 *
	 * @return $this
	 */
	public function setDisplayName(string $displayName): self {
		$this->displayName = $displayName;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getDescription(): string {
		return $this->description;
	}

	/**
	 * @param string $description
	 *
	 * @return Person
	 */
	public function setDescription(string $description): self {
		$this->description = $description;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getAvatar(): string {
		if ($this->hasIcon()) {
			return $this->getIcon()
						->getUrl();
		}

		return $this->avatar;
	}

	/**
	 * @param string $avatar
	 *
	 * @return $this
	 */
	public function setAvatar(string $avatar): self {
		$this->avatar = $avatar;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getHeader(): string {
		if ($this->header === '') {
			return $this->getAvatar();
		}

		return $this->header;
	}

	/**
	 * @param string $header
	 *
	 * @return $this
	 */
	public function setHeader(string $header): self {
		$this->header = $header;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getPublicKey(): string {
		return $this->publicKey;
	}

	/**
	 * @param string $publicKey
	 *
	 * @return Person
	 */
	public function setPublicKey(string $publicKey): self {
		$this->publicKey = $publicKey;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getPrivateKey(): string {
		return $this->privateKey;
	}

	/**
	 * @param string $privateKey
	 *
	 * @return Person
	 */
	public function setPrivateKey(string $privateKey): self {
		$this->privateKey = $privateKey;

		return $this;
	}


	/**
	 * @return int
	 */
	public function getCreation(): int {
		return $this->creation;
	}

	/**
	 * @param int $creation
	 *
	 * @return Person
	 */
	public function setCreation(int $creation): self {
		$this->creation = $creation;

		return $this;
	}


	/**
	 * @return int
	 */
	public function getDeleted(): int {
		return $this->deleted;
	}

	/**
	 * @param int $deleted
	 *
	 * @return Person
	 */
	public function setDeleted(int $deleted): self {
		$this->deleted = $deleted;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getFollowing(): string {
		return $this->following;
	}

	/**
	 * @param string $following
	 *
	 * @return Person
	 */
	public function setFollowing(string $following): self {
		$this->following = $following;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getFollowers(): string {
		return $this->followers;
	}

	/**
	 * @param string $followers
	 *
	 * @return Person
	 */
	public function setFollowers(string $followers): self {
		$this->followers = $followers;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getAccount(): string {
		return $this->account;
	}

	/**
	 * @param string $account
	 *
	 * @return Person
	 */
	public function setAccount(string $account): self {
		if ($account !== '' && substr($account, 0, 1) === '@') {
			$account = substr($account, 1);
		}

		$this->account = $account;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getInbox(): string {
		return $this->inbox;
	}

	/**
	 * @param string $inbox
	 *
	 * @return Person
	 */
	public function setInbox(string $inbox): self {
		$this->inbox = $inbox;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getOutbox(): string {
		return $this->outbox;
	}

	/**
	 * @param string $outbox
	 *
	 * @return Person
	 */
	public function setOutbox(string $outbox): self {
		$this->outbox = $outbox;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getSharedInbox(): string {
		return $this->sharedInbox;
	}

	/**
	 * @param string $sharedInbox
	 *
	 * @return Person
	 */
	public function setSharedInbox(string $sharedInbox): self {
		$this->sharedInbox = $sharedInbox;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		if ($this->name === '') {
			return $this->preferredUsername;
		}

		return $this->name;
	}

	/**
	 * @param string $name
	 *
	 * @return Person
	 */
	public function setName(string $name): self {
		$this->name = $name;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getFeatured(): string {
		return $this->featured;
	}

	/**
	 * @param string $featured
	 *
	 * @return Person
	 */
	public function setFeatured(string $featured): self {
		$this->featured = $featured;

		return $this;
	}


	/**
	 * @return bool
	 */
	public function isLocked(): bool {
		return $this->locked;
	}

	/**
	 * @param bool $locked
	 *
	 * @return Person
	 */
	public function setLocked(bool $locked): self {
		$this->locked = $locked;

		return $this;
	}


	/**
	 * @return bool
	 */
	public function isBot(): bool {
		return $this->bot;
	}

	/**
	 * @param bool $bot
	 *
	 * @return Person
	 */
	public function setBot(bool $bot): self {
		$this->bot = $bot;

		return $this;
	}


	/**
	 * @return bool
	 */
	public function isDiscoverable(): bool {
		return $this->discoverable;
	}

	/**
	 * @param bool $discoverable
	 *
	 * @return Person
	 */
	public function setDiscoverable(bool $discoverable): self {
		$this->discoverable = $discoverable;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getPrivacy(): string {
		return $this->privacy;
	}

	/**
	 * @param string $privacy
	 *
	 * @return Person
	 */
	public function setPrivacy(string $privacy): self {
		$this->privacy = $privacy;

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
	 * @return Person
	 */
	public function setSensitive(bool $sensitive): self {
		$this->sensitive = $sensitive;

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
	 * @return int
	 */
	public function getAvatarVersion(): int {
		return $this->avatarVersion;
	}

	/**
	 * @param int $avatarVersion
	 *
	 * @return Person
	 */
	public function setAvatarVersion(int $avatarVersion): self {
		$this->avatarVersion = $avatarVersion;

		return $this;
	}


	/**
	 * @return string
	 */
	public function getViewerLink(): string {
		return $this->viewerLink;
	}

	/**
	 * @param string $viewerLink
	 *
	 * @return Person
	 */
	public function setViewerLink(string $viewerLink): self {
		$this->viewerLink = $viewerLink;

		return $this;
	}

	/**
	 * @param array $data
	 *
	 * @throws ItemUnknownException
	 * @throws SocialAppConfigException
	 * @throws UrlCloudException
	 * @throws InvalidOriginException
	 */
	public function import(array $data) {
		parent::import($data);
		$this->setPreferredUsername($this->validate(ACore::AS_USERNAME, 'preferredUsername', $data, ''))
			 ->setPublicKey($this->get('publicKey.publicKeyPem', $data))
			 ->setSharedInbox($this->validate(ACore::AS_URL, 'endpoints.sharedInbox', $data))
			 ->setName($this->validate(ACore::AS_USERNAME, 'name', $data, ''))
			 ->setAccount($this->validate(ACore::AS_ACCOUNT, 'account', $data, ''))
			 ->setInbox($this->validate(ACore::AS_URL, 'inbox', $data, ''))
			 ->setOutbox($this->validate(ACore::AS_URL, 'outbox', $data, ''))
			 ->setFollowers($this->validate(ACore::AS_URL, 'followers', $data, ''))
			 ->setFollowing($this->validate(ACore::AS_URL, 'following', $data, ''))
			 ->setFeatured($this->validate(ACore::AS_URL, 'featured', $data, ''));

		/** @var Image $icon */
		$icon = AP::$activityPub->getItemFromType(Image::TYPE);
		$icon->setParent($this);
		$icon->import($this->getArray('icon', $data, []));

		if ($icon->getType() === Image::TYPE) {
			$this->setIcon($icon);
		}
	}

	/**
	 * @param array $data
	 *
	 * @return $this
	 */
	public function importFromLocal(array $data): self {
		parent::importFromLocal($data);

		$this->setId($this->get('url', $data));
		$this->setPreferredUsername($this->get('username', $data));
		$this->setAccount($this->get('acct', $data));
		$this->setDisplayName($this->get('display_name', $data));
		$this->setLocked($this->getBool('locked', $data));
		$this->setBot($this->getBool('bot', $data));
		$this->setDiscoverable($this->getBool('discoverable', $data));
		$this->setDescription($this->get('note', $data));
		$this->setUrl($this->get('url', $data));

		$this->setAvatar($this->get('avatar', $data));
		$this->setHeader($this->get('header', $data));

		$this->setPrivacy($this->get('source.privacy', $data));
		$this->setSensitive($this->getBool('source.sensitive', $data));
		$this->setLanguage($this->get('source.language', $data));

		try {
			$dTime = new DateTime($this->get('created_at', $data, 'yesterday'));
			$this->setCreation($dTime->getTimestamp());
		} catch (Exception $e) {
		}

		$count = [
			'followers' => $this->getInt('followers_count', $data),
			'following' => $this->getInt('following_count', $data),
			'post' => $this->getInt('statuses_count', $data),
			'last_post_creation' => $this->get('last_status_at', $data)
		];
		$this->setDetailArray('count', $count);

		return $this;
	}

	/**
	 * @param array $data
	 */
	public function importFromDatabase(array $data) {
		parent::importFromDatabase($data);
		$this->setPreferredUsername($this->validate(self::AS_USERNAME, 'preferred_username', $data, ''))
			 ->setUserId($this->get('user_id', $data, ''))
			 ->setName($this->validate(self::AS_USERNAME, 'name', $data, ''))
			 ->setDescription($this->validate(self::AS_CONTENT, 'summary', $data))
			 ->setAccount($this->validate(self::AS_ACCOUNT, 'account', $data, ''))
			 ->setPublicKey($this->get('public_key', $data, ''))
			 ->setPrivateKey($this->get('private_key', $data, ''))
			 ->setInbox($this->validate(self::AS_URL, 'inbox', $data, ''))
			 ->setOutbox($this->validate(self::AS_URL, 'outbox', $data, ''))
			 ->setFollowers($this->validate(self::AS_URL, 'followers', $data, ''))
			 ->setFollowing($this->validate(self::AS_URL, 'following', $data, ''))
			 ->setSharedInbox($this->validate(self::AS_URL, 'shared_inbox', $data, ''))
			 ->setFeatured($this->validate(self::AS_URL, 'featured', $data, ''))
			 ->setDetailsAll($this->getArray('details', $data, []));

		try {
			$cTime = new DateTime($this->get('creation', $data, 'yesterday'));
			$this->setCreation($cTime->getTimestamp());
		} catch (Exception $e) {
		}

		try {
			$deletedValue = $this->get('deleted', $data);
			if ($deletedValue === '' || $deletedValue === '0000-00-00 00:00:00') {
				return;
			}
			$dTime = new DateTime();
			$deleted = $dTime->getTimestamp();
			if ($deleted > 0) {
				$this->setDeleted($deleted);
			}
		} catch (Exception $e) {
		}
	}


	/**
	 * @return array
	 */
	public function exportAsActivityPub(): array {
		$data = [
			'aliases' => [
				$this->getUrlSocial() . '@' . $this->getPreferredUsername(),
				$this->getUrlSocial() . 'users/' . $this->getPreferredUsername()
			],
			'preferredUsername' => $this->getPreferredUsername(),
			'name' => $this->getName(),
			'inbox' => $this->getInbox(),
			'outbox' => $this->getOutbox(),
			'account' => $this->getAccount(),
			'following' => $this->getFollowing(),
			'followers' => $this->getFollowers(),
			'endpoints' => ['sharedInbox' => $this->getSharedInbox()],
			'publicKey' => [
				'id' => $this->getId() . '#main-key',
				'owner' => $this->getId(),
				'publicKeyPem' => $this->getPublicKey()
			]
		];

		if ($this->hasIcon()) {
			$icon = $this->getIcon();
			$data['icon'] = [
				'type' => $icon->getType(),
				'mediaType' => $icon->getMediaType(),
				'url' => $icon->getUrl()
			];
		}

		$result = array_merge(
			parent::exportAsActivityPub(),
			$data
		);

		if ($this->isCompleteDetails()) {
			$result['details'] = $this->getDetailsAll();
			$result['viewerLink'] = $this->getViewerLink();
		}

		return $result;
	}


	/**
	 * @return array
	 */
	public function exportAsLocal(): array {
		if ($this->hasIcon()) {
			$avatar = $this->getIcon()->getMediaUrl(Server::get(IURLGenerator::class));
		}

		$details = $this->getDetailsAll();
		$result =
			[
				'id' => (string)$this->getNid(),
				'username' => $this->getPreferredUsername(),
				'acct' => $this->isLocal() ? $this->getPreferredUsername() : $this->getAccount(),
				'display_name' => $this->getName(),
				'locked' => $this->isLocked(),
				'bot' => $this->isBot(),
				'discoverable' => $this->isDiscoverable(),
				'group' => false,
				'created_at' => date('Y-m-d\TH:i:s', $this->getCreation()) . '.000Z',
				'note' => $this->getDescription(),
				'url' => $this->getId(),
				'avatar' => $avatar ?? $this->getAvatar(),
				'avatar_static' => $avatar ?? $this->getAvatar(),
				'header' => $avatar ?? $this->getHeader(),
				'header_static' => $avatar ?? $this->getHeader(),
				'followers_count' => $this->getInt('count.followers', $details),
				'following_count' => $this->getInt('count.following', $details),
				'statuses_count' => $this->getInt('count.post', $details),
				'last_status_at' => $this->get('last_post_creation', $details),
				'source' => [
					'privacy' => $this->getPrivacy(),
					'sensitive' => $this->isSensitive(),
					'language' => $this->getLanguage(),
					'note' => $this->getDescription(),
					'fields' => [],
					'follow_requests_count' => 0
				],
				'emojis' => [],
				'fields' => []
			];

		return array_merge(parent::exportAsLocal(), $result);
	}
}
