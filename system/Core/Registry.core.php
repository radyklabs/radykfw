<?php declare (strict_types=1);

namespace System\Core;

if (!defined ('ABSPATH'))
{
	http_response_code (404);
	exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * RadykFW Registry - Single source of truth for boot-time paths & constants
 * Replaces global constants from foreach ($radykfw) $set($k, $v)
 * Old logic (preserved as comment):
 * foreach ($radykfw as $k => $v) {$set ($k, $v);}
 * foreach ($file_extension as $k => $e) {$set ($k, $e);}
 */
final class Registry
{
	/**
	 * Folder → file-extension maps. SINGLE SOURCE OF TRUTH for autoloading.
	 *
	 * @var array<string, array<string,string>>
	 */
	public const ext_maps = [
		// system/<Folder>/<Class> → extension (by folder)
		'system' => [
			'Contracts' => '.contract.php',
			'Config'    => '.conf.php',
			'Enums'     => '.enum.php',
			'Helpers'   => '.helper.php',
			'Middleware' => '.middleware.php',
			'Services'  => '.service.php',
			'Views'     => '.view.php',
			'Core'      => '.core.php'
		],
		// modules/<Module>/<Folder>/<Class> → extension (by folder)
		'modules' => [
			'Controllers' => '.controller.php',
			'Models'      => '.model.php',
			'Services'    => '.service.php',
			'Views'       => '.view.php'
		],
	];

	private static array $data = [];

	private static bool $initialized = false;

	/**
	 * Initialize the registry.
	 *
	 * Default: read root directory constants from .starter, path definitions
	 * from system/Config/path_meta.conf.php, and WP context.
	 *
	 * Optional override: pass a pre-built $data array (e.g. from tests/CLI)
	 * to skip the constant read entirely.
	 *
	 * @param array<string,mixed>|null $data Pre-built registry data; null = build from .starter constants
	 */
	public static function instance (?array $data = null): void
	{
		if (self::$initialized) {return;}

		self::$data = $data ?? self::buildFromStarter ();
		self::$initialized = true;
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	private static function pathMeta (): array
	{
		$metadata = get_config ('path_meta', null);
		if (!is_array ($metadata) || $metadata === [])
		{
			throw new \LogicException ("Registry path metadata is missing or invalid.");
		}

		foreach ($metadata as $key => $entry)
		{
			if (!is_string ($key) || !is_array ($entry))
			{
				throw new \LogicException ("Registry path metadata contains an invalid entry.");
			}

			foreach ($entry as $field => $value)
			{
				if (!is_string ($field) || !is_string ($value))
				{
					throw new \LogicException ("Registry path metadata entry '{$key}' must contain string values.");
				}
			}
		}

		/** @var array<string, array<string, string>> $metadata */
		return $metadata;
	}

	/**
	 * @param array<string, array<string, string>> $pathMeta
	 * @param array<string, mixed> $registry
	 * @return array<string, mixed>
	 */
	private static function registerPaths (array $registry, array $pathMeta): array
	{
		// $reg['cache_url'] = null;

		foreach ($pathMeta as $key => $metadata)
		{
			if (($metadata['type'] ?? null) === 'private_cache')
			{
				$directory = $metadata['directory'] ?? null;
				if (!is_string ($directory) || $directory === '')
				{
					throw new \LogicException ("Invalid Registry cache metadata for '{$key}'.");
				}

				$registry[$key] = self::privateCachePath ($registry['plugin_path'], $directory);
				continue;
			}

			$parent = $metadata['parent'] ?? null;
			$constant = $metadata['constant'] ?? null;
			$default = $metadata['default'] ?? null;

			if (!is_string ($parent) || !array_key_exists ($parent, $registry)
				|| !is_string ($default))
			{
				throw new \LogicException ("Invalid Registry path metadata for '{$key}'.");
			}

			if (array_key_exists ('constant', $metadata) && !is_string ($constant))
			{
				throw new \LogicException ("Invalid Registry directory constant metadata for '{$key}'.");
			}

			$directory = is_string ($constant) ? self::directoryName ($constant, $default) : $default;
			$registry[$key] = interlace ($registry[$parent], $directory);

			$extension = $metadata['extension'] ?? null;
			if (is_string ($extension)) {$registry["xt{$key}"] = $extension;}

			$namespaceKey = $metadata['namespace_key'] ?? null;
			if (is_string ($namespaceKey))
			{
				$namespaceParent = $metadata['namespace_parent'] ?? null;
				$namespace = is_string ($namespaceParent) ? ($registry[$namespaceParent] ?? null) : '';
				if (!is_string ($namespace))
				{
					throw new \LogicException ("Invalid Registry namespace metadata for '{$key}'.");
				}

				$registry[$namespaceKey] = $namespace . ucfirst ($directory) . '\\';
			}

			$urlParent = $metadata['url_parent'] ?? null;
			if (is_string ($urlParent))
			{
				$urlBase = $registry[$urlParent] ?? null;
				if (!is_string ($urlBase))
				{
					throw new \LogicException ("Invalid Registry URL metadata for '{$key}'.");
				}

				$registry["{$key}_url"] = urlify (
					rtrim ($urlBase, '/\\') . '/' . trim ($directory, '/\\')
				);
			}
		}

		return $registry;
	}

	private static function directoryName (string $constant, string $default): string
	{
		if (!defined ($constant)) {return $default;}

		$directory = constant ($constant);
		if (!is_string ($directory) || $directory === '')
		{
			throw new \UnexpectedValueException ("Registry directory constant '{$constant}' must be a non-empty string.");
		}

		return $directory;
	}

	private static function privateCachePath (string $pluginPath, string $directory): string
	{
		$siteKey = substr (hash ('sha256', $pluginPath), 0, 16);
		return rtrim (sys_get_temp_dir (), '/\\')
			. sep . 'radykfw-' . $siteKey
			. sep . $directory;
	}

	/**
	 * Build registry data from root constants, path metadata, and WP context.
	 *
	 * @return array<string,mixed>
	 */
	private static function buildFromStarter (): array
	{
		global $wp_version;

		$radykfw['version']     = version;
		$radykfw['textdomain']  = textdomain;

		$radykfw['plugin_path'] = plugin_dir_path (keyfile);
		$radykfw['plugin_url']  = plugin_dir_url (keyfile);
		$radykfw['plugin_base'] = plugin_basename (keyfile);
		$radykfw['plugin_key']  = interlace (WP_PLUGIN_DIR, $radykfw['plugin_base']);

		$radykfw['home_url'] = get_home_url();

		$wpupl = wp_upload_dir();
		$radykfw['upload_url'] = $wpupl['baseurl'];
		$radykfw['upload_path'] = $wpupl['basedir'];

		// $radykfw['cache_url'] = null;

		$radykfw['wp_version']  = $wp_version;
		$radykfw['ext_maps'] = self::ext_maps;
		return self::registerPaths ($radykfw, self::pathMeta ());
	}

	public static function get (string $key, mixed $default = null): mixed
	{
		return self::$data[$key] ?? $default;
	}

	public static function has (string $key): bool
	{
		return array_key_exists ($key, self::$data);
	}

	public static function all (): array
	{
		return self::$data;
	}

	/**
	 * Resolve the file extension for a namespace folder.
	 *
	 * @param string $root   'system' | 'modules'
	 * @param string $folder folder name (e.g. Contracts, Controllers, Core)
	 * @return string file extension
	 */
	public static function ext (string $folder, string $root = system_dir): string
	{
		// $map = self::ext_maps[$root] ?? [];
		return self::ext_maps[$root][$folder]; //?? $map['_default'] ?? '.php';
	}
}
