<?php
if( !defined( '_PS_VERSION_' ) )
    exit;

class BlocknewsletterOverride extends Blocknewsletter {
    protected function registerUser( $email ) {
        $sql = 'UPDATE ' . _DB_PREFIX_ . 'customer
				SET `newsletter` = 1, newsletter_date_add = NOW(), `ip_registration_newsletter` = \'' . pSQL(
                Tools::getRemoteAddr()
            ) . '\'
				WHERE `email` = \'' . pSQL( $email ) . '\'
				AND id_shop = ' . $this->context->shop->id;

        $result = Db::getInstance()->execute( $sql );

        Hook::exec(
            'actionCustomerNewsletterSubscribed',
            array( 'email' => $email )
        );

        return $result;
    }
}