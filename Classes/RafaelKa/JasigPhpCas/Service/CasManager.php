<?php

namespace RafaelKa\JasigPhpCas\Service;

/*                                                                       *
 * This script belongs to the TYPO3 Flow package "RafaelKa.JasigPhpCas". *
 *                                                                       *
 *                                                                       */

use phpCAS;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Utility\Arrays as ArraysUtility;

/**
 * Description of SingleSignOnManager.
 *
 *
 * @Flow\Scope("session")
 *
 * @todo move \phpCAS::* and DEFAULT_CAS_SERVER_* to PhpCasClientLocum. Remove systemLogger property if not needed.<br>
 * @todo move validate*
 * @todo replace miscellaneous properly
 * @todo finish and move resolveResoursceToRealpath() to Utility to use that static. Remove packageManager property
 */
class CasManager
{
    const

    /*
     * The default class name for CAS authentication provider.
     */
    DEFAULT_CAS_PROVIDER = 'RafaelKa\JasigPhpCas\Security\Authentication\Provider\PhpCasAuthenticationProvider',

    /*
     * The default class name for CAS Token
     */
    DEFAULT_CAS_TOKEN = 'RafaelKa\JasigPhpCas\Security\Authentication\Token\PhpCasToken',

    /*
     * The default class name for CAS attributes mapper class.
     */
    DEFAULT_CAS_MAPPER = 'RafaelKa\JasigPhpCas\Service\Mapping\DefaultMapper',

    /*
     * The default CAS server_version
     */
    DEFAULT_CAS_SERVER_VERSION = '2.0',

    /*
     * The default CAS server_port
     */
    DEFAULT_CAS_SERVER_PORT = 443;

    /**
     * settings from all cas providers. Provider name is the key.
     *
     * @var array
     */
    protected $miscellaneous = [];

    /**
     * settings from all cas providers. Provider name is the key.
     *
     * @var array
     */
    protected $casAttributes = [];

    /**
     * @Flow\Inject
     *
     * @var \TYPO3\Flow\Configuration\ConfigurationManager
     * @Flow\Transient
     */
    protected $configurationManager;

    /**
     * @Flow\Inject
     *
     * @var \TYPO3\Flow\Object\ObjectManagerInterface
     * @Flow\Transient
     */
    protected $objectManager;

    /**
     * @Flow\Inject
     *
     * @var \TYPO3\Flow\Reflection\ReflectionService
     * @Flow\Transient
     */
    protected $reflectionService;

    /**
     * @Flow\Inject
     *
     * @var \TYPO3\Flow\Package\PackageManagerInterface
     * @Flow\Transient
     */
    protected $packageManager;

    /**
     * @Flow\Inject
     * @Flow\Transient
     *
     * @var \TYPO3\Flow\Log\SystemLoggerInterface
     */
    protected $systemLogger;

    /**
     * @Flow\Transient
     *
     * @var array
     */
    protected $casProviders = [];

    /**
     * @Flow\Transient
     *
     * @var array
     */
    protected $providerMappers = [];

    /**
     * @Flow\Transient
     * @Flow\Inject
     *
     * @var \TYPO3\Flow\Security\Context
     */
    protected $securityContext;

