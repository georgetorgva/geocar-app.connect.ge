<?php

return [

    'subscribers' => [
        'paging'=>['perPage'=>20],
        'title'=>'Subscribers',
        'conf'=>[],
        'taxonomy' => [],

        'regularFields'=>[
            'email' => ['type' => 'text',  'required' => 1, 'useForSeo' => 0, 'dbFilter'=>'whereIn'],
            'info.full_name' => ['type' => 'text', 'title' => 'Full Name',  'required' => 1, 'useForSeo' => 0, 'dbFilter'=>'whereIn'],
            'info.company' => ['type' => 'text', 'title' => 'Company',  'required' => 1, 'useForSeo' => 0, 'dbFilter'=>'whereIn'],
            'info.position' => ['type' => 'text', 'title' => 'Position', 'required' => 1, 'useForSeo' => 0, 'dbFilter'=>'whereIn'],
            'info.investor_type' => ['type' => 'text', 'title' => 'Investor Type',  'required' => 1, 'useForSeo' => 0, 'dbFilter'=>'whereIn'],
            'lang' => [
                'type' => 'singleSelect',
                'title' => 'Language',
                'required' => 1,
                'useForSeo' => 0,
                'searchable'=> 0,
                'dbFilter' => 'whereIn',
                'predefinedVar' => 'indx.locales'
            ],
        ],

        'adminListFields'=>[
            ['title'=>'full_name', 'text'=> ['info.full_name'], 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'info.full_name'],
            ['title'=>'email', 'text'=> ['email'], 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'email'],
            ['title'=>'status', 'text'=> ['status'], 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'status'],
            ['title'=>'investor type', 'text'=> ['info.investor_type'], 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'info.investor_type'],
            ['title'=>'company', 'text'=> ['info.company'], 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'info.company'],
            ['title'=>'position', 'text'=> ['info.position'], 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'info.position'],
            ['title'=>'created_at', 'text'=> ['created_at'], 'sortable'=> 1, 'searchable'=> 0, 'tableKey'=>'created_at'],
        ],
    ],

    /** module general configs */
    'generalConfigs'=>[
        'statuses'=>['active', 'pending', 'passive', 'deleted'],
        'jsonFields' => [
            'info.company' => 'company',
            'info.position' => 'position',
            'info.full_name' => 'full_name',
            'info.investor_type' => 'investor_type'
        ],
        'excelExportFields' => [

            'regular' => [
                'email',
                'status',
                'created_at'
            ],

            'json' => [
                'full_name',
                'company',
                'position',
                'investor_type'
            ]
        ]
    ],

    /** send email config */

    'sendEmailConfigs' => [
        'defaultLocale' => 'en', // define if there is one language only
        'defaultContentType' => 'news'
    ],

    'configFields' => [ // supported field types: text, textarea
        [
            'sendgrid_api_key' => [
                'title' => 'API Key',
                'type' => 'text',
                'class' => 'col-md-6'
            ],
            'sendgrid_sender_email' => [
                'title' => 'Send From Email',
                'type' => 'text',
                'class' => 'col-md-6'
            ],
        ],

        [
            'sendgrid_test_emails' => [
                'title' => 'Test Emails',
                'type' => 'textarea',
                'class' => 'col-md-6',
                'description' => 'separate emails by comma',
                'validationRule' => [
                    'bail',
                    'nullable',
                    'string',
                    'regex:/^\s*(?:[A-Za-z0-9\._%\+-]+@[A-Za-z0-9\.-]+\.[A-Za-z]{2,6})(?:\s*[\n,]\s*(?:[A-Za-z0-9\._%\+-]+@[A-Za-z0-9\.-]+\.[A-Za-z]{2,6}))*\s*$/'
                ]
            ],

            'sendgrid_sender_name' => [
                'title' => 'Send From Name',
                'type' => 'text',
                'class' => 'col-md-6'
            ],
        ]
    ]
];

