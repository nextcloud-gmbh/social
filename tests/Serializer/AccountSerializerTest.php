<?php

declare(strict_types=1);

// Nextcloud - Social Support
// SPDX-FileCopyrightText: 2022 Carl Schwan <carl@carlschwan.eu>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Social\Tests\Entitiy;

use OCA\Social\Entity\Account;
use OCA\Social\InstanceUtils;
use OCA\Social\Serializer\AccountSerializer;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use Test\TestCase;

class AccountSerializerTest extends TestCase {
	public function testJsonLd(): void {
		$localDomain = "helloworld.social";
		$instanceUtil = $this->createMock(InstanceUtils::class);
		$instanceUtil->expects($this->any())
			->method('getLocalInstanceUrl')
			->willReturn('https://' . $localDomain);

		$alice = $this->createMock(IUser::class);
		$alice->expects($this->atLeastOnce())
			->method('getDisplayName')
			->willReturn('Alice Alice');

		$userManager = $this->createMock(IUserManager::class);
		$userManager->expects($this->once())
			->method('get')
			->with('alice_id')
			->willReturn($alice);

		$account = Account::newLocal();
		$account->setUserName('alice');
		$account->setUserId('alice_id');

		$accountSerializer = new AccountSerializer($userManager, $instanceUtil);
		$jsonLd = $accountSerializer->toJsonLd($account);
		$this->assertSame('https://' . $localDomain . '/alice', $jsonLd['id']);
		$this->assertSame('Alice Alice', $jsonLd['name']);
	}
}
