<?php

Authenticator::register('MFAAuthenticator');
Authenticator::unregister('MemberAuthenticator');
Authenticator::set_default_authenticator('MFAAuthenticator');

Config::inst()->update('Authenticator', 'default_authenticator', 'MFAAuthenticator');
