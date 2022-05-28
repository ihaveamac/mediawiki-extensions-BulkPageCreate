<?php

namespace MediaWiki\Extension\BulkPageCreate;

use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Permissions\PermissionManager;
use SpecialPage;
use Title;
use Status;
use WikiPage;
use ParserOptions;
use Html;
use Xml;
use HTMLForm;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;

class SpecialBulkPageCreate extends SpecialPage {

	/** @var PermissionManager */
	private $permissionManager;

	/** @var Status */
	private $status;

	/** @var string */
	private $state;

	public function __construct( RevisionStore $revisionStore, PermissionManager $permissionManager
	) {
		parent::__construct( 'BulkPageCreate', 'bulkpagecreate' );
		$this->revisionStore = $revisionStore;
		$this->permissionManager = $permissionManager;
	}

	public function doesWrites() {
		return true;
	}

	public function execute( $subPage ) {
		$out = $this->getOutput();
		$request = $this->getRequest();

		$this->setHeaders();
		$this->outputHeader();
		/** @noinspection PhpUnhandledExceptionInspection */
		$this->checkPermissions();
		/** @noinspection PhpUnreachableStatementInspection */

		$out->addModuleStyles( 'ext.BulkPageCreate' );
		$this->status = new Status();
		if ( $request->getCheck( 'submit-button' ) ) {
			$this->state = 'submit';
		} elseif ( $request->getCheck( 'preview-button' ) ) {
			$this->state = 'preview';
		} else {
			$this->state = 'form';
		}
		$form =
			HTMLForm::factory( 'ooui', $this->createForm(), $this->getContext(), 'bulkpagecreate' );
		$form->setId( 'mw-bulkpagecreate-form' );
		$form->setSubmitCallback( [ $this, 'callback' ] );
		$form->suppressDefaultSubmit();

		$form->prepareForm();
		$result = $form->tryAuthorizedSubmit();
		if ( $result === true || ( $result instanceof Status && $result->isGood() ) ) {
			$out->addWikiMsg('bulkpagecreate-queued');
		} else {
			if ( $this->state === 'preview' ) {
				$result = $this->status;
			}
			$form->displayForm( $result );
		}
	}

	protected function userCanOverwrite(): bool {
		return $this->permissionManager->userHasRight( $this->getUser(),
			'bulkpagecreate-overwrite' );
	}

	protected function createForm() {
		$isPreview = $this->state === 'preview';
		$controlTabIndex = 1;

		$formDescriptor = [];

		$formDescriptor['bulkpagecreate-sourcepage'] = [
			'id' => 'mw-bulkpagecreate-form-sourcepage',
			'name' => 'sourcepage',
			'label-message' => 'bulkpagecreate-sourcepage',
			'type' => 'title',
			'exists' => true,
			'tabindex' => $controlTabIndex ++,
		];

		$formDescriptor['bulkpagecreate-targetpages'] = [
			'id' => 'mw-bulkpagecreate-form-targetpages',
			'name' => 'targetpages',
			'label-message' => 'bulkpagecreate-targetpages',
			'type' => 'textarea',
			'tabindex' => $controlTabIndex ++,
		];

		if ( $isPreview ) {
			$formDescriptor['submit-button'] = [
				'id' => 'mw-bulkpagecreate-form-submit-button',
				'name' => 'submit-button',
				'type' => 'submit',
				'default' => $this->msg( 'htmlform-submit' ),
				'tabindex' => $controlTabIndex ++,
			];
		}

		$formDescriptor['preview-button'] = [
			'id' => 'mw-bulkpagecreate-form-preview-button',
			'name' => 'preview-button',
			'type' => 'submit',
			'tabindex' => $controlTabIndex ++,
			'default' => $this->msg( 'preview' ),
		];

		if ( $this->userCanOverwrite() ) {
			$formDescriptor['bulkpagecreate-overwrite'] = [
				'label-message' => 'bulkpagecreate-overwrite-pages',
				'type' => 'check',
				'tabindex' => $controlTabIndex ++,
			];
		}

		return $formDescriptor;
	}

	public function callback( $formData ) {
		$out = $this->getOutput();

		$parser = new BulkPageCreateParser( $this->getUser(), $this->getConfig() );
		$this->status = $parser->parseFormSubmit( $formData );

		if ( !$this->status->isOK() ) {
			$this->state = 'form';

			return $this->status;
		}

		if ( $this->state === 'submit' ) {
			$parser->queueJobs();

			return $this->status;
		} else {
			$out->addHTML( Xml::fieldset( 'Preview of parameters', $parser->buildTable() ) );

			return false;
		}
	}
}
