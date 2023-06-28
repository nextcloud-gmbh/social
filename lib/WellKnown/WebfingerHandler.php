<?php

declare(strict_types=1);

/**
 * @copyright 2018 Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2022 Carl Schwan <carl@carlschwan.eu>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Social\WellKnown;

use OCA\Social\AppInfo\Application;
use OCA\Social\Db\CacheActorsRequest;
use OCA\Social\Exceptions\ActorDoesNotExistException;
use OCA\Social\Exceptions\CacheActorDoesNotExistException;
use OCA\Social\Exceptions\SocialAppConfigException;
use OCA\Social\Exceptions\UnauthorizedFediverseException;
use OCA\Social\Service\CacheActorService;
use OCA\Social\Service\ConfigService;
use OCA\Social\Service\FediverseService;
use OCP\AppFramework\Http;
use OCP\Http\WellKnown\IHandler;
use OCP\Http\WellKnown\IRequestContext;
use OCP\Http\WellKnown\IResponse;
use OCP\IRequest;
use OCP\IURLGenerator;

class WebfingerHandler implements IHandler {
	private IURLGenerator $urlGenerator;
	private CacheActorsRequest $cacheActorsRequest;
	private CacheActorService $cacheActorService;
	private FediverseService $fediverseService;
	private ConfigService $configService;

	public function __construct(
		IURLGenerator $urlGenerator, CacheActorsRequest $cacheActorsRequest,
		CacheActorService $cacheActorService, FediverseService $fediverseService,
		ConfigService $configService
	) {
		$this->urlGenerator = $urlGenerator;
		$this->cacheActorsRequest = $cacheActorsRequest;
		$this->cacheActorService = $cacheActorService;
		$this->fediverseService = $fediverseService;
		$this->configService = $configService;
	}


	/**
	 * @see https://docs.joinmastodon.org/spec/webfinger/
	 *
	 * @param string $service
	 * @param IRequestContext $context
	 * @param IResponse|null $previousResponse
	 *
	 * @return IResponse|null
	 */
	public function handle(
		string $service,
		IRequestContext $context,
		?IResponse $previousResponse
	): ?IResponse {
		try {
			$this->fediverseService->jailed();
		} catch (UnauthorizedFediverseException $e) {
			return $previousResponse;
		}

		$response = null;
		switch (strtolower($service)) {
			case 'webfinger':
				$response = $this->handleWebfinger($context, $previousResponse);
				break;

			case 'nodeinfo':
				$response = $this->handleNodeInfo($context);
				break;

			case 'host-meta':
				$response = $this->handleHostMeta($context);
				break;
		}

		if ($response !== null) {
			return $response;
		}

		return $previousResponse;
	}


	/**
	 * handle request on /.well-known/webfinger
	 *
	 * @param IRequestContext $context
	 *
	 * @return IResponse|null
	 */
	public function handleWebfinger(IRequestContext $context, ?IResponse $previousResponse): ?IResponse {
		$subject = $this->getSubjectFromRequest($context->getHttpRequest());
		if (str_starts_with($subject, 'acct:')) {
			$subject = substr($subject, 5);
		}

		if ($subject === Application::APP_SUBJECT) {
			if ($previousResponse !== null && method_exists($previousResponse, 'addLink')) {
				$previousResponse->addLink(
					Application::APP_REL,
					'application/json',
					$this->urlGenerator->linkToRouteAbsolute('social.Navigation.navigate'),
					[],
					[
						'app' => Application::APP_ID,
						'name' => Application::APP_NAME,
						'version' => $this->configService->getAppValue('installed_version'),
					]
				);
			}

			return $previousResponse;
		}

		$actor = null;
		try {
			$actor = $this->cacheActorService->getFromLocalAccount($subject);
		} catch (ActorDoesNotExistException|SocialAppConfigException $e) {
			return null;
		} catch (CacheActorDoesNotExistException $e) {
		}

		if ($actor === null) {
			try {
				$actor = $this->cacheActorsRequest->getFromId($subject);
			} catch (CacheActorDoesNotExistException $e) {
			}
		}

		if ($actor === null || !$actor->isLocal()) {
			return new JrdResponse('', Http::STATUS_NOT_FOUND);
		}

		// ActivityPub profile
		$href = $this->configService->getSocialUrl() . '@' . $actor->getPreferredUsername();
		$href = rtrim($href, '/');
		$response = new JrdResponse($subject);
		$response->addAlias($href);
		$response->addLink('self', 'application/activity+json', $href);

		// Nextcloud profile page
		$profilePageUrl = $this->urlGenerator->linkToRouteAbsolute('core.ProfilePage.index', [
			'targetUserId' => $actor->getPreferredUsername()
		]);
		$response->addAlias($profilePageUrl);
		$response->addLink('http://webfinger.net/rel/profile-page', 'text/html', $profilePageUrl);

		// Ostatus subscribe url
		$subscribe = $this->urlGenerator->linkToRouteAbsolute('social.OStatus.subscribe') . '?uri={uri}';
		$response->addLink(
			'http://ostatus.org/schema/1.0/subscribe',
			'',
			'',
			null,
			null,
			['template' => $subscribe]
		);

		return $response;
	}


	/**
	 * handle request on /.well-known/nodeinfo
	 * returns Json
	 *
	 * @param IRequestContext $context
	 *
	 * @return IResponse|null
	 */
	private function handleNodeInfo(IRequestContext $context): ?IResponse {
		$response = new JrdResponse();
		$response->addLink(
			'http://nodeinfo.diaspora.software/ns/schema/2.0',
			null,
			$this->urlGenerator->linkToRouteAbsolute('social.OAuth.nodeinfo2')
		);

		return $response;
	}


	/**
	 * handle request on /.well-known/host-meta
	 * returns xml/xrd
	 *
	 * @param IRequestContext $context
	 *
	 * @return IResponse|null
	 */
	private function handleHostMeta(IRequestContext $context): ?IResponse {
		$response = new XrdResponse();
		try {
			$url = $this->configService->getCloudUrl(true) . '/.well-known/webfinger?resource={uri}';
		} catch (SocialAppConfigException $e) {
			return null;
		}

		$response->addLink('lrdd', $url);

		return $response;
	}

	private function getSubjectFromRequest(IRequest $request): string {
		$subject = $request->getParam('resource') ?? '';
		if ($subject !== '') {
			return $subject;
		}

		// work around to extract resource:
		// on some setup (i.e. tests) the data are not available from IRequest
		parse_str(parse_url($request->getRequestUri(), PHP_URL_QUERY), $query);

		return $query['resource'] ?? '';
	}
}