    /**
     * forces to authenticate.
     *
     * @todo force throwing exception by wrong settings for mapping before redirect.
     *
     * @param string $providerName Provider name to authenticate
     * @Flow\Session(autoStart = TRUE)
     *
     * @throws \RafaelKa\JasigPhpCas\Exception\InvalidArgumentException
     * @throws \RafaelKa\JasigPhpCas\Exception\CasAttributesEmptyException
     *
     * @return array array with CAS attributes.
     */
    public function authenticate($providerName)
    {
        if (!$this->isCasProvider($providerName)) {
            throw new \RafaelKa\JasigPhpCas\Exception\InvalidArgumentException(sprintf('%s is not CAS-Provider.'.$providerName), 1371247195);
        }
        $this->forceThrowingValidationException();

        if (!empty($this->casAttributes[$providerName])) {
            return $this->casAttributes[$providerName];
        }

        $this->createPhpCasClient($providerName);
        if (\phpCAS::isAuthenticated()) {
            $this->casAttributes[$providerName] = \phpCAS::getAttributes();

            // @todo handle phpCAS session -> use sessionhanling from php CAS or delete this.
            session_unset();
            session_destroy();
            if (empty($this->casAttributes[$providerName]) || !is_array($this->casAttributes[$providerName])) {
                throw new \RafaelKa\JasigPhpCas\Exception\CasAttributesEmptyException('Attributes given by CAS-Server are empty or not an array. Please trace it by your self!', 1371467113);
            }

            return $this->casAttributes[$providerName];
        }
        \phpCAS::forceAuthentication();
    }

    /**
     * Description.
     *
     * @param string $providerName
     *
     * @return void
     */
    public function finalizeAuthenticationByNewUser($providerName)
    {
        $casTokens = $this->securityContext->getAuthenticationTokensOfType(\RafaelKa\JasigPhpCas\Service\CasManager::DEFAULT_CAS_TOKEN);
        /* @var $casToken \RafaelKa\JasigPhpCas\Security\Authentication\Token\PhpCasToken */
        foreach ($casTokens as $casToken) {
            if ($casToken->getAuthenticationProviderName() === $providerName
            && !empty($this->miscellaneous[$providerName]['Account'])) {
                $casToken->setAccount($this->miscellaneous[$providerName]['Account']);
                $casToken->setAuthenticationStatus(\TYPO3\Flow\Security\Authentication\TokenInterface::AUTHENTICATION_SUCCESSFUL);
                $mapper = $this->getMapperByProviderName($providerName);
                $mapper->finalizePersistingNewUser($this->miscellaneous[$providerName]['Account']);
            }
        }
    }

    /**
     * All mapper should validate setting on creation time.
     *
     * @return void
     */
    private function forceThrowingValidationException()
    {
        $casProviderNames = $this->getAllCasProviderNames();
        foreach ($casProviderNames as $casProviderName) {
            $this->getMapperByProviderName($casProviderName);
        }
    }

    /**
     * Returns all providers, where provider is RafaelKa\JasigPhpCas\Security\Authentication\Provider\PhpCasAuthenticationProvider.
     *
     * @return array
     */
    public function getAllCasProviderNames()
    {
        if ($this->casProviders !== []) {
            return $this->casProviders;
        }

        $providers = $this->configurationManager->getConfiguration(
            \TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'TYPO3.Flow.security.authentication.providers');
        foreach ($providers as $providerName => $providerSettings) {
            if ($this->isCasProvider($providerName)) {
                $this->casProviders[] = $providerName;
            }
        }
        $this->casProviders = array_unique($this->casProviders);

        return $this->casProviders;
    }

    /**
     * @return void
     */
    public function forceCasAuthentification()
    {
        \phpCAS::forceAuthentication();
    }

    /**
     * @param string $providerName  Provider name
     * @param array  $casAttributes attributes given by CAS-Server.
     * @Flow\Session(autoStart = TRUE)
     *
     * @return void
     */
    public function startSession($providerName, array $casAttributes)
    {
        $this->casAttributes[$providerName] = $casAttributes;
    }

    /**
     * Returns Account.
     *
     * @param string $providerName  Provider name to fetch an Account from.
     * @param array  $casAttributes attributes given by cas server
     *
     * @return \TYPO3\Flow\Security\Account
     */
    public function getAccount($providerName, $casAttributes = null)
    {
        /* @var $mapper \RafaelKa\JasigPhpCas\Service\Mapping\DefaultMapper */
        $mapper = $this->getMapperByProviderName($providerName);

        if ($casAttributes === null && !empty($this->casAttributes[$providerName])) {
            $casAttributes = $this->casAttributes[$providerName];
        }
        $account = $mapper->getMappedUser($providerName, $casAttributes);

        return $account;
    }

