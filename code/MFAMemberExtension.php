<?php

class MFAMemberExtension extends DataExtension {

	private static $db = array(
		'MFAEnabled' => 'Boolean',
	);

	public function hasMFA() {
		return $this->getOwner()->MFAEnabled;
	}

}
