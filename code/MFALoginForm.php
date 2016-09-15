<?php

class MFALoginForm extends MemberLoginForm  {

	protected $authenticator_class = 'MFAAuthenticator';

	private $backURL;

	private static $allowed_actions = array(
		'doLogin',
	);

	public function __construct($controller, $name, FieldList $fields = null, FieldList $actions = null, $validator = null) {
		if (!$backURL = $controller->getRequest()->requestVar('BackURL')) {
			$backURL = Session::get('BackURL');
		}
		$this->backURL = $backURL;
		if (!$fields) {
			$fields = $this->getFormFields();
		}
		if (!$actions) {
			$actions = $this->getFormActions();
		}
		if (!$validator) {
			$validator = $this->getFormValidator();
		}
		$this->setFormMethod('POST', true);
		//skip the parent construct
		LoginForm::__construct($controller, $name, $fields, $actions, $validator);
	}

	public function getFormFields() {
		$label=singleton('Member')->fieldLabel(Member::config()->unique_identifier_field);
		$fields = FieldList::create(
			HiddenField::create("AuthenticationMethod", null, $this->authenticator_class, $this),
			$emailField = TextField::create('Email', $label),
			PasswordField::create('Password', _t('Member.PASSWORD', 'Password'))
		);
		if ($this->backURL) {
			$fields->push(HiddenField::create('BackURL', 'BackURL', $this->backURL));
		}
		if(Security::config()->remember_username) {
			$emailField->setValue(Session::get('SessionForms.MemberLoginForm.Email'));
		} else {
			// Some browsers won't respect this attribute unless it's added to the form
			$this->setAttribute('autocomplete', 'off');
			$emailField->setAttribute('autocomplete', 'off');
		}
		if (Security::config()->autologin_enabled) {
			$fields->push(CheckboxField::create(
				"Remember",
				_t('Member.REMEMBERME', "Remember me next time?")
			));
		}
		return $fields;
	}

	public function getFormActions() {
		return FieldList::create(
			FormAction::create('dologin', _t('Member.BUTTONLOGIN', "Log in")),
			LiteralField::create(
				'forgotPassword',
				'<p id="ForgotPassword"><a href="Security/lostpassword">'
				. _t('Member.BUTTONLOSTPASSWORD', "I've lost my password") . '</a></p>'
			)
		);
	}

	public function getFormValidator() {
		return RequiredFields::create(array(
			'Email',
			'Password',
		));
	}

	/**
	 * @param array $data
	 * @param MFALoginForm $form
	 * @param SS_HTTPRequest $request
	 */
	public function dologin($rawData, $form = null, $request = null) {
		$data = $form->getData();
		$member = call_user_func_array(array($this->authenticator_class, 'authenticate'), array($data, $form));
		if (!$member || !$member->exists()) {
			if(array_key_exists('Email', $data)){
				Session::set('SessionForms.MemberLoginForm.Email', $data['Email']);
				Session::set('SessionForms.MemberLoginForm.Remember', isset($data['Remember']));
			}

			if(isset($_REQUEST['BackURL'])) $backURL = $_REQUEST['BackURL'];
			else $backURL = null;

			if($backURL) Session::set('BackURL', $backURL);

			// Show the right tab on failed login
			$loginLink = Director::absoluteURL($this->controller->Link('login'));
			if($backURL) $loginLink .= '?BackURL=' . urlencode($backURL);
			$this->controller->redirect($loginLink . '#' . $this->FormName() .'_tab');
		}
		else {
			if ($member->hasMFA()) {
				die('got to get your MFA on');
			}
			else {
				$this->performLogin($data);
				$this->logInUserAndRedirect($data);
			}
		}
	}

}
