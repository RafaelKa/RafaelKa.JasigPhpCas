<?php
namespace RafaelKa\JasigPhpCas\Service\Mapping\Validator;

/*                                                                       *
 * This script belongs to the TYPO3 Flow package "RafaelKa.JasigPhpCas". *
 *                                                                       *
 *                                                                       */

use	TYPO3\Flow\Annotations as Flow;

/**
 * @todo Insert description
 * @todo move vilidation for mapper settings hither
 *
 * @Flow\Scope("singleton")
 */
class DefaultMapperValidator implements MappingValidatorInterface {

	/**
	 * @Flow\Transient
	 * @var array
	 */
	protected $casProvidersSettings = array();

	/**
	 * @Flow\Transient
	 * @var \TYPO3\Flow\Configuration\ConfigurationManager
	 */
	protected $configurationManager;

	/**
	 * @Flow\Transient
	 * @var array
	 */
	protected $validProviders = array();

	/**
	 * @Flow\Transient
	 * @var array
	 */
	protected $validationErrors = array();

	/**
	 *
	 * @param \TYPO3\Flow\Configuration\ConfigurationManager $configurationManager
	 * @return void
	 */
	public function __construct(\TYPO3\Flow\Configuration\ConfigurationManager $configurationManager) {
		$this->configurationManager = $configurationManager;
		$providers = $this->configurationManager->getConfiguration(
			\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
			'TYPO3.Flow.security.authentication.providers');
		foreach ($providers as $providerName => $providerSettings) {
			if ($providerSettings['provider'] === \RafaelKa\JasigPhpCas\Service\CasManager::DEFAULT_CAS_PROVIDER
			&& (empty($providerSettings['providerOptions']['Mapping']['mapperClass']) || $providerSettings['providerOptions']['Mapping']['mapperClass'] === \RafaelKa\JasigPhpCas\Service\CasManager::DEFAULT_CAS_MAPPER)
			&& !empty($providerSettings['providerOptions']['Mapping'])) {
				$this->casProvidersSettings[$providerName] = $providerSettings['providerOptions']['Mapping'];
				$this->validateMappingSettings($providerName);
			}
		}
	}

	/**
	 * Returns validation errors.
	 *
	 * @param type $providerName
	 * @return array
	 */
	public function getValidationErrors($providerName = NULL) {
		if (!empty($providerName)) {
			if (!empty($this->validationErrors[$providerName])) {
				return $this->validationErrors[$providerName];
			}
			return NULL;
		}
		return $this->validationErrors;
	}

