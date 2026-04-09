<?php

namespace MediaWiki\Extension\Produnto\Sandbox;

use MediaWiki\Extension\Produnto\Runtime\Loader;
use MediaWiki\Extension\Produnto\Runtime\ModuleInfo;
use MediaWiki\Extension\Produnto\Store\FileAccess;
use MediaWiki\Extension\Produnto\Store\FileCollection;

/**
 * Provide read-only access to a sandbox
 */
class SandboxAccess implements Loader {
	/**
	 * @var array<string,array<string,string>>
	 */
	private array $hashesByPackagePath;

	/**
	 * @var array<string,string>
	 */
	private array $texts;

	/**
	 * @var array<string,array{string,string}>
	 */
	private array $modules;

	/**
	 * @param FileAccess $fileAccess
	 * @param array $data The full data array as stored in the stash
	 */
	public function __construct(
		private FileAccess $fileAccess,
		array $data
	) {
		$this->hashesByPackagePath = $data[SandboxStore::HASHES_BY_PACKAGE_PATH] ?? [];
		$this->texts = $data[SandboxStore::TEXTS] ?? [];
		$this->modules = $data[SandboxStore::MODULES] ?? [];
	}

	/**
	 * Check whether a package is present in the sandbox
	 *
	 * @param string $packageName
	 * @return bool
	 */
	public function hasPackage( string $packageName ): bool {
		return $this->getPackage( $packageName ) !== null;
	}

	/**
	 * Get the contents of a package in the sandbox, or null if there is no such package.
	 *
	 * @param string $packageName
	 * @return FileCollection|null
	 */
	public function getPackage( string $packageName ): ?FileCollection {
		if ( !isset( $this->hashesByPackagePath[$packageName] ) ) {
			return null;
		}
		return new SandboxFileCollection(
			$this->fileAccess,
			$this->hashesByPackagePath[$packageName],
			$this->texts
		);
	}

	/**
	 * Get the names of the packages in the sandbox
	 *
	 * @return string[]
	 */
	public function getPackageNames(): array {
		return array_keys( $this->hashesByPackagePath );
	}

	/**
	 * Get module info for a module in the sandbox, or null if there is no such
	 * module.
	 *
	 * @param string $moduleName
	 * @return ModuleInfo|null
	 */
	public function getModuleInfo( string $moduleName ): ?ModuleInfo {
		$info = $this->modules[$moduleName] ?? null;
		if ( $info ) {
			[ $packageName, $path ] = $info;
			return new ModuleInfo(
				$packageName,
				$path,
				// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
				$this->getPackage( $packageName )?->getFileContents( $path )
			);
		}
		return null;
	}

	/** @inheritDoc */
	public function getFileContents( string $packageName, string $path ): ?string {
		return $this->getPackage( $packageName )?->getFileContents( $path );
	}
}
