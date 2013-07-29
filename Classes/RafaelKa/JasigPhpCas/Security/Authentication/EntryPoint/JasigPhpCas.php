<?php
namespace RafaelKa\JasigPhpCas\Security\Authentication\EntryPoint;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "RafaelKa.JasigPhpCas".  *
 *                                                                        *
 *                                                                        */

use	TYPO3\Flow\Annotations as Flow;

/**
 * An example for authentication entry point, that will not work here(see description for startAuthentication() method.).
 */
class JasigPhpCas extends \TYPO3\Flow\Security\Authentication\EntryPoint\AbstractEntryPoint {

	/**
	 * Entrypoint for redirecting to CAS-Server can not be fired, because it comes to infinite loop by:
	 * a: If authentication fails on SSO-Client site.
	 * b: If SSO Account missing some Role.
	 * c: search for AuthenticationRequiredException in Framework
	 * 
	 * Also do not try to force authentication in this way. 
	 * Using Flows own \TYPO3\Flow\Security\Authentication\EntryPoint\WebRedirect to some Action at SSO-Client-Machine, that checks current access state and gives feedback to customer/user ...
	 * 
	 * @return void
	 */
	public function startAuthentication(\TYPO3\Flow\Http\Request $request, \TYPO3\Flow\Http\Response $response) {
		throw new \Exception('This entrypoint "RafaelKa\JasigPhpCas\Security\Authentication\EntryPoint\JasigPhpCas" for redirecting to CAS-Server can not be fired, because it comes to infinite loop. Please use "WebRedirect" to login action with some options or form for credentials. Also dont try to force authentication with CAS, but use some action with message or fallback authentication provider', 1373057513);
	}
}

?>