	/**
	 * Validates mapping settings for provider.
	 * Also checks if TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping is declared.
	 *
	 * @param string $providerName Provider name to fetch an account from.
	 * @return boolean
	 */
	public function validateMappingSettings($providerName) {
		if (!empty($this->validProviders[$providerName]) && $this->validProviders[$providerName] === TRUE) {
			return TRUE;
		}

		$validationResult = TRUE;
		$mappingSettings = $this->configurationManager->getConfiguration(
			\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
			'TYPO3.Flow.security.authentication.providers.' . $providerName . '.providerOptions.Mapping');

		if (empty($mappingSettings)
		|| !is_array($mappingSettings)) {
			$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping is missing or empty. Please specify it in your Settings.yaml file.', 1370797669, array($providerName), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping', $providerName));
			return FALSE;
		}

		if (!empty($mappingSettings['mapperClass'])
		&& $mappingSettings['mapperClass'] !== \RafaelKa\JasigPhpCas\Service\CasManager::DEFAULT_CAS_MAPPER) {
			$this->validProviders[$providerName] = TRUE;
			return TRUE;
		}

		$validationResult = $this->validateRedirectByNewUserSettings($providerName) && $validationResult;
		$validationResult = $this->validateAccountSettings($providerName) && $validationResult;
		$validationResult = $this->validateRolesSettings($providerName) && $validationResult;
		$validationResult = $this->validatePartySettings($providerName) && $validationResult;

		if ($validationResult === TRUE) {
			$this->validProviders[$providerName] = TRUE;
		}

		return $validationResult;
	}

	/**
	 * Validates mapping settings for Account.
	 *
	 * @param string $providerName Provider name to fetch an account from.
	 * @return boolean
	 */
	public function validateAccountSettings($providerName) {
		$validationResult = TRUE;

		$casMappingSettings = $this->casProvidersSettings[$providerName];
		if (empty($casMappingSettings['Account'])) {
			$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Account is missing or empty. Please specify it in your Settings.yaml file.', 1370797670, array($providerName), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Account', $providerName));
			$validationResult = FALSE;
		}

		if (empty($casMappingSettings['Account']['accountidentifier'])) {
			$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Account.accountidentifier is missing or empty. Please specify it in your Settings.yaml file.', 1370797671, array($providerName), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Account.accountidentifier', $providerName));
			$validationResult = FALSE;
		}

		if (!empty($casMappingSettings['Account']['credentialsSource'])
		&& !is_string($casMappingSettings['Account']['credentialsSource'])) {
			$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('The value of TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Account.credentialsSource is not a string. Please specify it in your Settings.yaml file.', 1370797673, array($providerName), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Account.credentialsSource', $providerName));
			$validationResult = FALSE;
		}

		if (isset($casMappingSettings['Account']['useStaticProviderName'])
		&& !is_string($casMappingSettings['Account']['useStaticProviderName'])) {
			$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('The value of TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Account.useStaticProviderName is not a string. Please specify it in your Settings.yaml file properly or ommit this option.', 1371203321, array($providerName), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Account.useStaticProviderName', $providerName));
			$validationResult = FALSE;
		}
		// exists this provider?
		$globalProviders = $this->configurationManager->getConfiguration(\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'TYPO3.Flow.security.authentication.providers');
		if (!empty($casMappingSettings['Account']['useStaticProviderName']) && empty($globalProviders[$casMappingSettings['Account']['useStaticProviderName']])) {
			$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('Provider name "%s" defined in TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Account.useStaticProviderName does not exists. Please specify it in your Settings.yaml file properly or ommit this option.', 1371204022, array($providerName, $providerName), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Account.useStaticProviderName', $providerName));
			$validationResult = FALSE;
		}
		if (!empty($casMappingSettings['Account']['forceUseStaticProviderName'])
		&& !is_string($casMappingSettings['Account']['forceUseStaticProviderName'])) {
			$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('The value of TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Account.forceUseStaticProviderName is not a string. Please specify it in your Settings.yaml file properly or ommit this option.', 1371203323, array($providerName), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Account.forceUseStaticProviderName', $providerName));
			$validationResult = FALSE;
		}
		return $validationResult;
	}

	/**
	 * Validates mapping settings for Roles.
	 *
	 * @param string $providerName Provider name to fetch roles from.
	 * @return mixed
	 */
	public function validateRolesSettings($providerName) {
		$validationResult = TRUE;
		$casMappingSettings = $this->casProvidersSettings[$providerName];
		if (empty($casMappingSettings['Roles']) || !is_array($casMappingSettings['Roles'])) {
			$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Roles is missing or empty or is not an array. NOTE: Leastways one role must be defined. Please specify it in your Settings.yaml file.', 1370797672, array($providerName), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Roles', $providerName));
			return FALSE;
		}

		$iterator = 0;
		foreach ($casMappingSettings['Roles'] as $settingsForSingleRole) {
			if (empty($settingsForSingleRole['identifier']) && empty($settingsForSingleRole['staticIdentifier'])) {
				$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Roles.%s.identifier and ....Mapping.Roles.%s.staticIdentifier are empty or not set. One of both options must be defined. Use array path for CAS-Attributes by identifier or use roleidentifier(rolename without package key from Policy.yaml) by staticIdentifier. Please specify one of both options in your Settings.yaml file.', 1371056583, array($providerName, $iterator, $iterator), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Roles.%s.identifier', $providerName, $iterator, $iterator));
				$validationResult = FALSE;
			}
			if (empty($settingsForSingleRole['staticIdentifier'])
			&& (!empty($settingsForSingleRole['identifier']) && !is_string($settingsForSingleRole['identifier']) && !is_int($settingsForSingleRole['identifier']))) {
				$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Roles.%s.identifier is wrong. String or digit is expected but "%s" is specified.', 1373324861, array($providerName, $iterator, gettype($settingsForSingleRole['identifier'])), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Roles.%s.identifier', $providerName, $iterator));
				$validationResult = FALSE;
			}
			if (!empty($settingsForSingleRole['staticIdentifier'])
			&& (!empty($settingsForSingleRole['staticIdentifier']) && !is_string($settingsForSingleRole['staticIdentifier']) && !is_int($settingsForSingleRole['staticIdentifier']))) {
				$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Roles.%s.staticIdentifier is wrong. String or digit is expected but "%s" is specified.', 1373324862, array($providerName, $iterator, gettype($settingsForSingleRole['staticIdentifier'])), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Roles.%s.staticIdentifier', $providerName, $iterator));
				$validationResult = FALSE;
			}

			if (empty($settingsForSingleRole['packageKey'])) {
				$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Roles.%s.packageKey is missing or empty. Please specify it in your Settings.yaml file.', 1371056584, array($providerName, $iterator), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Roles.%s.packageKey', $providerName, $iterator));
				$validationResult = FALSE;
			}
			if (array_key_exists('rewriteRoles', $settingsForSingleRole)
			&& (empty($settingsForSingleRole['rewriteRoles']) || !is_array($settingsForSingleRole['rewriteRoles']))) {
				$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Roles.%s.rewriteRoles is set but empty or is not an array. Please specify it as associative array with possible value from CAS-Server as key and roleIdentifier(without package key) from Policy.yaml as value.', 1371058564, array($providerName, $iterator), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Roles.%s.rewriteRoles', $providerName, $iterator));
				$validationResult = FALSE;
			}
			$iterator++;
		}
		return $validationResult;
	}

	/**
	 * Validates mapping settings for Party.
	 *
	 * @param string $providerName Provider name to fetch a person from.
	 * @return boolean
	 */
	public function validatePartySettings($providerName) {
		$validationResult = TRUE;
		$casMappingSettings = $this->configurationManager->getConfiguration(\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'TYPO3.Flow.security.authentication.providers.' . $providerName . '.providerOptions.Mapping');

		if ((isset($casMappingSettings['doNotMapParties']) && $casMappingSettings['doNotMapParties'] === TRUE)
		|| empty($casMappingSettings['Party'])
		|| (isset($casMappingSettings['Party']['doNotMapParties']) && $casMappingSettings['Party']['doNotMapParties'] === TRUE )) {
			return TRUE;
		}

		if (!is_array($casMappingSettings['Party'])) {
			$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Party is defined but empty. Please specify it in your Settings.yaml file properly or delete this option. You can set TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Party.doNotMapParties to TRUE to skip this validation step and mapping of party.', 1371068064, array($providerName, $providerName), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Party', $providerName));
			$validationResult = FALSE;
		}

		if (empty($casMappingSettings['Party']['Person'])) {
				$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Party.Person is missing. Please specify it in your Settings.yaml file.', 1370797674, array($providerName), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Party.Person', $providerName));
			$validationResult = FALSE;
		}
		if (empty($casMappingSettings['Party']['Person']['name']) || !is_array($casMappingSettings['Party']['Person']['name'])) {
			$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Party.Person.name is missing, empty or not an array. Please specify at least one Party.name.propertyName in your Settings.yaml file.', 1370797675, array($providerName), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Party.Person.name', $providerName));
			$validationResult = FALSE;
		}

		return $this->validatePrimaryElectronicAddressSettings($providerName) && $validationResult;
	}

	/**
	 * Validates mapping settings for Party->primaryElectronicAddress.
	 *
	 * @param type $providerName
	 * @return boolean
	 */
	private function validatePrimaryElectronicAddressSettings ($providerName) {
		$validationResult = TRUE;
		$casMappingSettings = $this->configurationManager->getConfiguration(\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'TYPO3.Flow.security.authentication.providers.' . $providerName . '.providerOptions.Mapping');
		// Party.primaryElectronicAddress and Party.primaryElectronicAddress.* are optional but if defined
		if (empty($casMappingSettings['Party']['Person']['primaryElectronicAddress'])) {
			return TRUE;
		}

		if (empty($casMappingSettings['Party']['Person']['primaryElectronicAddress']['identifier'])) {
			$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Party.Person.primaryElectronicAddress.identifier is missing. Please specify it in your Settings.yaml file.', 1370797676, array($providerName), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Party.Person.primaryElectronicAddress.identifier', $providerName));
			$validationResult = FALSE;
		}
		if (empty($casMappingSettings['Party']['Person']['primaryElectronicAddress']['staticType'])
		&& empty($casMappingSettings['Party']['Person']['primaryElectronicAddress']['type'])) {
			$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Party.Person.primaryElectronicAddress.type and ...Party.Person.primaryElectronicAddress.staticType are missing. One of both options must be defined.', 1370797677, array($providerName), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Party.Person.primaryElectronicAddress.type', $providerName));
			$validationResult = FALSE;
		}
		if (empty($casMappingSettings['Party']['Person']['primaryElectronicAddress']['staticUsage'])
		&& empty($casMappingSettings['Party']['Person']['primaryElectronicAddress']['usage'])) {
			$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Party.Person.primaryElectronicAddress.usage and ...Party.Person.primaryElectronicAddress.staticUsage are missing. One of both options must be defined.', 1370797678, array($providerName), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Party.Person.primaryElectronicAddress.usage', $providerName));
			$validationResult = FALSE;
		}
		return $validationResult;
	}

	/**
	 *
	 *
	 * @param string $providerName
	 * @return boolean
	 */
	private function validateRedirectByNewUserSettings($providerName) {
		$casMappingSettings = $this->configurationManager->getConfiguration(\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'TYPO3.Flow.security.authentication.providers.' . $providerName . '.providerOptions.Mapping');

		if (empty($casMappingSettings['redirectByNewUser'])) {
			return TRUE;
		}

		$validationResult = TRUE;

		if (empty($casMappingSettings['redirectByNewUser']['@package'])) {
			$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.redirectByNewUser.@package is missing or empty. Please specify it in your Settings.yaml file.', 1372270684, array($providerName), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.redirectByNewUser.@package', $providerName));
			$validationResult = FALSE;
		} elseif (!is_string($casMappingSettings['redirectByNewUser']['@package'])) {
			$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('String is expected for TYPO3.Flow.security.authentication.providers.' . $providerName . '.providerOptions.Mapping.redirectByNewUser.@package but "' . gettype($casMappingSettings['redirectByNewUser']['@package']) . '" given.', 1372271184);
			$validationResult = FALSE;
		}

		if (isset($casMappingSettings['redirectByNewUser']['@subpackage'])
		&& !is_string($casMappingSettings['redirectByNewUser']['@subpackage'])) {
			$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('String is expected for TYPO3.Flow.security.authentication.providers.' . $providerName . '.providerOptions.Mapping.redirectByNewUser.@subpackage but "' . gettype($casMappingSettings['redirectByNewUser']['@subpackage']) . '" given.', 1372271185);
			$validationResult = FALSE;
		}

		if (empty($casMappingSettings['redirectByNewUser']['@controller'])) {
			$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.redirectByNewUser.@controller is missing or empty. Please specify it in your Settings.yaml file.', 1372270694, array($providerName), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.redirectByNewUser.@controller', $providerName));
			$validationResult = FALSE;
		} elseif (!is_string($casMappingSettings['redirectByNewUser']['@controller'])) {
			$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('String is expected for TYPO3.Flow.security.authentication.providers.' . $providerName . '.providerOptions.Mapping.redirectByNewUser.@controller but "' . gettype($casMappingSettings['redirectByNewUser']['@controller']) . '" given.', 1372271098);
			$validationResult = FALSE;
		}

		if (empty($casMappingSettings['redirectByNewUser']['@action'])) {
			$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.redirectByNewUser.@action is missing or empty. Please specify it in your Settings.yaml file.', 1372270695, array($providerName), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.redirectByNewUser.@action', $providerName));
			$validationResult = FALSE;
		} elseif (!is_string($casMappingSettings['redirectByNewUser']['@action'])) {
			$this->validationErrors[$providerName][] = new \TYPO3\Flow\Error\Error('String is expected for TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.redirectByNewUser.@action but "%s" given.', 1372271099, array($providerName, gettype($casMappingSettings['redirectByNewUser']['@action'])), sprintf('TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.redirectByNewUser.@action', $providerName));
			$validationResult = FALSE;
		}

		return $validationResult;
	}

	/**
	 * Checks if default mapper is configured to map party for given provider.
	 *
	 * @param string $providerName
	 * @return boolean
	 */
	private function shouldMapParty($providerName) {
		$casMappingSettings = $this->configurationManager->getConfiguration(\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'TYPO3.Flow.security.authentication.providers.' . $providerName . '.providerOptions.Mapping');

		if (isset($casMappingSettings['Party'])
		&& (empty($casMappingSettings['Party']['doNotMapParties']) || $casMappingSettings['Party']['doNotMapParties'] !== TRUE )
		&& (empty($casMappingSettings['doNotMapParties']) || $casMappingSettings['doNotMapParties'] !== TRUE )) {
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Validates mapping settings for all providers, which are 'RafaelKa\JasigPhpCas\Authentication\Provider\PhpCasAuthenticationProvider'.
	 *
	 * @param string $validationMode
	 * @return mixed
	 */
	public function validateMappingSettingsForAllCasProviders($validationMode) {
		foreach ($this->casProvidersSettings as $providerName) {
			$this->validateAccountSettings($providerName);
			$this->validateRolesSettings($providerName);
			$this->validatePartySettings($providerName);
		}
	}

	/**
	 * Use caching or simple property to economize multiple call for validating
	 *
	 * @param string $providerName
	 * @return boolean
	 */
	public function isProviderValidated($providerName) {
		if (!empty($this->validProviders[$providerName]) && $this->validProviders[$providerName] === TRUE) {
			return TRUE;
		}
		return FALSE;
	}
}
?>
