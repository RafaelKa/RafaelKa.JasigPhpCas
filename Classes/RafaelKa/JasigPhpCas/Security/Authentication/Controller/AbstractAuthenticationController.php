<?php
namespace RafaelKa\JasigPhpCas\Security\Authentication\Controller;
/*                                                                        *
 * This script belongs to the TYPO3 Flow package "RafaelKa.JasigPhpCas".  *
 *                                                                        *
 *                                                                        */

use	TYPO3\Flow\Annotations as Flow,
	TYPO3\Flow\Utility\Arrays as ArraysUtility;

/**
 * @todo Move that all to Aspect
 */
abstract class AbstractAuthenticationController extends \TYPO3\Flow\Security\Authentication\Controller\AbstractAuthenticationController {

	/**
	 * @Flow\Inject
	 * @var \RafaelKa\JasigPhpCas\Service\CasManager
	 */
	protected $casManager;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Configuration\ConfigurationManager
	 */
	protected $configurationManager;

	/**
	 * @var \TYPO3\Flow\Log\SystemLoggerInterface
	 * @Flow\Inject
	 */
	protected $systemLogger;

	/**
	 * Calls the authentication manager to authenticate all active tokens
	 * and redirects to the original intercepted request on success if there
	 * is one stored in the security context. If no intercepted request is
	 * found, the function simply returns.
	 *
	 * If authentication fails, the result of calling the defined
	 * $errorMethodName is returned.
	 *
	 * Note: Usually there is no need to override this action. You should use
	 * the according callback methods instead (onAuthenticationSuccess() and
	 * onAuthenticationFailure()).
	 *
	 * @return string
	 * @todo Make example Fluid form for Pretty Redirect Page. Maybe JS function with 5, 4, 3, 2, 1 -> redirect with fallback for non JS Browser.
	 */
	public function authenticateAction() {
		if (!$this->request->hasArgument('casProviderName')) {
			return parent::authenticateAction();
		}

		$providerName = $this->request->getArgument('casProviderName');
		$referer = $this->catchReferer($providerName);

		if ($this->definePrettyPreRedirectTemplate($providerName)) {
			$this->view->assignMultiple(array(
				'referer' => $referer,
				'__casAuthenticationProviderName' => $providerName));
		} else {
			$this->redirect('casAuthentication', NULL, NULL, array('__casAuthenticationProviderName' => $providerName));
		}
	}

	/**
	 * This method writes in CasManager referer request in session because redirecting to cas server is this referer lost.
	 * 
	 * You can get latest referer before redurect as follows: $this->casManager->getMiscellaneousByPath($providerName . '.beforeRedirectRefererUri');
	 * 
	 * @param string $providerName
	 * @return \TYPO3\Flow\Http\Uri
	 * @todo skip this step if currentRequest === refererRequest 
	 */
	private function catchReferer($providerName) {
		if (!$this->request->getHttpRequest()->getHeaders()->has('Referer') 
		|| !$this->request->getHttpRequest()->getHeaders()->has('Host')) {
			return NULL;
		}
		
		$hostName = $this->request->getHttpRequest()->getHeaders()->get('Host');
		$referer = $this->request->getHttpRequest()->getHeaders()->get('Referer');
		$refererUri = new \TYPO3\Flow\Http\Uri($referer);
		if ($refererUri->getHost() === $hostName) {
			$this->casManager->setMiscellaneousByPath($providerName . '.beforeRedirectRefererUri', $refererUri);
			return $referer;
		}
		return NULL;
	}

	/**
	 * This action makes session writing possible, because phpCAS stops request with redirect to cas server and brakes session writing.
	 * @see authenticateAction() ::: $this->catchReferer() 
	 *  
	 * @return string
	 */
	public function casAuthenticationAction() {
		//return parent::authenticateAction();

		$authenticationException = NULL;
		try {
			$this->authenticationManager->authenticate();
		} catch (\TYPO3\Flow\Security\Exception\AuthenticationRequiredException $exception) {
			$authenticationException = $exception;
		} catch (\TYPO3\Flow\Mvc\Exception\StopActionException $exception) {
			if ($exception->getCode() === 1375270925) {
				$this->makeRedirectByDetectingNewUser();
			}
		}

		if ($this->authenticationManager->isAuthenticated()) {
			$storedRequest = $this->securityContext->getInterceptedRequest();
			if ($storedRequest !== NULL) {
				$this->securityContext->setInterceptedRequest(NULL);
			}
			return $this->onAuthenticationSuccess($storedRequest);
		} else {
			$this->onAuthenticationFailure($authenticationException);
			return call_user_func(array($this, $this->errorMethodName));
		}

	}

