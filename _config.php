<?php

Authenticator::register('MFAAuthenticator');
Authenticator::unregister('MemberAuthenticator');

Config::inst()->update('Authenticator', 'default_authenticator', 'MFAAuthenticator');