    /**
     * Returns Collection of roles.
     *
     * @param array  $casAttributes
     * @param string $providerName  Provider name to fetch Roles from.
     *
     * @return \Doctrine\Common\Collections\Collection<\TYPO3\Flow\Security\Policy\Role>
     */
    public function getRoles(array $casAttributes, $providerName)
    {
        $mapper = $this->getMapperByProviderName($providerName);

        return $mapper->getRoles($providerName, $casAttributes);
    }

    /**
     * Returns Party.
     *
     * @param string $providerName  Provider name to fetch a person from.
     * @param array  $casAttributes
     *
     * @return \TYPO3\Party\Domain\Model\Person
     */
    public function getPerson($providerName, array $casAttributes)
    {
        $mapper = $this->getMapperByProviderName($providerName);

        return $mapper->getPerson($providerName, $casAttributes);
    }

    /**
     * Returns the class names of the tokens this provider can authenticate.
     * This must be an RafaelKa\JasigPhpCas\Authentication\Provider\PhpCasAuthenticationProvider otherwise throws this method \RafaelKa\JasigPhpCas\Exception\InvalidArgumentException .
     *
     * @param string $providerName defined in Settings.yaml
     *
     * @throws \RafaelKa\JasigPhpCas\Exception\InvalidArgumentException
     * @throws \RafaelKa\JasigPhpCas\Exception\InvalidConfigurationException
     *
     * @return array
     */
    public function getTokenClassNamesByProviderName($providerName)
    {
        if (!$this->isCasProvider($providerName)) {
            throw new \RafaelKa\JasigPhpCas\Exception\InvalidArgumentException(sprintf('Bad parameter for $providerName given in "%s()". The "%s" is not "%s". Please make sure that TYPO3.Flow.security.authentication.providers.%s.provider is "%s" or don\'t validate this provider with "%s->%s()".', __FUNCTION__, $providerName, self::DEFAULT_CAS_PROVIDER, $providerName, self::DEFAULT_CAS_PROVIDER, __CLASS__, __FUNCTION__), 1370797660);
        }

        $tokenClasses = $this->configurationManager->getConfiguration(
            \TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'TYPO3.Flow.security.authentication.providers.'.$providerName.'.tokenClasses');
        if (!empty($tokenClasses) && is_array($tokenClasses)) {
            foreach ($tokenClasses as $tokenClassName) {
                if (!class_exists($tokenClassName)) {
                    throw new \RafaelKa\JasigPhpCas\Exception\InvalidConfigurationException(sprintf('Class "%s" configured in Settings.yaml at "TYPO3.Flow.security.authentication.providers.%s.tokenClasses" does not exists.', $tokenClassName, $providerName), 1370947266);
                }
                if (!$this->reflectionService->isClassImplementationOf($tokenClassName, '\TYPO3\Flow\Security\Authentication\TokenInterface')) {
                    throw new \RafaelKa\JasigPhpCas\Exception\InvalidConfigurationException(sprintf('Class "%s" configured in Settings.yaml at "TYPO3.Flow.security.authentication.providers.%s.tokenClasses" is not implementation of "\TYPO3\Flow\Security\Authentication\TokenInterface". Please rediclare "%s" as "\TYPO3\Flow\Security\Authentication\TokenInterface" adapter Class.', $tokenClassName, $providerName, $tokenClassName), 1370947266);
                }
            }

            if (!in_array(self::DEFAULT_CAS_TOKEN, $tokenClasses)) {
                array_unshift($tokenClasses, self::DEFAULT_CAS_TOKEN);
            }

            return $tokenClasses;
        }

        return [self::DEFAULT_CAS_TOKEN];
    }

