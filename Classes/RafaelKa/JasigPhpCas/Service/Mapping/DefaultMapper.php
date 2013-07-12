<?php
namespace RafaelKa\JasigPhpCas\Service\Mapping;

/*                                                                       *
 * This script belongs to the TYPO3 Flow package "RafaelKa.JasigPhpCas". *
 *                                                                       *
 *                                                                       */

use	TYPO3\Flow\Annotations as Flow,
	TYPO3\Flow\Utility\Arrays as ArraysUtility,
	TYPO3\Flow\Reflection\ObjectAccess;

/**
 * Description of JasigCASMappingService
 *
 * @Flow\Scope("singleton")
 */
class DefaultMapper implements MapperInterface {

	const
	/**
	 * The default class name for CAS authentication provider.
	 * @var string
	 */ 
	DEFAULT_CAS_PROVIDER = 'RafaelKa\JasigPhpCas\Authentication\Provider\PhpCasAuthenticationProvider',

	/**
	 * The default class name for CAS authentication provider.
	 * @var string
	 */
	DEFAULT_MAPPING_VALIDATOR = 'RafaelKa\JasigPhpCas\Service\Validator\DefaultMapperValidator';

	const
	/**
	 * The path to settings for Account configuration.
	 * @var string
	 */ 
	SETTINGS_PATH_FOR_ACCOUNT = 'TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Account',

	/**
	 * The path to settings for Roles configuration.
	 * @var string
	 */ 
	SETTINGS_PATH_FOR_ROLES = 'TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Roles',

	/**
	 * The path to settings for Roles configuration.
	 * @var string
	 */ 
	SETTINGS_PATH_FOR_PARTY = 'TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Party',

	/**
	 * The path to settings for Roles configuration.
	 * @var string
	 */ 
	SETTINGS_PATH_FOR_ElectronicAddress = 'TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Party.primaryElectronicAddress';

	/**
	 * The Flow settings for settings of CAS providers
	 * @Flow\Transient
	 * @var array
	 */
	protected $settings = array();

	/**
	 * @Flow\Transient
	 * @var \TYPO3\Flow\Configuration\ConfigurationManager
	 */
	protected $configurationManager;

	/**
	 * @Flow\Inject
	 * @Flow\Transient
	 * @var \TYPO3\Flow\Persistence\PersistenceManagerInterface
	 */
	protected $persistenceManager;

	/**
	 * @Flow\Inject
	 * @Flow\Transient
	 * @var \TYPO3\Flow\Security\AccountRepository
	 */
	protected $accountRepository;

	/**
	 * @Flow\Inject
	 * @Flow\Transient
	 * @var \TYPO3\Flow\Security\Policy\RoleRepository
	 */
	protected $roleRepository;

	/**
	 * @Flow\Inject
	 * @Flow\Transient
	 * @var \TYPO3\Party\Domain\Repository\PartyRepository
	 */
	protected $partyRepository;

	/**
	 * @Flow\Inject
	 * @Flow\Transient
	 * @var \TYPO3\Flow\Security\Policy\PolicyService
	 */
	protected $policyService;

	/**
	 * @Flow\Inject
	 * @Flow\Transient
	 * @var \RafaelKa\JasigPhpCas\Service\CasManager
	 */
	protected $casManager;

	/**
	 * @Flow\Transient
	 * @var \RafaelKa\JasigPhpCas\Service\Mapping\Validator\DefaultMapperValidator
	 */
	protected $settingsValidator;

	/**
	 * Constructor for this Mapper
	 * 
	 * @param \TYPO3\Flow\Configuration\ConfigurationManager $configurationManager
	 * @param \RafaelKa\JasigPhpCas\Service\CasManager $casManager
	 * @param \RafaelKa\JasigPhpCas\Service\Mapping\Validator\DefaultMapperValidator $settingsValidator
	 * @return void
	 */
	public function __construct(\TYPO3\Flow\Configuration\ConfigurationManager $configurationManager, \RafaelKa\JasigPhpCas\Service\CasManager $casManager, \RafaelKa\JasigPhpCas\Service\Mapping\Validator\DefaultMapperValidator $settingsValidator) {
		$this->configurationManager = $configurationManager;
		$this->casManager = $casManager;
		$this->settingsValidator = $settingsValidator;
		$this->buildSettings();
		$this->validateSettings();
	}

