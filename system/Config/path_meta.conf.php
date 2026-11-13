<?php declare (strict_types=1);

if (!defined ('ABSPATH'))
{
	http_response_code (404);
	exit ('<h1>404 Not Found</h1><p>The requested page could not be found.</p>');
}

/**
 * Registry path definitions. Only the plugin's root directories are
 * configurable through .starter; framework subdirectories are fixed here.
 *
 * @return array<string, array<string, string>>
 */
return [
	'modules' => [
		'parent' => 'plugin_path',
		'constant' => 'modules_dir',
		'default' => 'modules',
		'namespace_key' => 'nsmod',
	],
	'system' => [
		'parent' => 'plugin_path',
		'constant' => 'system_dir',
		'default' => 'system',
		'namespace_key' => 'nssys',
	],
	'assets' => [
		'parent' => 'plugin_path',
		'constant' => 'assets_dir',
		'default' => 'assets',
		'url_parent' => 'plugin_url',
	],
	'config' => [
		'parent' => 'system',
		'default' => 'Config',
		'extension' => '.config.php',
	],
	'contracts' => [
		'parent' => 'system',
		'default' => 'Contracts',
		'extension' => '.contract.php',
		'namespace_key' => 'nscontracts',
		'namespace_parent' => 'nssys',
	],
	'core' => [
		'parent' => 'system',
		'default' => 'Core',
		'extension' => '.core.php',
		'namespace_key' => 'nscore',
		'namespace_parent' => 'nssys',
	],
	'enum' => [
		'parent' => 'system',
		'default' => 'Enum',
		'extension' => '.enum.php',
		'namespace_key' => 'nsenum',
		'namespace_parent' => 'nssys',
	],
	'helpers' => [
		'parent' => 'system',
		'default' => 'Helpers',
		'extension' => '.helper.php',
	],
	'middleware' => [
		'parent' => 'system',
		'default' => 'Middleware',
		'extension' => '.middleware.php',
		'namespace_key' => 'nsmiddleware',
		'namespace_parent' => 'nssys',
	],
	'services' => [
		'parent' => 'system',
		'default' => 'Services',
		'extension' => '.service.php',
		'namespace_key' => 'nsservices',
		'namespace_parent' => 'nssys',
	],
	'templates' => [
		'parent' => 'system',
		'default' => 'Templates',
	],
	'cache' => [
		'type' => 'private_cache',
		'directory' => 'cache',
	],
	'controllers' => [
		'parent' => 'modules',
		'default' => 'Controllers',
		'extension' => '.controller.php',
		'namespace_key' => 'nscontrollers',
		'namespace_parent' => 'nsmod',
	],
	'models' => [
		'parent' => 'modules',
		'default' => 'Models',
		'extension' => '.model.php',
		'namespace_key' => 'nsmodels',
		'namespace_parent' => 'nsmod',
	],
	'views' => [
		'parent' => 'modules',
		'default' => 'Views',
		'extension' => '.view.php',
	],
	'components' => [
		'parent' => 'templates',
		'default' => 'Components',
		'extension' => '.component.php',
	],
	'errors' => [
		'parent' => 'templates',
		'default' => 'Errors',
		'extension' => '.error.php',
	],
	'layouts' => [
		'parent' => 'templates',
		'default' => 'Layouts',
		'extension' => '.layout.php',
	],
	'pages' => [
		'parent' => 'templates',
		'default' => 'Pages',
		'extension' => '.page.php',
	],
	'css' => [
		'parent' => 'assets',
		'default' => 'css',
		'url_parent' => 'assets_url',
	],
	'images' => [
		'parent' => 'assets',
		'default' => 'images',
		'url_parent' => 'assets_url',
	],
	'js' => [
		'parent' => 'assets',
		'default' => 'js',
		'url_parent' => 'assets_url',
	],
];