    /**
     * Adds something to storage.
     * WARN: you can not overrule something.
     * If you want to overrule something then use please setMiscellaneousByPath().
     *
     * @param string $providerName  provider name
     * @param array  $miscellaneous storage for miscellaneous internal things.
     * @Flow\Session(autoStart = TRUE)
     *
     * @return void
     */
    public function addMiscellaneous($providerName, array $miscellaneous)
    {
        $this->miscellaneous[$providerName] = ArraysUtility::arrayMergeRecursiveOverrule($miscellaneous, $this->miscellaneous[$providerName], false, false);
    }

    /**
     * @param string $path
     * @param array  $value
     * @Flow\Session(autoStart = TRUE)
     *
     * @return void
     */
    public function setMiscellaneousByPath($path, $value)
    {
        $this->miscellaneous = ArraysUtility::setValueByPath($this->miscellaneous, $path, $value);
    }

    /**
     * returns value by path from miscellaneous array.
     *
     * @param string $path
     *
     * @return array|string
     */
    public function getMiscellaneousByPath($path)
    {
        return ArraysUtility::getValueByPath($this->miscellaneous, $path);
    }

    /**
     * Adds something.
     *
     * @param string $providerName provider name
     *
     * @return array array with miscellaneous things by provider name
     */
    public function getMiscellaneous($providerName)
    {
        if (!empty($this->miscellaneous[$providerName])) {
            return $this->miscellaneous[$providerName];
        }

        return [];
    }

    /**
     * Returns cas attributes array from cas server.
     *
     * @param string $providerName provider name
     *
     * @return array
     */
    public function getCasAttributes($providerName)
    {
        if (!empty($this->casAttributes[$providerName])) {
            return $this->casAttributes[$providerName];
        }

        return [];
    }

    /**
     * checks validation status for given provider.
     *
     * @todo Disable this functionality, because settingsvalidation in production context is fast enough.
     *
     * @param string $providerName
     *
     * @return bool
     */
    private function wasProviderValidated($providerName)
    {
        if (isset($this->miscellaneous[$providerName]['casSettingsAreValid'])
        && $this->miscellaneous[$providerName]['casSettingsAreValid'] === true) {
            return true;
        }

        return false;
    }

    /**
     * Marks given provider as successfully validated.
     *
     * @param string $providerName
     *
     * @return void
     */
    private function markProviderAsValidated($providerName)
    {
        $this->miscellaneous[$providerName]['casSettingsAreValid'] = true;
    }

    /**
     * Checks whether a provider is a CAS provider.
     *
     * @param string $providerName provider name
     *
     * @return bool
     */
    public function isCasProvider($providerName)
    {
        $provider = $this->configurationManager->getConfiguration(\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'TYPO3.Flow.security.authentication.providers.'.$providerName);
        if (!empty($provider['provider']) && $provider['provider'] === self::DEFAULT_CAS_PROVIDER) {
            return true;
        }

        return false;
    }

    /**
     * initilize phpCAS::client().
     *
     * @todo move to other class
     *
     * @param string $providerName defined in Settings.yaml
     *
     * @return void
     */
    private function createPhpCasClient($providerName)
    {
        if (!$this->isCasProvider($providerName)) {
            throw new \RafaelKa\JasigPhpCas\Exception\InvalidArgumentException(sprintf('%s is not CAS-Provider.'.$providerName), 1371247195);
        }

        $casClientSettings = $this->getClientSettingsByProviderName($providerName);
        try {
            \phpCAS::client(
                $casClientSettings['server_version'],
                $casClientSettings['server_hostname'],
                $casClientSettings['server_port'],
                $casClientSettings['server_uri'],
                $casClientSettings['changeSessionID']);
        } catch (\Exception $exc) {
            throw new \RafaelKa\JasigPhpCas\Exception\JasigPhpCasException('CasAuthenticationProvider::createPhpCasClient() \phpCAS::client() can not be initialized.'.PHP_EOL.$exc->getMessage(), 1371245280);
        }
        $this->setCasServerCACert($providerName);
    }