	/**
	 * Defines template if configured for provider.
	 * 
	 * @param string $providerName
	 * @return boolean TRUE if some configuration found, FALSE if no configuration defined for given provider.
	 */
	private function definePrettyPreRedirectTemplate($providerName){
		$prettyPreRedirectPage = $this->configurationManager->getConfiguration(
			\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 
			'TYPO3.Flow.security.authentication.providers.' . $providerName . '.providerOptions.Miscellaneous.prettyPreRedirectPage');

		if (empty($prettyPreRedirectPage)) {
			return FALSE;
		}
		$log = '';
		if (isset($prettyPreRedirectPage['layoutRootPath']) && method_exists($this->view, 'setLayoutRootPath')) {
			$this->view->setLayoutRootPath($prettyPreRedirectPage['layoutRootPath']);
		} elseif (isset($prettyPreRedirectPage['layoutRootPath']) && !method_exists($this->view, 'setLayoutRootPath')) {
			$log .= sprintf('Pretty pre redirect page for "%s" provider can not be used, because you use custom teplating engine and this does not know method setLayoutRootPath().', $providerName);
		}
		if (isset($prettyPreRedirectPage['partialRootPath']) && method_exists($this->view, 'setPartialRootPath')) {
			$this->view->setPartialRootPath($prettyPreRedirectPage['partialRootPath']);
		} elseif (isset($prettyPreRedirectPage['partialRootPath']) && !method_exists($this->view, 'setPartialRootPath')) {
			$log .= sprintf('Pretty pre redirect page for "%s" provider can not be used, because you use custom teplating engine and this does not know method setPartialRootPath().', $providerName);
		}
		if (isset($prettyPreRedirectPage['templatePathAndFilename']) && method_exists($this->view, 'setTemplatePathAndFilename')) {
			$this->view->setTemplatePathAndFilename($prettyPreRedirectPage['templatePathAndFilename']);
		} elseif (isset($prettyPreRedirectPage['templatePathAndFilename']) && !method_exists($this->view, 'setTemplatePathAndFilename')) {
			$log .= sprintf('Pretty pre redirect page for "%s" provider can not be used, because you use custom teplating engine and this does not know method setTemplatePathAndFilename().', $providerName);
		}

		if (!empty($log)) {
			$this->systemLogger->log($log, LOG_ERR);
		}

		$this->view->assignMultiple($prettyPreRedirectPage);
		return TRUE;
	}

	/**
	 * @todo provide this functionality.
	 * redirection action must persist account and Party after accepting some agreements.
	 * 
	 * @return void
	 */
	protected function makeRedirectByDetectingNewUser() {
		$providerName = $this->request->getInternalArgument('__casAuthenticationProviderName');

		if (empty($providerName)) {
			throw new \TYPO3\Flow\Security\Exception('New user detected but can not provide redirect to defined action, because requered argument "__casAuthenticationProviderName" is not set.', 1375272628);
		}
	    
		if (!$this->casManager->isCasProvider($providerName)) {
			throw new \RafaelKa\JasigPhpCas\Exception\InvalidArgumentException(sprintf('New user detected but can not provide redirect to defined action, because "%s" provider is not of type "%s".', $providerName, \RafaelKa\JasigPhpCas\Service\CasManager::DEFAULT_CAS_PROVIDER), 1375273096);
		}
		
		$redirectByNewUser = $this->configurationManager->getConfiguration(
			\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 
			'TYPO3.Flow.security.authentication.providers.' . $providerName . '.providerOptions.Mapping.redirectByNewUser');
		
		if (empty($redirectByNewUser['@action'])) {
			throw new \RafaelKa\JasigPhpCas\Exception\InvalidArgumentException(sprintf(''));
		}
		if (empty($redirectByNewUser['@controller'])) {
			$controllerName = NULL;
		} else {
			$controllerName = $redirectByNewUser['@controller'];
		}
		if (empty($redirectByNewUser['@package'])) {
			$packageKey = NULL;
		} elseif (!empty($redirectByNewUser['@subpackage'])) {
			$packageKey = $redirectByNewUser['@package'] . '\\' . $redirectByNewUser['@subpackage'];
		} else {
			$packageKey = $redirectByNewUser['@package'];
		}
		if (empty($redirectByNewUser['@arguments'])) {
			$arguments = array('providerName' => $providerName);
		} else {
			$arguments = ArraysUtility::arrayMergeRecursiveOverrule(array('providerName' => $providerName), $redirectByNewUser['@arguments']);
		}
		if (empty($redirectByNewUser['@delay'])) {
			$delay = NULL;
		} else {
			$delay = $redirectByNewUser['@delay'];
		}
		if (empty($redirectByNewUser['@statusCode'])) {
			$statusCode = 303;
		} else {
			$statusCode = $redirectByNewUser['@statusCode'];
		}
		if (empty($redirectByNewUser['@format'])) {
			$format = NULL;
		} else {
			$format = $redirectByNewUser['@format'];
		}

		$this->redirect($redirectByNewUser['@action'], $controllerName, $packageKey, $arguments, $delay, $statusCode, $format);
	}
}

?>