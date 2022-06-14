<?php

namespace MediaWiki\Extension\BulkPageCreate;

use ContentHandler;
use Job;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MWException;
use Title;
use WikiPage;
use CommentStoreComment;

class BulkPageCreateJob extends Job {

	public function __construct( Title $title, $params = null ) {
		parent::__construct( 'BulkPageCreateJob', $title, $params );
	}

	/*
	stuff i need to keep for later
	i am very organized, i promise

	$title = Title::newFromText( 'Main Page' );
	$rs = $this->revisionStore->getRevisionByTitle( $title, 1 );
	$content = $rs->getContent( SlotRecord::MAIN );
	$popt = ParserOptions::newFromUser( $this->getUser() );
	$cpo = new ContentParseParams( $title, 1, $popt, true );
	$parserout = $content->getContentHandler()->getParserOutput( $content, $cpo );
	*/

	/**
	 * @inheritDoc
	 */
	public function run(): bool {
		$services = MediaWikiServices::getInstance();
		$userFactory = $services->getUserFactory();
		$user = $userFactory->newFromId( 1 );
		$sourcePageTitle =
			Title::makeTitle( $this->params['sourceNamespace'], $this->params['sourceTitle'] );
		$targetPageTitle = $this->title;

		$revisionStore = $services->getRevisionStore();
		$revision =
			$revisionStore->getRevisionByTitle( $sourcePageTitle, $this->params['sourceRevId'] );
		$revisionContent = $revision->getContent( SlotRecord::MAIN );
		$targetWikiPage = new WikiPage( $targetPageTitle );
		$targetText = $revisionContent->getText();

		foreach ( $this->params['targetParams'] as $index => $value ) {
			$needle = '__BPC' . ( $index + 1 ) . '__';
			$targetText = str_replace( $needle, $value, $targetText );
		}

		try {
			$targetContent =
				ContentHandler::makeContent( $targetText, $targetPageTitle,
					$revisionContent->getContentHandler()->getModelID() );
			$updater = $targetWikiPage->newPageUpdater( $user );
			$updater->setContent( SlotRecord::MAIN, $targetContent );
			$updater->addTag( 'bulkpagecreate' );
			$comment =
				CommentStoreComment::newUnsavedComment( wfMessage( 'bulkpagecreate-edit-summary',
					$sourcePageTitle->getFullText(), $revision->getId() )
					->inContentLanguage()
					->plain() );
			$newRev = $updater->saveRevision( $comment );
		}
		catch ( MWException $e ) {
			// idk lol
			return false;
		}

		return true;
	}
}