    /**
     * @todo make this options usable.
     * @todo move to other class
     *
     * @param string $providerName defined in Settings.yaml
     *
     * @throws \TYPO3\Flow\Exception
     *
     * @return void
     */
    private function setOptionalClientSettings($providerName)
    {
        $casClientSettings = $this->getClientSettingsByProviderName($providerName);

        try {
            if (!empty($casClientSettings['serverLoginURL'])) {
                \phpCAS::setServerLoginURL($casClientSettings['serverLoginURL']);
            }
            if (!empty($casClientSettings['serverLogoutURL'])) {
                \phpCAS::setServerLogoutURL($casClientSettings['serverLogoutURL']);
            }
            if (!empty($casClientSettings['serverProxyValidateURL'])) {
                \phpCAS::setServerProxyValidateURL($casClientSettings['serverProxyValidateURL']);
            }
            if (!empty($casClientSettings['serverSamlValidateURL'])) {
                \phpCAS::setServerSamlValidateURL($casClientSettings['serverSamlValidateURL']);
            }
            if (!empty($casClientSettings['serverServiceValidateURL'])) {
                \phpCAS::setServerServiceValidateURL($casClientSettings['serverServiceValidateURL']);
            }
            // since CAS 4.0 disbled
            if (!empty($casClientSettings['singleSignoutCallback'])) {
                \phpCAS::setSingleSignoutCallback($casClientSettings['singleSignoutCallback']);
            }
        } catch (\Exception $exc) {
            throw new \TYPO3\Flow\Exception('Can not set some optianal property in Jasigs phpCAS broken on: '.$exc->getCode().' with message: '.$exc->getMessage(), 1372519681);
        }
    }

    /**
     * Returns settings for Cas-Client by given cas provider.
     * WARNING: Given provider must be of type RafaelKa\JasigPhpCas\Authentication\Provider\PhpCasAuthenticationProvider
     * validateConfigurationForCasProvider.
     *
     * @todo move to other class
     *
     * @param string $providerName defined in Settings.yaml providers name
     *
     * @return array with setting for CAS Client
     */
    private function getClientSettingsByProviderName($providerName)
    {
        if ($this->validateCASSettingsByProvider($providerName)) {
            $casClientSettings = $this->configurationManager->getConfiguration(\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'TYPO3.Flow.security.authentication.providers.'.$providerName.'.providerOptions.casClient');
            // Default options
            if (empty($casClientSettings['server_version'])) {
                $casClientSettings['server_version'] = self::DEFAULT_CAS_SERVER_VERSION;
            }
            if (empty($casClientSettings['changeSessionID'])) {
                $casClientSettings['changeSessionID'] = true;
            }
            if (empty($casClientSettings['server_port'])) {
                $casClientSettings['server_port'] = self::DEFAULT_CAS_SERVER_PORT;
            }

            return $casClientSettings;
        }

        return;
    }

    /**
     * Set the certificate of the CAS server CA with phpCAS::setCasServerCACert
     * you should set noCasServerValidation or
     * casServerCACertificatePath: 'resource://........' in Settings.yaml.
     *
     * @todo move to other class
     *
     * @param string $providerName defined in Settings.yaml
     *
     * @return bool
     */
    private function setCasServerCACert($providerName)
    {
        $casClientSettings = $this->getClientSettingsByProviderName($providerName);

        if (!empty($casClientSettings['noCasServerValidation']) && $casClientSettings['noCasServerValidation']) {
            try {
                \phpCAS::setNoCasServerValidation();
            } catch (\RuntimeException $exc) {
                throw new \TYPO3\Flow\Exception('Can not set phpCAS::setNoCasServerValidation() please debug it self :) . Original Message: '.$exc->getMessage(), 1371245975);
            }
        }

        if (!empty($casClientSettings['casServerCACertificatePath'])) {
            \phpCAS::setCasServerCACert($this->resolveResoursceToRealpath($casClientSettings['casServerCACertificatePath']));
            //\phpCAS::setCasServerCACert(\RafaelKa\JasigPhpCas\Utility\Files::resolveResoursceToRealpath($casClientSettings['casServerCACertificatePath']));
        }
    }

