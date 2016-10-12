<?php

class MFABackupCode extends DataObject {

	private static $db = array(
		'Token' => 'Varchar(6)',
		'IsUsed' => 'Boolean',
	);

	private static $has_one = array(
		'Member' => 'Member',
	);

	private static $indexes = array(
		'MemberTokens' => array(
			'type' => 'index',
			'value' => '"MemberID","Token"',
		),
	);

	protected function generateToken() {
		return CodeGenerator::inst()->numbersonly()->setLength(6)->generate();
	}

	public function populateDefaults() {
		$this->Token = $this->generateToken();
		return parent::populateDefaults();
	}

	/**
	 * @return DataList
	 */
	public static function get_valid_tokens() {
		return static::get()->filter(array(
			'IsUsed' => false,
		));
	}

	public function expire() {
		$this->IsUsed = true;
		$this->write();
		return $this;
	}

	public function requireDefaultRecords() {
		parent::requireDefaultRecords();
		foreach (Member::get() as $member) {
			$numCodes = static::get_valid_tokens()->filter('Member.ID', $member->ID)->count();
			$limit = 5 - $numCodes;
			if ($limit < 1) {
				break;
			}
			for ($i = 0; $i < $limit; ++$i) {
				$code = static::create();
				$code->MemberID = $member->ID;
				$code->write();
				$code->destroy();
				DB::alteration_message(sprintf('Backup code for user %d: %d', $member->ID, $code->Token));
			}
		}
	}

}
