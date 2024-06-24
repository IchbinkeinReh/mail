<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Service\Sync;

use OCA\Mail\Account;
use OCA\Mail\Contracts\IMailSearch;
use OCA\Mail\Db\Mailbox;
use OCA\Mail\Db\MailboxMapper;
use OCA\Mail\Db\Message;
use OCA\Mail\Db\MessageMapper;
use OCA\Mail\Exception\ClientException;
use OCA\Mail\Exception\MailboxLockedException;
use OCA\Mail\Exception\MailboxNotCachedException;
use OCA\Mail\Exception\ServiceException;
use OCA\Mail\IMAP\MailboxSync;
use OCA\Mail\IMAP\PreviewEnhancer;
use OCA\Mail\IMAP\Sync\Response;
use OCA\Mail\Service\Search\FilterStringParser;
use OCA\Mail\Service\Search\SearchQuery;
use Psr\Log\LoggerInterface;
use function array_diff;
use function array_map;

class SyncService {
	/** @var ImapToDbSynchronizer */
	private $synchronizer;

	/** @var FilterStringParser */
	private $filterStringParser;

	/** @var MailboxMapper */
	private $mailboxMapper;

	/** @var MessageMapper */
	private $messageMapper;

	/** @var PreviewEnhancer */
	private $previewEnhancer;

	/** @var LoggerInterface */
	private $logger;

	/** @var MailboxSync */
	private $mailboxSync;

	public function __construct(ImapToDbSynchronizer $synchronizer,
		FilterStringParser $filterStringParser,
		MailboxMapper $mailboxMapper,
		MessageMapper $messageMapper,
		PreviewEnhancer $previewEnhancer,
		LoggerInterface $logger,
		MailboxSync $mailboxSync) {
		$this->synchronizer = $synchronizer;
		$this->filterStringParser = $filterStringParser;
		$this->mailboxMapper = $mailboxMapper;
		$this->messageMapper = $messageMapper;
		$this->previewEnhancer = $previewEnhancer;
		$this->logger = $logger;
		$this->mailboxSync = $mailboxSync;
	}

	/**
	 * @param Account $account
	 * @param Mailbox $mailbox
	 *
	 * @throws MailboxLockedException
	 * @throws ServiceException
	 */
	public function clearCache(Account $account,
		Mailbox $mailbox): void {
		$this->synchronizer->clearCache($account, $mailbox);
	}

	/**
	 * @param Account $account
	 * @param Mailbox $mailbox
	 * @param int $criteria
	 * @param bool $partialOnly
	 * @param string|null $filter
	 *
	 * @param int[] $knownIds
	 *
	 * @return Response
	 * @throws ClientException
	 * @throws MailboxNotCachedException
	 * @throws ServiceException
	 */
	public function syncMailbox(Account $account,
		Mailbox $mailbox,
		int $criteria,
		bool $partialOnly,
		?int $lastMessageTimestamp,
		?array $knownIds = null,
		string $sortOrder = IMailSearch::ORDER_NEWEST_FIRST,
		?string $filter = null): Response {
		if ($partialOnly && !$mailbox->isCached()) {
			throw MailboxNotCachedException::from($mailbox);
		}

		$this->synchronizer->sync(
			$account,
			$mailbox,
			$this->logger,
			$criteria,
			$knownIds === null ? null : $this->messageMapper->findUidsForIds($mailbox, $knownIds),
			!$partialOnly
		);

		$this->mailboxSync->syncStats($account, $mailbox);

		$query = $filter === null ? null : $this->filterStringParser->parse($filter);
		return $this->getDatabaseSyncChanges(
			$account,
			$mailbox,
			$knownIds ?? [],
			$lastMessageTimestamp,
			$sortOrder,
			$query
		);
	}

	/**
	 * @param Account $account
	 * @param Mailbox $mailbox
	 * @param int[] $knownIds
	 * @param SearchQuery $query
	 *
	 * @return Response
	 * @todo does not work with text token search queries
	 *
	 */
	private function getDatabaseSyncChanges(Account $account,
		Mailbox $mailbox,
		array $knownIds,
		?int $lastMessageTimestamp,
		string $sortOrder,
		?SearchQuery $query): Response {
		if ($knownIds === []) {
			$newIds = $this->messageMapper->findAllIds($mailbox);
		} else {
			$newIds = $this->messageMapper->findNewIds($mailbox, $knownIds, $lastMessageTimestamp, $sortOrder);
		}
		$order = $sortOrder === 'oldest' ? IMailSearch::ORDER_OLDEST_FIRST : IMailSearch::ORDER_NEWEST_FIRST;
		if ($query !== null) {
			// Filter new messages to those that also match the current filter
			$newUids = $this->messageMapper->findUidsForIds($mailbox, $newIds);
			$newIds = $this->messageMapper->findIdsByQuery($mailbox, $query, $order, null, $newUids);
		}
		$new = $this->messageMapper->findByMailboxAndIds($mailbox, $account->getUserId(), $newIds);

		// TODO: $changed = $this->messageMapper->findChanged($account, $mailbox, $uids);
		if ($query !== null) {
			$changedUids = $this->messageMapper->findUidsForIds($mailbox, $knownIds);
			$changedIds = $this->messageMapper->findIdsByQuery($mailbox, $query, $order, null, $changedUids);
		} else {
			$changedIds = $knownIds;
		}
		$changed = $this->messageMapper->findByMailboxAndIds($mailbox, $account->getUserId(), $changedIds);

		$stillKnownIds = array_map(static function (Message $msg) {
			return $msg->getId();
		}, $changed);
		$vanished = array_values(array_diff($knownIds, $stillKnownIds));

		return new Response(
			$this->previewEnhancer->process($account, $mailbox, $new),
			$changed,
			$vanished,
			$mailbox->getStats()
		);
	}
}
