<?php declare (strict_types=1);

namespace {
    foreach (['add_action', 'add_filter', 'apply_filters', 'add_menu_page', 'add_submenu_page', 'remove_submenu_page', '__', 'wp_kses_post'] as $function)
    {
        if (function_exists ($function)) {continue;}

        if ($function === 'add_action')
        {
            function add_action ($hook, $callback, $priority = 10, $accepted_args = 1): bool
            {
                $GLOBALS ['radykfw_test_actions'] [$hook] [] = $callback;
                return true;
            }
        }
        elseif ($function === 'add_filter')
        {
            function add_filter ($hook, $callback, $priority = 10, $accepted_args = 1): bool
            {
                $GLOBALS ['radykfw_test_filters'] [$hook] [] = $callback;
                return true;
            }
        }
        elseif ($function === 'apply_filters')
        {
            function apply_filters ($hook, $value, ...$args): mixed
            {
                foreach ($GLOBALS ['radykfw_test_filters'] [$hook] ?? [] as $callback)
                {
                    $value = $callback ($value, ...$args);
                }
                return $value;
            }
        }
        elseif ($function === 'add_menu_page')
        {
            function add_menu_page ($page_title, $menu_title, $capability, $menu_slug, $callback = null, $icon_url = '', $position = null): string
            {
                $GLOBALS ['radykfw_test_menus'] [] = compact ('page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'icon_url', 'position');
                return 'toplevel_page_' . $menu_slug;
            }
        }
        elseif ($function === 'add_submenu_page')
        {
            function add_submenu_page ($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = null, $position = null): string
            {
                $GLOBALS ['radykfw_test_submenus'] [] = compact ('parent_slug', 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'position');
                return $parent_slug . '_page_' . $menu_slug;
            }
        }
        elseif ($function === 'remove_submenu_page')
        {
            function remove_submenu_page ($menu_slug, $submenu_slug): bool
            {
                $GLOBALS ['radykfw_test_removed_submenus'] [] = [$menu_slug, $submenu_slug];
                return true;
            }
        }
        elseif ($function === '__')
        {
            function __ ($text, $domain = 'default'): string {return $text;}
        }
        elseif ($function === 'wp_kses_post')
        {
            function wp_kses_post ($text): string {return (string) $text;}
        }
    }
}

namespace RadykFW\Tests {
    use PHPUnit\Framework\TestCase;
    use System\Core\Registry;
    use System\Services\AdminUI;

    final class AdminUIMenuTest extends TestCase
    {
        private string $modulesDirectory;
        private bool $registryWasInitialized;
        private array $registryData;

        protected function setUp (): void
        {
            $this->modulesDirectory = __DIR__ . '/../cache' . sep . 'radykfw-menus-' . bin2hex (random_bytes (6));
            mkdir ($this->modulesDirectory);

            $registry = new \ReflectionClass (Registry::class);
            $initialized = $registry->getProperty ('initialized');
            $this->registryWasInitialized = $initialized->getValue ();
            $this->registryData = $registry->getProperty ('data')->getValue ();
            $initialized->setValue (null, false);
            $data = $registry->getProperty ('data');
            $data->setValue (null, []);
            Registry::instance (['modules' => $this->modulesDirectory]);

            $admin_ui = new \ReflectionClass (AdminUI::class);
            foreach ([
                'top_menus' => [],
                'sub_menus' => [],
                'assets' => [],
                'top_registered' => false,
                'hooks_registered' => false,
                'menus_registered' => false,
                'admin_notices' => [],
                'helper_skips' => [],
            ] as $property => $value)
            {
                $admin_ui->getProperty ($property)->setValue (null, $value);
            }

            $GLOBALS ['radykfw_test_actions'] = [];
            $GLOBALS ['radykfw_test_filters'] = [];
            $GLOBALS ['radykfw_test_menus'] = [];
            $GLOBALS ['radykfw_test_submenus'] = [];
            $GLOBALS ['radykfw_test_removed_submenus'] = [];

            require_once __DIR__ . '/support/esc-html-stub.php';
        }

        protected function tearDown (): void
        {
            foreach (glob ($this->modulesDirectory . sep . '*' . sep . 'Config' . sep . 'menus.conf.php') ?: [] as $file)
            {
                unlink ($file);
                $config_directory = dirname ($file);
                rmdir ($config_directory);
                rmdir (dirname ($config_directory));
            }
            rmdir ($this->modulesDirectory);

            $registry = new \ReflectionClass (Registry::class);
            $registry->getProperty ('data')->setValue (null, $this->registryData);
            $registry->getProperty ('initialized')->setValue (null, $this->registryWasInitialized);
        }

