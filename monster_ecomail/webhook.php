<?php

require(dirname(__FILE__) . '/../../config/config.inc.php');

$requestJson = file_get_contents('php://input');

if($requestJson){
    $request = json_decode($requestJson);

    $email = $request->payload->email;

    $customer = new Customer();

    $customers = $customer->getCustomersByEmail($email);

    if($request->payload->status === 'SUBSCRIBED'){
        $newsletterStatus = '1';
    } else {
        $newsletterStatus = '0';
    }

    foreach ($customers as $customer) {
        $customerObject = new Customer($customer['id_customer']);
        $customerObject->newsletter = $newsletterStatus;
        $customerObject->save();
    }
}