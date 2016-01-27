<?php

namespace RafaelKa\JasigPhpCas\Service\Mapping;

/*                                                                       *
 * This script belongs to the TYPO3 Flow package "RafaelKa.JasigPhpCas". *
 *                                                                       *
 *                                                                       */

use Doctrine\Common\Collections\ArrayCollection;
use RafaelKa\JasigPhpCas\Exception\JasigPhpCasSecurityException;
use RafaelKa\JasigPhpCas\Service\CasManager;
use RafaelKa\JasigPhpCas\Service\Mapping\Exception\WrongMappingConfigurationException;
use RafaelKa\JasigPhpCas\Service\Mapping\Validator\DefaultMapperValidator;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Configuration\ConfigurationManager;
use TYPO3\Flow\Mvc\Exception\StopActionException;
use TYPO3\Flow\Persistence\PersistenceManagerInterface;
use TYPO3\Flow\Security\Account;
use TYPO3\Flow\Security\AccountRepository;
use TYPO3\Flow\Security\Authentication\TokenInterface;
use TYPO3\Flow\Security\Exception\NoSuchRoleException;
use TYPO3\Flow\Security\Policy\PolicyService;
use TYPO3\Flow\Utility\Arrays as ArraysUtility;
use TYPO3\Party\Domain\Model\AbstractParty;
use TYPO3\Party\Domain\Model\ElectronicAddress;
use TYPO3\Party\Domain\Model\Person;
use TYPO3\Party\Domain\Model\PersonName;
use TYPO3\Party\Domain\Repository\PartyRepository;

/**
 * Description of DefaultMapper.
 *
 * @Flow\Scope("singleton")
 */
class DefaultMapper implements MapperInterface
{
    const
    /*
     * The default class name for CAS authentication provider.
     * @var string
     */
    DEFAULT_CAS_PROVIDER = 'RafaelKa\JasigPhpCas\Authentication\Provider\PhpCasAuthenticationProvider',

    /*
     * The default class name for CAS authentication provider.
     * @var string
     */
    DEFAULT_MAPPING_VALIDATOR = 'RafaelKa\JasigPhpCas\Service\Validator\DefaultMapperValidator',

    /*
     * The path to settings for Account configuration.
     * @var string
     */
    SETTINGS_PATH_FOR_ACCOUNT = 'TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Account',

    /*
     * The path to settings for Roles configuration.
     * @var string
     */
    SETTINGS_PATH_FOR_ROLES = 'TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Roles',

    /*
     * The path to settings for Roles configuration.
     * @var string
     */
    SETTINGS_PATH_FOR_PARTY = 'TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Party',

    /*
     * The path to settings for Roles configuration.
     * @var string
     */
    SETTINGS_PATH_FOR_ElectronicAddress = 'TYPO3.Flow.security.authentication.providers.%s.providerOptions.Mapping.Party.primaryElectronicAddress';

    /**
     * The Flow settings for settings of CAS providers.
     *
     * @Flow\Transient
     *
     * @var array
     */
    protected $settings = [];

    /**
     * The default settings for this mapper.
     * 
     * @Flow\Transient
     *
     * @var array
     */
    protected $defaultSettings = [];

    /**
     * @Flow\Transient
     *
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @Flow\Inject
     * @Flow\Transient
     *
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     * @Flow\Transient
     *
     * @var AccountRepository
     */
    protected $accountRepository;

    /**
     * @Flow\Inject
     * @Flow\Transient
     *
     * @var PartyRepository
     */
    protected $partyRepository;

    /**
     * @Flow\Inject
     * @Flow\Transient
     *
     * @var PolicyService
     */
    protected $policyService;

    /**
     * @Flow\Inject
     * @Flow\Transient
     *
     * @var CasManager
     */
    protected $casManager;

    /**
     * @Flow\Transient
     *
     * @var DefaultMapperValidator
     */
    protected $settingsValidator;

    /**
     * @Flow\Inject
     *
     * @var \TYPO3\Flow\Security\Context
     */
    protected $securityContext;

    /**
     * Constructor for this Mapper.
     *
     * @param ConfigurationManager   $configurationManager
     * @param CasManager             $casManager
     * @param DefaultMapperValidator $settingsValidator
     */
    public function __construct(ConfigurationManager $configurationManager, CasManager $casManager, DefaultMapperValidator $settingsValidator)
    {
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
    public function buildSettings()
    {
        $globalProviders = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'TYPO3.Flow.security.authentication.providers');
        foreach ($globalProviders as $providerName => $providerSettings) {
            if ($this->casManager->isCasProvider($providerName)
            && $this->isDefaultMapperChoosed($providerName)) {
                $this->settings[$providerName] = $providerSettings['providerOptions']['Mapping'];
            }
        }
    }

