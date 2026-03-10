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
        'main' => [],
        'footer' => [],
        //        'footer_large' => [],
        //        'investors' => [],
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
        //        'contact-form' => ['function' => 'contactForm', 'perPage' => 50, 'toMails' => "", 'disableSave' => true,],
        //        'contact-form-contact-us' => ['function' => 'contactForm', 'perPage' => 50, 'toMails' => "", 'disableSave' => true,],
        'subscribeForm' => ['perPage' => 50, 'disableSave' => false, 'validate' => ['unique' => 'email']],
        //    'sendVacancy' => ['function' => 'sendVacancy', 'perPage' => 50, 'disableSave' => true,],
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
        'news' => [
            'title' => 'News',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 1,
            'taxonomy' => ['timeline_year'],
            'fields' => [
                'title' => ['title' => 'Title', 'type' => 'text', 'translate' => 1, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'teaser' => ['title' => 'Teaser', 'type' => 'textarea', 'translate' => 1, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'image' => ['title' => 'Image', 'type' => 'image', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'limit' => 1, 'size' => 5],
                'banner_image' => ['title' => 'Banner Image', 'type' => 'image', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'limit' => 1, 'size' => 5],
                'content' => [
                    'title' => 'Content',
                    'type' => 'multifield2',
                    'translate' => 0,
                    'required' => 0,
                    'showOnAdminList' => 0,
                    'useForSeo' => 0,
                    'childs' => [
                        'editor' => ['type' => 'editor', 'title' => 'Editor', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'editableTitle' => 1],
                        'image' => ['type' => 'image', 'title' => 'Image', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'size' => 5, 'editableTitle' => 1],
                        'file' => ['type' => 'file', 'title' => 'File', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'limit' => 1, 'size' => 10, 'editableTitle' => 1],
                        'youtube_video' => ['type' => 'url', 'title' => 'YouTube Video', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'editableTitle' => 1],
                    ],

                ]
            ]
        ],

        'credit_ratings' => [
            'title' => 'Credit Ratings',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 0,
            'taxonomy' => [],
            'fields' => [
                'title' => ['title' => 'Title', 'type' => 'text', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'content' => [
                    'title' => 'Content',
                    'type' => 'multifield2',
                    'translate' => 0,
                    'required' => 0,
                    'showOnAdminList' => 1,
                    'useForSeo' => 0,
                    'childs' => [
                        'text' => ['type' => 'text', 'title' => 'Text', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'editableTitle' => 1],
                        'image' => ['type' => 'image', 'title' => 'Image', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'size' => 5, 'editableTitle' => 1],
                        'file' => ['type' => 'file', 'title' => 'File', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'limit' => 1, 'size' => 20, 'editableTitle' => 1]
                    ],

                ]
            ]
        ],

        'investment_faq' => [
            'title' => 'Investment FAQ',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 1,
            'taxonomy' => [],
            'fields' => [
                'title' => ['title' => 'Title', 'type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'content' => [
                    'title' => 'Content',
                    'type' => 'multifield2',
                    'translate' => 0,
                    'required' => 0,
                    'showOnAdminList' => 0,
                    'useForSeo' => 0,
                    'childs' => [
                        'editor' => ['type' => 'editor', 'title' => 'Editor', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0]
                    ],

                ]
            ]
        ],

        'investment_strategy' => [
            'title' => 'Investment Strategy',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 1,
            'taxonomy' => [],
            'fields' => [
                'title' => ['title' => 'Title', 'type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'content' => [
                    'title' => 'Content',
                    'type' => 'multifield2',
                    'translate' => 0,
                    'required' => 0,
                    'showOnAdminList' => 0,
                    'useForSeo' => 0,
                    'childs' => [
                        'editor' => ['type' => 'editor', 'title' => 'Editor', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0],
                        'image' => ['type' => 'image', 'title' => 'Image', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'size' => 5],
                        'text' => ['type' => 'text', 'title' => 'Text', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0]
                    ],
                ]
            ]
        ],

        'shareholder_meetings' => [
            'title' => 'Shareholder Meetings',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 1,
            'taxonomy' => ['timeline_year'],
            'fields' => [
                'title' => ['title' => 'Title', 'type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'content' => [
                    'title' => 'Content',
                    'type' => 'multifield2',
                    'translate' => 0,
                    'required' => 0,
                    'showOnAdminList' => 0,
                    'useForSeo' => 0,
                    'childs' => [
                        'editor' => ['type' => 'editor', 'title' => 'Editor', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0],
                        'file' => ['type' => 'file', 'title' => 'File', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'limit' => 20, 'size' => 20]
                    ],
                ]
            ]
        ],

        'portfolio_company' => [
            'title' => 'Portfolio Company',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 1,
            'taxonomy' => ['portfolio_category'],
            'fields' => [
                'title' => ['title' => 'Title', 'type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'image' => ['title' => 'Image', 'type' => 'image', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0, 'limit' => 1, 'size' => 5],
                'company_website' => ['title' => 'Company Website', 'type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'company_status' => ['title' => 'Status', 'type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'sector' => ['title' => 'Sector', 'type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'year_of_investment' => ['title' => 'Year of Investment', 'type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                //                'tooltip' => ['type' => 'tooltip', 'title' => 'Tooltip', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'editableTitle' => 1, 'values' => ['title' => 'Title', 'label' => 'Label']],
                'content' => ['type' => 'tooltip', 'title' => 'Content', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'editableTitle' => 1, 'values' => ['title' => 'Title', 'label' => 'Label', 'value' => 'Value']],
                'editor' => ['title' => 'Editor', 'type' => 'editor', 'translate' => 1, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0]
            ]
        ],

        'governance' => [
            'title' => 'Governance',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 1,
            'taxonomy' => [],
            'fields' => [
                'title' => ['title' => 'Full Name', 'type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'teaser' => ['title' => 'Position', 'type' => 'text', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0],
                'image' => ['title' => 'Image', 'type' => 'image', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'limit' => 1, 'size' => 5],
                'content' => ['title' => 'Content', 'type' => 'editor', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0],
                'linkedin' => ['title' => 'LinkedIn', 'type' => 'text', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0],
                'soc_x' => ['title' => 'Soc. X', 'type' => 'text', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0],
                'mail' => ['title' => 'Email', 'type' => 'text', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0],
            ]
        ],

        'history' => [
            'title' => 'History',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 1,
            'taxonomy' => ['history_category', 'history_year'],
            'fields' => [
                'title' => ['title' => 'Title', 'type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'content' => ['title' => 'Content', 'type' => 'editor', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0],
                'image' => ['title' => 'Image', 'type' => 'image', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0, 'limit' => 1, 'size' => 5],
            ]
        ],

        'annual_reports' => [
            'title' => 'Annual Reports',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 1,
            'taxonomy' => ['timeline_year'],
            'fields' => [
                'title' => ['title' => 'Title', 'type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'image' => ['title' => 'Image', 'type' => 'image', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0, 'limit' => 1, 'size' => 5],
                'pdf_files' => ['title' => 'PDF Files', 'type' => 'file', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0, 'size' => 50],
                'xhtml_files' => ['title' => 'XHTML Files', 'type' => 'file', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0, 'size' => 50],
            ]
        ],

        'investor_day_presentations' => [
            'title' => 'Investor Day Presentations',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 1,
            'taxonomy' => ['timeline_year'],
            'fields' => [
                'title' => ['title' => 'Title', 'type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'file' => ['title' => 'File', 'type' => 'file', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'size' => 50, 'limit' => 1]
            ]
        ],

        'financial_statements' => [
            'title' => 'Financial Statements',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 1,
            'taxonomy' => ['timeline_year'],
            'fields' => [
                'title' => ['title' => 'Title', 'type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'period_type' => ['title' => 'Period Type', 'type' => 'text', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 1, 'useForSeo' => 0],
                'file' => ['title' => 'File', 'type' => 'file', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'size' => 50, 'limit' => 1]
            ]
        ],

        'financial_results' => [
            'title' => 'Financial Results',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 1,
            'taxonomy' => ['timeline_year', 'financial_results_category'],
            'fields' => [
                'title' => ['title' => 'Title', 'type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'quarter' => ['type' => 'singleSelect', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 0, 'values' => ['q1' => 1, 'q2' => 2, 'q3' => 3, 'q4' => 4], 'keyAsValue' => 1],
                'file' => ['title' => 'File', 'type' => 'file', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'size' => 50, 'limit' => 1]
            ]
        ],

        'view_reports' => [
            'title' => 'View Reports',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 1,
            'taxonomy' => [],
            'fields' => [
                'title' => ['title' => 'Title', 'type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'file' => ['title' => 'File', 'type' => 'file', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'size' => 50, 'limit' => 1]
            ]
        ],

        'share_trading' => [
            'title' => 'Share Trading',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 1,
            'taxonomy' => [],
            'fields' => [
                'title' => ['title' => 'Title', 'type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'stats_by_year' => [
                    'title' => 'Stats By Year',
                    'type' => 'table',
                    'translate' => 0,
                    'required' => 0,
                    'showOnAdminList' => 0,
                    'useForSeo' => 0,
                    'fields' => [
                        'lse_monthly_adtv_shares' => ['type' => 'text', 'title' => 'LSE monthly adtv* (Shares)'],
                        'otc_monthly_adtv_shares' => ['type' => 'text', 'title' => 'otc** monthly adtv* (Shares)'],
                        'total_shares' => ['type' => 'text', 'title' => 'Total (Shares)'],
                        'lse_monthly_adtv' => ['type' => 'text', 'title' => 'LSE monthly adtv* (gbp ‘000)'],
                        'otc_monthly_adtv' => ['type' => 'text', 'title' => 'otc** monthly adtv* (gbp ‘000)'],
                        'total' => ['type' => 'text', 'title' => 'total (gbp ‘000)'],
                    ]
                ],
            ]
        ],

        'navHighlights' => [
            'title' => 'NAV Highlights',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 1,
            'taxonomy' => [],
            'fields' => [
                'title' => ['title' => 'Title', 'type' => 'text', 'translate' => 1, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],
                'change' => ['title' => 'Change', 'type' => 'singleSelect', 'translate' => 0, 'required' => 0, 'useForSeo' => 0, 'values' => ['rise' => 'rise', 'fall' => 'fall']],
                'navHighlights' => [
                    'title' => 'NAV Highlights',
                    'type' => 'multifield2',
                    'translate' => 1,
                    'required' => 0,
                    'showOnAdminList' => 0,
                    'useForSeo' => 0,
                    'childs' => [
                        'tooltip' => ['type' => 'tooltip', 'title' => 'Tooltip Highlights', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 1, 'values' => ['number' => 'number', 'percent' => 'percent', 'value' => 'value'], 'editableTitle' => 1, 'confs' => ['unit', 'percent', 'unit_mm', 'unit_bn', 'GBP']],
                    ],
                ],

                'tooltipDiagram' => ['type' => 'tooltip', 'title' => 'Tooltip Diagram', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 1, 'values' => ['quarter' => 'quarter', 'amount' => 'amount'], 'editableTitle' => 1],
            ]
        ],

        'esg_charts' => [
            'title' => 'ESG Charts',
            'route' => 'contentManagement',
            'slug_field' => 'title',
            'searchable' => 1,
            'taxonomy' => [],
            'fields' => [
                'title' => ['title' => 'Title', 'type' => 'text', 'translate' => 1, 'required' => 1, 'showOnAdminList' => 1, 'useForSeo' => 1],

                'gender_diversity' => ['type' => 'tooltip', 'title' => 'Gender Diversity (Pie chart)', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'values' => ['year' => 'Year', 'male' => 'Male', 'female' => 'Female'], 'limit' => 2],
                'age_diversity' => ['type' => 'tooltip', 'title' => 'Age Diversity (Bar chart)', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0, 'values' => ['title' => 'Title', 'value' => 'Value']],

            ]
        ],
    ],

    /// taxonomies
    'taxonomy' => [

        'portfolio_category' => [
            'select' => 'single', /// multy/single/maxNumber
            'fields' => [
                'title' => ['title' => 'Portfolio Company', 'type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1],
                'ownership' => ['title' => 'Ownership', 'type' => 'text', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0, 'useForSeo' => 0],
                'ownership_percent' => ['title' => 'Ownership %', 'type' => 'text', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0],
                'percent_share_in_total_portfolio' => ['title' => '% share in total portfolio', 'type' => 'text', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0],
                'value_gel_mm' => ['title' => 'Value ₾ mm', 'type' => 'text', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0],
                'url' => ['title' => 'URL', 'type' => 'url', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 0],
            ],
        ],

        'investor_type' => [
            'select' => 'single', /// multy/single/maxNumber
            'fields' => [
                'title' => ['type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1],
            ],
        ],

        'timeline_year' => [
            'select' => 'single', /// multy/single/maxNumber
            'fields' => [
                'title' => ['type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1],
            ],
        ],

        'history_category' => [
            'select' => 'single', /// multy/single/maxNumber
            'fields' => [
                'title' => ['type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1],
                'teaser' => ['type' => 'text', 'translate' => 0, 'required' => 0, 'showOnAdminList' => 1],
            ],
        ],

        'financial_results_category' => [
            'select' => 'single', /// multy/single/maxNumber
            'fields' => [
                'title' => ['type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1]
            ],
        ],

        'history_year' => [
            'select' => 'multy', /// multy/single/maxNumber
            'fields' => [
                'title' => ['type' => 'text', 'translate' => 0, 'required' => 1, 'showOnAdminList' => 1],
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
        'AboutUs' => ['title' => 'About Us', 'content_types' => ['page'], 'type' => 'content', 'conf' => []],
        'NewsSlider' => ['title' => 'News Slider', 'content_types' => ['news'], 'type' => 'content', 'conf' => []],
        'MainBanner' => ['title' => 'Main Banner', 'content_types' => ['banner'], 'type' => 'content', 'conf' => ['hide-request-meeting-button']],
        'NavHighlights' => ['title' => 'Nav Highlights', 'content_types' => ['navHighlights'], 'type' => 'content', 'conf' => []],
        'AboutBox' => ['title' => 'About box', 'content_types' => ['page'], 'type' => 'content', 'conf' => []],
        'QuickLinks' => ['title' => 'Quick links', 'content_types' => ['page'], 'type' => 'content', 'conf' => []],
        'Portfolio' => ['title' => 'Portfolio', 'content_types' => ['banner'], 'type' => 'content', 'conf' => []],
        'ManageSubscription' => ['title' => 'Manage Subscription', 'content_types' => ['banner'], 'type' => 'content', 'conf' => []],
        'NewsList' => ['title' => 'News List', 'content_types' => ['news'], 'type' => 'content', 'conf' => []],
        'MenuBanner' => ['title' => 'Menu Banner', 'content_types' => ['banner'], 'type' => 'content', 'conf' => ['show-breadcrumbs']],
        'NewsInner' => ['title' => 'News Inner', 'content_types' => ['news'], 'type' => 'content', 'conf' => []],
        'Governance' => ['title' => 'Governance', 'content_types' => ['governance'], 'type' => 'content', 'conf' => ['five-in-row']],
        'SimilarNewsSlider' => ['title' => 'Similar News Slider', 'content_types' => ['news'], 'type' => 'content', 'conf' => []],
        'ContactCard' => ['title' => 'Contact Card', 'content_types' => ['page'], 'type' => 'content', 'conf' => []],
        'DayPresentations' => ['title' => 'DayPresentations', 'content_types' => ['investor_day_presentations', 'financial_statements'], 'type' => 'content', 'conf' => []],
        'ShareholderMeetings' => ['title' => 'Shareholder Meetings', 'content_types' => ['shareholder_meetings'], 'type' => 'content', 'conf' => []],
        'Documents' => ['title' => 'Documents', 'content_types' => ['page'], 'type' => 'content', 'conf' => []],
        'BondsKeyData' => ['title' => 'Bonds Key Data', 'content_types' => ['page'], 'type' => 'content', 'conf' => []],
        'AnnualReports' => ['title' => 'Annual Reports', 'content_types' => ['annual_reports'], 'type' => 'content', 'conf' => []],
        'OurHistory' => ['title' => 'Our History', 'content_types' => ['history'], 'type' => 'content', 'conf' => []],
        'GcapPortfolioChart' => ['title' => 'Gcap Portfolio Chart', 'content_types' => ['page'], 'type' => 'content', 'conf' => ['slot_next_component']],
        'NavHighlightsStats' => ['title' => 'Nav Highlights Chart', 'content_types' => ['navHighlights'], 'type' => 'content', 'conf' => ['slot_next_component']],
        'FinancialResults' => ['title' => 'Financial Results', 'content_types' => ['financial_results'], 'type' => 'content', 'conf' => []],
        'CreditRatings' => ['title' => 'Credit Ratings', 'content_types' => ['credit_ratings'], 'type' => 'content', 'conf' => []],
        'TopShareholders' => ['title' => 'Top Shareholders', 'content_types' => ['page'], 'type' => 'content', 'conf' => ['slot_next_component']],
        'ImageBanner' => ['title' => 'Image Banner', 'content_types' => ['banner'], 'type' => 'content', 'conf' => []],
        'MacroOverview' => ['title' => 'Macro Overview', 'content_types' => ['page'], 'type' => 'content', 'conf' => []],
        'OpportunityBox' => ['title' => 'Opportunity Box', 'content_types' => ['page'], 'type' => 'content', 'conf' => []],
        'OurStrategy' => ['title' => 'Our Strategy', 'content_types' => ['page'], 'type' => 'content', 'conf' => []],
        'StrategyCard' => ['title' => 'Strategy Card', 'content_types' => ['investment_strategy'], 'type' => 'content', 'conf' => []],
        'PortfolioCompany' => ['title' => 'Portfolio Company', 'content_types' => ['portfolio_company'], 'type' => 'content', 'conf' => []],
        'IFrameComponent' => ['title' => 'iFrame Component', 'content_types' => ['page'], 'type' => 'content', 'conf' => ['has-background']],
        'FinancialStatements' => ['title' => 'Financial Statements', 'content_types' => ['financial_statements'], 'type' => 'content'],
        'FileDocs' => ['title' => 'File Docs', 'content_types' => ['view_reports', 'page'], 'type' => 'content', 'conf' => ['show_agreement']],
        'StrategyCycle' => ['title' => 'Strategy Cycle', 'content_types' => ['page'], 'type' => 'content', 'conf' => ['slot_next_component']],
        'StrategyCycleChart' => ['title' => 'Strategy Cycle Chart', 'content_types' => ['page'], 'type' => 'content', 'conf' => ['slot_next_component']],
        'TradingChart' => ['title' => 'Trading Chart', 'content_types' => ['share_trading'], 'type' => 'content', 'conf' => []],
        'TradingTable' => ['title' => 'Trading Table', 'content_types' => ['share_trading'], 'type' => 'content', 'conf' => []],
        'InvestmentFAQ' => ['title' => 'FAQ', 'content_types' => ['investment_faq'], 'type' => 'content', 'conf' => []],
        'Table' => ['title' => 'Table', 'content_types' => ['page'], 'type' => 'content', 'conf' => []],
        'NAVCalculator' => ['title' => 'NAV Calculator', 'content_types' => ['page'], 'type' => 'content', 'conf' => []],
        'TextComponent' => ['title' => 'Text Component', 'content_types' => ['page'], 'type' => 'content', 'conf' => []],
        'EsgCards' => ['title' => 'ESG Cards', 'content_types' => ['page'], 'type' => 'content', 'conf' => []],
        'EsgCharts' => ['title' => 'ESG Charts', 'content_types' => ['esg_charts'], 'type' => 'content', 'conf' => []],
        'EsgStatistics' => ['title' => 'ESG Statistics', 'content_types' => ['esg_charts'], 'type' => 'content', 'conf' => []],

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
