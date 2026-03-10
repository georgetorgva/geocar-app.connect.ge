<?php

return [
    //// website main information
    'siteTitles' => [
        'title'=>'Web site main Titles and SEO data',
        'fields' =>
            [
                'website_title'=>[ 'title'=>'Website Title', 'type'=>'text'],
                'website_slogan'=>[ 'title'=>'Website Slogan', 'type'=>'text'],
                'website_description'=>[ 'title'=>'Website Description', 'type'=>'text'],
                'website_meta_title'=>[ 'title'=>'Website Meta Title', 'type'=>'text'],
                'website_meta_description'=>[ 'title'=>'Website Meta Description', 'type'=>'text'],
                'website_meta_keywords'=>[ 'title'=>'Website Meta Keywords', 'type'=>'text']
            ]
    ],

    'siteKeys' => [
        'title'=>'Web site main keys',
        'fields' =>
            [
                'website_recaptcha_secret_key'=>[ 'title'=>'Recaptcha Secret key', 'type'=>'text'],
                'website_recaptcha_key'=>[ 'title'=>'Recaptcha key', 'type'=>'text'],
                // 'website_google_maps_key'=>[ 'title'=>'Google maps api key', 'type'=>'text']
            ]
    ],

//    'email' => ['title'=>'Web Email', 'fields'=>[
//        'contact_email_to'=>[ 'title'=>'Email To', 'type'=>'text' ],
//        'contact_email_from'=>[ 'title'=>'Email From', 'type'=>'text' ],
//    ]],
//
//    'contact_form' => ['title'=>'Contact Form Pop-up', 'fields'=>[
//        'contact_form_popup'=>[ 'title'=>'Enable Contact Form Pop-up', 'type'=>'text', 'helptext' => 'Available values: yes , no' ],
//    ]],
//
//    'google_analytics' => ['title'=>'Google Analytics', 'fields'=>[
//        'google_application_credentials'=>[ 'title'=>'Google App Credentials', 'type'=>'text'],
//        'ga_property_id'=>[ 'title'=>'GA Property ID', 'type'=>'text'],
//    ]],


];
