<?php

namespace MediaWiki\Extension\BulkPageCreate;

use MediaWiki\MediaWikiServices;
use DeferredUpdates;
use Status;
use User;
use Config;
use Html;
use Title;

class BulkPageCreateParser {
	/** @var User */
	private $user;

	/** @var Title */
	private $sourcePage;

	/** @var Config */
	private $config;

	/** @var Status */
	private $status;

	/** @var array */
	private $params;

	/** @var array */
	private $badPages;

	/**
	 * The most amount of parameters a single page has. Used when building the HTML table, where
	 * one column is one parameter.
	 * @var int
	 */
	private $mostPageParams;

	public function __construct( User $user, Config $config ) {
		$this->user = $user;
		$this->config = $config;
	}

	/**
	 * Parse form data submitted at Special:BulkPageCreate.
	 * @param array $formData
	 * @return Status
	 */
	public function parseFormSubmit( array $formData ): Status {
		// at this point we can probably assume this title exists
		// (it has been validated earlier by HTMLTitleTextField)
		/** @var string $sourceTitle */
		$sourceTitle = $formData['bulkpagecreate-sourcepage'];
		/** @var string $sourcePagesRaw */
		$sourcePagesRaw = $formData['bulkpagecreate-targetpages'];

		$this->sourcePage = Title::newFromText( $sourceTitle );
		// TODO: check namespace here

		$this->status = new Status();

		$currentCount = 0;
		$maxCount = $this->config->get( 'BPCMaxPageTargets' );
		$this->mostPageParams = 0;
		$this->params = [];
		// https://stackoverflow.com/a/14789147
		$sep = "\n";
		$line = strtok( $sourcePagesRaw, $sep );
		while ( $line !== false ) {
			$line = trim( $line );
			if ( $line === '' ) {
				$line = strtok( $sep );
				continue;
			}
			$currentCount ++;
			if ( $currentCount > $maxCount ) {
				$this->status->fatal( 'bulkpagecreate-too-many-targets', $maxCount );

				return $this->status;
			}

			$lineParams = explode( "|", $line );
			$title = Title::newFromText( $lineParams[0] );
			// TODO: check namespace here
			$lineInfo = [ $title, array_slice( $lineParams, 1 ) ];
			$paramCount = count( $lineInfo[1] );
			$this->mostPageParams = max( $this->mostPageParams, $paramCount );

			$this->params[] = $lineInfo;

			$line = trim( strtok( $sep ) );
		}

		return $this->status;
	}

	public function buildTable(): string {
		$headers = [];
		$headers[] = Html::rawElement( 'th', [], 'Target page' );
		for ( $i = 1; $i <= $this->mostPageParams; $i ++ ) {
			$headers[] = Html::rawElement( 'th', [], "Param $i" );
		}

		$rows = [];
		foreach ( $this->params as $param ) {
			/** @var Title $title */
			$title = $param[0];
			$rows[] = Html::openElement( 'tr', [] );
			$rows[] = Html::element( 'td', [], $title->getFullText() );
			foreach ( array_pad( $param[1], $this->mostPageParams, '' ) as $pageParam ) {
				$rows[] = Html::element( 'td', [], $pageParam );
			}
			$rows[] = Html::closeElement( 'tr' );
		}

		return Html::rawElement( 'table', [ 'class' => 'wikitable' ],
			Html::rawElement( 'tr', [], implode( $headers ) ) . implode( $rows ) );
	}

	/**
	 * Push jobs into the queue. This does it via DeferredUpdates.
	 * @return void
	 */
	public function queueJobs() {
		DeferredUpdates::addCallableUpdate( [ $this, 'deferredJobQueueAdditions' ] );
	}

	/**
	 * Push jobs into the queue. This is the actual implementation that is deferred with queueJobs.
	 * @return void
	 */
	public function deferredJobQueueAdditions() {
		$userId = $this->user->getId();
		$sourceTitleNS = $this->sourcePage->getNamespace();
		$sourceTitleKey = $this->sourcePage->getDBkey();
		$sourceRevId = $this->sourcePage->getLatestRevID();

		$jobs = [];

		foreach ( $this->params as $param ) {
			/** @var Title $targetTitle */
			$targetTitle = $param[0];
			/** @var array $targetParams */
			$targetParams = $param[1];
			$jobs[] = new BulkPageCreateJob( $targetTitle, [
				'userId' => $userId,
				'sourceNamespace' => $sourceTitleNS,
				'sourceTitle' => $sourceTitleKey,
				'sourceRevId' => $sourceRevId,
				'targetParams' => $targetParams,
			] );
		}

		$jobQueueGroup = MediaWikiServices::getInstance()->getJobQueueGroup();
		$jobQueueGroup->push( $jobs );
	}

	/**
	 * @return array
	 */
	public function getParams(): array {
		return $this->params;
	}
}
