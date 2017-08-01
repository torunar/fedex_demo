<?php

use Tygh\Languages\Languages;

function fn_fedex_demo_services_add()
{
    if (!Tygh::$app['db']->getField('SELECT 1 FROM ?:shipping_services WHERE module = ?s', 'fedex_demo')) {
        $service_id = Tygh::$app['db']->query('INSERT INTO ?:shipping_services ?e', array(
            'status' => 'A',
            'module' => 'fedex_demo',
            'code' => 'GROUND_HOME_DELIVERY',
        ));

        foreach (Languages::getAll() as $lang) {
            Tygh::$app['db']->query('INSERT INTO ?:shipping_service_descriptions ?e', array(
                'service_id' => $service_id,
                'description' => 'FedEx demo',
                'lang_code' => $lang['lang_code'],
            ));
        }
    }
}