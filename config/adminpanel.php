<?php
$smartMenuConfs = [
    'bannerCollorPallete' => [
        'bgColor' => 'Background',
        'bgHoverColor' => 'Background hover',
        'textColor' => 'Text',
        'textHoverColor' => 'Text hover',
        'gradientColor' => 'Gradient',
        'borderColor' => 'Border',
        'borderHoverColor' => 'Border hover',
    ],
    'clearCache' => 1, /// enable or disable cache clear
    'additionalFields' => [
        'teaser' => 1,
        'banner' => 0,
        'svg' => 0,
        'colors' => 1,
        'optionalTitle' => 0,
        'optionalTeaser' => 0,
        'coverImage' => 1,
        'optionalImage' => 1
    ],
    'contentSelectorTaxonomyFilterType' => 2, /// false: for old versions (default); 2: for newer versions
    'disableNotifications' => 1, /// disable main notifications
    'disableDrafts' => 1, /// disable draft functional
    'enableWatermark' => 0, /// enable or disable watermark over uploaded image
    'enableSmartImages' => 1,
    'menuImages' => ['cover'],
];
//
//$seoFields = [
//    'seo_title' => ['title' => 'Seo Title', 'type' => 'text', 'translate' => 1, 'required' => 0],
//    'seo_description' => ['title' => 'Seo Description', 'type' => 'text', 'translate' => 1, 'required' => 0],
//    'seo_keywords' => ['title' => 'Seo Keywords', 'type' => 'text', 'translate' => 1, 'required' => 0],
//    'seo_image' => ['title' => 'Seo Image', 'type' => 'image', 'translate' => 1, 'required' => 0, 'limit' => 1],
//
//];

