<?php

namespace MediaWiki\Extension\BulkPageCreate;

use ContentHandler;
use Job;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserFactory;
use MWException;
use Title;
use Wikimedia\Dodo\Comment;
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
	public function run() {
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		$user = $userFactory->newFromId( 1 );
		$sourcePageTitle =
			Title::makeTitle( $this->params['sourceNamespace'], $this->params['sourceTitle'] );
		$targetPageTitle = $this->title;

		$sourceWikiPage = new WikiPage( $sourcePageTitle );
		$targetWikiPage = new WikiPage( $targetPageTitle );
		$targetText = $sourceWikiPage->getContent()->getText();
		// TODO: get the specific revision (COMMENT ABOVE)
		// TODO: text replacement
		$targetContent =
			ContentHandler::makeContent( $targetText, $targetPageTitle,
				$sourceWikiPage->getContentModel() );
		try {
			$updater = $targetWikiPage->newPageUpdater( $user );
			$updater->setContent( SlotRecord::MAIN, $targetContent );
			$updater->addTag( 'bulkpagecreate' );
			$comment =
				CommentStoreComment::newUnsavedComment( wfMessage( 'bulkpagecreate-edit-summary',
					$sourcePageTitle->getFullText() )->inContentLanguage()->plain() );
			$newRev = $updater->saveRevision( $comment );
		}
		catch ( MWException $e ) {
			return false;
		}

		return true;
	}
}
