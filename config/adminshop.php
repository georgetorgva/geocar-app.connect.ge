<?php
return [

    'stock' => [
        'paging'=>['perPage'=>20],
        'conf'=>['other'],
        'adminListFields'=>[
            'sku'=> [ 'text'=> ['sku'], 'sortable'=> 1, 'searchable'=>'shop_stock.sku', 'tableKey'=>'sku' ],
            'slug'=> [ 'text'=> ['slug'], 'sortable'=> 1, 'searchable'=>'shop_stock.sku', 'tableKey'=>'slug' ],
            'title'=> [ 'text'=> ['title'], 'sortable'=> 1, 'searchable'=>'shop_stock.sku','tableKey'=>'title' ],
            'price'=> [ 'text'=> ['price'], 'sortable'=> 1, 'searchable'=> 'shop_stock.sku','tableKey'=>'price' ],
            'price_old'=> [ 'text'=> ['price_old'], 'sortable'=> 1, 'searchable'=> 'shop_stock.sku','tableKey'=>'price_old' ],
            'conf'=> [ 'text'=> ['conf'], 'sortable'=> 1, 'searchable'=> 'shop_stock.sku','tableKey'=>'conf' ],
        ],
        'regularFields'=>[
            'sku'=>[ 'type'=>'number', 'title'=>'sku','required'=>1, 'conf'=>[],],
            'slug'=>[ 'type'=>'text', 'title'=>'slug','required'=>1, 'conf'=>[],],
            'title'=>[ 'type'=>'text', 'title'=>'title','required'=>0, 'conf'=>[],],
            'price'=>[ 'type'=>'number', 'title'=>'price','required'=>0, 'conf'=>[],],
            'price_old'=>[ 'type'=>'number', 'title'=>'price_old','required'=>0, 'conf'=>[],],
            'images'=>[ 'type'=>'media', 'title'=>'images','required'=>0, 'conf'=>[],],
            'info'=>[ 'type'=>'editor', 'title'=>'info','required'=>0, 'conf'=>[],],
            'conf'=>[ 'type'=>'select', 'title'=>'conf','required'=>0, 'values'=>['other'=>'other',],],


        ],

    ],

    'wallets' => [
        'paging'=>['perPage'=>20],
        'conf'=>['other'],
        'adminListFields'=>[
            'user'=> [ 'text'=> ['users_fullname'], 'sortable'=> 1,'searchable'=>'joinedTable_user.fullname','tableKey'=>'fullname' ],
            'type'=> [ 'text'=> ['type'], 'title'=>'wallet type', 'sortable'=> 1,  'tableKey'=>'type' ],
            'amount'=> [ 'text'=> ['amount'], 'sortable'=> 1,  'tableKey'=>'amount' ],
            'currency'=> [ 'text'=> ['currency'], 'sortable'=> 1,  'tableKey'=>'currency' ],
        ],
        'regularFields'=>[
            'user_id'=>[ 'type'=>'singleSelect', 'title'=>'User','searchable'=>1,'required'=>0, 'showOnAdminList' => 1,'dbFilter'=>'whereIn', 'ajaxData'=>'ltb/getMasters', 'valueField'=>'fullname', 'keyField'=>'id'],
            'type'=>[ 'type'=>'singleSelect', 'title'=>'wallet type','required'=>1, 'searchable'=>1, 'dbFilter'=>'whereIn', 'values'=>['points'=>'points', 'balance'=>'balance']],
            'amount'=>[ 'type'=>'number', 'title'=>'amount','required'=>1, 'conf'=>[],],
            'currency'=>[ 'type'=>'singleSelect', 'title'=>'currency','required'=>1, 'values'=>['points'=>'Points', 'gel'=>'GEL']],
        ],

        'join'=>[
            'user'=>['joinTable'=>'users', 'joinField'=> 'id', 'joinOn'=> 'user_id', 'select'=>['fullname'=>'like']],
        ],


    ],

    'products' => [
        'paging'=>['perPage'=>20],
        'getList'=>['maxListLimit'=>10000],
        'title'=>'General',
        'conf'=>['sellWithPoints', 'disableDiscount'],
        'adminListFields'=>[
            'slug'=> [ 'text'=> ['slug'], 'sortable'=> 1, 'searchable'=>'shop_products.slug', 'tableKey'=>'slug' ],
            'title'=> [ 'text'=> ['title_ge'], 'class'=>'small text-muted', 'sortable'=> 1, 'searchable'=>'meta_title.val', 'tableKey'=>'title' ],
            'quantity'=> [ 'text'=> ['qty', '<span class="text-muted small">', 'dimension', '</span>'], 'sortable'=> 1, 'searchable'=> 'shop_products.quantity','tableKey'=>'qty' ],
            'sku'=> [ 'text'=> ['sku'], 'sortable'=> 1, 'searchable'=> 'shop_products.sku','tableKey'=>'sku' ],
            'price'=> [ 'text'=> ['price', '<span class="text-muted">₾</span>'], 'sortable'=> 1, 'searchable'=> 'shop_products.price','tableKey'=>'price' ],
            'views'=> [ 'text'=> ['views'], 'sortable'=> 1, 'searchable'=> 'shop_products.views','tableKey'=>'views' ],
            'status'=> [ 'text'=> ['status'], 'sortable'=> 1, 'searchable'=>'shop_products.status','tableKey'=>'status' ],
//            'offers'=> [ 'text'=> ['offers'], 'method'=>'offersView', 'sortable'=> 0, 'searchable'=> '','tableKey'=>'' ],
//            'old_price'=> [ 'text'=> ['old_price'], 'sortable'=> 1, 'searchable'=> 'shop_products.old_price','tableKey'=>'old_price' ],
        ],
        'fields'=>[
            'recommend_sort'=>[ 'type'=>'number', 'title'=>'recommend sort','translate'=> 0,'required'=>0, 'showOnAdminList' => 1, 'conf'=>[], 'searchable'=>1,],
            'favorite_sort'=>[ 'type'=>'number', 'title'=>'favorite sort','translate'=> 0,'required'=>0, 'showOnAdminList' => 1, 'conf'=>[], 'searchable'=>1,],
            'title'=>[ 'type'=>'text', 'title'=>'Title','translate'=> 1,'required'=>0, 'showOnAdminList' => 1, 'conf'=>[], 'searchable'=>1,],
            'description'=>[ 'type'=>'editor', 'title'=>'Description','translate'=> 1,'required'=>0, 'showOnAdminList' => 1, 'conf'=>[], 'searchable'=>1],
            'images'=>['type'=>'media','title'=>'Images', 'translate'=> 0, 'required'=>0, 'showOnAdminList' => 1, 'limit'=>50, 'conf'=>[]],
            'characteristic'=>[ 'type'=>'editor', 'title'=>'Characteristic','translate'=>1,'required'=>0, 'showOnAdminList' => 0, 'conf'=>['showOnDropDown'], 'searchable'=>1],
            'video' => ['type'=>'video', 'translate'=> 0, 'required'=>0, 'showOnAdminList' => 0, 'useForSeo'=>0, 'limit'=>1, 'allowed'=>'mp4', 'conf'=>['showOnDropDown'] ],
            'documentationDescription'=>[ 'type'=>'textarea', 'title'=>'Documentation Description','translate'=> 1,'required'=>0, 'showOnAdminList' => 0, 'conf'=>['additionToFiles']],
            'files'=>['type'=>'file','title'=>'Documentation', 'translate'=> 0, 'required'=>0, 'showOnAdminList' => 0, 'limit'=>20, 'size'=>50, 'conf'=>['showOnDropDown']],
            'views'=>['type'=>'number','title'=>'views', 'translate'=> 0, 'required'=>0, 'showOnAdminList' => 0, ],

            'seo_title'=>[ 'type'=>'text', 'title'=>'Seo Title','translate'=> 1,'required'=>0, 'showOnAdminList' => 0, 'conf'=>[], 'searchable'=>0,],
            'seo_description'=>[ 'type'=>'textarea', 'title'=>'Seo Description','translate'=> 1,'required'=>0, 'showOnAdminList' => 0, 'conf'=>[], 'searchable'=>0,],
            'seo_keywords'=>[ 'type'=>'text', 'title'=>'Seo Keywords','translate'=> 1,'required'=>0, 'showOnAdminList' => 0, 'conf'=>[], 'searchable'=>0,],

        ],
    ],

    'order' => [
        'getList'=>['maxListLimit'=>300],
        'paging'=>['perPage'=>20],

        'adminListFields'=>[
            [ 'title'=>'Order id', 'text'=> ['id'], 'sortable'=> 1, 'searchable'=> "shop_orders.id", 'tableKey'=>'id' ],
            [ 'title'=>'Total amount', 'text'=> ['total_amount', '<span class="text-muted small">₾</span>'], 'sortable'=> 1, 'searchable'=> 'shop_orders.total_amount', 'tableKey'=>'total_amount' ],
            [ 'title'=>'Order Status', 'text'=> ['order_status'], 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'order_status', 'class'=>['processing'=>'text-warning', 'complete'=>'text-success', 'pending'=>'text-info', 'failed'=>'text-danger', 'refunded'=>'text-danger', 'finished'=>'text-success',] ],
            [ 'title'=>'Payment Status', 'text'=> ['shop_transactions_status', ' | ', 'shop_transactions_provider'], 'sortable'=> 1, 'searchable'=> 'shop_transactions.status', 'tableKey'=>'payment_status', 'class'=>['processing'=>'text-warning', 'paid'=>'text-success', 'pending'=>'text-info', 'failed'=>'text-danger', 'returned'=>'text-danger',] ],
            [ 'title'=>'Shipping', 'text'=> ['shipping_status', ' | ', 'shipping_price'], 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'shipping_status', 'class'=>['shipped'=>'text-warning', 'arrived'=>'text-success', 'pending'=>'text-info', 'returned'=>'text-danger', ] ],
//            [ 'title'=>'Shipping Price', 'text'=> ['shipping_price'], 'sortable'=> 1, 'searchable'=> 1, 'tableKey'=>'shipping_price' ],
            [ 'title'=>'User', 'text'=> ['users_fullname'], 'sortable'=> 1, 'searchable'=>'users.fullname', 'tableKey'=>'fullname' ],
            [ 'title'=>'Date', 'text'=> ['created_at'], 'sortable'=> 1, 'searchable'=> "shop_orders.created_at", 'tableKey'=>'created_at' ],
        ],
        'regularFields' => [
//            'total_amount' => [ 'title'=>'order total amount', 'type'=>'text', 'required'=>1, 'translate'=>0],
            'user_id' => [ 'title'=>'User', 'type'=>'singleSelect', 'required'=>1, 'ajaxData'=>'ltb/getMasters', 'valueField'=>'fullname', 'keyField'=>'id', 'dbFilter'=>'where'],
            'order_status' => [ 'title'=>'order status', 'type'=>'singleSelect', 'required'=>1, 'predefinedVar'=>'cache.shopIndex.conf.order.order_status', 'keyAsValue'=>1, 'dbFilter'=>'where'],
            'logistic_type' => [ 'title'=>'logistic_type', 'type'=>'singleSelect', 'required'=>1, 'predefinedVar'=>'cache.shopIndex.conf.order.logistic_type'],
//            'shipping_price' => [ 'title'=>'shipping_price', 'type'=>'text', 'required'=>0, 'translate'=>0],
            'created_at' => [ 'title'=>'Date created', 'type'=>'calendar', 'required'=>1, 'translate'=>0, 'dbFilter'=>'range'],
//            'conf' => [ 'title'=>'conf', 'type'=>'select', 'values'=>['special'=>'special'], 'translate'=>0],
            'shipping_status' => [ 'title'=>'shipping status', 'type'=>'singleSelect', 'required'=>1, 'predefinedVar'=>'cache.shopIndex.conf.order.shipping_status'],
            'remote_guid' => [ 'title'=>'remote guid', 'type'=>'text', 'required'=>0, 'dbFilter'=>'where'],
            'remote_order_id' => [ 'title'=>'remote order id', 'type'=>'text', 'required'=>0, 'dbFilter'=>'where'],
        ],

        'moduleConfigs' => [
            'updUrl'=> 'shop/main/updOrder',
            'getSingleUrl'=> 'shop/main/getOrder',
            'deleteUrl'=> 'shop/main/updOrder',
            'getListUrl'=> 'shop/main/getOrders',
            'permissionFor'=> 'ss_orders',
            'cacheKey'=> 'ssOrders',
            'listOb'=> 'cache.ssOrders.list',
            'moduleGeneralConfigs'=> 'shopIndex.conf.generalConfigs',
            'moduleConfigs'=> 'shopIndex.conf.order',
            'slug'=> 'order',
            'actionTypeEdit'=> 'edit_ss_order',
            'actionTypeDelete'=> 'delete_ss_order',
        ],

        'join' => [
            'transaction' => [
                'joinTable' => 'shop_transactions',
                'joinField' => 'order_id',
                'joinOn' => 'id',
                'select' => ['status' => 'whereIn', 'provider' => 'where', 'provider_transaction_id'=>'where', 'provider_response'=>'where'],
            ],
            'users' => [
                'joinTable' => 'users',
                'joinField' => 'id',
                'joinOn' => 'user_id',
                'select' => ['fullname' => 'whereIn'],
            ],

        ],

        'payment_status'=>[
            'pending','processing','paid','failed','returned',
        ],
        'shipping_status'=>[
            'pending','shipped','arrived','returned',
        ],
        'order_status'=>[
//            'pending','processing','shipped','complete','canceled','denied','reversal','failed','refunded','reversed','chargeback','expired','processed','voided',
            'order-received','processing','shipped','complete', 20=>'refunded', 25=>'canceled', 30=>'failed'
        ],
        'logistic_type'=>[
            'shipping',
            'pickup',
        ],
        'returnUrl' => "https://ltb.ge/ge/payment-status/",

        "keyInfo"=>[
            ['title'=>"Contact", 'keys'=>['meta_info.cartMeta.userInfo.fullname', 'meta_info.cartMeta.userInfo.email', 'meta_info.cartMeta.userInfo.phone']],
            ['title'=>"Address", 'keys'=>['meta_info.cartMeta.address.city', 'meta_info.cartMeta.address.district', 'meta_info.cartMeta.address.address', 'meta_info.cartMeta.address.floor']],
            ['title'=>"Total (₾)", 'keys'=>['total_amount', '₾']],
        ]

    ],

    'shippings'=>[
        'paging'=>['perPage'=>20],
        'adminListFields'=>[
            'title'=> [ 'text'=> ['info.ge.title'], 'sortable'=> 1, 'filterable'=> 1 ],
            'slug'=> [ 'text'=> ['slug'], 'sortable'=> 1, 'filterable'=> 1 ],
            'rates'=> [ 'title'=>'Rates from - to', 'text'=> ['cart_min_amount', 'cart_max_amount'], 'sortable'=> 1, 'filterable'=> 1 ],
//            'relation_shipping_location'=> [ 'title'=>'Location', 'text'=> ['relation_shipping_location'], 'searchable'=> "relation_shipping_location.id_sec", 'tableKey'=> 'relation_shipping_location' ],
            'conf'=> [ 'text'=> ['conf'], 'sortable'=> 0, 'filterable'=> 0 ],
        ],
        'validation'=>[
            'slug'=>'required|string',
            'rates'=>'nullable|array',
            'info'=>'array',
            'conf'=>'array',
        ],
        'regularFields' => [
            'slug' => [ 'title'=>'Slug', 'type'=>'text', 'required'=>1, 'values'=>['special'=>'special'], 'translate'=>0],
            'conf' => [ 'title'=>'conf', 'type'=>'select', 'values'=>['special'=>'special'], 'translate'=>0],
//            'locations'=>['title'=>'City', 'text'=> ['city'], 'type'=>'select', 'predefinedVar'=>'cache.locations.list', 'keyField'=>'id','valueField'=>'name_ge', 'tableKey'=>'city', 'table'=>'shop_shippings',  ],
            'shipping_amount'=>['title'=>'Shipping Amount', 'text'=> ['shipping_amount'], 'type'=>'number', 'tableKey'=>'shipping_amount', 'table'=>'shop_shippings',  ],
            'cart_min_amount'=>['title'=>'Cart min amount', 'text'=> ['cart_min_amount'], 'type'=>'number', 'tableKey'=>'cart_min_amount', 'table'=>'shop_shippings',  ],
            'cart_max_amount'=>['title'=>'Cart max amount', 'text'=> ['cart_max_amount'], 'type'=>'number', 'tableKey'=>'cart_max_amount', 'table'=>'shop_shippings',  ],
            'info'=>['title'=>'Additional Info', 'translate'=> 1,
                'fields'=>[
                    'title'=>[ 'type'=>'text', 'title'=>'Title','required'=>0, 'conf'=>[] ],
                    'description'=>[ 'type'=>'textarea', 'title'=>'Description', 'required'=>0, 'conf'=>[] ],
                ],
            ],
        ],

        'relations'=>[
            [ 'table'=>'table_relations', 'id'=>'id_first', 'data_id'=>'id_sec', 'module'=>'shipping_location', 'type'=>'select', 'predefinedVar'=>'cache.locations.list', 'keyField'=>'id','valueField'=>'name_ge',
                'relationTable'=>'shop_locations', "select"=>['name_ge', 'domain']
                ],
        ],
        'shippingTypes'=>[
            'flat'=>[],
            'free'=>[],
        ],
//        'fields'=>[
//            'title'=>[ 'type'=>'text', 'title'=>'Title','translate'=> 1,'required'=>0, 'conf'=>[] ],
//            'description'=>[ 'type'=>'textarea', 'title'=>'Description','translate'=> 1,'required'=>0, 'conf'=>[] ],
//        ],

    ],

    'offers' => [
        'getList'=>['maxListLimit'=>300],
        'paging'=>['perPage'=>20],
        'customSelectFields'=>['if((start_date<=NOW() AND end_date >= NOW()), 1, 0) as active_status'],
        'adminListFields'=>[
            'title'=> [ 'text'=> ['info.ge.title', '<br><span class="text-muted small">', 'slug', '</span>'], 'sortable'=> 1, 'searchable'=> 'shop_products.price','tableKey'=>'price' ],
//            'slug'=> [ 'text'=> ['slug'], 'sortable'=> 1, 'searchable'=> 'slug', 'tableKey'=>'slug' ],
//            'title'=> [ 'text'=> ['info.ge.title'], 'sortable'=> 1, 'searchable'=> 'slug', 'tableKey'=>'title' ],
            'offer_type'=> [ 'text'=> ['offer_type'], 'sortable'=> 1, 'searchable'=> 'offer_type', 'tableKey'=>'offer_type' ],
            'offer_target'=> [ 'text'=> ['offer_target'], 'sortable'=> 1, 'searchable'=> 'offer_target', 'tableKey'=>'offer_target' ],
            'discount_dimension'=> [ 'text'=> ['discount_dimension','discount_amount'], 'sortable'=> 1, 'searchable'=> 'discount_dimension', 'tableKey'=>'discount_dimension' ],
            'card_option'=> [ 'text'=> ['card_option'], 'sortable'=> 1, 'searchable'=> 'card_option', 'tableKey'=>'card_option' ],
            'active_period'=> [ 'title'=>'Active period', 'text'=> ['from: ','start_date', ' - to: ', 'end_date'], 'class'=>'small', 'sortable'=> 0, 'searchable'=> 'start_date', 'tableKey'=>'start_date' ],
//            'end_date'=> [ 'text'=> ['end_date'], 'sortable'=> 0, 'searchable'=> 'end_date', 'tableKey'=>'end_date' ],
        ],
        'validation'=>[
//            'slug'=>'required|string',
            'info'=>'required|array',
            'offer_type_rule'=>'array',
            'shipping_plans'=>'array',
            'offer_type'=>'string',
            'offer_target'=>'string',
            'offer_target_rule'=>'array',
            'discount_dimension'=>'string',
            'discount_amount'=>'numeric',
            'start_date'=>'date',
            'end_date'=>'date',
            'conf'=>'array',
        ],

        'offer_types' => [
            'discount'=>['product', 'category', 'cart'],
            'gift'=>['product'],
            'coupons'=>[],
            'dependentDiscount'=>['product', 'category'],
            'boxDiscount'=>['product', 'category'],
            'userGroupsDiscount'=>['product', 'category'],
            'userDiscount'=>['product', 'category'],
            'limitDiscount'=>['product', 'category']
        ], /// 'userDiscount'=>['product', 'category'], /// offerType=[offertarget1,offertarget2,...]

        'offer_dimension' => ['percent', 'amount'],///'amount'
        'regularFields' => [
            'slug' => [ 'title'=>'Slug', 'type'=>'text', 'required'=>1, 'values'=>['special'=>'special'], 'translate'=>0],
            'offer_type' => [ 'title'=>'offer_type', 'type'=>'hidden', 'required'=>0, 'translate'=>0, 'dbFilter'=>'where'],
//            'status' => [ 'title'=>'status', 'type'=>'hidden', 'required'=>0, 'translate'=>0, 'dbFilter'=>'where'],
//            'conf' => [ 'title'=>'conf', 'type'=>'select', 'values'=>['special'=>'special'], 'translate'=>0],
        ],
        'fields' => [
            'title' => ['title'=>'Title', 'type'=>'text'],
            'teaser' => [ 'title'=>'Teaser', 'type'=>'textarea'],
        ],
        'moduleConfigs' => [
            'updUrl'=> 'shop/main/updOffer',
            'getSingleUrl'=> 'shop/main/getOffer',
            'deleteUrl'=> 'shop/main/deleteOffer',
            'getListUrl'=> 'shop/main/getOffersList',
            'permissionFor'=> 'ss_offers',
            'cacheKey'=> 'ssOffers',
            'listOb'=> 'cache.ssOffers.list',
            'moduleGeneralConfigs'=> 'shopIndex.conf.generalConfigs',
            'moduleConfigs'=> 'shopIndex.conf.offers',
            'slug'=> 'offers',
            'actionTypeEdit'=> 'edit_ss_offer',
            'actionTypeDelete'=> 'delete_ss_offer',
        ],
        'relations'=>[
//            [ 'table'=>'shop_offer_relations', 'id'=>'offer_id', 'data_id'=>'data_id', 'module'=>'attributes'],
            [ 'table'=>'shop_offer_relations', 'id'=>'offer_id', 'data_id'=>'data_id', 'module'=>'product'],
            [ 'table'=>'shop_offer_relations', 'id'=>'offer_id', 'data_id'=>'data_id', 'module'=>'offer_type_data_ids'],
        ],

        'card_options' => ['pointsNo_discountNo_'=>'Disable Loyalty Card','points_discountNo_'=>'Collect Points, No Discount','pointsNo_discount_'=>'Enable Discount, No Points', 'points_discount_'=>'Enable Discount, Enable Points'],
    ],

    'coupons' => [
        'getList'=>['maxListLimit'=>1000],
        'paging'=>['perPage'=>10],
        'adminListFields'=>[
            'can_be_used'=> [ 'text'=> ['can_be_used'], 'sortable'=> 0, 'searchable'=> 'slug', 'tableKey'=>'slug' ],
            'used'=> [ 'text'=> ['used'], 'sortable'=> 0, 'searchable'=> 'slug', 'tableKey'=>'title' ],
            'code'=> [ 'text'=> ['code'], 'sortable'=> 0, 'searchable'=> 'offer_type', 'tableKey'=>'offer_type' ],
            'status'=> [ 'text'=> ['status'], 'sortable'=> 0, 'searchable'=> 'status', 'tableKey'=>'status' ],
        ],
        'validation'=>[
            'can_be_used'=>'required|numeric',
            'used'=>'nullable|numeric',
            'code'=>'required|string',
            'offer_id'=>'required|numeric',
        ],

        'regularFields' => [
            'can_be_used' => [ 'title'=>'can_be_used', 'type'=>'numeric', 'required'=>1, 'dbFilter'=>'whereIn' ],
            'used' => [ 'title'=>'used', 'type'=>'numeric', 'required'=>0, 'dbFilter'=>'whereIn' ],
            'code' => [ 'title'=>'code', 'type'=>'text', 'required'=>1, 'dbFilter'=>'whereIn'],
            'offer_id' => [ 'title'=>'offer_id', 'type'=>'numeric', 'required'=>1, 'dbFilter'=>'whereIn' ],
        ],

        'moduleConfigs' => [
            'updUrl'=> 'shop/main/updCoupon',
            'getSingleUrl'=> 'shop/main/getCoupon',
            'deleteUrl'=> 'shop/main/deleteCoupon',
            'getListUrl'=> 'shop/main/getCouponsList',
            'permissionFor'=> 'ss_coupons',
            'cacheKey'=> 'ssCoupons',
            'listOb'=> 'cache.ssCoupons.list',
            'moduleGeneralConfigs'=> 'shopIndex.conf.generalConfigs',
            'moduleConfigs'=> 'shopIndex.conf.coupons',
            'slug'=> 'coupons',
            'actionTypeEdit'=> 'edit_ss_coupon',
            'actionTypeDelete'=> 'delete_ss_coupon',
        ],


    ],

    'attributes' => [
        'orderField'=>'slug',
        'orderDirection'=>'asc',
//        'fields' => [
//            'title' => ['title'=>'Title', 'type'=>'text', 'translate'=>1,],
//            'svg-icon' => [ 'title'=>'svg-icon', 'type'=>'svg'],
//            'description' => [ 'title'=>'description', 'type'=>'editor'],
//            'faq' => [ 'title'=>'faq', 'type'=>'tip'],
//        ],

        'regularFields' => [
            'attribute' => [ 'title'=>'Attribute type', 'type'=>'number', 'required'=>1, 'translate'=>0, 'dbFilter'=>'whereIn'],
            'pid' => [ 'title'=>'Parent ID', 'type'=>'number', 'required'=>0, 'translate'=>0, 'dbFilter'=>'whereIn'],
            'slug' => [ 'title'=>'Slug', 'type'=>'text', 'required'=>0, 'translate'=>0, 'dbFilter'=>'like'],
            'conf' => [ 'title'=>'Conf', 'type'=>'text', 'required'=>0, 'translate'=>0, 'dbFilter'=>'like'],
            'hierarchy_hash' => [ 'title'=>'Conf', 'type'=>'text', 'required'=>0, 'translate'=>0, 'dbFilter'=>'where'],
        ],
        'attributeTypeConfigs' => ['mainAttributeType', 'default', 'showAsSelector', 'catalogSideFilter', 'showOnDropDown', 'selectorColor', 'selectorImage', 'selectorTitle', 'adminListFilterable','dontshowonweb', 'showInLabel'],
        'attributeConfigs' => ['noBadge'],
        'join' => [
            'products' => [
                'joinTable' => 'shop_product_attribute_relation',
                'joinField' => 'attribute_id',
                'joinOn' => 'id',
//                'select' => ['product_id' => 'where'],
            ],

        ],

    ],

    'locations' => [
        'paging'=>['perPage'=>200],
        'orderField'=>'sort desc, name_ge',
        'orderDirection'=>'asc',
        'title'=>'Locations',
        'conf'=>['other', 'gift'],
        'adminListFields'=>[

            'name_ge'=> [ 'text'=> ['name_ge'], 'sortable'=> 1, 'searchable'=>'shop_locations.name_ge','tableKey'=>'name_ge' ],
            'name_en'=> [ 'text'=> ['name_en'], 'sortable'=> 1, 'searchable'=>'shop_locations.name_en','tableKey'=>'name_en' ],
            'domain'=> [ 'text'=> ['domain'], 'sortable'=> 1, 'searchable'=>'shop_locations.domain','tableKey'=>'domain' ],
//            'parentName'=> [ 'text'=> ['parentName'], 'sortable'=> 1, 'searchable'=>'shop_locations.parentName', 'tableKey'=>'parentName', ],
//            'location_type'=> [ 'text'=> ['location_type'], 'sortable'=> 1, 'searchable'=>'shop_locations.location_type','tableKey'=>'location_type' ],
            'sort'=>[ 'type'=>'number', 'text'=>['sort'],'translate'=> 0,'required'=>0, 'showOnAdminList' => 1, 'conf'=>[], 'sortable'=>1,],

        ],
        'regularFields'=>[
            'sort'=>[ 'type'=>'number', 'title'=>'Order','translate'=> 0,'required'=>0, 'showOnAdminList' => 1, 'conf'=>[], 'searchable'=>1,],
            'domain'=>[ 'type'=>'text', 'title'=>'Domain','translate'=> 0,'required'=>0, 'showOnAdminList' => 1, 'conf'=>[], 'searchable'=>1,],
            'name_en'=>[ 'type'=>'text', 'title'=>'Name en','translate'=> 0,'required'=>0, 'showOnAdminList' => 1, 'conf'=>[], 'searchable'=>1,],
            'name_ge'=>[ 'type'=>'text', 'title'=>'Name ge','translate'=> 0,'required'=>0, 'showOnAdminList' => 1, 'conf'=>[], 'searchable'=>1],
//            'location_type'=>['type'=>'singleSelect','title'=>'Location type', 'translate'=> 0, 'required'=>0, 'showOnAdminList' => 1, 'values'=>['settlement'=>'settlement','region'=>'region','city'=>'city','country'=>'country',]],
        ],
        ],

    'cart'=>[
        'paymentMethods'=>['tbc', 'payPal'],
    ],

    'wishlist' => [
        'getList'=>['maxListLimit'=>300],
        'paging'=>['perPage'=>20],
        'adminListFields'=>[
//            'slug'=> [ 'text'=> ['slug'], 'sortable'=> 1, 'searchable'=> 'slug', 'tableKey'=>'slug' ],
        ],
        'regularFields' => [
            'list_type' => [ 'title'=>'Slug', 'type'=>'text', 'required'=>1, 'searchable'=>1, 'dbFilter'=>'whereIn', ],
        ],
    ],

    'generalConfigs'=>[
        'statuses'=>['published', 'hidden', 'deleted'],
        'wishlistTypes'=>['wishlist', 'postponed', 'sharelist'],
        'paymentProviders'=>['tbc','ufc','bog', 'paypal'],
        'xrates'=>['']
    ],

];