$confs = [

    'editor_styles' => [
        [
            'name' => 'Background',
            'element' => 'p',
            'classes' => ['bg-gray']
        ]
    ],

    'log_urls' => [
        'sitemap/updMenu',
        'sitemap/deleteMenu',
        'words/updWord',
        'words/deleteWord',
        'Roles/Update',
        'Roles/DeleteData',
        'users/upduser',
        'options/setConfigurations',
        'main/updRedirections',
        'main/deleteRedirections',
        'options/updOption',
        'onlineforms/deleteform',
        'onlineforms/restoreform',
        'taxonomy/updTerm',
        'taxonomy/deleteTerm',
        'widgets/updWidget',
        'widgets/deleteWidget',
        'page/updPage',
        'page/setPageStatus',
        'page/deletePage',
        'options/updContentTypeSettings'
    ],

    /// menu config format:
    /// key as slug: title, role[admin], children[], type:menu|head,
    'admin_menu' => [
        'dashboard' => ['title' => 'Dashboard', 'icon' => 'fa-window', 'type' => 'menu'],

        'smartmenu' => ['title' => 'Smart Menus', 'icon' => 'fa-tag', 'type' => 'menu', 'conf' => $smartMenuConfs],
        'users' => [
            'title' => 'Manage Users',
            'icon' => 'fa-user',
            'type' => 'menu',
            'children' => [
                //            'user' =>['title' => 'Public users',  'icon'=>'fa-user', 'type' => 'menu' ],
                'roles' => ['title' => 'Roles And Permissions', 'icon' => 'fa-user', 'type' => 'menu'],
                'admins' => ['title' => 'Manage Admins users', 'icon' => 'fa-user', 'type' => 'menu'],
            ]
        ],
        /*** /
                'formBuilder' => [
                    'title' => 'Form builder',
                    'icon' => 'fa-list-alt',
                    'type' => 'menu',
                    'children' => [
                        'buildForm' => ['title' => 'Build Form', 'icon' => 'fa-info-circle', 'type' => 'menu'],
                    ]
                ],
        /***/
        'configurations' => [
            'title' => 'Configurations',
            'icon' => 'fa-cog',
            'type' => 'menu',
            'children' => [
                'configurations' => ['title' => 'Site Configurations', 'icon' => 'fa-cog', 'type' => 'menu'],
                'redirections' => ['title' => 'Redirections', 'icon' => 'fa-user', 'type' => 'menu'],
                //                'contentlog' => ['title' => 'Content Log Viewer', 'icon' => 'fa-user', 'type' => 'menu'],
                'cookies' => ['title' => 'Cookies', 'icon' => 'fa-user', 'type' => 'menu'],
                //                'accessibility' => ['title' => 'Accessibility', 'icon' => 'fa-user', 'type' => 'menu'],
            ]
        ],
/*** /
        'subscription' => [
            'module' => 'customModule',
            'modulePart' => 'subscribers',
            'title' => 'Subscription Control',
            'type' => 'menu',
            'icon' => 'fa-info-circle',
            'dropDown' => 'false',
            'externalChildren' => false,
            'children' => [
                'subscribers' => [
                    'title' => 'Subscribers List',
                    'type' => 'menu',
                    'icon' => 'fa-info-circle',
                    'dropDown' => 'false',
                ],
                'send_email' => [
                    'title' => 'Send Email',
                    'type' => 'menu',
                    'icon' => 'fa-info-circle',
                    'dropDown' => 'false',
                ],
                'subscription_configurations' => [
                    'title' => 'Configurations',
                    'type' => 'menu',
                    'icon' => 'fa-info-circle',
                    'dropDown' => 'false',
                ]
            ],
        ],
        /***/
        /*** /
                'google_analytics' => [
                    'title' => 'Google Analytics',
                    'icon' => 'fa-cogs',
                    'type' => 'menu',
                    'children' => [
                        'google_analytics' => ['title' => 'Home', 'icon' => 'fa-cog', 'type' => 'menu'],
                        'events' => ['title' => 'Events', 'icon' => 'fa-user', 'type' => 'menu'],
                        'traffic_acquisition' => ['title' => 'Traffic Acquisition', 'icon' => 'fa-user', 'type' => 'menu'],
                        'pages_and_screens' => ['title' => 'Pages and Screens', 'icon' => 'fa-user', 'type' => 'menu'],
                    ]
                ],
        /***/
        'stringTranslations' => ['title' => 'String Translations', 'icon' => 'fa-font', 'type' => 'menu'],
//        'onlineForms' => ['title' => 'Site Online Forms', 'icon' => 'fa-list-alt', 'type' => 'menu'],
        'taxonomy' => ['title' => 'Taxonomies', 'icon' => 'fa-window', 'type' => 'menu'],
        'widgets' => ['title' => 'Custom Widgets', 'icon' => 'fa-window', 'type' => 'menu'],

        /*** /
                'smartShop' => [
                'title' => 'Smart Shop',  'type'=>'menu', 'icon'=>'fa-info-circle', 'dropDown'=>'false', 'externalChildren' => false,
                'children' => [
                'ss_wallets' => ['title' => 'Wallets',  'type'=>'menu', 'icon'=>'fa-info-circle', 'dropDown'=>'false', ],
                'ss_orders' => ['title' => 'Orders',  'type'=>'menu', 'icon'=>'fa-info-circle', 'dropDown'=>'false', ],
                'ss_catalog' => ['title' => 'Products Catalog',  'type'=>'menu', 'icon'=>'fa-info-circle', 'dropDown'=>'false', 'taxonomy'=>['silk_locations']],
                //                'ss_stock' => ['title' => 'Products Stock',  'type'=>'menu', 'icon'=>'fa-info-circle', 'dropDown'=>'false', 'taxonomy'=>['chanel_categories','chanel_thematic_packages'] ],
                'ss_attributes' => ['title' => 'Attributes',  'type'=>'menu', 'icon'=>'fa-info-circle', 'dropDown'=>'false', ],
                //                'ss_shipping' => ['title' => 'Shipping',  'type'=>'menu', 'icon'=>'fa-info-circle', 'dropDown'=>'false', ],
                'ss_payment' => ['title' => 'Payments',  'type'=>'menu', 'icon'=>'fa-info-circle', 'dropDown'=>'false', ],
                'ss_offers' => ['title' => 'Offers',  'type'=>'menu', 'icon'=>'fa-info-circle', 'dropDown'=>'false', ],
                'ss_locations' => ['title' => 'Locations',  'type'=>'menu', 'icon'=>'fa-info-circle', 'dropDown'=>'false', ],
                'ss_settings' => ['title' => 'Settings',  'type'=>'menu', 'icon'=>'fa-info-circle', 'dropDown'=>'false', ],
                ]
                ],
        /***/
        'content' => [
            'title' => 'Content',
            'type' => 'menu',
            'icon' => 'fa-info-circle',
            'dropDown' => 'false',
            'externalChildren' => 'contentTypes',
            'children' => []
        ],

    ],

    'site_menus' => [
        'main'      => [],  /// Header nav: Home, About Us, Services, Branches, Blog, FAQ, Contacts, Corporate Offers
        'footer'    => [],  /// Footer site links column
    ],

    'cookies' => [
        'types' => [
            'strictly-necessary' => ['title' => 'Strictly Necessary', 'acceptable' => 0, 'type' => 'editor',],
            'preferences' => ['acceptable' => 1, 'type' => 'editor'],
            'targeting' => ['acceptable' => 1, 'type' => 'editor'],
            'functionality' => ['acceptable' => 1, 'type' => 'editor'],
            'unclassified' => ['acceptable' => 1, 'type' => 'editor'],
        ],
        'fields' => [
            'title' => ['type' => 'text', 'title' => 'Title', 'translate' => 1,],
            'teaser' => ['type' => 'editor', 'title' => 'Teaser', 'translate' => 1,],
            'description' => ['type' => 'editor', 'title' => 'Description', 'translate' => 1,],
        ]

    ],

    'accessibility' => [
        'fields' => [
            'contrast' => [
                'description' => ['type' => 'text', 'title' => 'Description', 'translate' => 1],
                'status' => ['type' => 'checkbox', 'translate' => 0, 'title' => 'Status', 'values' => ['enable' => 'Enable', 'enable_mobile' => 'View on mobile', 'en' => 'View En', 'ge' => 'View Ge'], 'showOnAdminList' => 0],
                'tooltip' => ['type' => 'tooltip', 'title' => 'Tooltip', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 1, 'editableTitle' => 1, 'values' => ['title' => 'Title', 'logo' => 'Logo', 'label' => 'Label']],
            ],
            'text_size' => [
                'description' => ['type' => 'text', 'title' => 'Description', 'translate' => 1],
                'status' => ['type' => 'checkbox', 'translate' => 0, 'title' => 'Status', 'values' => ['enable' => 'Enable', 'enable_mobile' => 'View on mobile', 'en' => 'View En', 'ge' => 'View Ge'], 'showOnAdminList' => 0],
                'tooltip' => ['type' => 'tooltip', 'title' => 'Tooltip', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 1, 'editableTitle' => 1, 'values' => ['title' => 'Title', 'logo' => 'Logo', 'label' => 'Label']],
            ],
            'text_bold' => [
                'description' => ['type' => 'text', 'title' => 'Description', 'translate' => 1],
                'status' => ['type' => 'checkbox', 'translate' => 0, 'title' => 'Status', 'values' => ['enable' => 'Enable', 'enable_mobile' => 'View on mobile', 'en' => 'View En', 'ge' => 'View Ge'], 'showOnAdminList' => 0],
                'tooltip' => ['type' => 'tooltip', 'title' => 'Tooltip', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 1, 'editableTitle' => 1, 'values' => ['title' => 'Title', 'logo' => 'Logo', 'label' => 'Label']],
            ],
            'text_spacing' => [
                'description' => ['type' => 'text', 'title' => 'Description', 'translate' => 1],
                'status' => ['type' => 'checkbox', 'translate' => 0, 'title' => 'Status', 'values' => ['enable' => 'Enable', 'enable_mobile' => 'View on mobile', 'en' => 'View En', 'ge' => 'View Ge'], 'showOnAdminList' => 0],
                'tooltip' => ['type' => 'tooltip', 'title' => 'Tooltip', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 1, 'editableTitle' => 1, 'values' => ['title' => 'Title', 'logo' => 'Logo', 'label' => 'Label']],
            ],
            'line_height' => [
                'description' => ['type' => 'text', 'title' => 'Description', 'translate' => 1],
                'status' => ['type' => 'checkbox', 'translate' => 0, 'title' => 'Status', 'values' => ['enable' => 'Enable', 'enable_mobile' => 'View on mobile', 'en' => 'View En', 'ge' => 'View Ge'], 'showOnAdminList' => 0],
                'tooltip' => ['type' => 'tooltip', 'title' => 'Tooltip', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 1, 'editableTitle' => 1, 'values' => ['title' => 'Title', 'logo' => 'Logo', 'label' => 'Label']],
            ],
            'hide_image' => [
                'description' => ['type' => 'text', 'title' => 'Description', 'translate' => 1],
                'status' => ['type' => 'checkbox', 'translate' => 0, 'title' => 'Status', 'values' => ['enable' => 'Enable', 'enable_mobile' => 'View on mobile', 'en' => 'View En', 'ge' => 'View Ge'], 'showOnAdminList' => 0],
                'tooltip' => ['type' => 'tooltip', 'title' => 'Tooltip', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 1, 'editableTitle' => 1, 'values' => ['title' => 'Title', 'logo' => 'Logo', 'label' => 'Label']],
            ],
            'cursor' => [
                'description' => ['type' => 'text', 'title' => 'Description', 'translate' => 1],
                'status' => ['type' => 'checkbox', 'translate' => 0, 'title' => 'Status', 'values' => ['enable' => 'Enable', 'enable_mobile' => 'View on mobile', 'en' => 'View En', 'ge' => 'View Ge'], 'showOnAdminList' => 0],
                'tooltip' => ['type' => 'tooltip', 'title' => 'Tooltip', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 1, 'editableTitle' => 1, 'values' => ['title' => 'Title', 'logo' => 'Logo', 'label' => 'Label']],
            ],
            'saturation' => [
                'description' => ['type' => 'text', 'title' => 'Description', 'translate' => 1],
                'status' => ['type' => 'checkbox', 'translate' => 0, 'title' => 'Status', 'values' => ['enable' => 'Enable', 'enable_mobile' => 'View on mobile', 'en' => 'View En', 'ge' => 'View Ge'], 'showOnAdminList' => 0],
                'tooltip' => ['type' => 'tooltip', 'title' => 'Tooltip', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 1, 'editableTitle' => 1, 'values' => ['title' => 'Title', 'logo' => 'Logo', 'label' => 'Label']],
            ],
            'tooltips' => [
                'description' => ['type' => 'text', 'title' => 'Description', 'translate' => 1],
                'status' => ['type' => 'checkbox', 'translate' => 0, 'title' => 'Status', 'values' => ['enable' => 'Enable', 'enable_mobile' => 'View on mobile', 'en' => 'View En', 'ge' => 'View Ge'], 'showOnAdminList' => 0],
                'tooltip' => ['type' => 'tooltip', 'title' => 'Tooltip', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 1, 'editableTitle' => 1, 'values' => ['title' => 'Title', 'logo' => 'Logo', 'label' => 'Label']],
            ],
            'dyslexia' => [
                'description' => ['type' => 'text', 'title' => 'Description', 'translate' => 1],
                'status' => ['type' => 'checkbox', 'translate' => 0, 'title' => 'Status', 'values' => ['enable' => 'Enable', 'enable_mobile' => 'View on mobile', 'en' => 'View En', 'ge' => 'View Ge'], 'showOnAdminList' => 0],
                'tooltip' => ['type' => 'tooltip', 'title' => 'Tooltip', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 1, 'editableTitle' => 1, 'values' => ['title' => 'Title', 'logo' => 'Logo', 'label' => 'Label']],
            ],
            'dictionary' => [

                'description' => ['type' => 'text', 'title' => 'Description', 'translate' => 1],
                'status' => ['type' => 'checkbox', 'translate' => 0, 'title' => 'Status', 'values' => ['enable' => 'Enable', 'enable_mobile' => 'View on mobile', 'en' => 'View En', 'ge' => 'View Ge'], 'showOnAdminList' => 0],
                'tooltip' => ['type' => 'tooltip', 'title' => 'Tooltip', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 1, 'editableTitle' => 1, 'values' => ['title' => 'Title', 'logo' => 'Logo', 'label' => 'Label']],
            ],
            'screen_reader' => [
                'description' => ['type' => 'text', 'title' => 'Description', 'translate' => 1],
                'status' => ['type' => 'checkbox', 'translate' => 0, 'title' => 'Status', 'values' => ['enable' => 'Enable', 'enable_mobile' => 'View on mobile', 'en' => 'View En', 'ge' => 'View Ge'], 'showOnAdminList' => 0],
                'tooltip' => ['type' => 'tooltip', 'title' => 'Tooltip', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 1, 'editableTitle' => 1, 'values' => ['title' => 'Title', 'logo' => 'Logo', 'label' => 'Label']],
                'gender' => ['type' => 'tooltip', 'title' => 'Gender', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 1, 'editableTitle' => 1, 'values' => ['title' => 'Title', 'label' => 'Label']],
            ],
        ],

    ],



    /// online form aggregator settings /// aggregator class do some custom actions to specific form data (f.e. )
    /// 'online form slug'=>['aggregatorClass'=>FormAggregators/'classname', 'perPage'=>50,  ]
    'onlineForms' => [
        'contact-form' => ['perPage' => 50, 'disableSave' => false, 'toMails' => ''],
        'subscribeForm' => ['perPage' => 50, 'disableSave' => false, 'validate' => ['unique' => 'email']],
    ],

    //////////////////////////////////
    /// content type settings
    //    'content_seo_fields' => $seoFields,
    'content_seo_fields' => [
        'title' => ['title' => 'Seo Title', 'type' => 'text', 'translate' => 1, 'required' => 0],
        'description' => ['title' => 'Seo Description', 'type' => 'text', 'translate' => 1, 'required' => 0],
        'keywords' => ['title' => 'Seo Keywords', 'type' => 'text', 'translate' => 1, 'required' => 0],
        'image' => ['title' => 'Seo Image', 'type' => 'image', 'translate' => 1, 'required' => 0, 'limit' => 1],
        'scripts' => ['title' => 'Seo scripts', 'type' => 'textarea', 'translate' => 1, 'required' => 0, 'limit' => 1],
    ],
    'use_for_slug_fields' => ['slug', 'name', 'title'],
    'website_menu_custom_configs' => [
        'hide-from-menu',
        'hide-from-burger',
        'has-redirect-icon',
        'has-navigation-links',
        'has-navigation-scroll',
        'hide-background',
        'is-button',
    ],

    /** @todo add attributes support /// f.e. ['step'=> "any", 'title'=>"any" ] ...
    awailable field types: text, number, textarea, select, editor, table, statictable, smartbuttons, dinamictable, smartTable, tabs, smarttabs, pins, calendar, color, media, file, checkbox
     * select: => [ 'type'=>'select', 'translate'=> 0, 'required'=>0, 'showOnAdminList' => 0, 'predefinedVar'=>'indx.operators', 'limit'=>15, 'keyField'=>'id', 'valueField'=>'title', 'keyAsValue'=>1],
    multifield field types: ['text', 'textarea','number', 'editor', 'images', 'file', 'tooltip', 'smartbuttons', 'url', 'calendar', 'innerSmartComponent'],
    /// relations sample
    'relation_services' => ['type' => 'select', 'related_content_type'=>'services', 'predefinedVar'=>'cache.pageTitles_services', 'keyField'=>'id', 'valueField'=>'title', 'title' => 'Services', 'required' => 0],

     */
    //// content page slug is generated automaticaly;
    ///  if content_types has 'slug_field' param uses that field for slug;
    ///  else uses predefined fields [slug, name, title]
    ///  by default uses 'en' version
    'content_types' => [
        'page' => [
            'title' => 'Pages',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 1,
            'taxonomy' => [],
            'fields' => [
                'title' => ['title' => 'page title', 'type' => 'text', 'translate' => 1, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                //                'content' => [
                //                    'title' => 'Content',
                //                    'type' => 'multifield',
                //                    'translate' => 1,
                //                    'required' => 0,
                //                    'showOnAdminList' => 0,
                //                    'useForSeo' => 0,
                //                    'layout' => ['slider-static-image', 'slider-static-url'],
                //                    'fieldTypes' => ['editor', 'images', 'banner', 'video', 'url', 'tooltip', 'table'],
                //                ],
                'content' => [
                    'title' => 'Content',
                    'type' => 'multifield2',
                    'translate' => 1,
                    'required' => 0,
                    'showOnAdminList' => 0,
                    'useForSeo' => 0,
                    'childs' => [
                        'title' => ['type' => 'text', 'title' => 'Title', 'translate' => 1, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1, 'editableTitle' => 1, 'confs' => ['right']],
                        'editor' => ['type' => 'editor', 'title' => 'Editor', 'translate' => 1, 'required' => 0, 'editableTitle' => 1, 'confs' => ['right', 'left']],
                        'images' => ['type' => 'media', 'title' => 'Images', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 1, 'editableTitle' => 1, 'confs' => ['slider-static-image', 'cover-image', 'mobile-image'], 'seoFields' => ['title', 'url']],
                        'video' => ['type' => 'text', 'title' => 'Video', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 1, 'editableTitle' => 1],
                        'textarea' => ['type' => 'textarea', 'title' => 'Textarea', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 1, 'editableTitle' => 1, 'confs' => ['right']],
                        'file' => ['type' => 'file', 'title' => 'File', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 1, 'editableTitle' => 1, 'size' => 50, 'confs' => ['right']],
                        'link' => ['type' => 'url', 'title' => 'Url', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 1, 'editableTitle' => 1, 'confs' => ['slider-static-url'], 'values' => ['teaser']],
                        'tooltip' => ['type' => 'tooltip', 'title' => 'Tooltip', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 1, 'editableTitle' => 1, 'values' => ['value' => 'Value', 'value2' => 'Value2', 'value3' => 'Value3']],
                        'table' => [
                            'title' => 'Table',
                            'type' => 'table',
                            'translate' => 0,
                            'disable' => ['tableTitle' => 1, 'tableTeaser' => 1],
                            'required' => 0,
                            'showOnAdminList' => 0,
                            'useForSeo' => 0,
                            'editableTitle' => 1
                        ],
                    ],

                ],

            ]
        ],
        'banner' => [
            'title' => 'Banners',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 1,
            'taxonomy' => [],
            'fields' => [
                'title' => ['title' => 'Title', 'type' => 'text', 'translate' => 1, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'teaser' => ['title' => 'Teaser', 'type' => 'editor', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0],
                'url' => ['title' => 'URL', 'type' => 'url', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0],
                'image' => ['title' => 'Image', 'type' => 'image', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'limit' => 1, 'size' => 5],
                'video' => ['title' => 'Video', 'type' => 'video', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0],
                'portfolio' => [
                    'title' => 'Portfolio',
                    'type' => 'multifield2',
                    'translate' => 1,
                    'required' => 0,
                    'showOnAdminList' => 0,
                    'useForSeo' => 0,
                    'childs' => [
                        'tooltip' => ['type' => 'tooltip', 'title' => 'Tooltip', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 1, 'values' => ['number' => 'number', 'percent' => 'percent', 'value' => 'value'], 'editableTitle' => 1, 'confs' => ['unit', 'percent', 'unit_mm', 'unit_bn']],
                        'image' => ['type' => 'image', 'title' => 'Image', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'limit' => 1, 'size' => 5],
                    ],

                ],
            ]
        ],
        // ── GeoCar content types ─────────────────────────────────────────────

        /// Hero carousel slides (HeroCarousel component)
        /// Re-uses existing 'banner' type — add items with image + optional url.
        /// Listed here as reference only; no separate type needed.

        /// Services page + ServicesSection on home
        'service' => [
            'title' => 'Services',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 1,
            'taxonomy' => ['service_category'],
            'fields' => [
                'title'       => ['title' => 'Title',             'type' => 'text',     'translate' => 1, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'teaser'      => ['title' => 'Short Description', 'type' => 'textarea', 'translate' => 1, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'image'       => ['title' => 'Image',             'type' => 'image',    'translate' => 0, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 0, 'limit' => 1, 'size' => 5],
                'is_featured' => ['title' => 'Featured (large card on home)', 'type' => 'checkbox', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0, 'values' => ['1' => 'Yes']],
                'content'     => [
                    'title' => 'Full Description', 'type' => 'multifield2', 'translate' => 1,
                    'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0,
                    'childs' => [
                        'editor' => ['type' => 'editor', 'title' => 'Editor', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'editableTitle' => 1],
                        'image'  => ['type' => 'image',  'title' => 'Image',  'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'size' => 5, 'editableTitle' => 1],
                    ],
                ],
            ],
        ],

        /// Blog list + single post
        'blog' => [
            'title' => 'Blog',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 1,
            'taxonomy' => ['blog_category'],
            'fields' => [
                'title'  => ['title' => 'Title',       'type' => 'text',     'translate' => 1, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'teaser' => ['title' => 'Teaser',      'type' => 'textarea', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'image'  => ['title' => 'Cover Image', 'type' => 'image',    'translate' => 0, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0, 'limit' => 1, 'size' => 5],
                'author' => ['title' => 'Author',      'type' => 'text',     'translate' => 0, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0],
                'content' => [
                    'title' => 'Content', 'type' => 'multifield2', 'translate' => 1,
                    'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0,
                    'childs' => [
                        'editor'        => ['type' => 'editor', 'title' => 'Editor',        'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'editableTitle' => 1],
                        'image'         => ['type' => 'image',  'title' => 'Image',         'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'size' => 5, 'editableTitle' => 1],
                        'youtube_video' => ['type' => 'url',    'title' => 'YouTube Video', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'editableTitle' => 1],
                    ],
                ],
            ],
        ],

        /// FAQ page — accordion items grouped by faq_category taxonomy tabs
        'faq' => [
            'title' => 'FAQ',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 1,
            'taxonomy' => ['faq_category'],
            'fields' => [
                'title'   => ['title' => 'Question', 'type' => 'text',   'translate' => 1, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'content' => ['title' => 'Answer',   'type' => 'editor', 'translate' => 1, 'required' => 1, 'showOnAdminList' => 0, 'useForSeo' => 0],
            ],
        ],

        /// Branches page — map pins + branch detail cards
        /// address, week hours, saturday hours, lat/lng for Google Maps embed
        'branch' => [
            'title' => 'Branches',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 0,
            'taxonomy' => ['branch_city'],
            'fields' => [
                'title'              => ['title' => 'Branch Name',        'type' => 'text',  'translate' => 1, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 0],
                'address'            => ['title' => 'Address',            'type' => 'text',  'translate' => 1, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 0],
                'working_hours_week' => ['title' => 'Working Hours (Week)', 'type' => 'text','translate' => 1, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0],
                'working_hours_sat'  => ['title' => 'Working Hours (Saturday)', 'type' => 'text', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0],
                'lat'                => ['title' => 'Latitude',           'type' => 'text',  'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0],
                'lng'                => ['title' => 'Longitude',          'type' => 'text',  'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0],
                'image'              => ['title' => 'Branch Photo',       'type' => 'image', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'limit' => 1, 'size' => 5],
            ],
        ],

        /// Partners section — logo grid (PartnersSection component)
        'partner' => [
            'title' => 'Partners',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 0,
            'taxonomy' => [],
            'fields' => [
                'title' => ['title' => 'Company Name', 'type' => 'text',  'translate' => 0, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 0],
                'image' => ['title' => 'Logo',         'type' => 'image', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 0, 'limit' => 1, 'size' => 2],
                'url'   => ['title' => 'Website URL',  'type' => 'url',   'translate' => 0, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0],
            ],
        ],

        /// "Quick and Convenient" feature list (QuickConvenient component)
        /// icon + title + description; is_highlighted controls blue title colour
        'app_feature' => [
            'title' => 'App Features',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 0,
            'taxonomy' => [],
            'fields' => [
                'title'          => ['title' => 'Title',       'type' => 'text',  'translate' => 1, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 0],
                'teaser'         => ['title' => 'Description', 'type' => 'textarea', 'translate' => 1, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 0],
                'icon'           => ['title' => 'Icon (SVG)',  'type' => 'image', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0, 'limit' => 1, 'size' => 1],
                'is_highlighted' => ['title' => 'Highlighted (blue title + border)', 'type' => 'checkbox', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0, 'values' => ['1' => 'Yes']],
            ],
        ],

        /// "Premium Features of Our App" cards (PremiumFeatures component)
        /// photo + title + description + CTA link
        'premium_feature' => [
            'title' => 'Premium Features',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 0,
            'taxonomy' => [],
            'fields' => [
                'title'     => ['title' => 'Title',           'type' => 'text',     'translate' => 1, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 0],
                'teaser'    => ['title' => 'Description',     'type' => 'textarea', 'translate' => 1, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 0],
                'image'     => ['title' => 'Card Image',      'type' => 'image',    'translate' => 0, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 0, 'limit' => 1, 'size' => 5],
                'cta_label' => ['title' => 'CTA Button Label','type' => 'text',     'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0],
                'cta_url'   => ['title' => 'CTA Button URL',  'type' => 'url',      'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0],
            ],
        ],

        /// Corporate Offers — highlighted nav link, dedicated page
        'corporate_offer' => [
            'title' => 'Corporate Offers',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 1,
            'taxonomy' => ['offer_category'],
            'fields' => [
                'title'     => ['title' => 'Title',             'type' => 'text',     'translate' => 1, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'teaser'    => ['title' => 'Short Description', 'type' => 'textarea', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'image'     => ['title' => 'Image',             'type' => 'image',    'translate' => 0, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0, 'limit' => 1, 'size' => 5],
                'valid_from'=> ['title' => 'Valid From (date)', 'type' => 'text',     'translate' => 0, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0],
                'valid_to'  => ['title' => 'Valid To (date)',   'type' => 'text',     'translate' => 0, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0],
                'cta_label' => ['title' => 'CTA Button Label',  'type' => 'text',     'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0],
                'cta_url'   => ['title' => 'CTA Button URL',    'type' => 'url',      'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0],
                'content'   => [
                    'title' => 'Content', 'type' => 'multifield2', 'translate' => 1,
                    'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0,
                    'childs' => [
                        'editor' => ['type' => 'editor', 'title' => 'Editor', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'editableTitle' => 1],
                        'image'  => ['type' => 'image',  'title' => 'Image',  'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'size' => 5, 'editableTitle' => 1],
                        'file'   => ['type' => 'file',   'title' => 'File',   'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'size' => 20, 'editableTitle' => 1],
                    ],
                ],
            ],
        ],
    ],

    /// taxonomies
    'taxonomy' => [

        // ── GeoCar taxonomies ────────────────────────────────────────────────

        /// Blog category tabs (blog list page + BlogsSection home)
        'blog_category' => [
            'select' => 'multy', /// posts can belong to multiple categories
            'fields' => [
                'title' => ['type' => 'text', 'translate' => 1, 'required' => 1, 'showOnAdminList' => 1],
            ],
        ],

        /// FAQ category tabs (faq page)
        'faq_category' => [
            'select' => 'single',
            'fields' => [
                'title' => ['type' => 'text', 'translate' => 1, 'required' => 1, 'showOnAdminList' => 1],
            ],
        ],

        /// Branch city filter (branches page)
        'branch_city' => [
            'select' => 'single',
            'fields' => [
                'title' => ['type' => 'text', 'translate' => 1, 'required' => 1, 'showOnAdminList' => 1],
            ],
        ],

        /// Service category (services page)
        'service_category' => [
            'select' => 'single',
            'fields' => [
                'title' => ['type' => 'text', 'translate' => 1, 'required' => 1, 'showOnAdminList' => 1],
            ],
        ],

        /// Corporate offer category
        'offer_category' => [
            'select' => 'single',
            'fields' => [
                'title' => ['type' => 'text', 'translate' => 1, 'required' => 1, 'showOnAdminList' => 1],
            ],
        ],

    ],

    /// smart layout templates
    /// next gen layouts
    /// old templates should be removed after beta release
    'smartLayouts' => [
        'Default' => ['conf' => [], 'list_view' => 1, 'single_view' => 1,],
        'Other' => ['conf' => [], 'list_view' => 1, 'single_view' => 1,],
    ],

    ///[ 'title'=>'user friendly title', 'content_types'=>['page',....], 'type'=>'content|product', ],

    'smartComponents' => [
        // ── GeoCar smart components ──────────────────────────────────────────

        /// Home page sections
        'HeroCarousel'    => ['title' => 'Hero Carousel',         'content_types' => ['banner'],          'type' => 'content', 'conf' => []],
        'AboutSection'    => ['title' => 'About Section',         'content_types' => ['page'],            'type' => 'content', 'conf' => []],
        'ServicesSection' => ['title' => 'Services Section',      'content_types' => ['service'],         'type' => 'content', 'conf' => ['show-all-link']],
        'DownloadAppBanner' => ['title' => 'Download App Banner', 'content_types' => ['banner'],          'type' => 'content', 'conf' => []],
        'QuickConvenient' => ['title' => 'Quick & Convenient',    'content_types' => ['app_feature'],     'type' => 'content', 'conf' => []],
        'PremiumFeatures' => ['title' => 'Premium Features',      'content_types' => ['premium_feature'], 'type' => 'content', 'conf' => []],
        'PartnersSection' => ['title' => 'Partners Section',      'content_types' => ['partner'],         'type' => 'content', 'conf' => []],
        'BlogsSection'    => ['title' => 'Blogs Section (home)',  'content_types' => ['blog'],            'type' => 'content', 'conf' => []],

        /// Blog page / single
        'BlogList'  => ['title' => 'Blog List',        'content_types' => ['blog'], 'type' => 'content', 'conf' => []],
        'BlogInner' => ['title' => 'Blog Single Post', 'content_types' => ['blog'], 'type' => 'content', 'conf' => []],

        /// FAQ page
        'FaqList' => ['title' => 'FAQ List', 'content_types' => ['faq'], 'type' => 'content', 'conf' => []],

        /// Branches page
        'BranchList' => ['title' => 'Branch List + Map', 'content_types' => ['branch'], 'type' => 'content', 'conf' => []],

        /// Corporate Offers page
        'CorporateOffers'      => ['title' => 'Corporate Offers List',   'content_types' => ['corporate_offer'], 'type' => 'content', 'conf' => []],
        'CorporateOfferInner'  => ['title' => 'Corporate Offer Single',  'content_types' => ['corporate_offer'], 'type' => 'content', 'conf' => []],

    ],

];

/**
Key: kMMxwRT96sNfuH55KiPrqMCW8KUq4hAD
Secret: aAzyRzuXxxpsnRZA
 */

foreach ($confs['content_types'] as $key => $value) {
    //    $confs['content_types'][$key]['fields'] = array_merge($value['fields'], $seoFields);
    $confs['content_types'][$key]['fields']['seo'] = ['title' => 'seo', 'translate' => 1,];
    $confs['content_types'][$key]['fields']['singlePageRoute'] = ['type' => 'hidden', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0];
}

return $confs;
