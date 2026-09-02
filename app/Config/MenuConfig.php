<?php

namespace App\Config;

class MenuConfig
{
    public static function getAllowedRoutesByRole()
    {
        return [
            'ADMIN' => ['*'],
            'EMPLOYEE' => [
                'admin/ventas',
                'clientes/despachos',
            ],
        ];
    }

    public static function getMenuItems()
    {
        $items = [
            [
                'id' => 'dashboard',
                'title' => 'Administración',
                'icon' => [
                    'viewBox' => '0 0 24 24',
                    'paths' => [
                        '<rect x="3" y="3" width="7" height="7"></rect>',
                        '<rect x="14" y="3" width="7" height="7"></rect>',
                        '<rect x="14" y="14" width="7" height="7"></rect>',
                        '<rect x="3" y="14" width="7" height="7"></rect>'
                    ]
                ],
                'submenu' => [
                    [
                        'url' => 'admin/categorias',
                        'title' => 'Categorías',
                        'gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                        'shadow' => 'rgba(102, 126, 234, 0.3)',
                        'icon' => [
                            'name' => 'feather-grid',
                            'paths' => [
                                '<rect x="3" y="3" width="7" height="7"></rect>',
                                '<rect x="14" y="3" width="7" height="7"></rect>',
                                '<rect x="14" y="14" width="7" height="7"></rect>',
                                '<rect x="3" y="14" width="7" height="7"></rect>'
                            ]
                        ]
                    ],
                    [
                        'url' => 'admin/productos',
                        'title' => 'Productos',
                        'gradient' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
                        'shadow' => 'rgba(240, 147, 251, 0.3)',
                        'icon' => [
                            'name' => 'feather-package',
                            'paths' => [
                                '<line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line>',
                                '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>',
                                '<polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>',
                                '<line x1="12" y1="22.08" x2="12" y2="12"></line>'
                            ]
                        ]
                    ],
                    [
                        'url' => 'admin/ventas',
                        'title' => 'Ventas',
                        'gradient' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
                        'shadow' => 'rgba(79, 172, 254, 0.3)',
                        'icon' => [
                            'name' => 'feather-shopping-cart',
                            'paths' => [
                                '<circle cx="9" cy="21" r="1"></circle>',
                                '<circle cx="20" cy="21" r="1"></circle>',
                                '<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>'
                            ]
                        ]
                    ],
                    [
                        'url' => 'admin/inventario',
                        'title' => 'Inventario',
                        'gradient' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
                        'shadow' => 'rgba(67, 233, 123, 0.3)',
                        'icon' => [
                            'name' => 'feather-archive',
                            'paths' => [
                                '<polyline points="21 8 21 21 3 21 3 8"></polyline>',
                                '<rect x="1" y="3" width="22" height="5"></rect>',
                                '<line x1="10" y1="12" x2="14" y2="12"></line>'
                            ]
                        ]
                    ],
                    [
                        'url' => 'admin/indicadores',
                        'title' => 'Indicadores',
                        'gradient' => 'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)',
                        'shadow' => 'rgba(17, 153, 142, 0.3)',
                        'icon' => [
                            'name' => 'feather-bar-chart-2',
                            'paths' => [
                                '<line x1="18" y1="20" x2="18" y2="10"></line>',
                                '<line x1="12" y1="20" x2="12" y2="4"></line>',
                                '<line x1="6" y1="20" x2="6" y2="14"></line>'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'id' => 'clientes',
                'title' => 'Clientes',
                'icon' => [
                    'viewBox' => '0 0 24 24',
                    'paths' => [
                        '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>',
                        '<circle cx="12" cy="7" r="4"></circle>'
                    ]
                ],
                'submenu' => [
                    [
                        'url' => 'clientes',
                        'title' => 'Clientes',
                        'gradient' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
                        'shadow' => 'rgba(250, 112, 154, 0.3)',
                        'icon' => [
                            'name' => 'feather-users',
                            'paths' => [
                                '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>',
                                '<circle cx="9" cy="7" r="4"></circle>',
                                '<path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>',
                                '<path d="M16 3.13a4 4 0 0 1 0 7.75"></path>'
                            ]
                        ]
                    ],
                    [
                        'url' => 'clientes/despachos',
                        'title' => 'Despachos',
                        'gradient' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
                        'shadow' => 'rgba(250, 112, 154, 0.3)',
                        'icon' => [
                            'name' => 'feather-users',
                            'paths' => [
                                '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>',
                                '<circle cx="9" cy="7" r="4"></circle>',
                                '<path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>',
                                '<path d="M16 3.13a4 4 0 0 1 0 7.75"></path>'
                            ]
                        ]
                    ]
                ]
            ],
            [
                'id' => 'sistema',
                'title' => 'Sistema',
                'icon' => [
                    'viewBox' => '0 0 24 24',
                    'paths' => [
                        '<circle cx="12" cy="12" r="3"></circle>',
                        '<path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83l-0.01 0.01a2 2 0 0 1-2.83 0l-0.06-0.06a1.65 1.65 0 0 0-1.82-0.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2h-0.02a2 2 0 0 1-2-2v-0.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82 0.33l-0.06 0.06a2 2 0 0 1-2.83 0l-0.01-0.01a2 2 0 0 1 0-2.83l0.06-0.06a1.65 1.65 0 0 0 0.33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2v-0.02a2 2 0 0 1 2-2h0.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-0.33-1.82l-0.06-0.06a2 2 0 0 1 0-2.83l0.01-0.01a2 2 0 0 1 2.83 0l0.06 0.06a1.65 1.65 0 0 0 1.82 0.33h0.01a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2h0.02a2 2 0 0 1 2 2v0.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-0.33l0.06-0.06a2 2 0 0 1 2.83 0l0.01 0.01a2 2 0 0 1 0 2.83l-0.06 0.06a1.65 1.65 0 0 0-0.33 1.82v0.01a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2v0.02a2 2 0 0 1-2 2h-0.09a1.65 1.65 0 0 0-1.51 1z"></path>'
                    ]
                ],
                'submenu' => [
                    [
                        'url' => 'sistema/users',
                        'title' => 'Usuarios',
                        'gradient' => 'linear-gradient(135deg, #30cfd0 0%, #330867 100%)',
                        'shadow' => 'rgba(48, 207, 208, 0.3)',
                        'icon' => [
                            'name' => 'feather-users',
                            'paths' => [
                                '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>',
                                '<circle cx="9" cy="7" r="4"></circle>',
                                '<path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>',
                                '<path d="M16 3.13a4 4 0 0 1 0 7.75"></path>'
                            ]
                        ]
                    ],
                    [
                        'url' => 'sistema/roles',
                        'title' => 'Roles',
                        'gradient' => 'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)',
                        'shadow' => 'rgba(168, 237, 234, 0.3)',
                        'icon' => [
                            'name' => 'feather-grid',
                            'paths' => [
                                '<rect x="3" y="3" width="7" height="7"></rect>',
                                '<rect x="14" y="3" width="7" height="7"></rect>',
                                '<rect x="14" y="14" width="7" height="7"></rect>',
                                '<rect x="3" y="14" width="7" height="7"></rect>'
                            ]
                        ]
                    ],
                    [
                        'url' => 'sistema/sitio',
                        'title' => 'Sitio Web',
                        'gradient' => 'linear-gradient(135deg, #f6a821 0%, #f6d365 100%)',
                        'shadow' => 'rgba(246, 168, 33, 0.3)',
                        'icon' => [
                            'name' => 'feather-globe',
                            'paths' => [
                                '<circle cx="12" cy="12" r="10"></circle>',
                                '<line x1="2" y1="12" x2="22" y2="12"></line>',
                                '<path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>'
                            ]
                        ]
                    ],
                    [
                        'url' => 'sistema/mercado-pago',
                        'title' => 'Mercado Pago',
                        'gradient' => 'linear-gradient(135deg, #009ee3 0%, #21d4fd 100%)',
                        'shadow' => 'rgba(0, 158, 227, 0.3)',
                        'icon' => [
                            'name' => 'feather-credit-card',
                            'paths' => [
                                '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>',
                                '<line x1="1" y1="10" x2="23" y2="10"></line>'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return self::filterMenuItemsByRole($items, self::resolveCurrentRoleKey());
    }

    public static function getVisibleRoutesForCurrentUser()
    {
        $roleKey = self::resolveCurrentRoleKey();
        $routes = self::getAllowedRoutesByRole()[$roleKey] ?? [];

        return $routes;
    }

    private static function filterMenuItemsByRole($items, $roleKey)
    {
        $allowedRoutes = self::getAllowedRoutesByRole()[$roleKey] ?? [];

        if (in_array('*', $allowedRoutes, true)) {
            return $items;
        }

        $filteredItems = [];

        foreach ($items as $item) {
            $submenus = $item['submenu'] ?? [];
            $submenus = array_values(array_filter($submenus, function ($submenu) use ($allowedRoutes) {
                return in_array($submenu['url'], $allowedRoutes, true);
            }));

            if (!empty($submenus)) {
                $item['submenu'] = $submenus;
                $filteredItems[] = $item;
            }
        }

        return $filteredItems;
    }

    private static function resolveCurrentRoleKey()
    {
        if (!auth()->check()) {
            return 'EMPLOYEE';
        }

        $user = auth()->user();

        $profile = strtoupper((string) ($user->profile ?? ''));
        if (in_array($profile, ['ADMIN', 'EMPLOYEE'], true)) {
            return $profile;
        }

        return 'EMPLOYEE';
    }

    public static function renderIcon($icon, $size = '22')
    {
        $paths = implode('', $icon['paths']);
        return "
            <svg viewBox=\"{$icon['viewBox']}\" width=\"{$size}\" height=\"{$size}\" stroke=\"currentColor\"
                stroke-width=\"2.5\" fill=\"none\" stroke-linecap=\"round\" stroke-linejoin=\"round\"
                class=\"css-i6dzq1\" style=\"filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));\">
                {$paths}
            </svg>
        ";
    }

    public static function renderSubmenuIcon($icon, $size = '22')
    {
        $paths = implode('', $icon['paths']);
        return "
            <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$size}\" height=\"{$size}\"
                viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.5\"
                stroke-linecap=\"round\" stroke-linejoin=\"round\" class=\"feather {$icon['name']}\">
                {$paths}
            </svg>
        ";
    }
}