	/**
	 * Builds an array with settings for all CAS providers.
	 * 
	 * @todo build settings only for providers, which configured for default mapper
	 * 
	 * @return void
	 */
	public function buildSettings(){
		$globalProviders = $this->configurationManager->getConfiguration(\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'TYPO3.Flow.security.authentication.providers');
		foreach ($globalProviders as $providerName => $providerSettings) {

			if ($this->casManager->isCasProvider($providerName)
			&& $this->isDefaultMapperChoosed($providerName)) {
				$this->settings[$providerName] = $providerSettings['providerOptions']['Mapping'];
			}
		}
	}

	/**
	 * Checks configuration for given provider and returns TRUE if provider uses DefaultMapper
	 * 
	 * @param string $providerName
	 * @return boolean
	 */
	private function isDefaultMapperChoosed($providerName) {
		$mapper = $this->configurationManager->getConfiguration(
			\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 
			'TYPO3.Flow.security.authentication.providers.' . $providerName . '.Mapping.mapperClass');
		if (empty($mapper) || $mapper === \RafaelKa\JasigPhpCas\Service\CasManager::DEFAULT_CAS_MAPPER) {
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Returns settings for all cas providers. 
	 * Arrays key is provider name.
	 * 
	 * @return array
	 */
	public function getSettings() {
		return $this->settings;
	}

	/**
	 * Returns mapped user, according to configuration rules.
	 * <pre>
	 * <b>Possible configurations:</b>
	 * <b>persistAccounts</b>  = TRUE|FALSE*
	 * <b>doNotMapParties</b>  = TRUE|FALSE*
	 * <b>persistParties</b>   = TRUE|FALSE*
	 * 
	 * <b>Auto generated values:
	 * persistAccounts	doNotMapParties	    persistParties					Party is set in Account</b>
	 * FALSE*		FALSE*		    FALSE (Autovalue owing to persistAccount:FALSE)	yes (depends on config) **
	 * FALSE*		TRUE		    FALSE (Autovalue owing to persistAccount:FALSE)	without
	 * TRUE 		TRUE		    FALSE (Autovalue owing to doNotMapParties:TRUE)	without
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
	public function getMappedUser($providerName, array $casAttributes = NULL) {
		if (empty($casAttributes)) {
			$this->casManager->getCasAttributes($providerName);
		}
		$party = $this->getPerson($providerName, $casAttributes);
		$account = $this->getAccount($providerName, $casAttributes);
		$roles = $this->getRoles($providerName, $casAttributes);

		$account->setRoles($roles);

		if ($this->settings[$providerName]['persistAccounts'] === FALSE
		&& $this->settings[$providerName]['doNotMapParties'] === FALSE) {
			$account->setParty($party);
			return $account;
		}

		if ($this->settings[$providerName]['persistAccounts'] === FALSE
		&& $this->settings[$providerName]['doNotMapParties'] === TRUE) {
			return $account;
		}
		
		if ($this->settings[$providerName]['persistAccounts'] === TRUE
		&& $this->settings[$providerName]['doNotMapParties'] === TRUE) {
			return $account;
		}

		if ($this->settings[$providerName]['persistAccounts'] === TRUE
		&& $this->settings[$providerName]['doNotMapParties'] === FALSE
		&& $this->settings[$providerName]['persistParties'] === TRUE) {
			
			$account->setParty($party);
		}

		return $account;
	}

	/**
	 * Returns Account 
	 * 
	 * @param string $providerName  Provider name to fetch an account from.
	 * @param array $casAttributes
	 * @return \TYPO3\Flow\Security\Account 
	 */
	public function getAccount($providerName, array $casAttributes) {
		$accountSettings = $this->settings[$providerName]['Account'];

		$account = new \TYPO3\Flow\Security\Account();
		$account->setAuthenticationProviderName($providerName);

		$accountIdentifier = ArraysUtility::getValueByPath($casAttributes, $accountSettings['accountidentifier']);
		if (is_string($accountIdentifier)) {
			$account->setAccountIdentifier($accountIdentifier);
		} else {
			throw new \RafaelKa\JasigPhpCas\Exception\JasigPhpCasSecurityException(sprintf('Cas attribute for ... .%s.providerOptions.Mapping.Account.accountidentifier is not a string. Doubtless you configured path to CAS-Attributes-array-value wrong.', $providerName));
		}

		if (isset($accountSettings['useStaticProviderNameByPersistingAccounts']) && empty($accountSettings['forceUseStaticProviderNameByPersistingAccounts'])) {
			$account->setAuthenticationProviderName($accountSettings['useStaticProviderNameByPersistingAccounts']);
		}
		if (isset($accountSettings['forceUseStaticProviderNameByPersistingAccounts'])) {
			$account->setAuthenticationProviderName($accountSettings['forceUseStaticProviderNameByPersistingAccounts']);
		}

		if (isset($accountSettings['periodOfValidity']) && is_int($accountSettings['periodOfValidity'])) {
			$date = new \DateTime();
			$date->modify('+' . $accountSettings['periodOfValidity'] . ' day');
			$account->setExpirationDate($date);
		} 

		return $account;
	}

	/**
	 * Returns Collection of roles. 
	 * 
	 * @param string $providerName  Provider name to fetch roles from.
	 * @param array $casAttributes
	 * @return \Doctrine\Common\Collections\Collection<\TYPO3\Flow\Security\Policy\Role>
	 */
	public function getRoles($providerName, array $casAttributes) {
		$rolesSettings = $this->settings[$providerName]['Roles'];
		$rolesCollection = new \Doctrine\Common\Collections\ArrayCollection();

		$iterator = 0;
		foreach ($rolesSettings as $roleSettings) {

			// Map Cas Attributes
			if (empty($roleSettings['staticIdentifier']) && !empty($roleSettings['identifier']) && is_string($roleSettings['identifier'])) {
				$roleIdentifier = ArraysUtility::getValueByPath($casAttributes, $roleSettings['identifier']);

				if (!is_string($roleIdentifier) && !is_int($roleIdentifier)) {
					throw new \RafaelKa\JasigPhpCas\Service\Mapping\Exception\WrongMappingConfigurationException(sprintf('Cas attribute catched by path "%s" from CAS-Attributes array defined at ....%s.providerOptions.Mapping.Roles.%s.identifier must be a string but "%s" is given.', $roleSettings['identifier'], $providerName, $iterator, gettype($roleIdentifier)), 1371209193);
				}

				if (isset($roleSettings['rewriteRoles'])) {
					$roleIdentifier = $this->rewriteRole($roleIdentifier, $roleSettings['rewriteRoles']);
				}
			}
			// Map static Role
			if (isset($roleSettings['staticIdentifier']) && is_string($roleSettings['staticIdentifier'])) {
				$roleIdentifier = $roleSettings['staticIdentifier'];
				if (isset($roleSettings['rewriteRoles'])) {
					$roleIdentifier = $this->rewriteRole($roleIdentifier, $roleSettings['rewriteRoles']);
				}
			}

			if (is_string($roleIdentifier) || is_int($roleIdentifier)) {
				try {
					$role = $this->policyService->getRole($roleSettings['packageKey'] . ':' . $roleIdentifier);
				} catch (\TYPO3\Flow\Security\Exception\NoSuchRoleException $exc) {
					/* @var $exc \Exception */
					if ($exc->getCode() === 1353085860) {
						$role =	$this->policyService->createRole($roleSettings['packageKey'] . ':' . $roleIdentifier);
					} else {
						throw new \Exception('Unknown exception by \TYPO3\Flow\Security\Policy\PolicyService->getRole(). Message: ' . $exc->getMessage() . ' Code: ' . $exc->getCode(), 1371211532); 
					}
				}
			}

			$rolesCollection->add($role);
			$iterator++;
		}
		return $rolesCollection;
	}

	/**
	 * Returns Collection of roles. 
	 * 
	 * @param string $casRoleIdentifier
	 * @param array $rewriteRules Rules defined by ... Mapping.Roles.%d.rewriteRoles
	 * @return string
	 */
	private function rewriteRole($casRoleIdentifier, $rewriteRules) {
		if (empty($rewriteRules[$casRoleIdentifier])) {
			return $casRoleIdentifier;
		}
		return $rewriteRules[$casRoleIdentifier];
	}

	/**
	 * Returns Party 
	 * 
	 * @param string $providerName Provider name to fetch a person from.
	 * @param array $casAttributes
	 * @return \TYPO3\Party\Domain\Model\Person
	 */
	public function getPerson($providerName, array $casAttributes) {
		$partySettings = $this->settings[$providerName]['Party'];
		if (isset($partySettings['doNotMapParties']) && $partySettings['doNotMapParties'] === TRUE) {
			return NULL;
		}

		if (isset($partySettings['Person']['name'])) {
			$title = ''; $firstName = ''; $middleName = ''; $lastName = ''; $otherName = ''; $alias = '';
			if (isset($partySettings['Person']['name']['alias']) && is_string($partySettings['Person']['name']['alias'])) {
				$alias = ArraysUtility::getValueByPath($casAttributes, $partySettings['Person']['name']['alias']);
			}
			if (isset($partySettings['Person']['name']['firstName']) && is_string($partySettings['Person']['name']['firstName'])) {
				$firstName = ArraysUtility::getValueByPath($casAttributes, $partySettings['Person']['name']['firstName']);
			}
			if (isset($partySettings['Person']['name']['lastName']) && is_string($partySettings['Person']['name']['lastName'])) {
				$lastName = ArraysUtility::getValueByPath($casAttributes, $partySettings['Person']['name']['lastName']);
			}
			if (isset($partySettings['Person']['name']['middleName']) && is_string($partySettings['Person']['name']['middleName'])) {
				$middleName = ArraysUtility::getValueByPath($casAttributes, $partySettings['Person']['name']['middleName']);
			}
			if (isset($partySettings['Person']['name']['otherName']) && is_string($partySettings['Person']['name']['otherName'])) {
				$otherName = ArraysUtility::getValueByPath($casAttributes, $partySettings['Person']['name']['otherName']);
			}
			if (isset($partySettings['Person']['name']['title']) && is_string($partySettings['Person']['name']['title'])) {
				$title = ArraysUtility::getValueByPath($casAttributes, $partySettings['Person']['name']['title']);
			}

			$person = new \TYPO3\Party\Domain\Model\Person();
			$personName = new \TYPO3\Party\Domain\Model\PersonName($title, $firstName, $middleName, $lastName, $otherName, $alias);
			$person->setName($personName);
		} else {
			return NULL;
		}

		if (isset($partySettings['Person']['primaryElectronicAddress'])) {

			if (isset($partySettings['Person']['primaryElectronicAddress']['identifier']) 
			&& is_string($partySettings['Person']['primaryElectronicAddress']['identifier'])) {
				$identifier = ArraysUtility::getValueByPath($casAttributes, $partySettings['Person']['primaryElectronicAddress']['identifier']);
				// todo : throw exception if not string
				if (!is_string($identifier)) {
					
				}
			}

			if (empty($partySettings['Person']['primaryElectronicAddress']['staticType']) 
			&& isset($partySettings['Person']['primaryElectronicAddress']['type']) 
			&& is_string($partySettings['Person']['primaryElectronicAddress']['type'])) {
				$type = ArraysUtility::getValueByPath($casAttributes, $partySettings['Person']['primaryElectronicAddress']['type']);
				// todo : throw exception if not string
				if (!is_string($type)) {
					
				}
			}
			if (empty($partySettings['Person']['primaryElectronicAddress']['staticUsage']) 
			&& isset($partySettings['Person']['primaryElectronicAddress']['usage']) 
			&& is_string($partySettings['Person']['primaryElectronicAddress']['usage'])) {
				$usage = ArraysUtility::getValueByPath($casAttributes, $partySettings['Person']['primaryElectronicAddress']['usage']);
				// todo : throw exception if not string
				if (!is_string($usage)) {
					
				}
			}
			// static values for type and usage
			if (isset($partySettings['Person']['primaryElectronicAddress']['staticType']) 
			&& is_string($partySettings['Person']['primaryElectronicAddress']['staticType'])) {
				$type = $partySettings['Person']['primaryElectronicAddress']['staticType'];
				// todo : throw exception if not string
				if (!is_string($type)) {
					
				}
			}
			if (isset($partySettings['Person']['primaryElectronicAddress']['staticUsage']) 
			&& is_string($partySettings['Person']['primaryElectronicAddress']['staticUsage'])) {
				$usage = $partySettings['Person']['primaryElectronicAddress']['staticUsage'];
				// todo : throw exception if not string
				if (!is_string($usage)) {
					
				}
			}
			$primaryElectronicAddress = new \TYPO3\Party\Domain\Model\ElectronicAddress();
			$primaryElectronicAddress->setIdentifier($identifier);
			$primaryElectronicAddress->setType($type);
			$primaryElectronicAddress->setUsage($usage);

			$person->setPrimaryElectronicAddress($primaryElectronicAddress);
		}

//		if (empty($partySettings['persistParty']) || $partySettings['persistParty'] !== TRUE) {
//			$this->setSessionedUuidByParty($providerName, $person);
//		}

		return $person;
	}

	/**
	 * Compares two person names.
	 * 
	 * @param \TYPO3\Party\Domain\Model\PersonName $person1
	 * @param \TYPO3\Party\Domain\Model\PersonName $person2
	 * @return boolean ELSE if some properties are different.
	 */
	private function equalPartyName(\TYPO3\Party\Domain\Model\PersonName $personName1, \TYPO3\Party\Domain\Model\PersonName $personName2) {
		$personName1Properties = ObjectAccess::getGettableProperties($personName1);
		$personName2Properties = ObjectAccess::getGettableProperties($personName2);
		unset($personName1Properties['__isInitialized__']);
		unset($personName2Properties['__isInitialized__']);
		
		foreach ($personName1Properties as $propertyname => $personName1PropertyValue) {
			if ($personName1PropertyValue !== $personName2Properties[$propertyname]) {
				return FALSE;
			}
		}
	}

	/**
	 * compare two electronic addresses
	 * 
	 * @todo : handle redirect by updated cas attributes in some other place
	 * @param \TYPO3\Party\Domain\Model\ElectronicAddress $address1
	 * @param \TYPO3\Party\Domain\Model\ElectronicAddress $address2
	 * @return boolean
	 */
	private function equalElectronikAddress(\TYPO3\Party\Domain\Model\ElectronicAddress $address1, \TYPO3\Party\Domain\Model\ElectronicAddress $address2) {
		$electronicAddress1Properties = ObjectAccess::getGettableProperties($address1);
		$electronicAddress2Properties = ObjectAccess::getGettableProperties($address2);
		foreach ($electronicAddress1Properties as $propertyname => $electronicAddress1PropertyValue) {
			if ($electronicAddress1PropertyValue !== $electronicAddress2Properties[$propertyname]) {
				return FALSE;
			}
		}
		return TRUE;
	}

	/**
	 * First must be from repository.
	 * 
	 * @param \TYPO3\Party\Domain\Model\PersonName $personName1
	 * @param \TYPO3\Party\Domain\Model\PersonName $personName2
	 * @return void
	 */
	private function updateFirstPartyNameWithSecond(\TYPO3\Party\Domain\Model\PersonName &$personName1, \TYPO3\Party\Domain\Model\PersonName &$personName2) {
		$personName1->setAlias($personName2->getAlias());
		$personName1->setFirstName($personName2->getFirstName());
		$personName1->setLastName($personName2->getLastName());
		$personName1->setMiddleName($personName2->getMiddleName());
		$personName1->setOtherName($personName2->getOtherName());
		$personName1->setTitle($personName2->getTitle());
	}

	/**
	 * checks settings for mapping Settings.yaml for each CAS-Provider.
	 * 
	 * @todo make it possible to get all messages in command line.
	 * @return mixed TRUE if all setting are valid. Array with providername as key and array with errors.
	 */
	public function validateSettings() {
		if (FLOW_SAPITYPE === 'CLI') {
			return TRUE;
		}
		$settingsAreValid = TRUE;
		if (FLOW_SAPITYPE === 'Web') {
			$validationErrors = $this->settingsValidator->getValidationErrors();
			foreach ($validationErrors as $providerName => $errors) {
				if (!empty($errors)) {
					throw new \RafaelKa\JasigPhpCas\Service\Mapping\Exception\WrongMappingConfigurationException(sprintf('Configuration for "%s" provider is not valid. Use "%s validate:provider %s" to get more information', $providerName , $this->getFlowInvocationString(), $providerName), 1373543447);
				}
			}
		}
	}

	/**
	 * Returns the CLI Flow command depending on the environment
	 *
	 * @return string
	 */
	private function getFlowInvocationString() {
		if (DIRECTORY_SEPARATOR === '/' || (isset($_SERVER['MSYSTEM']) && $_SERVER['MSYSTEM'] === 'MINGW32')) {
			return './flow';
		} else {
			return 'flow.bat';
		}
	}

	/**
	 * Validates settings for given cas provider. 
	 * WARNING: Given provider must be of type RafaelKa\JasigPhpCas\Authentication\Provider\PhpCasAuthenticationProvider
	 * validateConfigurationForCasProvider
	 * 
	 * @param string $providerName provider name to Validate
	 * @return boolean 
	 * @throws \RafaelKa\JasigPhpCas\Exception\InvalidArgumentException
	 * @throws \TYPO3\Flow\Security\Exception\MissingConfigurationException
	 */
	public function validateMappingConfigurationByCasProvider($providerName) {
		
	}

	/**
	 * 
	 * 
	 * @param string $providerName
	 * @return array
	 */
	private function getAccountMappingConfiguration($providerName) {
		return $this->configurationManager->getConfiguration(
			\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 
			sprintf(self::SETTINGS_PATH_FOR_ACCOUNT, $providerName));
	}

	/**
	 * 
	 * 
	 * @param string $providerName
	 * @return array
	 */
	private function getRolesMappingConfiguration($providerName) {
		return $this->configurationManager->getConfiguration(
			\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 
			sprintf(self::SETTINGS_PATH_FOR_ROLES, $providerName));
	}

	/**
	 * 
	 * 
	 * @param string $providerName
	 * @return array
	 */
	private function getPartyMappingConfiguration($providerName) {
		return $this->configurationManager->getConfiguration(
			\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 
			sprintf(self::SETTINGS_PATH_FOR_PARTY, $providerName));
	}

	/**
	 * 
	 * 
	 * @param string $providerName
	 * @return array
	 */
	private function getElectronicAddressMappingConfiguration($providerName) {
		return $this->configurationManager->getConfiguration(
			\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 
			sprintf(self::SETTINGS_PATH_FOR_PARTY, $providerName));
	}
}

?>