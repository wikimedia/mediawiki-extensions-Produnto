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
				'description' => 'The ID of the deployment, pd_id.',
				'example' => 1,
			],
			'controlWiki' => [
				'type' => 'string',
				'description' => 'The wiki ID of the initiating action.',
				'example' => 'enwiki',
			],
			'revision' => [
				'type' => 'integer',
				'description' => 'The revision ID of the initiating action.',
				'example' => 1,
			],
			'active' => [
				'type' => 'boolean',
				'description' => 'Whether the deployment is active by default on the wiki.',
			],
			'packages' => [
				'type' => 'object',
				'description' => 'Map of package name to deployed version.',
				'additionalProperties' => [ 'type' => 'string' ],
				'example' => [ 'some-package' => '1.0' ],
			],
			'userText' => [
				'type' => 'string',
				'description' => 'The name of the initiating user.',
				'example' => 'Some user',
			],
			'timestamp' => [
				'type' => 'string',
				'description' => 'The ISO-8601 combined date and time of when ' .
					'the deployment was created.',
				'example' => '2026-07-16T05:22:29Z',
			],
			'summary' => [
				'type' => 'string',
				'description' => 'The edit summary or comment supplied by the initiating user.',
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
				'description' => 'The name of the package.',
				'example' => 'produnto-test',
			],
			'version' => [
				'type' => 'string',
				'description' => 'The package version.',
				'example' => '1.0',
			],
			'id' => [
				'type' => 'integer',
				'description' => 'The package ID (ppv_id) identifying the name/version pair.',
				'example' => 1,
			],
			'fetchedUrl' => [
				'type' => 'string',
				'description' => 'An internal URL used when the package was fetched. ' .
					'May be an empty string or otherwise invalid.',
				'example' => 'https://gitlab.wikimedia.org/tstarling/produnto-test',
			],
			'upstreamRef' => [
				'type' => 'string',
				'description' => 'A string identifying the upstream package version. ' .
					'For packages fetched from Git, this will be a git ref. ' .
					'For packages fetched from elsewhere, this may be an empty string ' .
					'or otherwise not unique.',
				'example' => 'refs/tags/v1.2',
			],
			'localName' => [
				'type' => 'object',
				'description' => 'The localised package name.',
				'additionalProperties' => [ 'type' => 'string' ],
				'example' => [ 'en' => 'Produnto test' ],
			],
			'description' => [
				'type' => 'object',
				'description' => 'A short localised description of the package.',
				'additionalProperties' => [ 'type' => 'string' ],
				'example' => [ 'en' => 'A test package for testing the package manager.' ],
			],
			'type' => [
				'type' => 'string',
				'description' => 'The type of the package.',
				'enum' => [ 'scribunto' ],
			],
			'homepageUrl' => [
				'type' => 'string',
				'description' => 'The URL of the package homepage. Optional: use collabUrl ' .
					'and docUrl if there is no separate homepage. This is the `url` property ' .
					'from the package manifest.',
				'example' => 'https://www.mediawiki.org/wiki/Extension:Produnto',
			],
			'collabUrl' => [
				'type' => 'string',
				'description' => 'The URL of a human-readable entry point for source code ' .
					'review and contributions. For example, a GitLab project page.',
				'example' => 'https://gitlab.wikimedia.org/tstarling/produnto-test',
			],
			'docUrl' => [
				'type' => 'string',
				'description' => 'The URL of the documentation page.',
				'example' => 'https://www.mediawiki.org/wiki/Extension:Produnto',
			],
			'issueUrl' => [
				'type' => 'string',
				'description' => 'The URL of the package\'s bug tracker.',
				'example' => 'https://phabricator.wikimedia.org/project/view/8472/',
			],
			'authors' => [
				'type' => 'array',
				'items' => [ 'type' => 'string' ],
				'description' => 'The package\'s authors.',
				'example' => [ 'C. Scott Ananian', 'Subramanya Sastry', 'Tim Starling' ],
			],
			'license' => [
				'type' => 'string',
				'description' => 'SPDX identifier for the license under which ' .
					'the package is released.',
				'example' => 'GPL-2.0-or-later'
			],
			'requires' => [
				'type' => 'object',
				'description' => 'The dependencies of the package',
				'properties' => [
					'Lua' => [
						'type' => 'string',
						'description' => 'The Lua version required'
					],
					'MediaWiki' => [
						'type' => 'string',
						'description' => 'The MediaWiki version required'
					],
				],
				'patternProperties' => [ '.*' => [
					'type' => 'string',
					'description' => 'Required package versions'
				] ],
				'example' => [ 'some-package' => '>1.0' ],
			],
			'modules' => [
				'type' => 'object',
				'description' => 'A map of each global Scribunto module name to ' .
					'its implementing file, relative to the package root.',
				'patternProperties' => [ '.*' => [ 'type' => 'string' ] ],
				'example' => [ 'produnto_test' => 'src/init.lua' ],
			],
			'state' => [
				'type' => 'string',
				'description' => 'The fetch state of the package',
				'enum' => [ 'ready', 'fetching', 'failed' ],
				'default' => 'ready'
			],
			'errors' => [
				'type' => 'array',
				'description' => 'Errors which occurred while fetching the package ' .
					'and parsing its manifest file',
				'items' => self::MESSAGE
			]
		]
	];

	public const MESSAGE = [
		'type' => 'object',
		'properties' => [
			'key' => [
				'type' => 'string',
				'description' => 'The MediaWiki message key',
				'example' => 'produnto-fetch-manifest'
			],
			'translations' => [
				'type' => 'object',
				'description' => 'An array mapping the MediaWiki language code ' .
					'to the translation in that language. ',
				'required' => [ 'en' ],
				'additionalProperties' => [
					'type' => 'string',
					'description' => 'The string in each language',
				],
				'example' => [
					'en' => 'Error in produnto.json: invalid JSON'
				],
			],
		],
	];
}
