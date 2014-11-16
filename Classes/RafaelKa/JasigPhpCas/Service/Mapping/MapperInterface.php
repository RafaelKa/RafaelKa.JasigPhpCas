<?php

namespace RafaelKa\JasigPhpCas\Service\Mapping;

/*                                                                       *
 * This script belongs to the TYPO3 Flow package "RafaelKa.JasigPhpCas". *
 *                                                                       *
 *                                                                       */

use	TYPO3\Flow\Annotations as Flow;

/**
 * Interface for a mapping service that maps a CAS Attributes to Flow's Account, Roles, Party
 */
interface MapperInterface {

	/**
	 * Returns mapped user, according to the configuration rules.
	 * <pre>
	 * <b>Possible configurations:</b>
	 * <b>persistAccounts</b>  = TRUE|FALSE*
	 * <b>doNotMapParties</b>  = TRUE|FALSE*
	 * <b>persistParties</b>   = TRUE|FALSE*
	 * 
	 * <b>Auto generated values:
	 * persistAccounts	doNotMapParties	    persistParties					Party in Account</b>
	 * FALSE*		FALSE*		    FALSE (Autovalue owing to persistAccount:FALSE)	yes (depends on config) **
	 * FALSE*		TRUE		    FALSE (Autovalue owing to persistAccount:FALSE)	without
	 * TRUE 		TRUE		    FALSE (Autovalue owing to doNotMapParty:TRUE)	without
	 * TRUE 		FALSE*		    TRUE						yes (depends on config)
	 * 
	 * <b>
	 * (*)	    Default value.
	 * (**)	    Default if nothing specified</b></pre>
	 * 
	 * @param string $providerName
	 * @param array $casAttributes
	 * @return \TYPO3\Flow\Security\Account According to the configuration see description of this method. 
	 */
	public function getMappedUser($providerName, array $casAttributes = NULL);

	/**
	 * Returns Account without referenced party but with roles. 
	 * 
	 * @param string $providerName Provider name to fetch an account from.
	 * @param array $casAttributes
	 * @return \TYPO3\Flow\Security\Account
	 */
	public function getAccount($providerName, array $casAttributes);

	/**
	 * Returns Collection of roles. 
	 * 
	 * @param string $providerName Provider name to fetch roles from.
	 * @param array $casAttributes
	 * @return \Doctrine\Common\Collections\Collection<\TYPO3\Flow\Security\Policy\Role>
	 */
	public function getRoles($providerName, array $casAttributes);

	/**
	 * Returns Party 
	 * 
	 * @todo rename this method to getParty()
	 * @param string $providerName Provider name to fetch a person from.
	 * @param array $casAttributes
	 * @return \TYPO3\Party\Domain\Model\Person
	 */
	public function getPerson($providerName, array $casAttributes);
	
	/**
	 * Is in two cases needed:
	 * a: if you want to persist Accounts per default
	 * b: if you want confirmation
	 * 
	 * @param \TYPO3\Flow\Security\Account $account
	 * @return void
	 */
	public function finalizePersistingNewUser(\TYPO3\Flow\Security\Account $account);
}