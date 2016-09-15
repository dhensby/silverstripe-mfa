<?php

class MFAAuthenticator extends MemberAuthenticator {

	public static function get_login_form(Controller $controller) {
		return MFALoginForm::create($controller, "LoginForm");
	}

}
