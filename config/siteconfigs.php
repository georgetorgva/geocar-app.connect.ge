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

    'nav_calculator' => [
        'title'=>'NAV Calculator',
        'fields' => [
            'bgeo_ownership_override' => ['title' => 'BGEO - Ownership Override', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'bgeo_profit' => ['title' => 'BGEO - Profit', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'bgeo_shares_owned' => ['title' => 'BGEO - Shares Owned', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'healthcare_ebitda' => ['title' => 'Healthcare - EBITDA', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'healthcare_net_debt' => ['title' => 'Healthcare - Net Debt', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'healthcare_ownership_perc_decimal' => ['title' => 'Healthcare - Ownership Perc. (decimal)', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'healthcare_public_visible_ownership_perc_decimal' => ['title' => 'Healthcare - Public/Visible Ownership Perc. (decimal)', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'healthcare_multiples' => ['title' => 'Healthcare - Multiples', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'retail_pharmacy_ebitda' => ['title' => 'Retail Pharmacy - EBITDA', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'retail_pharmacy_net_debt' => ['title' => 'Retail Pharmacy - Net Debt', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'retail_pharmacy_ownership_perc_decimal' => ['title' => 'Retail Pharmacy - Ownership Perc. (decimal)', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'retail_pharmacy_public_visible_ownership_perc_decimal' => ['title' => 'Retail Pharmacy - Public/Visible Ownership Perc. (decimal)', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'retail_pharmacy_multiples' => ['title' => 'Retail Pharmacy - Multiples', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'insurance_multiples' => ['title' => 'Insurance - Multiples', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'insurance_public_visible_ownership_perc_decimal' => ['title' => 'Insurance - Public/Visible Ownership Perc. (decimal)', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'p_and_c_insurance_net_income' => ['title' => 'P&C Insurance - Net Income', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'p_and_c_insurance_ownership' => ['title' => 'P&C Insurance - Ownership', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'pandc_insurance_multiple' => ['title' => 'P&C Insurance - Multiple', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'medical_insurance_net_income' => ['title' => 'Medical Insurance - Net Income', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'medical_insurance_ownership' => ['title' => 'Medical Insurance - Ownership', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'medical_insurance_multiple' => ['title' => 'Medical Insurance - Multiple', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'other_portfolio_companies_value' => ['title' => 'Other Portfolio Companies - Value', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'net_debt_gcap' => ['title' => 'Net Debt (GCAP)', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'net_other_assets' => ['title' => 'Net Other Assets', 'type' => 'text', 'class' => 'col-3 mt-3'],
            'shares_outstanding' => ['title' => 'Shares Outstanding', 'type' => 'text', 'class' => 'col-3 mt-3'],
        ],
    ],

];
