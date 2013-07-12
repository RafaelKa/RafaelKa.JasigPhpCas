<?php
namespace RafaelKa\JasigPhpCas\Security\Authentication\Token;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "RafaelKa.JasigPhpCas".  *
 *                                                                        *
 *                                                                        */

use	TYPO3\Flow\Annotations as Flow;

/**
 * An authentication token used for CAS authentication.
 */
abstract class AbstractCasToken extends \TYPO3\Flow\Security\Authentication\Token\AbstractToken {

	/**
	 * @Flow\Transient
	 * @var array
	 */
	protected $settings;

	/**
	 * @Flow\Transient
	 * @var array
	 */
	protected $allowedArguments = array();

	/**
	 * @Flow\Transient
	 * @var array
	 */
	protected $allowedInternalArguments = array();

	/**
	 * @var \TYPO3\Flow\Http\Uri
	 */
	protected $beforeAuthenticationUri;

	/**
	 * Prepare settings
	 *
	 * @param array $settings
	 * @return void
	 */
	public function injectSettings(array $settings) {

		if (isset($settings['Authentication']['Token']['allowedArguments'])) {
			$this->allowedArguments = $settings['Authentication']['Token']['allowedArguments'];
		}
		if (isset($settings['Authentication']['Token']['allowedInternalArguments'])) {
			$this->allowedInternalArguments = $settings['Authentication']['Token']['allowedInternalArguments'];
		}

		// set Defaults
		if (!in_array('__casAuthenticationProviderName', $this->allowedInternalArguments)) {
			$this->allowedInternalArguments[] = '__casAuthenticationProviderName';
		}
		if (!in_array('ticket', $this->allowedArguments)) {
			$this->allowedArguments[] = 'ticket';
		}
	}

	/**
	 * Returns array with allowed arguments. 
	 * 
	 * @return array
	 */
	public function getAllowedArguments() {
		return $this->allowedArguments;
	}

	/**
	 * Adds allowed argument.
	 * 
	 * @param string $allowedArgument
	 * @throws \
	 */
	public function addAllowedArgument($allowedArgument) {
		if (!is_string($allowedArgument)) {
			throw new \InvalidArgumentException('Could not set value. Only string can be set but "' . gettype($allowedArgument) . '" given.', 1372503290);
		}
		if (!in_array($allowedArgument, $this->allowedArguments)) {
			$this->allowedArguments[] = $allowedArgument;
		}
	}

	/**
	 * Adds allowed arguments.
	 * 
	 * @param array $allowedArguments
	 */
	public function addMultipleAllowedArguments(array $allowedArguments) {
		foreach ($allowedArguments as $allowedArgument) {
			$this->addAllowedArgument($allowedArgument);
		}
	}

	/**
	 * Returns array with allowed internal arguments.
	 * 
	 * @return array
	 */
	public function getAllowedInternalArguments() {
		return $this->allowedInternalArguments;
	}


	/**
	 * Adds allowed internal argument. 
	 * 
	 * @param string $allowedInternalArgument
	 */
	public function addAllowedInternalArgument($allowedInternalArgument) {
		if (!is_string($allowedInternalArgument)) {
			throw new \InvalidArgumentException('Could not set value. Only string can be set but "' . gettype($allowedInternalArgument) . '" given.', 1372503291);
		}
		if (!in_array($allowedInternalArgument, $this->allowedInternalArguments)) {
			$this->allowedInternalArguments[] = $allowedInternalArgument;
		}
	}

	/**
	 * Adds allowed internal argument. 
	 * 
	 * @param array $allowedInternalArguments
	 */
	public function addMutipleAllowedInternalArguments(array $allowedInternalArguments) {
		foreach ($allowedInternalArguments as $allowedInternalArgument) {
			$this->addAllowedInternalArgument($allowedInternalArgument);
		}
	}

	/**
	 * Returns callback uri before authentication.
	 * Note: Is not stored in session.
	 * 
	 * @return \TYPO3\Flow\Http\Uri
	 */
	public function getBeforeAuthenticationUri() {
		return $this->beforeAuthenticationUri;
	}
}

?>