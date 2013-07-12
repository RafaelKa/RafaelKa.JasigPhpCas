<?php
namespace RafaelKa\JasigPhpCas\Security\Authentication\EntryPoint;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "RafaelKa.JasigPhpCas".  *
 *                                                                        *
 *                                                                        */

use	TYPO3\Flow\Annotations as Flow;
/**
 * Description of JasigPhpCasEntryPoint
 *
 * @author rafael
 */
class JasigPhpCas extends \TYPO3\Flow\Security\Authentication\EntryPoint\AbstractEntryPoint {

	/**
	 * Entrypoints for redirecting to CAS-Server can not be fired, because it comes to infinite loop.
	 * 
	 * 
	 * @Flow\Inject
	 * @var \RafaelKa\JasigPhpCas\Service\CasManager
	 */
	protected $casManager;

	public function startAuthentication(\TYPO3\Flow\Http\Request $request, \TYPO3\Flow\Http\Response $response) {
		throw new \Exception('This entrypoint "RafaelKa\JasigPhpCas\Security\Authentication\EntryPoint\JasigPhpCas" for redirecting to CAS-Server can not be fired, because it comes to infinite loop. Please use "WebRedirect" to login action with some options or form for credentials. Also dont try to force authentication with CAS, but use some action with message or fallback authentication provider', 1373057513);
	}
}

?>