        public function testDiscoversModuleMenusInStableOrderAndAppliesWordPressFilter (): void
        {
            $this->addModuleConfig ('Zulu', [
                'menus' => [$this->menu ('zulu-menu', 'Zulu')],
                'submenus' => ['radyk' => [$this->menu ('radyk-zulu', 'Zulu submenu', 20)]],
            ]);
            $this->addModuleConfig ('Alpha', [
                'menus' => [$this->menu ('alpha-menu', 'Alpha')],
                'submenus' => [
                    'radyk'       => [$this->menu ('radyk-alpha', 'Alpha submenu', 10)],
                    'alpha-menu'  => [$this->menu ('alpha-child', 'Alpha child')],
                ],
            ]);
            add_filter ('radykfw_admin_menu_config', static function (array $config): array {
                $config ['submenus'] ['radyk'] [] = [
                    'slug'       => 'radyk-filtered',
                    'title'      => 'Filtered',
                    'capability' => 'manage_options',
                    'callback'   => null,
                    'position'   => 30,
                ];
                return $config;
            });

            AdminUI::registerHooks ();
            AdminUI::registerMenus ();

            $this->assertCount (1, $GLOBALS ['radykfw_test_actions'] ['admin_menu']);
            $this->assertSame (
                ['radyk', 'alpha-menu', 'zulu-menu'],
                array_column ($GLOBALS ['radykfw_test_menus'], 'menu_slug')
            );
            $this->assertSame (
                ['radyk', 'radyk-modules', 'radyk-alpha', 'radyk-zulu', 'radyk-filtered', 'radyk-settings', 'radyk-tools', 'radyk-about'],
                array_column (
                    array_values (array_filter (
                        $GLOBALS ['radykfw_test_submenus'],
                        static fn (array $submenu): bool => $submenu ['parent_slug'] === 'radyk'
                    )),
                    'menu_slug'
                )
            );
            $this->assertSame (['radyk', 'alpha-menu', 'zulu-menu'], array_keys (AdminUI::getTopMenus ()));
            $this->assertSame ([], $GLOBALS ['radykfw_test_removed_submenus']);
            $this->assertSame (
                ['alpha-child'],
                array_column (
                    array_values (array_filter (
                        $GLOBALS ['radykfw_test_submenus'],
                        static fn (array $submenu): bool => $submenu ['parent_slug'] === 'alpha-menu'
                    )),
                    'menu_slug'
                )
            );
            $this->assertSame ('radyk', AdminUI::getOwnerSlug ());
            $this->assertFalse (AdminUI::registerMenu ($this->menu ('radyk', 'Conflicting owner')));
        }

        public function testRejectsInvalidOrDuplicateModuleSlugsWithoutLosingDefaultMenus (): void
        {
            $this->addModuleConfig ('Broken', [
                'menus' => [$this->menu ('radyk', 'Conflicting menu')],
                'submenus' => [],
            ]);
            $this->addModuleConfig ('Unsafe', [
                'menus' => [$this->menu ('../outside', 'Unsafe menu')],
                'submenus' => [],
            ]);

            AdminUI::registerMenus ();

            $this->assertSame (['radyk'], array_column ($GLOBALS ['radykfw_test_menus'], 'menu_slug'));
            $this->assertNotEmpty (AdminUI::getAdminNotices ());
        }

        public function testOverviewUsesTheTopLevelSlugAndRunnerDoesNotOwnMenuRegistration (): void
        {
            AdminUI::registerMenus ();

            $overview = array_values (array_filter (
                $GLOBALS ['radykfw_test_submenus'],
                static fn (array $submenu): bool => $submenu ['parent_slug'] === 'radyk'
                    && $submenu ['menu_slug'] === 'radyk'
            ));

            $this->assertCount (1, $overview);
            $this->assertSame ('Overview', $overview [0] ['menu_title']);
            $this->assertSame ('radyk', $overview [0] ['menu_slug']);
            $this->assertFalse (method_exists (\System\Core\Runner::class, 'registerAdminMenus'));
        }

        /**
         * @return array<string, mixed>
         */
        private function menu (string $slug, string $title, int $position = 10): array
        {
            return [
                'slug'       => $slug,
                'title'      => $title,
                'capability' => 'manage_options',
                'callback'   => null,
                'position'   => $position,
            ];
        }

        /**
         * @param array<string, mixed> $config
         */
        private function addModuleConfig (string $name, array $config): void
        {
            $config_directory = $this->modulesDirectory . sep . $name . sep . 'Config';
            mkdir ($config_directory, 0777, true);
            file_put_contents (
                $config_directory . sep . 'menus.conf.php',
                '<?php return ' . var_export ($config, true) . ';'
            );
        }
    }
}
