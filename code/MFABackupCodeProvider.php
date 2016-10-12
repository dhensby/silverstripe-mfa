<?php

class MFABackupCodeProvider implements MFAProvider {

	private $member;

	public function setMember($member) {
		$this->member = $member;
	}

	public function getMember() {
		return $this->member ?: Member::create();
	}

	public function generateToken() {
		// Users already have the token (code) in their posession
	}

	public function verifyToken($token) {
		/** @var MFABackupCode $backupCode */
		$backupCode = MFABackupCode::get_valid_tokens()->filter(array(
			'Member.ID' => $this->getMember()->ID,
			'Token' => $token,
		))->first();
		//Debug::show($this->getMember());
		//Debug::message($token);
		//Debug::show(MFABackupCode::get());
		if ($backupCode && $backupCode->exists()) {
			$backupCode->expire();
			return true;
		}
		return false;
	}

	/**
	 * Send the token to the member
	 */
	public function sendToken() {
		// noop - members have the tokens already
		return true;
	}

}
