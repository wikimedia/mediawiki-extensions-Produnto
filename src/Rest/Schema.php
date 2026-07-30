<?php

namespace MediaWiki\Extension\Produnto\Rest;

/**
 * Shared definitions for REST schemas
 */
class Schema {
	public const DEPLOYMENT = [
		'type' => 'object',
		'required' => [ 'id' ],
		'properties' => [
			'id' => [
				'type' => 'integer',
				'x-i18n-description' => 'rest-property-desc-produnto-deployment-id',
				'example' => 1,
			],
			'controlWiki' => [
				'type' => 'string',
				'x-i18n-description' => 'rest-property-desc-produnto-deployment-control-wiki',
				'example' => 'enwiki',
			],
			'revision' => [
				'type' => 'integer',
				'x-i18n-description' => 'rest-property-desc-produnto-deployment-revision',
				'example' => 1,
			],
			'active' => [
				'type' => 'boolean',
				'x-i18n-description' => 'rest-property-desc-produnto-deployment-active',
			],
			'packages' => [
				'type' => 'object',
				'x-i18n-description' => 'rest-property-desc-produnto-deployment-packages',
				'additionalProperties' => [ 'type' => 'string' ],
				'example' => [ 'some-package' => '1.0' ],
			],
			'userText' => [
				'type' => 'string',
				'x-i18n-description' => 'rest-property-desc-produnto-deployment-user-text',
				'description' => '',
				'example' => 'Some user',
			],
			'timestamp' => [
				'type' => 'string',
				'x-i18n-description' => 'rest-property-desc-produnto-deployment-timestamp',
				'example' => '2026-07-16T05:22:29Z',
			],
			'summary' => [
				'type' => 'string',
				'x-i18n-description' => 'rest-property-desc-produnto-deployment-summary',
				'example' => 'Updated some-package from 1.0 to 2.0',
			],
		]
	];

	public const PACKAGE = [
		'type' => 'object',
		'required' => [ 'name', 'version', 'id' ],
		'properties' => [
			'name' => [
				'type' => 'string',
				'x-i18n-description' => 'rest-property-desc-produnto-package-name',
				'example' => 'produnto-test',
			],
			'version' => [
				'type' => 'string',
				'x-i18n-description' => 'rest-property-desc-produnto-package-version',
				'example' => '1.0',
			],
			'id' => [
				'type' => 'integer',
				'x-i18n-description' => 'rest-property-desc-produnto-package-id',
				'example' => 1,
			],
			'fetchedUrl' => [
				'type' => 'string',
				'x-i18n-description' => 'rest-property-desc-produnto-package-fetched-url',
				'example' => 'https://gitlab.wikimedia.org/tstarling/produnto-test',
			],
			'upstreamRef' => [
				'type' => 'string',
				'x-i18n-description' => 'rest-property-desc-produnto-package-upstream-ref',
				'example' => 'refs/tags/v1.2',
			],
			'localName' => [
				'type' => 'object',
				'x-i18n-description' => 'rest-property-desc-produnto-package-local-name',
				'additionalProperties' => [ 'type' => 'string' ],
				'example' => [ 'en' => 'Produnto test' ],
			],
			'description' => [
				'type' => 'object',
				'x-i18n-description' => 'rest-property-desc-produnto-package-description',
				'additionalProperties' => [ 'type' => 'string' ],
				'example' => [ 'en' => 'A test package for testing the package manager.' ],
			],
			'type' => [
				'type' => 'string',
				'x-i18n-description' => 'rest-property-desc-produnto-package-type',
				'enum' => [ 'scribunto' ],
			],
			'homepageUrl' => [
				'type' => 'string',
				'x-i18n-description' => 'rest-property-desc-produnto-package-homepage-url',
				'example' => 'https://www.mediawiki.org/wiki/Extension:Produnto',
			],
			'collabUrl' => [
				'type' => 'string',
				'x-i18n-description' => 'rest-property-desc-produnto-package-collab-url',
				'example' => 'https://gitlab.wikimedia.org/tstarling/produnto-test',
			],
			'docUrl' => [
				'type' => 'string',
				'x-i18n-description' => 'rest-property-desc-produnto-package-doc-url',
				'example' => 'https://www.mediawiki.org/wiki/Extension:Produnto',
			],
			'issueUrl' => [
				'type' => 'string',
				'x-i18n-description' => 'rest-property-desc-produnto-package-issue-url',
				'example' => 'https://phabricator.wikimedia.org/project/view/8472/',
			],
			'authors' => [
				'type' => 'array',
				'items' => [ 'type' => 'string' ],
				'x-i18n-description' => 'rest-property-desc-produnto-package-authors',
				'example' => [ 'C. Scott Ananian', 'Subramanya Sastry', 'Tim Starling' ],
			],
			'license' => [
				'type' => 'string',
				'x-i18n-description' => 'rest-property-desc-produnto-package-license',
				'example' => 'GPL-2.0-or-later'
			],
			'requires' => [
				'type' => 'object',
				'x-i18n-description' => 'rest-property-desc-produnto-package-requires',
				'properties' => [
					'Lua' => [
						'type' => 'string',
						'x-i18n-description' => 'rest-property-desc-produnto-package-requires-lua',
					],
					'MediaWiki' => [
						'type' => 'string',
						'x-i18n-description' => 'rest-property-desc-produnto-package-requires-mediawiki',
					],
				],
				'patternProperties' => [ '.*' => [
					'type' => 'string',
					'x-i18n-description' => 'rest-property-desc-produnto-package-requires-other',
				] ],
				'example' => [ 'some-package' => '>1.0' ],
			],
			'modules' => [
				'type' => 'object',
				'x-i18n-description' => 'rest-property-desc-produnto-package-modules',
				'patternProperties' => [ '.*' => [ 'type' => 'string' ] ],
				'example' => [ 'produnto_test' => 'src/init.lua' ],
			],
			'state' => [
				'type' => 'string',
				'x-i18n-description' => 'rest-property-desc-produnto-package-state',
				'enum' => [ 'ready', 'fetching', 'failed' ],
				'default' => 'ready'
			],
			'errors' => [
				'type' => 'array',
				'x-i18n-description' => 'rest-property-desc-produnto-package-errors',
				'items' => self::MESSAGE
			]
		]
	];

	public const MESSAGE = [
		'type' => 'object',
		'properties' => [
			'key' => [
				'type' => 'string',
				'x-i18n-description' => 'rest-property-desc-produnto-message-key',
				'example' => 'produnto-fetch-manifest'
			],
			'translations' => [
				'type' => 'object',
				'x-i18n-description' => 'rest-property-desc-produnto-message-translations',
				'required' => [ 'en' ],
				'additionalProperties' => [
					'type' => 'string',
					'x-i18n-description' => 'rest-property-desc-produnto-message-translation',
				],
				'example' => [
					'en' => 'Error in produnto.json: invalid JSON'
				],
			],
		],
	];
}
