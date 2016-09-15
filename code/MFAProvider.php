<?php

interface MFAProvider {

	/**
	 * @param Member $member
	 */
	public function setMember($member);

	/**
	 * Generate the token ready for the member
	 */
	public function generateToken();

	/**
	 * Send the token to the member
	 */
	public function sendToken();

	public function verifyToken($token);

}
