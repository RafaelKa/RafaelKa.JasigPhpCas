<?php
namespace RafaelKa\JasigPhpCas\Controller;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "RafaelKa.JasigPhpCas".  *
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

abstract class AbstractAuthenticationController extends \TYPO3\Flow\Security\Authentication\Controller\AbstractAuthenticationController {

	/**
	 * @Flow\Inject
	 * @var \RafaelKa\JasigPhpCas\Service\CasManager
	 */
	protected $casManager;

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
	 */
	public function authenticateAction() {
		if ($this->request->hasArgument('casProviderName')) {
			
		} else {
			parent::authenticateAction();
		}
		
	}

	/**
	 * @todo provide this functionality.
	 * redirection action must persist account and Party after accepting some agreements.
	 * @todo : make functional
	 * 1. check config if user must be redirected and if it is new user.
	 * 2. make sure that user is not authenticated before accepting agreements.
	 * 3. make sure that intercepted request stay stored in session.
	 * 4. think about .
	 * 
	 * @return void
	 */
	protected function redirectToNewUserAction() {
		$internalArguments = $this->request->getInternalArguments();
		if (!empty($internalArguments['__casAuthenticationProviderName'])
		&& $this->casManager->isCasProvider($internalArguments['__casAuthenticationProviderName'])) {
			$this->redirect($actionName, $controllerName, $packageKey, $arguments);
		}
	}

	/**
	 * @todo: provide this functionality.
	 * @return void
	 */
	protected function redirectToPrettyPreRedirectPage(){
		$internalArguments = $this->request->getInternalArguments();
		if (!empty($internalArguments['__casAuthenticationProviderName'])
		&& $this->casManager->isCasProvider($internalArguments['__casAuthenticationProviderName'])) {
			/* @var $view \TYPO3\Fluid\View\AbstractTemplateView */
			$view->setTemplatePathAndFilename($templatePathAndFilename);
		}
		//$this->response->setContent('<html><head><meta http-equiv="refresh" content="' . intval($delay) . ';url=' . $escapedUri . '"/></head></html>');
	}

	/**
	 * show pretty pre redirect page configured for each provieder.
	 * 
	 * @todo provide this functionality.: 
	 * 
	 * 1. set Fluid Template
	 * 2. assign redirect Uri for fallback on redirect fail.
	 * 3. assign example Fluid-Template to this doccoment
	 * 4. assign example in Settings.yaml.example. 
	 * 
	 * @return string
	 */
	public function preRedirectToCasServerAction() {
		$oldView = $this->view;
		
		// Check if in settings is preRedirectToCasServerAction.templatePathAndFilename is set and make following
		$this->view = new \TYPO3\Fluid\View\TemplateView();
		$this->view->setTemplatePathAndFilename($templatePathAndFilename);

		// Check if in settings is preRedirectToCasServerAction.templateSource is set and make following
		$this->view = new \TYPO3\Fluid\View\StandaloneView();
		$view->setTemplateSource($templateSource);
	}

}

?>