    /**
     * @todo Provide persisted resources
     *
     * @param string $resource   'resource://'
     * @param bool   $fileExists <b>&$fileExists</b> if set then checks file existency and sets given variable accordingly result of check.
     *
     * @return string
     */
    private function resolveResoursceToRealpath($resource, &$fileExists = null)
    {
        $uriParts = parse_url($resource);

        if (!is_array($uriParts)
            || !isset($uriParts['scheme'])
            || $uriParts['scheme'] !== 'resource'
            || !isset($uriParts['path'])) {
            return false;
        }

        $isOnPackage = isset($uriParts['host']);
        if ($isOnPackage) {
            $package = $this->packageManager->getPackage($uriParts['host']);
            $filename = \TYPO3\Flow\Utility\Files::concatenatePaths([$package->getResourcesPath(), $uriParts['path']]);
        } else {
            // will not work
            return;
            $filename = \TYPO3\Flow\Utility\Files::concatenatePaths([FLOW_PATH_DATA, 'Persistent/Resources'.$uriParts['path']]);
        }

        $filename = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $filename);
        $fileExists = file_exists($filename);

        return $filename;
    }

    /**
     * Validates tokenClasses and CAS-client settings by given cas provider.
     * WARNING: Given provider must be of type RafaelKa\JasigPhpCas\Authentication\Provider\PhpCasAuthenticationProvider
     * validateConfigurationForCasProvider.
     *
     * @todo move validation to other class.
     *
     * @param string $providerName defined in Settings.yaml providers name
     *
     * @throws \RafaelKa\JasigPhpCas\Exception\InvalidArgumentException
     * @throws \RafaelKa\JasigPhpCas\Exception\InvalidConfigurationException
     *
     * @return bool
     */
    public function validateCASSettingsByProvider($providerName)
    {
        $provider = $this->configurationManager->getConfiguration(
            \TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'TYPO3.Flow.security.authentication.providers.'.$providerName);

        if (empty($provider)) {
            throw new \RafaelKa\JasigPhpCas\Exception\InvalidArgumentException(sprintf('"%s" - provider does not exists.', $providerName), 1371136764);
        }

        if ($provider['provider'] !== self::DEFAULT_CAS_PROVIDER) {
            throw new \RafaelKa\JasigPhpCas\Exception\InvalidArgumentException(sprintf('Bad parameter for $providerName given in "%s()". The "%s" is not "%s". Please make sure that TYPO3.Flow.security.authentication.providers.%s.provider is "%s" or don\'t validate this provider with "%s->%s()".', __FUNCTION__, $providerName, self::DEFAULT_CAS_PROVIDER, $providerName, self::DEFAULT_CAS_PROVIDER, __CLASS__, __FUNCTION__), 1370963205);
        }

        // tokenClasses can be ommitted but if is set, then properly ;)
        if (!empty($provider['tokenClasses']) && is_array($provider['tokenClasses'])) {
            foreach ($provider['tokenClasses'] as $tokenClassName) {
                if (!class_exists($tokenClassName)) {
                    throw new \RafaelKa\JasigPhpCas\Exception\InvalidConfigurationException(sprintf('Class "%s" configured in Settings.yaml at "TYPO3.Flow.security.authentication.providers.%s.tokenClasses" does not exists.', $tokenClassName, $providerName), 1370947266);
                }
                if (!$this->reflectionService->isClassImplementationOf($tokenClassName, 'TYPO3\Flow\Security\Authentication\TokenInterface')) {
                    throw new \RafaelKa\JasigPhpCas\Exception\InvalidConfigurationException(sprintf('Class "%s" configured in Settings.yaml at "TYPO3.Flow.security.authentication.providers.%s.tokenClasses" is not implementation of "\TYPO3\Flow\Security\Authentication\TokenInterface". Please rediclare "%s" as "\TYPO3\Flow\Security\Authentication\TokenInterface" adapter Class.', $tokenClassName, $providerName, $tokenClassName), 1370947266);
                }
            }
        } elseif (isset($provider['tokenClasses']) && !is_array($provider['tokenClasses'])) {
            throw new \RafaelKa\JasigPhpCas\Exception\InvalidConfigurationException(sprintf('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.tokenClasses is set but empty or is not an array. This option can be ommited but if it is set, then must contain a list(yaml array with - ) of token class names, which this provider can authenticate.', $providerName), 1371140452);
        }

        // cas Client Settings : required
        if (empty($provider['providerOptions']['casClient'])) {
            throw new \TYPO3\Flow\Security\Exception\MissingConfigurationException(sprintf('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.casClient is missing. Please specify it in your Settings.yaml file. Beware: This file must not be accessible by the public!', $providerName), 1370797663);
        }
        if (empty($provider['providerOptions']['casClient']['server_hostname'])) {
            throw new \TYPO3\Flow\Security\Exception\MissingConfigurationException(sprintf('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.casClient.server_hostname is missing. Please specify it in your Settings.yaml file. Beware: This file must not be accessible by the public!', $providerName), 1370797665);
        }
        if (empty($provider['providerOptions']['casClient']['server_uri'])) {
            throw new \TYPO3\Flow\Security\Exception\MissingConfigurationException(sprintf('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.casClient.server_uri is missing. Please specify it in your Settings.yaml file. Beware: This file must not be accessible by the public!', $providerName), 1370797667);
        }
        if (empty($provider['providerOptions']['casClient']['noCasServerValidation']) || $provider['providerOptions']['casClient']['noCasServerValidation'] === false) {
            if (empty($provider['providerOptions']['casClient']['casServerCACertificatePath'])) {
                throw new \TYPO3\Flow\Security\Exception\MissingConfigurationException(sprintf('The configuration setting for TYPO3.Flow.security.authentication.providers.%s.providerOptions.casClient.casServerCACertificatePath is missing. Please specify it in your Settings.yaml file. Beware: This file must not be accessible by the public!', $providerName), 1370797668);
            } elseif (!is_readable($provider['providerOptions']['casClient']['casServerCACertificatePath'])) {
                throw new \RafaelKa\JasigPhpCas\Exception\InvalidConfigurationException(sprintf('Certificate "%s" difined in "TYPO3.Flow.security.authentication.providers.%s.providerOptions.casClient.casServerCACertificatePath" does not exists or is not readable. Please specify it correctly in your Settings.yaml file or make it readable. Beware: This both files must not be accessible by the public!', $provider['providerOptions']['casClient']['casServerCACertificatePath'], $providerName), 1370775324);
            }
        } else {
            // @todo : LOG WARNING -> WARNING: Beware: sprintf('[Warning:] Set TYPO3.Flow.security.authentication.providers.%s.providerOptions.casClient.noCasServerValidation to TRUE in your Settings.yaml file makes CAS-Provider insecure. Please set it to FALSE and TYPO3.Flow.security.authentication.providers.%s.providerOptions.casClient.casServerCACertificatePath correctly.', $providerName, $providerName);
            $this->systemLogger->log(sprintf('[Warning:] Set TYPO3.Flow.security.authentication.providers.%s.providerOptions.casClient.noCasServerValidation to TRUE in your Settings.yaml file makes CAS-Provider insecure. Please set it to FALSE and TYPO3.Flow.security.authentication.providers.%s.providerOptions.casClient.casServerCACertificatePath correctly.', $providerName, $providerName), LOG_NOTICE);
        }

        // Mapper class if set
        if (!empty($provider['providerOptions']['Mapping']['MapperClass'])) {
            $mapperClassName = $provider['providerOptions']['Mapping']['MapperClass'];
            if (!class_exists($mapperClassName)) {
                throw new \RafaelKa\JasigPhpCas\Exception\InvalidConfigurationException(sprintf('Class "%s" configured in Settings.yaml at "TYPO3.Flow.security.authentication.providers.%s.Mapping.MapperClass" does not exists.', $mapperClassName, $providerName), 1370983932);
            }
            if (!$this->reflectionService->isClassImplementationOf($mapperClassName, 'RafaelKa\JasigPhpCas\Service\MapperInterface')) {
                throw new \RafaelKa\JasigPhpCas\Exception\InvalidClassDefinitionForMapperException(sprintf('Class "%s" configured in Settings.yaml at "TYPO3.Flow.security.authentication.providers.%s.MapperClass" is not implementation of "RafaelKa\JasigPhpCas\Service\MapperInterface". Please rediclare "%s" as "\TYPO3\Flow\Security\Authentication\TokenInterface" adapter Class. Don\'t forget to flush caches.', $mapperClassName, $providerName, $mapperClassName), 1370981664);
            }
            if ($this->reflectionService->getClassAnnotation($mapperClassName, 'TYPO3\Flow\Annotations\Scope')->value !== 'singleton') {
                throw new \RafaelKa\JasigPhpCas\Exception\InvalidClassDefinitionForMapperException(sprintf('Class "%s" configured in Settings.yaml at "TYPO3.Flow.security.authentication.providers.%s.MapperClass" is not a singleton. Please declare "%s" as "@Flow\Scope("singleton")" Class.', $mapperClassName, $providerName, $mapperClassName), 1371036890);
            }
        }

        return true;
    }

    /**
     * Returns Mapper-Service configured for CAS Provider.
     *
     * @param string $providerName provider name to fetch a mapper from.
     *
     * @throws \RafaelKa\JasigPhpCas\Exception\InvalidConfigurationException
     * @throws \RafaelKa\JasigPhpCas\Exception\InvalidClassDefinitionForMapperException
     *
     * @return \RafaelKa\JasigPhpCas\Service\MapperInterface
     */
    private function getMapperByProviderName($providerName)
    {
        if (!empty($this->providerMappers[$providerName])) {
            return $this->providerMappers[$providerName];
        }

        $mapperClassName = $this->configurationManager->getConfiguration(\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'TYPO3.Flow.security.authentication.providers.'.$providerName.'Mapping.MapperClass');
        if (empty($mapperClassName)) {
            $mapperClassName = self::DEFAULT_CAS_MAPPER;
        } else {
            if (!class_exists($mapperClassName)) {
                throw new \RafaelKa\JasigPhpCas\Exception\InvalidConfigurationException(sprintf('Class "%s" configured in Settings.yaml at "TYPO3.Flow.security.authentication.providers.%s.Mapping.MapperClass" does not exists.', $mapperClassName, $providerName), 1370983932);
            }
            if (!$this->reflectionService->isClassImplementationOf($mapperClassName, 'RafaelKa\JasigPhpCas\Service\MapperInterface')) {
                throw new \RafaelKa\JasigPhpCas\Exception\InvalidClassDefinitionForMapperException(sprintf('Class "%s" configured in Settings.yaml at "TYPO3.Flow.security.authentication.providers.%s.MapperClass" is not implementation of "RafaelKa\JasigPhpCas\Service\MapperInterface". Please rediclare "%s" as "\TYPO3\Flow\Security\Authentication\TokenInterface" adapter Class.', $mapperClassName, $providerName, $mapperClassName), 1370981664);
            }
            if (!$this->reflectionService->getClassAnnotation($mapperClassName, 'TYPO3\Flow\Annotations\Scope')->value !== 'singleton') {
                throw new \RafaelKa\JasigPhpCas\Exception\InvalidClassDefinitionForMapperException(sprintf('Class "%s" configured in Settings.yaml at "TYPO3.Flow.security.authentication.providers.%s.MapperClass" is not a singleton. Please declare "%s" as "@Flow\Scope("singleton")" Class.', $mapperClassName, $providerName, $mapperClassName), 1371036890);
            }
        }

        $this->providerMappers[$providerName] = $this->objectManager->get($mapperClassName);

        return $this->providerMappers[$providerName];
    }
}