    /**
     * Checks configuration for given provider and returns TRUE if provider uses DefaultMapper.
     *
     * @param string $providerName
     *
     * @return bool
     */
    private function isDefaultMapperChoosed($providerName)
    {
        $mapper = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'TYPO3.Flow.security.authentication.providers.'.$providerName.'.Mapping.mapperClass');
        if (empty($mapper) || $mapper === CasManager::DEFAULT_CAS_MAPPER) {
            return true;
        }

        return false;
    }

    /**
     * Returns settings for all cas providers.
     * Arrays key is provider name.
     *
     * @return array
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * Returns mapped user, according to configuration rules.
     * <pre>
     * <b>Possible configurations:</b>
     * <b>persistAccounts</b>  = TRUE|FALSE*
     * <b>doNotMapParties</b>  = TRUE|FALSE*
     * <b>persistParties</b>   = TRUE|FALSE*.
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
     * @param array  $casAttributes
     *
     * @return Account According to the configuration see description of this method.
     */
    public function getMappedUser($providerName, array $casAttributes = null)
    {
        if (empty($casAttributes) && $this->casManager->getCasAttributes($providerName) === []) {
            return;
        } elseif (empty($casAttributes)) {
            $casAttributes = $this->casManager->getCasAttributes($providerName);
        }

        $account = $this->getAccount($providerName, $casAttributes);
        $party = $this->getPerson($providerName, $casAttributes);
        $roles = $this->getRoles($providerName, $casAttributes);

        $account->setRoles($roles);

        if ($this->settings[$providerName]['persistAccounts'] === false
        && $this->settings[$providerName]['doNotMapParties'] === false) {
            $account->setParty($party);

            return $account;
        }

        if ($this->settings[$providerName]['persistAccounts'] === false
        && $this->settings[$providerName]['doNotMapParties'] === true) {
            return $account;
        }

        if ($this->settings[$providerName]['persistAccounts'] === true
        && $this->settings[$providerName]['doNotMapParties'] === true) {
            // @todo : skip return account if redirectByNewUser is defined and this is new user
            $this->persistAccount($providerName, $account);

            return $account;
        }

        if ($this->settings[$providerName]['persistAccounts'] === true
        && $this->settings[$providerName]['persistParties'] === false) {
            $this->persistAccount($providerName, $account);

            return $account;
        }

        if ($this->settings[$providerName]['persistAccounts'] === true
        && $this->settings[$providerName]['persistParties'] === true) {
            // @todo : skip return account if redirectByNewUser is defined and this is new user
            $account->setParty($party);
            $this->persistAccount($providerName, $account);

            return $account;
        }

        return;
    }

    /**
     * Returns Account.
     *
     * @param string $providerName  Provider name to fetch an account from.
     * @param array  $casAttributes
     *
     * @throws JasigPhpCasSecurityException
     *
     * @return Account
     */
    public function getAccount($providerName, array $casAttributes)
    {
        $accountSettings = $this->settings[$providerName]['Account'];

        $account = new Account();
        $account->setAuthenticationProviderName($providerName);

        $accountIdentifier = ArraysUtility::getValueByPath($casAttributes, $accountSettings['accountidentifier']);
        if (is_string($accountIdentifier)) {
            $account->setAccountIdentifier($accountIdentifier);
        } else {
            throw new JasigPhpCasSecurityException(sprintf('Cas attribute for ... .%s.providerOptions.Mapping.Account.accountidentifier is not a string. Doubtless you configured path to CAS-Attributes-array-value wrong.', $providerName));
        }

        if (isset($accountSettings['useStaticProviderName']) && empty($accountSettings['forceUseStaticProviderName'])) {
            $account->setAuthenticationProviderName($accountSettings['useStaticProviderName']);
        }
        if (isset($accountSettings['forceUseStaticProviderName'])) {
            $account->setAuthenticationProviderName($accountSettings['forceUseStaticProviderName']);
        }

        if (isset($accountSettings['periodOfValidity']) && is_int($accountSettings['periodOfValidity'])) {
            $date = new \DateTime();
            $date->modify('+'.$accountSettings['periodOfValidity'].' day');
            $account->setExpirationDate($date);
        }

        return $account;
    }

    /**
     * Persists new accounts only. If account is allready persisted, then account will be overridden with account from repository.
     *
     * @param string  $providerName Provider name
     * @param Account $account      account to persist.
     *
     * @return void
     */
    private function persistAccount($providerName, Account &$account)
    {
        $accountFromRepository = $this->accountRepository->findActiveByAccountIdentifierAndAuthenticationProviderName($account->getAccountIdentifier(), $account->getAuthenticationProviderName());
        if ($accountFromRepository instanceof Account) {
            $account = $accountFromRepository;
            $this->updateRolesInAccount($providerName, $account);

            return;
        }

        $casAttributes = $this->casManager->getCasAttributes($providerName);
        $account->setRoles($this->getRoles($providerName, $casAttributes));

        $this->mekeRedirectByNewUserIfNeeded($providerName, $account);

        $this->finalizePersistingNewUser($account);
    }

    /**
     * Adds new roles from CAS server since last authentication if some was added in CAS-Server.
     * Is used only if Account was persisted. See persistAccount() method.
     *
     * @param string  $providerName Provider name. WARNING: not in settings set useStaticProviderNameByPersistingAccounts.
     * @param Account $account
     *
     * @return void
     *
     * @todo : move persistAll() at shutdown
     */
    private function updateRolesInAccount($providerName, Account &$account)
    {
        $casAttributes = $this->casManager->getCasAttributes($providerName);
        $casServerRoles = $this->getRoles($providerName, $casAttributes);
        $accountMustBeUpdated = false;
        foreach ($casServerRoles as $casServerRole) {
            $accountMustBeUpdated = $accountMustBeUpdated == true ? $accountMustBeUpdated : !$account->hasRole($casServerRole);
            $account->addRole($casServerRole);
        }

        if ($accountMustBeUpdated) {
            $this->accountRepository->update($account);
        }

        $this->persistenceManager->persistAll();
    }

    /**
     * If Action for new users is defined and new user is detected, then makes this method redirect to defined Action and breaks authentication.
     *
     * You must persist new user self and afterwards authenticate this user by calling $this->casManager->authenticateNewUser($providerName).
     *
     * @param string  $providerName
     * @param Account $account
     *
     * @throws StopActionException
     *
     * @return void
     */
    private function mekeRedirectByNewUserIfNeeded($providerName, Account $account)
    {
        $redirectControllerAndAction =
            $this->configurationManager->getConfiguration(
                ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
                'TYPO3.Flow.security.authentication.providers.'.$providerName.'.providerOptions.Mapping.redirectByNewUser');
        if (!empty($redirectControllerAndAction)) {
            $this->casManager->setMiscellaneousByPath($providerName.'.Account', $account);
            $this->fixWhiteScreenByAbortingAuthentication($providerName);
            throw new StopActionException('New user detectded.', 1375270925);
        }
    }

    /**
     * If authentication status is set to AUTHENTICATION_NEEDED by some token, then each action that calls some security method returns blank/white screen.
     *
     * This method sets authentication status to NO_CREDENTIALS_GIVEN by tokens, where authentication status was set to AUTHENTICATION_NEEDED by aborting authenticaion.
     *
     * @param string $providerName
     *
     * @return void
     */
    private function fixWhiteScreenByAbortingAuthentication($providerName)
    {
        $casTokens = $this->securityContext->getAuthenticationTokensOfType(CasManager::DEFAULT_CAS_TOKEN);
        /* @var $casToken \RafaelKa\JasigPhpCas\Security\Authentication\Token\PhpCasToken */
        foreach ($casTokens as $casToken) {
            if ($casToken->getAuthenticationStatus() !== TokenInterface::AUTHENTICATION_NEEDED
            || $casToken->getAuthenticationProviderName() !== $providerName) {
                continue;
            }
            $casToken->setAuthenticationStatus(TokenInterface::NO_CREDENTIALS_GIVEN);
        }
    }

    /**
     * Persists new Account.
     *
     * @param Account $account
     *
     * @return void
     */
    public function finalizePersistingNewUser(Account $account)
    {
        $party = $account->getParty();
        if ($party instanceof AbstractParty) {
            $this->partyRepository->add($party);
        }

        $this->accountRepository->add($account);
        $this->persistenceManager->persistAll();
    }

    /**
     * Returns Collection of roles.
     *
     * @param string $providerName  Provider name to fetch roles from.
     * @param array  $casAttributes
     *
     * @throws \Exception
     * @throws Exception\WrongMappingConfigurationException
     *
     * @return \Doctrine\Common\Collections\Collection<\TYPO3\Flow\Security\Policy\Role>
     */
    public function getRoles($providerName, array $casAttributes)
    {
        $rolesSettings = $this->settings[$providerName]['Roles'];
        $rolesCollection = new ArrayCollection();

        $iterator = 0;
        foreach ($rolesSettings as $roleSettings) {

            // Map Cas Attributes
            if (empty($roleSettings['staticIdentifier']) && !empty($roleSettings['identifier']) && is_string($roleSettings['identifier'])) {
                $roleIdentifier = ArraysUtility::getValueByPath($casAttributes, $roleSettings['identifier']);

                if (!is_string($roleIdentifier) && !is_int($roleIdentifier)) {
                    throw new WrongMappingConfigurationException(sprintf('Cas attribute catched by path "%s" from CAS-Attributes array defined at ....%s.providerOptions.Mapping.Roles.%s.identifier must be a string but "%s" is given.', $roleSettings['identifier'], $providerName, $iterator, gettype($roleIdentifier)), 1371209193);
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
                    $role = $this->policyService->getRole($roleSettings['packageKey'].':'.$roleIdentifier);
                } catch (NoSuchRoleException $exc) {
                    /* @var $exc \Exception */
                    if ($exc->getCode() === 1353085860) {
                        $role = $this->policyService->createRole($roleSettings['packageKey'].':'.$roleIdentifier);
                    } else {
                        throw new \Exception('Unknown exception by PolicyService->getRole(). Message: '.$exc->getMessage().' Code: '.$exc->getCode(), 1371211532);
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
     * @param array  $rewriteRules      Rules defined by ... Mapping.Roles.%d.rewriteRoles
     *
     * @return string
     */
    private function rewriteRole($casRoleIdentifier, $rewriteRules)
    {
        if (empty($rewriteRules[$casRoleIdentifier])) {
            return $casRoleIdentifier;
        }

        return $rewriteRules[$casRoleIdentifier];
    }

    /**
     * Returns Party.
     *
     * @param string $providerName  Provider name to fetch a person from.
     * @param array  $casAttributes
     *
     * @return Person
     */
    public function getPerson($providerName, array $casAttributes)
    {
        $partySettings = $this->settings[$providerName]['Party'];
        if (isset($partySettings['doNotMapParties']) && $partySettings['doNotMapParties'] === true) {
            return;
        }

        if (isset($partySettings['Person']['name'])) {
            $title = '';
            $firstName = '';
            $middleName = '';
            $lastName = '';
            $otherName = '';
            $alias = '';
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

            $person = new Person();
            $personName = new PersonName($title, $firstName, $middleName, $lastName, $otherName, $alias);
            $person->setName($personName);
        } else {
            return;
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
            $primaryElectronicAddress = new ElectronicAddress();
            $primaryElectronicAddress->setIdentifier($identifier);
            $primaryElectronicAddress->setType($type);
            $primaryElectronicAddress->setUsage($usage);

            $person->setPrimaryElectronicAddress($primaryElectronicAddress);
        }

        return $person;
    }

    /**
     * Checks settings for mapping Settings.yaml for each CAS-Provider.
     *
     * @todo make it possible to get all messages in command line.
     *
     * @throws Exception\WrongMappingConfigurationException
     *
     * @return mixed TRUE if all setting are valid. Array with providername as key and array with errors.
     */
    public function validateSettings()
    {
        if (FLOW_SAPITYPE === 'CLI') {
            return true;
        }

        if (FLOW_SAPITYPE === 'Web') {
            $validationErrors = $this->settingsValidator->getValidationErrors();
            foreach ($validationErrors as $providerName => $errors) {
                if (!empty($errors)) {
                    throw new WrongMappingConfigurationException(sprintf('Configuration for "%s" provider is not valid. Use "%s validate:provider %s" to get more information', $providerName, $this->getFlowInvocationString(), $providerName), 1373543447);
                }
            }
        }

        return false;
    }

    /**
     * Returns the CLI Flow command depending on the environment.
     *
     * @return string
     */
    private function getFlowInvocationString()
    {
        if (DIRECTORY_SEPARATOR === '/' || (isset($_SERVER['MSYSTEM']) && $_SERVER['MSYSTEM'] === 'MINGW32')) {
            return './flow';
        } else {
            return 'flow.bat';
        }
    }
}
