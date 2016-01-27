<?php

namespace RafaelKa\JasigPhpCas\Service\Mapping\Validator;

/*                                                                       *
 * This script belongs to the TYPO3 Flow package "RafaelKa.JasigPhpCas". *
 *                                                                       *
 *                                                                       */

use    TYPO3\Flow\Annotations as Flow;

/**
 * Interface for the validator that checks settings Account, Roles, Party.
 */
interface MappingValidatorInterface
{
    /**
     * Validates mapping settings for Account.
     * 
     * @param string $providerName Provider name to fetch an account from.
     *
     * @return mixed
     */
    public function validateAccountSettings($providerName);

    /**
     * Validates mapping settings for Roles. 
     * 
     * @param string $providerName Provider name to fetch roles from.
     *
     * @return mixed
     */
    public function validateRolesSettings($providerName);

    /**
     * Validates mapping settings for Party. 
     * 
     * @param string $providerName Provider name to fetch a person from.
     *
     * @return mixed
     */
    public function validatePartySettings($providerName);

    /**
     * Validates mapping settings for all providers, which are 'RafaelKa\JasigPhpCas\Authentication\Provider\PhpCasAuthenticationProvider'.
     * 
     * @param string $validationMode
     *
     * @return mixed
     */
    public function validateMappingSettingsForAllCasProviders($validationMode);

    /**
     * Use caching or simple property to economize multiple call for validating.
     * 
     * @param string $providerName
     *
     * @return bool
     */
    public function isProviderValidated($providerName);
}
