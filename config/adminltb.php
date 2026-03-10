<?php

return [

    'masters' => [
        'paging'=>['perPage'=>20],
        'title'=>'Masters',
        'conf'=>['other', 'gift'],

        'regularFields'=>[
            'fullname'=>[ 'type'=>'text', 'title'=>'fullname','searchable'=> 1,'required'=>1, 'showOnAdminList' => 1,'dbFilter'=>'whereIn'],
            'username'=>[ 'type'=>'text', 'title'=>'username','searchable'=>1,'required'=>1, 'showOnAdminList' => 1,'dbFilter'=>'whereIn', 'keyField'=>'title'],
            'email'=>[ 'type'=>'text', 'title'=>'email','searchable'=>1,'required'=>1, 'showOnAdminList' => 1,'dbFilter'=>'whereIn', 'keyField'=>'title'],
            'phone'=>[ 'type'=>'number', 'title'=>'phone','searchable'=>1,'required'=>1, 'showOnAdminList' => 1,'dbFilter'=>'whereIn', 'keyField'=>'title'],
            'rating'=>[ 'type'=>'number', 'title'=>'Ltb Rating','searchable'=>1,'required'=>0, 'showOnAdminList' => 1,'dbFilter'=>'whereIn', 'keyField'=>'rating'],
            'password'=>[ 'type'=>'text', 'title'=>'password'],
//            'conf'=>[ 'type'=>'select', 'title'=>'conf','required'=>0, 'values'=>['vip'=>'Vip'],],
            'member_group'=>[ 'type'=>'singleSelect', 'title'=>'Member Groups','required'=>0, 'values'=> array_combine(range(1, 100), range(1, 100))
            ],
            ],

        'accountType'=>[
            'person'=>['fields'=>[
                ['title'=>'Profile Picture', 'text'=> ['profilePicture'], 'type'=>'media', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'profilePicture', 'table'=>'users', 'limit' => 1],
                ['title'=>'Full name', 'text'=> ['fullname'], 'type'=>'text', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'fullname', 'table'=>'users',  ],
                ['title'=>'Resident Status', 'text'=> ['residentStatus'], 'type'=>'checkbox', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'residentStatus', 'table'=>'users', 'values' => [1 => 'Yes'] ],
                ['title'=>'Private ID', 'text'=> ['privateId'], 'type'=>'text', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'privateId', 'table'=>'users',  ],
                ['title'=>'Mobile Phone', 'text'=> ['mobile'], 'type'=>'number', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'mobile', 'table'=>'users',  ],
                ['title'=>'Agreements', 'text'=> ['agreements'], 'type'=>'checkbox', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'agreements', 'table'=>'users', 'values'=>['ruleAgrement'=>'Rule Agreement', 'subscribeAgreement'=>'Subscribe Agreement', ]  ],
            ]],
            'company'=>['fields'=>[
                ['title'=>'Profile Picture', 'text'=> ['profilePicture'], 'type'=>'media', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'profilePicture', 'table'=>'users', 'limit' => 1],
                ['title'=>'Member Status', 'text'=> ['memberStatus'], 'type'=>'select', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'memberStatus', 'table'=>'users', 'keyField'=>'id','valueField'=>'title', 'predefinedVar'=>'indx.terms.user_statuses',  ],
                ['title'=>'Legal Name', 'text'=> ['legalName'], 'type'=>'text', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'legalName', 'table'=>'users',  ],
                ['title'=>'Serial Number', 'text'=> ['serialNumber'], 'type'=>'text', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'serialNumber', 'table'=>'users',  ],
                ['title'=>'Mobile Number', 'text'=> ['mobile'], 'type'=>'number', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'mobile', 'table'=>'users',  ],
                ['title'=>'Agreements', 'text'=> ['agreements'], 'type'=>'checkbox', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'agreements', 'table'=>'users', 'values'=>['ruleAgrement'=>'Rule Agreement', 'subscribeAgreement'=>'Subscribe Agreement', ]  ],
            ]],
            'master'=>['fields'=>[
                ['title'=>'Is Physical Person', 'text'=> ['physicalPerson'], 'type'=>'singleCheckbox', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'physicalPerson', 'table'=>'users'],
                ['title'=>'Profile Picture', 'text'=> ['profilePicture'], 'type'=>'media', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'profilePicture', 'table'=>'users', 'limit' => 1],
                ['title'=>'Member Status', 'text'=> ['memberStatus'], 'type'=>'select', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'memberStatus', 'table'=>'users',  'keyField'=>'id', 'valueField'=>'title', 'predefinedVar'=>'indx.terms.user_statuses',  ],
                ['title'=>'Master Legal Name', 'text'=> ['legalName'], 'type'=>'text', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'legalName', 'table'=>'users',  ],
                ['title'=>'Serial Number', 'text'=> ['serialNumber'], 'type'=>'text', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'serialNumber', 'table'=>'users',  ],
                ['title'=>'Default Email', 'text'=> ['emailCheck'], 'type'=>'singleCheckbox', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'emailCheck', 'table'=>'users', 'helptext' => 'if checked email will be main email'],
                ['title'=>'Email', 'text'=> ['email'], 'type'=>'text', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'email', 'table'=>'users',  ],
                ['title'=>'Viber Default Number', 'text'=> ['viberCheck'], 'type'=>'singleCheckbox', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'viberCheck', 'table'=>'users', 'helptext' => 'if checked viber number will be phone number'],
                ['title'=>'Viber Number', 'text'=> ['viber'], 'type'=>'text', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'viber', 'table'=>'users',  ],
                ['title'=>'Whatsapp Default Number', 'text'=> ['whatsappCheck'], 'type'=>'singleCheckbox', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'whatsappCheck', 'table'=>'users', 'helptext' => 'if checked whatsapp number will be phone number'],
                ['title'=>'Whatsapp Number', 'text'=> ['whatsapp'], 'type'=>'number', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'whatsapp', 'table'=>'users',  ],
                ['title'=>'Working Experience', 'text'=> ['workingExperience'], 'type'=>'text', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'workingExperience', 'table'=>'users',  ],
                ['title'=>'Region', 'text'=> ['region'], 'type'=>'select', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'region', 'table'=>'users', 'keyField'=>'id','valueField'=>'title', 'predefinedVar'=>'indx.terms.region', ],
                ['title'=>'Work occupation', 'text'=> ['workOccupation'], 'type'=>'select', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'workOccupation', 'table'=>'users',  'keyField'=>'id','valueField'=>'title', 'predefinedVar'=>'indx.terms.master_project_category'],
                ['title'=>'Premium product share', 'text'=> ['premiumProductShare'], 'type'=>'number', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'premiumProductShare', 'table'=>'users', ],
                ['title'=>'Standard product share', 'text'=> ['standardProductShare'], 'type'=>'number', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'standardProductShare', 'table'=>'users', ],
                ['title'=>'Economy product share', 'text'=> ['economyProductShare'], 'type'=>'number', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'economyProductShare', 'table'=>'users', ],
                ['title'=>'Agreements', 'text'=> ['agreements'], 'type'=>'checkbox', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'agreements', 'table'=>'users', 'values'=>['ruleAgrement'=>'Rule Agreement', 'subscribeAgreement'=>'Subscribe Agreement', ]  ],
            ]]
            ],

        'address'=>[
            'myAddress'=>['fields'=>[
                ['title'=>'Address Type', 'text'=> ['addressType'], 'type'=>'radio', 'tableKey'=>'addressType', 'table'=>'users', 'values'=>['myAddress'=>'My Address', 'other'=>'Other address', ] ],

                ['title'=>'City', 'text'=> ['city'], 'type'=>'singleSelect', 'predefinedVar'=>'cache.locations.list', 'keyField'=>'id','valueField'=>'name_ge', 'tableKey'=>'city', 'table'=>'users',  ],
                ['title'=>'CityId', 'type'=>'hidden', 'tableKey'=>'cityId', 'table'=>'users',  ],
                ['title'=>'District', 'text'=> ['district'], 'type'=>'text', 'tableKey'=>'district', 'table'=>'users',  ],
                ['title'=>'Street', 'text'=> ['street'], 'type'=>'text', 'tableKey'=>'address', 'table'=>'users',  ],
                ['title'=>'Floor', 'text'=> ['floor'], 'type'=>'number', 'tableKey'=>'floor', 'table'=>'users',  ],
                ['title'=>'Default', 'text'=> ['default'], 'type'=>'checkbox', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'default', 'table'=>'users', 'values'=>['default'=>'Default', ]  ],
            ]],
            'other'=>['fields'=>[
                ['title'=>'Address Type', 'text'=> ['addressType'], 'type'=>'radio', 'tableKey'=>'addressType', 'table'=>'users', 'values'=>['myAddress'=>'My Address', 'other'=>'Other address', ] ],
                ['title'=>'Name', 'text'=> ['name'], 'type'=>'text', 'tableKey'=>'name', 'table'=>'users',  ],
                ['title'=>'Surname', 'text'=> ['surname'], 'type'=>'text', 'tableKey'=>'surname', 'table'=>'users',  ],
                ['title'=>'Private Id', 'text'=> ['privateId'], 'type'=>'number', 'tableKey'=>'privateId', 'table'=>'users',  ],
                ['title'=>'Phone', 'text'=> ['phone'], 'type'=>'number', 'tableKey'=>'phone', 'table'=>'users',  ],
                ['title'=>'City', 'text'=> ['city'], 'type'=>'singleSelect', 'predefinedVar'=>'cache.locations.list', 'keyField'=>'id','valueField'=>'name_ge', 'tableKey'=>'city', 'table'=>'users',  ],
                ['title'=>'CityId', 'type'=>'hidden', 'tableKey'=>'cityId', 'table'=>'users',  ],
                ['title'=>'District', 'text'=> ['district'], 'type'=>'text', 'tableKey'=>'district', 'table'=>'users',  ],
                ['title'=>'Street', 'text'=> ['street'], 'type'=>'text', 'tableKey'=>'address', 'table'=>'users',  ],
                ['title'=>'Floor', 'text'=> ['floor'], 'type'=>'number', 'tableKey'=>'floor', 'table'=>'users',  ],
                ['title'=>'Default', 'text'=> ['default'], 'type'=>'checkbox', 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'default', 'table'=>'users', 'values'=>['default'=>'Default', ]  ],
            ]],
        ],

        'contragents'=>[
            'fields'=>[
                ['title'=>'Masters Visibility', 'text'=> ['masterVisibility'], 'type'=>'singleCheckbox', 'tableKey'=>'masterVisibility', 'table'=>'users', ],
                ['title'=>'Owns Loyalty Card', 'text'=> ['loyaltyCard'], 'type'=>'singleCheckbox', 'tableKey'=>'loyaltyCard', 'table'=>'users', ],
                ['title'=>'Loyalty Discount Percent', 'text'=> ['loyaltyDiscountPercent'], 'type'=>'text', 'tableKey'=>'loyaltyDiscountPercent', 'table'=>'users',  ],
                ['title'=>'Contragent Discount Percent', 'text'=> ['contragentDiscountPercent'], 'type'=>'text', 'tableKey'=>'contragentDiscountPercent', 'table'=>'users',  ],
            ],
        ],

        'adminListFields'=>[
            ['title'=>'fullname', 'text'=> ['fullname'], 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'fullname', 'table'=>'users',  ],
            ['title'=>'Username', 'text'=> ['username'], 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'username', 'table'=>'users',  ],
            ['title'=>'email', 'text'=> ['email'], 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'email', 'table'=>'users',  ],
            ['title'=>'phone', 'text'=> ['phone'], 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'phone', 'table'=>'users',  ],
            ['title'=>'public Rating','text'=> ['publicRating'], 'sortable'=> 1, 'searchable'=>'publicRating', 'tableKey'=>'publicRating', ],
            ['title'=>'number Of Comments','text'=> ['numberOfComments'], 'sortable'=> 1, 'searchable'=>'numberOfComments', 'tableKey'=>'numberOfComments', ],
            ['title'=>'created_at', 'text'=> ['created_at'], 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'created_at', 'table'=>'users',  ],
            ['title'=>'member group', 'text'=> ['member_group'], 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'member_group', 'table'=>'users',  ],
            ['title'=>'Member Status', 'text'=> ['status'], 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'status', 'table'=>'users',  ],
            ['title'=>'Subscribe', 'text'=> ['subscribe'], 'sortable'=> 0, 'searchable'=> 0  ],
        ],

    ],

    /// for sample
    'projects' => [
        'title'=>'Projects',
        'paging'=>['perPage'=>20],
        'taxonomy'=>['master_project_category'],

        'adminListFields'=>[
            'id'=> [ 'text'=> ['id'], 'sortable'=> 1, 'searchable'=> 'ltb_projects.id', 'tableKey'=>'id' ],
            'title'=> [ 'text'=> ['title'], 'sortable'=> 1, 'searchable'=> 'meta_title.val', 'tableKey'=>'title' ],
            'description'=> [ 'text'=> ['description'], 'sortable'=> 1, 'searchable'=> 'meta_description.val', 'tableKey'=>'description' ],
            'fullname'=> [ 'text'=> ['user_fullname'], 'sortable'=> 1, 'searchable'=> 'joinedTable_user.fullname','tableKey'=>'fullname' ],
        ],
        'validation'=>[
            'user_id'=>'required|numeric',
            'status'=>'required|string',
            'date'=>'required|date',
        ],

        'conf'=>['other'],

        'regularFields'=>[
            'slug'=>[ 'type'=>'text', 'title'=>'Slug','searchable'=> 1,'required'=>0, 'showOnAdminList' => 1,'dbFilter'=>'whereIn'],
            'date'=>[ 'type'=>'calendar', 'title'=>'Date','searchable'=>1,'required'=>0, 'showOnAdminList' => 1,'dbFilter'=>'whereIn' ],
            'user_id'=>[ 'type'=>'singleSelect', 'title'=>'Select Master','searchable'=>1,'required'=>0, 'showOnAdminList' => 1,'dbFilter'=>'whereIn', 'ajaxData'=>'ltb/getMasters', 'ajaxParams' => ['status' => 'master'], 'keyField'=>'id', 'valueField'=>'fullname', 'helptext'=> 'Search Name'],
        ],

        'fields'=>[
            'title'=>[ 'type'=>'text', 'title'=>'title','translate'=> 0,'required'=>1, 'showOnAdminList' => 1],
            'description'=>[ 'type'=>'editor', 'title'=>'description','translate'=> 0,'required'=>0, 'showOnAdminList' => 1],
            'images'=>[ 'type'=>'media', 'title'=>'images','translate'=> 0,'required'=>0, 'showOnAdminList' => 1, 'limit'=>5],
        ],
        'join'=>[
            'user'=>['joinTable'=>'users',  'joinField'=> 'id', 'joinOn'=> 'user_id', 'tablePrefix'=>'user', 'select'=>['fullname'=>'like', 'additional_info' => 'like', 'rating' => 'like'], 'sameStatus'=>false],
            'comments'=>['joinTable'=>'ltb_comments',  'joinField'=> 'master_id', 'joinOn'=> 'user_id',  'select' => ['rating'=>'like'], 'sameStatus'=>true],
        ],

    ],

    'comments' => [
        'title'=>'Comments',
        'paging'=>['perPage'=>20],
        'taxonomy'=>['comment_tag'],

        'adminListFields'=>[
//            'id'=> [ 'text'=> ['id'], 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'id', 'table'=>'ltb_comments',  ],
            'author'=> [ 'text'=> ['author_fullname'], 'sortable'=> 1, 'searchable'=>'joinedTable_author.fullname','tableKey'=>'fullname' ],
            'commentary'=> [ 'text'=> ['commentary'], 'sortable'=> 1, 'searchable'=>'ltb_comments.commentary','tableKey'=>'commentary' ],
            'rating'=> [ 'text'=> ['rating'], 'sortable'=> 1, 'searchable'=>'ltb_comments.rating','tableKey'=>'rating' ],
            'master'=> [ 'text'=> ['master_fullname'], 'sortable'=> 1, 'searchable'=>'joinedTable_master.fullname','tableKey'=>'fullname' ],
        ],
        'validation'=>[
            // 'id'=>'numeric',
            'rating'=>'required|numeric|max:5',
            'master_id'=>'required|numeric',
            'status'=>'required|string',
            'date'=>'required|date',
        ],

        'conf'=>['other'],

        'regularFields'=>[
            'author_id'=>[ 'type'=>'singleSelect', 'title'=>'Comment Author','searchable'=>1,'required'=>0, 'showOnAdminList' => 1,'dbFilter'=>'whereIn', 'ajaxData'=>'ltb/getMasters', 'valueField'=>'fullname', 'keyField'=>'id'],
            'slug'=>[ 'type'=>'text', 'title'=>'Slug','searchable'=> 1,'required'=>1, 'showOnAdminList' => 1,'dbFilter'=>'whereIn'],
            'date'=>[ 'type'=>'calendar', 'title'=>'Date','searchable'=>1,'required'=>0, 'showOnAdminList' => 1,'dbFilter'=>'whereIn' ],
            'master_id'=>[ 'type'=>'singleSelect', 'title'=>'Select Master','searchable'=>1,'required'=>0, 'showOnAdminList' => 1,'dbFilter'=>'whereIn', 'ajaxData'=>'ltb/getMasters', 'ajaxParams' => ['status' => 'master'], 'valueField'=>'fullname', 'keyField'=>'id'],
            'rating'=>[ 'type'=>'number', 'title'=>'rating','searchable'=>1,'required'=>1, 'showOnAdminList' => 1,'dbFilter'=>'whereIn', 'keyField'=>'rating'],
            'commentary'=>[ 'type'=>'textarea', 'title'=>'Commentary','translate'=> 0,'required'=>0, 'showOnAdminList' => 1],
            'image'=>[ 'type'=>'media', 'title'=>'Images','translate'=> 0,'required'=>0, 'showOnAdminList' => 1, 'limit'=>5],
            'video'=>[ 'type'=>'video', 'title'=>'Video','translate'=> 0,'required'=>0, 'showOnAdminList' => 1, 'limit'=>1]
        ],

        'join'=>[
            'author'=>['joinTable'=>'users', 'joinField'=> 'id', 'joinOn'=> 'author_id', 'tablePrefix' => 'author', 'select'=>['fullname'=>'like', 'additional_info' => 'like', 'status' => 'like'], 'sameStatus'=>false],
            'master'=>['joinTable'=>'users', 'joinField'=> 'id', 'joinOn'=> 'master_id', 'tablePrefix' => 'master', 'select'=>['fullname'=>'like'], 'sameStatus'=>false]
        ],

//        'fields'=>[
//            'name'=>[ 'type'=>'text', 'title'=>'Name','translate'=> 0,'required'=>0, 'showOnAdminList' => 1],
//            'comment'=>[ 'type'=>'editor', 'title'=>'Comment','translate'=> 0,'required'=>0, 'showOnAdminList' => 1]
//        ],
    ],


    /** module general configs */
    'generalConfigs'=>[
        'statuses'=>['published', 'hidden', 'deleted']
    ]
];
