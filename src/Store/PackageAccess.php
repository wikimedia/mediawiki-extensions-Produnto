<?php

namespace MediaWiki\Extension\Produnto\Store;

use MediaWiki\Extension\Produnto\Fetcher\FetchStatus;
use MediaWiki\Extension\Produnto\Manifest\ManifestStatus;
use MediaWiki\Language\LanguageFallback;
use StatusValue;
use Wikimedia\Message\MessageValue;

/**
 * Read-only access to data relating to a package version
 */
class PackageAccess implements FileCollection {
	public const STATUS_CLASSES = [
		StatusValue::class,
		FetchStatus::class,
		ManifestStatus::class,
		MessageValue::class
	];

	public function __construct(
		private FileAccess $fileAccess,
		private int $id,
		private string $name,
		private string $version,
		private string $upstreamRef,
		private string $fetchedUrl,
		private array $props,
		private int $state,
		private ?string $error
	) {
	}

	/**
	 * Get the package name
	 *
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Get the version
	 *
	 * @return string
	 */
	public function getVersion(): string {
		return $this->version;
	}

	/**
	 * Get the commit hash, or some other server-dependent reference
	 *
	 * @return string
	 */
	public function getUpstreamRef(): string {
		return $this->upstreamRef;
	}

	/**
	 * Get the URL used for fetching the package
	 *
	 * @return string
	 */
	public function getFetchedUrl(): string {
		return $this->fetchedUrl;
	}

	/**
	 * Get the package property array
	 *
	 * @return array
	 */
	public function getProps(): array {
		return $this->props;
	}

	/**
	 * Get the package type, e.g. "scribunto"
	 *
	 * @return string|null
	 */
	public function getType(): ?string {
		return $this->props['type'] ?? null;
	}

	/**
	 * Get the homepage URL
	 *
	 * @return string|null
	 */
	public function getHomepageUrl(): ?string {
		return $this->props['homepage-url'] ?? null;
	}

	/**
	 * Get the documentation URL
	 *
	 * @return string|null
	 */
	public function getDocUrl(): ?string {
		return $this->props['doc-url'] ?? null;
	}

	/**
	 * Get the collab URL, e.g. GitLab project page
	 *
	 * @return string|null
	 */
	public function getCollabUrl(): ?string {
		return $this->props['collab-url'] ?? null;
	}

	/**
	 * Get the bug tracker URL
	 *
	 * @return string|null
	 */
	public function getIssueUrl(): ?string {
		return $this->props['issue-url'] ?? null;
	}

	/**
	 * Get the package authors
	 *
	 * @return string[]
	 */
	public function getAuthors(): array {
		return $this->props['authors'] ?? [];
	}

	/**
	 * Get the license
	 *
	 * @return string|null
	 */
	public function getLicense(): ?string {
		return $this->props['license'] ?? null;
	}

	/**
	 * Get constraints by package name
	 *
	 * @return array<string,string>
	 */
	public function getRequires(): array {
		return $this->props['requires'] ?? [];
	}

	/**
	 * Get the map of Lua module name to implementation path
	 *
	 * @return array<string,string>
	 */
	public function getModules(): array {
		return $this->props['modules'] ?? [];
	}

	/**
	 * Get the package description in the given language, with optional fallback.
	 *
	 * @param string $lang
	 * @param LanguageFallback|null $fallbackProvider
	 * @return string|null
	 */
	public function getDescription( string $lang, ?LanguageFallback $fallbackProvider = null ): ?string {
		return $this->getLocalisedProperty( 'description', $lang, $fallbackProvider );
	}

	/**
	 * Get the localised package name, falling back to the canonical name
	 *
	 * @param string $lang
	 * @param LanguageFallback|null $falllbackProvider
	 * @return string
	 */
	public function getLocalName( string $lang, ?LanguageFallback $falllbackProvider = null ): string {
		return $this->getLocalisedProperty( 'name', $lang, $falllbackProvider )
			?? $this->getName();
	}

	private function getLocalisedProperty( string $prop, string $lang,
		?LanguageFallback $fallbackProvider
	): ?string {
		if ( !isset( $this->props[$prop] ) ) {
			return null;
		}
		$values = $this->props[$prop];
		if ( isset( $values[$lang] ) ) {
			return $values[$lang];
		}
		if ( $fallbackProvider ) {
			foreach ( $fallbackProvider->getAll( $lang ) as $fallback ) {
				if ( isset( $values[$fallback] ) ) {
					return $values[$fallback];
				}
			}
		}
		return null;
	}

	/**
	 * Get the ppv_id value
	 *
	 * @return int
	 */
	public function getId(): int {
		return $this->id;
	}

	/**
	 * Get the package state, one of the ProduntoStore::STATE_* constants
	 * @return int
	 */
	public function getState(): int {
		return $this->state;
	}

	/**
	 * Get the fetch status
	 *
	 * @return StatusValue
	 */
	public function getStatus(): StatusValue {
		if ( $this->error ) {
			return unserialize(
				$this->error,
				[ 'allowed_classes' => self::STATUS_CLASSES ]
			);
		} else {
			return StatusValue::newGood();
		}
	}

	/** @inheritDoc */
	public function getFileContents( string $path ): ?string {
		return $this->fileAccess->getFileContents( $this->id, $path );
	}

	/** @inheritDoc */
	public function hasFile( string $path ): bool {
		return $this->fileAccess->hasFile( $this->id, $path );
	}
}
