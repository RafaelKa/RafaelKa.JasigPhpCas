<?php
namespace RafaelKa\JasigPhpCas\Security\Authentication\Token;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "RafaelKa.JasigPhpCas".  *
 *                                                                        *
 *                                                                        */

use	TYPO3\Flow\Annotations as Flow;

/**
 * Contract for an authentication token with CAS-Server.
 * 
 * This is an interface for next version of this package. Also currently not used.
 */
interface CasTokenInterface {

	/**
	 * Sets cas attributes in session.
	 * 
	 * @param array $casAttributes
	 * @return void
	 */
	public function setCasAttributes($casAttributes);

	/**
	 * Returns cas attributes in session, Only if is authenticated.
	 * 
	 * @return array $casAttributes
	 */
	public function getCasAttributes();

	/**
	 * Sets some value by path in miscellaneous array.
	 * 
	 * @param string $path
	 * @param array $value
	 * @return void
	 */
	public function setMiscellaneousByPath($path, $value);

	/**
	 * Returns value by path from miscellaneous array.
	 * 
	 * @param string $path
	 * @return array|string
	 */
	public function getMiscellaneousByPath($path);

	/**
	 * Returns whole miscellaneous array for this token.
	 * 
	 * @return array Array with miscellaneous things by provider name.
	 */
	public function getMiscellaneous();

	
}

?>
