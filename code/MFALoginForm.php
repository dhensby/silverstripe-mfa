<?php

class MFALoginForm extends MemberLoginForm  {

	protected $authenticator_class = 'MFAAuthenticator';

	private $backURL;

	private static $allowed_actions = array(
		'doLogin',
		'challenge',
		'TFAForm',
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
				Session::set('MFA.MemberID', $member->ID);
				Session::set('MFA.loginData', $data);
				$this->getController()->redirect($this->Link('challenge'));
			}
			else {
				$this->performLogin($data);
				$this->logInUserAndRedirect($data);
			}
		}
	}

	public function Link($action = null) {
		return Controller::join_links(
			$this->getController()->Link(),
			$this->getName(),
			$action
		);
	}

	public function challenge($request) {
		return $this->customise(array(
			'Content' => $this->TFAForm(),
		))->renderWith('Page');
	}

	public function TFAForm() {
		return Form::create(
			$this,
			'TFAForm',
			FieldList::create(
				TextField::create('Token', 'Token')
			),
			FieldList::create(
				FormAction::create('doChallenge', 'Submit')
			),
			RequiredFields::create('Token')
		);
	}

	/**
	 * @param array $data
	 * @param Form $form
	 * @param SS_HTTPRequest $request
	 */
	public function doChallenge($data, $form, $request) {
		$memberID = Session::get('MFA.MemberID');
		/** @var Member $member */
		$member = Member::get()->byID($memberID);
		$mfaProvider = new MFABackupCodeProvider();
		$mfaProvider->setMember($member);
		$data = $form->getData();
		if ($mfaProvider->verifyToken($data['Token'])) {
			$loginData = Session::get('MFA.loginData');
			$member->logIn(isset($loginData['Remember']));
			$this->logInUserAndRedirect($loginData);
		}
		else {
			die('whoops');
		}

	}

	protected function logInUserAndRedirect($data) {
		Session::clear('SessionForms.MemberLoginForm.Email');
		Session::clear('SessionForms.MemberLoginForm.Remember');

		if(Member::currentUser()->isPasswordExpired()) {
			if(isset($data['BackURL']) && $backURL = $data['BackURL']) {
				Session::set('BackURL', $backURL);
			}
			$cp = new ChangePasswordForm($this->controller, 'ChangePasswordForm');
			$cp->sessionMessage(
				_t('Member.PASSWORDEXPIRED', 'Your password has expired. Please choose a new one.'),
				'good'
			);
			return $this->controller->redirect('Security/changepassword');
		}

		// Absolute redirection URLs may cause spoofing
		if(!empty($data['BackURL'])) {
			$url = $data['BackURL'];
			if(Director::is_site_url($url) ) {
				$url = Director::absoluteURL($url);
			} else {
				// Spoofing attack, redirect to homepage instead of spoofing url
				$url = Director::absoluteBaseURL();
			}
			return $this->controller->redirect($url);
		}

		// If a default login dest has been set, redirect to that.
		if ($url = Security::config()->default_login_dest) {
			$url = Controller::join_links(Director::absoluteBaseURL(), $url);
			return $this->controller->redirect($url);
		}

		// Redirect the user to the page where they came from
		$member = Member::currentUser();
		if($member) {
			$firstname = Convert::raw2xml($member->FirstName);
			if(!empty($data['Remember'])) {
				Session::set('SessionForms.MemberLoginForm.Remember', '1');
				$member->logIn(true);
			} else {
				$member->logIn();
			}

			Session::set('Security.Message.message',
						 _t('Member.WELCOMEBACK', "Welcome Back, {firstname}", array('firstname' => $firstname))
			);
			Session::set("Security.Message.type", "good");
		}
		Controller::curr()->redirectBack();
	}

}
