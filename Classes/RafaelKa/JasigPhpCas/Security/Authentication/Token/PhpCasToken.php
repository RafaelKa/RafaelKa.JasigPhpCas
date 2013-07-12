<?php
namespace RafaelKa\JasigPhpCas\Security\Authentication\Token;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "RafaelKa.JasigPhpCas".  *
 *                                                                        *
 *                                                                        */

use	TYPO3\Flow\Annotations as Flow,
	TYPO3\Flow\Security\RequestPatternInterface,
	TYPO3\Flow\Security\Authentication\TokenInterface;

/**
 * An authentication token used for CAS authentication.
 */
class PhpCasToken extends AbstractCasToken {

	/**
	 * @Flow\Inject
	 * @var \RafaelKa\JasigPhpCas\Service\CasManager
	 * @Flow\Transient
	 */
	protected $casManager;

	/**
	 * @Flow\Inject
	 * @Flow\Transient
	 * @var \TYPO3\Flow\Http\HttpRequestHandlerInterface
	 */
	protected $requestHandler;

	/**
	 * Returns NULL
	 *
	 * @return NULL
	 */
	public function getCredentials() {
		return NULL;
	}

	/**
	 * Returns the account if one is authenticated, NULL otherwise.
	 *
	 * @todo remove this method for using parent.
	 * @return \TYPO3\Flow\Security\Account An account object
	 */
	public function getAccount() {
		return parent::getAccount();
	}

	/**
	 * Updates the authentication credentials, the authentication manager needs to authenticate this token.
	 * This could be a username/password from a login controller.
	 * This method is called while initializing the security context. By returning TRUE you
	 * make sure that the authentication manager will (re-)authenticate the tokens with the current credentials.
	 * Note: You should not persist the credentials!
	 *
	 * @param \TYPO3\Flow\Mvc\ActionRequest $actionRequest The current request instance
	 * @return boolean TRUE if this token needs to be (re-)authenticated
	 */
	public function updateCredentials(\TYPO3\Flow\Mvc\ActionRequest $actionRequest) {

		$httpRequest = $actionRequest->getHttpRequest();
		if ($httpRequest->getMethod() !== 'GET') {
			return;
		}
		if ($actionRequest->getInternalArgument('__casAuthenticationProviderName') === $this->authenticationProviderName){
			$this->authenticationStatus = self::AUTHENTICATION_NEEDED;
		}
	}
}

?>