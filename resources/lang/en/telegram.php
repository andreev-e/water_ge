<?php

return [
    'shutdown' => 'Shutdown',
    'you_are_subscribed' => 'You are subscribed to shutdown notifications in :cities.',
    'no_shutdowns' => 'No actual shutdowns. Check your subscriptions /subscribe',
    'default_answer' => 'Can\'t help you.',
    'change_subscriptions' => 'Change subscriptions /subscribe',
    'start' => 'Hi there! This bot can notify you about upcoming shutdowns. Set your subscription cities /subscribe',
    'select_city' => 'Select cities. Only your streets: /filter',
    'subscribe_success' => 'You have successfully subscribed to shutdown notifications in :city',
    'unsubscribe_success' => 'You have successfully unsubscribed from shutdown notifications in :city',
    'subscribe_fail' => 'Subscription failed. Please try again later.',
    'buttons' => [
        'set_city' => 'Select city',
    ],
    'change_filter' => 'Street filter /filter',
    'filter_no_subscriptions' => 'Subscribe to a city first: /subscribe',
    'filter_select_city' => 'Select a city to set a street filter for:',
    'filter_enter_streets' => ":city — current filter: :current.\n\nSend a street name as it is spelled in notifications (in Latin letters, e.g. nikea) or in Georgian. Several streets — comma-separated. You will be notified only if the outage includes such a street. Send :clear to get every outage in the city.",
    'filter_none' => 'none, every outage is sent',
    'filter_saved' => ':city: notifications only for :streets. Change /filter',
    'filter_cleared' => ':city: filter removed, every outage is sent. Change /filter',
    'mail_not_subscribed' => 'You are not subscribed to any city. Please press /subscribe and select cities you want to receive notifications about upcoming shutdowns.',
    'promo' => '',
];
