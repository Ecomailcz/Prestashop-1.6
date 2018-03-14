<?php
if( !defined( '_PS_VERSION_' ) )
    exit;

class BlocknewsletterOverride extends Blocknewsletter {
    protected function register($email, $register_status)
    {
        Hook::exec(
            'actionCustomerNewsletterSubscribed',
            array( 'email' => $email )
        );

        if ($register_status == self::GUEST_NOT_REGISTERED)
            return $this->registerGuest($email);

        if ($register_status == self::CUSTOMER_NOT_REGISTERED)
            return $this->registerUser($email);

        return false;
    }
}